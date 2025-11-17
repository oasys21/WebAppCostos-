<?php
declare(strict_types=1);

class ProyectoEtapas
{
    private static function pdo(): \PDO {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) return $GLOBALS['pdo'];
        if (function_exists('db')) { $c = db(); if ($c instanceof \PDO) return $c; }
        $dbcfg = $GLOBALS['cfg']['DB'] ?? null;
        if (!$dbcfg) throw new \Exception('DB config no disponible');
        $opts  = $dbcfg['options'] ?? [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];
        $pdo = new \PDO($dbcfg['dsn'], $dbcfg['user'] ?? '', $dbcfg['pass'] ?? '', $opts);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
        return $pdo;
    }

    /* ===== Listados ===== */
    /**
     * Lista de proyectos según usuario:
     * - Admin: todos los proyectos activos.
     * - No admin: solo proyectos donde participa en proyecto_usuarios.
     */
    public static function listarProyectos(?int $userId = null, bool $isAdmin = false): array {
        $pdo = self::pdo();

        if ($userId !== null && $userId > 0 && !$isAdmin) {
            $sql = "SELECT DISTINCT p.id, p.nombre
                      FROM proyectos p
                      INNER JOIN proyecto_usuarios pu
                              ON pu.proyecto_id = p.id
                     WHERE p.activo = 1
                       AND pu.user_id = :uid
                  ORDER BY p.nombre";
            $st = $pdo->prepare($sql);
            $st->execute([':uid' => $userId]);
        } else {
            $sql = "SELECT id, nombre
                      FROM proyectos
                     WHERE activo = 1
                  ORDER BY nombre";
            $st = $pdo->query($sql);
        }

        return $st->fetchAll() ?: [];
    }

    /**
     * Búsqueda de etapas con filtros:
     * - proyecto_id, item_costo, estado, titulo, usuario_id
     * - usuario_rut (JOIN con usuarios)
     * - current_user_id + scope (alcance owner+guest o all si admin)
     */
    public static function buscar(array $f = [], int $limit = 200): array {
        $pdo = self::pdo();
        $w=[]; $p=[];

        if (!empty($f['proyecto_id'])) {
            $w[] = 'pe.proyecto_id = :pid';
            $p[':pid'] = (int)$f['proyecto_id'];
        }
        if (!empty($f['item_costo'])) {
            $w[] = 'pe.item_costo = :ic';
            $p[':ic'] = substr((string)$f['item_costo'], 0, 10);
        }
        if (!empty($f['estado'])) {
            $w[] = 'pe.estado = :es';
            $p[':es'] = (string)$f['estado'];
        }
        if (!empty($f['titulo'])) {
            $w[] = 'pe.titulo LIKE :tit';
            $p[':tit'] = '%' . trim((string)$f['titulo']) . '%';
        }
        if (!empty($f['usuario_id'])) {
            $w[] = 'pe.usuario_id = :uidf';
            $p[':uidf'] = (int)$f['usuario_id'];
        }

        // NUEVO: filtro por RUT de usuario (tabla usuarios.u.rut)
        if (!empty($f['usuario_rut'])) {
            $rut = trim((string)$f['usuario_rut']);
            $w[] = 'u.rut = :ur'; // <-- ajusta nombre de columna si tu tabla usa otro nombre
            $p[':ur'] = $rut;
        }

        // Alcance por usuario (owner + guest) salvo que se pida "all" (admin)
        $uid   = isset($f['current_user_id']) ? (int)$f['current_user_id'] : 0;
        $scope = $f['scope'] ?? null;
        if ($uid > 0 && $scope !== 'all') {
            $w[] = '(pe.usuario_id = :uid OR EXISTS (
                        SELECT 1
                          FROM proyecto_usuarios pu
                         WHERE pu.proyecto_id = pe.proyecto_id
                           AND pu.user_id = :uid
                     ))';
            $p[':uid'] = $uid;
        }

        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

        $sql = "SELECT pe.*,
                       pr.nombre AS proyecto_nombre,
                       COALESCE(SUM(pi.cantidad),0) AS cantidad_total,
                       COALESCE(SUM(pi.monto),0)    AS valor_total,
                       CASE WHEN COALESCE(SUM(pi.porcentaje),0) > 0
                            THEN ROUND(SUM((pi.avance_pct * pi.porcentaje)/100.0), 2)
                            ELSE ROUND(COALESCE(AVG(pi.avance_pct),0), 2)
                       END AS avance_pct
                  FROM proyecto_etapas pe
             LEFT JOIN proyectos pr ON pr.id = pe.proyecto_id
             LEFT JOIN proyecto_etapas_items pi ON pi.etapa_id = pe.id
             LEFT JOIN usuarios u ON u.id = pe.usuario_id   -- para filtro por RUT
                {$where}
              GROUP BY pe.id
              ORDER BY pe.id DESC
              LIMIT ".(int)$limit;

        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll() ?: [];
    }

    public static function buscarPorId(int $id): ?array {
        $pdo = self::pdo();
        $sql = "SELECT pe.*, pr.nombre AS proyecto_nombre
                  FROM proyecto_etapas pe
             LEFT JOIN proyectos pr ON pr.id = pe.proyecto_id
                 WHERE pe.id = :id";
        $st = $pdo->prepare($sql);
        $st->execute([':id'=>$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function listarItems(int $etapa_id): array {
        $pdo = self::pdo();
        $st = $pdo->prepare("SELECT * FROM proyecto_etapas_items WHERE etapa_id = :id ORDER BY linea, id");
        $st->execute([':id'=>$etapa_id]);
        return $st->fetchAll() ?: [];
    }

    /* ===== Crear / Actualizar ===== */
    public static function crear(array $cab, array $items): int {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            $sql = "INSERT INTO proyecto_etapas
                        (proyecto_id, item_costo, titulo, estado,
                         fecha_inicio_prog, fecha_fin_prog, fecha_inicio_real, fecha_fin_real,
                         usuario_id, cantidad_total, valor_total, avance_pct)
                    VALUES
                        (:p, :ic, :t, :e, :fi_p, :ff_p, :fi_r, :ff_r, :u, 0, 0, 0)";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':p'    => (int)$cab['proyecto_id'],
                ':ic'   => substr((string)$cab['item_costo'],0,10),
                ':t'    => $cab['titulo'] ?: null,
                ':e'    => $cab['estado'] ?: 'borrador',
                ':fi_p' => $cab['fecha_inicio_prog'],
                ':ff_p' => $cab['fecha_fin_prog'],
                ':fi_r' => $cab['fecha_inicio_real'],
                ':ff_r' => $cab['fecha_fin_real'],
                ':u'    => $cab['usuario_id'],
            ]);
            $id = (int)$pdo->lastInsertId();

            self::upsertItems($pdo, $id, $items);
            self::recalcTotales($pdo, $id);

            $pdo->commit();
            return $id;
        }catch(\Throwable $e){
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function actualizar(int $id, array $cab, array $items): void {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            $sql = "UPDATE proyecto_etapas
                       SET proyecto_id = :p,
                           item_costo  = :ic,
                           titulo      = :t,
                           estado      = :e,
                           fecha_inicio_prog = :fi_p,
                           fecha_fin_prog    = :ff_p,
                           fecha_inicio_real = :fi_r,
                           fecha_fin_real    = :ff_r
                     WHERE id = :id";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':p'    => (int)$cab['proyecto_id'],
                ':ic'   => substr((string)$cab['item_costo'],0,10),
                ':t'    => $cab['titulo'] ?: null,
                ':e'    => $cab['estado'] ?: 'borrador',
                ':fi_p' => $cab['fecha_inicio_prog'],
                ':ff_p' => $cab['fecha_fin_prog'],
                ':fi_r' => $cab['fecha_inicio_real'],
                ':ff_r' => $cab['fecha_fin_real'],
                ':id'   => $id,
            ]);

            self::upsertItems($pdo, $id, $items);
            self::recalcTotales($pdo, $id);

            $pdo->commit();
        }catch(\Throwable $e){
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function upsertItems(\PDO $pdo, int $etapa_id, array $items): void {
        // existentes
        $stSel = $pdo->prepare("SELECT id FROM proyecto_etapas_items WHERE etapa_id = :id");
        $stSel->execute([':id'=>$etapa_id]);
        $exist = array_map('intval', $stSel->fetchAll(\PDO::FETCH_COLUMN));

        $keep = [];

        $ins = $pdo->prepare(
            "INSERT INTO proyecto_etapas_items
                (etapa_id, linea, descripcion, unidad_med, cantidad, valor, porcentaje,
                 estado_paso, avance_pct, fecha_inicio_prog, fecha_fin_prog, fecha_inicio_real, fecha_fin_real)
             VALUES
                (:et, :ln, :d, :um, :qty, :val, :por,
                 :es, :av, :fi_p, :ff_p, :fi_r, :ff_r)"
        );
        $upd = $pdo->prepare(
            "UPDATE proyecto_etapas_items
                SET linea=:ln, descripcion=:d, unidad_med=:um, cantidad=:qty, valor=:val, porcentaje=:por,
                    estado_paso=:es, avance_pct=:av, fecha_inicio_prog=:fi_p, fecha_fin_prog=:ff_p,
                    fecha_inicio_real=:fi_r, fecha_fin_real=:ff_r
              WHERE id=:id AND etapa_id=:et"
        );

        foreach($items as $i){
            $ln  = isset($i['linea']) ? (int)$i['linea'] : null;
            $d   = $i['descripcion'] ?? null;
            $um  = in_array(($i['unidad_med'] ?? 'UN'), ['ML','M2','M3','UN','KG','TM','OT']) ? $i['unidad_med'] : 'UN';
            $qty = number_format((float)($i['cantidad'] ?? 0), 2, '.', '');
            $val = number_format((float)($i['valor'] ?? 0), 2, '.', '');
            $por = (int)($i['porcentaje'] ?? 0);
            $es  = in_array(($i['estado_paso'] ?? 'pendiente'), ['pendiente','en_proceso','terminado','anulado']) ? $i['estado_paso'] : 'pendiente';
            $av  = number_format((float)($i['avance_pct'] ?? 0), 2, '.', '');
            $fi_p= $i['fecha_inicio_prog'] ?? null;
            $ff_p= $i['fecha_fin_prog'] ?? null;
            $fi_r= $i['fecha_inicio_real'] ?? null;
            $ff_r= $i['fecha_fin_real'] ?? null;

            if (!empty($i['id'])) {
                $keep[] = (int)$i['id'];
                $upd->execute([
                    ':ln'=>$ln, ':d'=>$d, ':um'=>$um, ':qty'=>$qty, ':val'=>$val, ':por'=>$por,
                    ':es'=>$es, ':av'=>$av, ':fi_p'=>$fi_p, ':ff_p'=>$ff_p, ':fi_r'=>$fi_r, ':ff_r'=>$ff_r,
                    ':id'=>(int)$i['id'], ':et'=>$etapa_id,
                ]);
            } else {
                $ins->execute([
                    ':et'=>$etapa_id, ':ln'=>$ln, ':d'=>$d, ':um'=>$um, ':qty'=>$qty, ':val'=>$val, ':por'=>$por,
                    ':es'=>$es, ':av'=>$av, ':fi_p'=>$fi_p, ':ff_p'=>$ff_p, ':fi_r'=>$fi_r, ':ff_r'=>$ff_r,
                ]);
                $keep[] = (int)$pdo->lastInsertId();
            }
        }

        // borrar los que no vinieron
        if (!empty($exist)) {
            $toDel = array_values(array_diff($exist, $keep));
            if (!empty($toDel)) {
                $in = implode(',', array_fill(0,count($toDel),'?'));
                $del = $pdo->prepare("DELETE FROM proyecto_etapas_items WHERE etapa_id = ? AND id IN ($in)");
                $del->execute(array_merge([$etapa_id], $toDel));
            }
        }
    }

    private static function recalcTotales(\PDO $pdo, int $etapa_id): void {
        // totales cantidad y valor (monto)
        $st = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) qty, COALESCE(SUM(monto),0) amt,
                                    COALESCE(SUM(porcentaje),0) sp, COALESCE(AVG(avance_pct),0) avgs
                               FROM proyecto_etapas_items WHERE etapa_id = :id");
        $st->execute([':id'=>$etapa_id]);
        $row = $st->fetch() ?: ['qty'=>0,'amt'=>0,'sp'=>0,'avgs'=>0];

        $avance = 0.00;
        if ((float)$row['sp'] > 0) {
            $st2 = $pdo->prepare("SELECT avance_pct, porcentaje FROM proyecto_etapas_items WHERE etapa_id = :id");
            $st2->execute([':id'=>$etapa_id]);
            $num = 0.0;
            foreach($st2->fetchAll() as $r){
                $num += ((float)$r['avance_pct'] * (int)$r['porcentaje']) / 100.0;
            }
            $avance = round($num, 2);
        } else {
            $avance = round((float)$row['avgs'], 2);
        }

        $upd = $pdo->prepare("UPDATE proyecto_etapas
                                 SET cantidad_total = :q,
                                     valor_total    = :a,
                                     avance_pct     = :av
                               WHERE id = :id");
        $upd->execute([
            ':q'=>number_format((float)$row['qty'],2,'.',''),
            ':a'=>number_format((float)$row['amt'],2,'.',''),
            ':av'=>number_format((float)$avance,2,'.',''),
            ':id'=>$etapa_id
        ]);
    }

    public static function eliminar(int $id): void {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            $st = $pdo->prepare("DELETE FROM proyecto_etapas WHERE id = :id");
            $st->execute([':id'=>$id]);
            $pdo->commit();
        }catch(\Throwable $e){
            $pdo->rollBack();
            throw $e;
        }
    }
}
