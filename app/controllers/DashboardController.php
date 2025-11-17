<?php
declare(strict_types=1);

// Models opcionales; si no existen, caemos a SQL directo.
@require_once __DIR__ . '/../models/Proyectos.php';
@require_once __DIR__ . '/../models/ProyectoCostos.php';

final class DashboardController extends Controller
{
    private string $tz = 'America/Santiago';

    public function __construct(PDO $pdo, array $cfg = [])
    {
        parent::__construct($pdo, $cfg);
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['user']['id'])) { $this->redirect('/auth/login'); }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        date_default_timezone_set($this->tz);
    }

    private function base(): string { return rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'); }
    private function uid(): int { return (int)($_SESSION['user']['id'] ?? 0); }

    /** Aviso de cierre (≤2 días hábiles) */
    private function cierreWarn(): ?string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->tz));
        $y = (int)$now->format('Y'); $m = (int)$now->format('n');
        $last = (new \DateTimeImmutable("$y-$m-01", new \DateTimeZone($this->tz)))->modify('last day of this month');
        $d = $now->modify('+1 day')->setTime(0,0,0);
        $hab = 0;
        while ($d <= $last) { $w = (int)$d->format('N'); if ($w <= 5) $hab++; $d = $d->modify('+1 day'); }
        if ($hab > 2) return null;
        if ($hab === 0) return "⚠️ Hoy cierra tu caja del mes. Revisa y completa tus rendiciones.";
        if ($hab === 1) return "⚠️ Queda 1 día hábil para el cierre automático de caja.";
        return "⚠️ Quedan 2 días hábiles para el cierre automático de caja.";
    }

    /** Lista de proyectos del usuario (owner o miembro) */
    private function listProjectsForUser(int $uid): array
    {
        if (class_exists('Proyectos')) {
            try {
                $P = new \Proyectos($this->pdo);
                if (method_exists($P, 'listarActivosDeUsuario')) {
                    $rows = $P->listarActivosDeUsuario($uid);
                    if (is_array($rows)) return $rows;
                }
                if (method_exists($P, 'listarPorUsuario')) {
                    $rows = $P->listarPorUsuario($uid);
                    if (is_array($rows)) {
                        return array_values(array_filter($rows, fn($r)=> (int)($r['activo'] ?? 1) === 1));
                    }
                }
            } catch (\Throwable $e) {}
        }

        $sql = "
            SELECT DISTINCT p.id, p.nombre, p.codigo_proy, p.activo
              FROM proyectos p
              LEFT JOIN proyecto_usuarios pu
                ON pu.proyecto_id = p.id AND pu.user_id = :uid
             WHERE p.activo = 1
               AND (p.owner_user_id = :uid OR pu.user_id = :uid)
          ORDER BY p.nombre ASC, p.id ASC
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':uid'=>$uid]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function firstProjectForUser(int $uid): ?array
    {
        if (class_exists('Proyectos')) {
            try {
                $P = new \Proyectos($this->pdo);
                if (method_exists($P, 'primerProyectoDeUsuario')) {
                    $row = $P->primerProyectoDeUsuario($uid);
                    if ($row) return $row;
                }
            } catch (\Throwable $e) {}
        }

        $sql = "
            SELECT p.*
              FROM proyectos p
              LEFT JOIN proyecto_usuarios pu
                ON pu.proyecto_id = p.id AND pu.user_id = :uid
             WHERE p.activo = 1
               AND (p.owner_user_id = :uid OR pu.user_id = :uid)
          ORDER BY (p.fecha_inicio IS NULL), p.fecha_inicio ASC, p.id ASC
             LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':uid'=>$uid]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getProjectForUser(int $uid, int $proyectoId): ?array
    {
        if (class_exists('Proyectos')) {
            try {
                $P = new \Proyectos($this->pdo);
                if (method_exists($P, 'getById')) {
                    $row = $P->getById($proyectoId);
                    if ($row && (int)($row['activo'] ?? 1) === 1) {
                        if ($this->userHasProject($uid, $proyectoId)) return $row;
                    }
                }
            } catch (\Throwable $e) {}
        }

        $sql = "
            SELECT p.*
              FROM proyectos p
              LEFT JOIN proyecto_usuarios pu
                ON pu.proyecto_id = p.id AND pu.user_id = :uid
             WHERE p.id = :pid
               AND p.activo = 1
               AND (p.owner_user_id = :uid OR pu.user_id = :uid)
             LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':uid'=>$uid, ':pid'=>$proyectoId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function userHasProject(int $uid, int $pid): bool
    {
        $sql = "
            SELECT 1
              FROM proyectos p
              LEFT JOIN proyecto_usuarios pu
                ON pu.proyecto_id = p.id AND pu.user_id = :uid
             WHERE p.id = :pid
               AND p.activo = 1
               AND (p.owner_user_id = :uid OR pu.user_id = :uid)
             LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':uid'=>$uid, ':pid'=>$pid]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Condición SQL para filtrar SOLO ítems hoja (excluye familia/grupo).
     * Ajusta los “ceros” según tu convención si fuera distinta.
     */
    private function leafWhere(): string
    {
        // item con valor real (no vacío ni ceros típicos)  O  código no-agrupador
        return "
            (
                (item IS NOT NULL AND TRIM(item) <> '' AND item NOT IN ('0','000','0000'))
                OR
                (codigo IS NOT NULL AND LENGTH(TRIM(codigo)) = 10 AND RIGHT(TRIM(codigo), 4) <> '0000')
            )
        ";
    }

    /** KPIs: SOLO ítems hoja, desde columnas base (evita triggers/subtotales) */
    private function kpisProyecto(int $proyectoId): array
    {
        // Si tu modelo ya tiene método “estricto”, úsalo
        if (class_exists('ProyectoCostos')) {
            try {
                $PC = new \ProyectoCostos($this->pdo);
                if (method_exists($PC, 'kpisProyectoEstrictoSoloItems')) {
                    $k = (array)$PC->kpisProyectoEstrictoSoloItems($proyectoId);
                    if ($k) return $k;
                }
            } catch (\Throwable $e) {}
        }

        $sql = "
            SELECT
              COALESCE(SUM(ROUND(COALESCE(cantidad_presupuestada,0) * COALESCE(precio_unitario_presupuestado,0),0)), 0) AS total_pres,
              COALESCE(SUM(ROUND(COALESCE(cantidad_real,0)           * COALESCE(precio_unitario_real,0),0)), 0)           AS total_real,
              COUNT(*) AS items
            FROM proyecto_costos
            WHERE proyecto_id = :pid
              AND {$this->leafWhere()}
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':pid'=>$proyectoId]);
        $k = $st->fetch(\PDO::FETCH_ASSOC) ?: ['total_pres'=>0,'total_real'=>0,'items'=>0];

        $k['desvio'] = (float)$k['total_pres'] - (float)$k['total_real'];
        $k['avance'] = (float)$k['total_pres'] > 0 ? ((float)$k['total_real'] / (float)$k['total_pres'])*100.0 : 0.0;
        return $k;
    }

    /** Top ítems: SOLO ítems hoja (usa subtotal_* solo para orden y display) */
    private function topItems(int $proyectoId, int $limit = 12): array
    {
        $sql = "
            SELECT id, codigo, COALESCE(costo_glosa,'') AS glosa,
                   subtotal_pres, subtotal_real,
                   ROUND(COALESCE(cantidad_presupuestada,0) * COALESCE(precio_unitario_presupuestado,0),0) AS pres_calc,
                   ROUND(COALESCE(cantidad_real,0)           * COALESCE(precio_unitario_real,0),0)           AS real_calc
              FROM proyecto_costos
             WHERE proyecto_id = :pid
               AND {$this->leafWhere()}
          ORDER BY real_calc DESC
             LIMIT :lim
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':pid', $proyectoId, \PDO::PARAM_INT);
        $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // GET /dashboard
    public function index(): void
    {
        $uid   = $this->uid();
        $warn  = $this->cierreWarn();

        $proyectos = $this->listProjectsForUser($uid);
        $selPid = (int)($_GET['proyecto_id'] ?? 0);
        $ids = array_map(fn($r)=>(int)$r['id'], $proyectos);

        if ($selPid > 0 && in_array($selPid, $ids, true)) {
            $proy = $this->getProjectForUser($uid, $selPid);
            if (!$proy) { $proy = null; }
        } else {
            $proy = !empty($proyectos) ? $this->getProjectForUser($uid, (int)$proyectos[0]['id'])
                                       : $this->firstProjectForUser($uid);
        }

        $kpis = null;
        $items = [];
        if ($proy) {
            $selPid = (int)$proy['id'];
            $kpis  = $this->kpisProyecto($selPid);
            $items = $this->topItems($selPid, 12);
        } else {
            $selPid = 0;
        }

        $this->view('dashboard_index', [
            'base'      => $this->base(),
            'warn'      => $warn,
            'proy'      => $proy,
            'kpis'      => $kpis,
            'items'     => $items,
            'proyectos' => $proyectos,
            'selPid'    => $selPid,
            'usuario'   => $_SESSION['user'] ?? [],
        ]);
    }
}
