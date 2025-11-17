<?php
// /costos/app/controllers/LogsysController.php
declare(strict_types=1);

final class LogsysController extends Controller
{
    private function requireAdm(): array
    {
        $u = Session::user();
        if (!$u) { http_response_code(401); exit('No autenticado'); }
        if (($u['perfil'] ?? '') !== 'ADM') { http_response_code(403); exit('Sin permiso'); }
        return $u;
    }

    /**
     * Listado con filtros:
     *  GET:
     *   - q: texto libre (rut, nombre, ip, accion, entidad, detalle_json)
     *   - acc: acción exacta (LOGIN_OK, LOGIN_FAIL, INSERT, UPDATE, DELETE, DOC_* ...)
     *   - ent: entidad exacta (auth, usuarios, documentos, etc.)
     *   - f1: fecha desde (YYYY-MM-DD)
     *   - f2: fecha hasta (YYYY-MM-DD)
     *   - p: página (1..n)
     */
    public function index(): void
    {
        $u = $this->requireAdm();

        $q   = trim((string)($_GET['q'] ?? ''));
        $acc = trim((string)($_GET['acc'] ?? ''));
        $ent = trim((string)($_GET['ent'] ?? ''));
        $f1  = trim((string)($_GET['f1'] ?? ''));
        $f2  = trim((string)($_GET['f2'] ?? ''));
        $p   = max(1, (int)($_GET['p'] ?? 1));
        $pp  = 25; // por página

        $where = [];
        $bind  = [];

        if ($q !== '') {
            $where[] = "(rut LIKE :q OR nombre LIKE :q OR ip LIKE :q OR accion LIKE :q OR entidad LIKE :q OR detalle_json LIKE :q)";
            $bind[':q'] = '%' . $q . '%';
        }
        if ($acc !== '') {
            $where[] = "accion = :acc";
            $bind[':acc'] = $acc;
        }
        if ($ent !== '') {
            $where[] = "entidad = :ent";
            $bind[':ent'] = $ent;
        }
        if ($f1 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f1)) {
            $where[] = "creado_en >= :f1";
            $bind[':f1'] = $f1 . ' 00:00:00';
        }
        if ($f2 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f2)) {
            $where[] = "creado_en <= :f2";
            $bind[':f2'] = $f2 . ' 23:59:59';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total
        $sqlCnt = "SELECT COUNT(*) FROM logsys $whereSql";
        $stCnt  = $this->pdo->prepare($sqlCnt);
        foreach ($bind as $k=>$v) $stCnt->bindValue($k, $v);
        $stCnt->execute();
        $total = (int)$stCnt->fetchColumn();

        // Paginación
        $pages = max(1, (int)ceil($total / $pp));
        if ($p > $pages) $p = $pages;
        $off = ($p - 1) * $pp;

        // Datos
        $sql = "SELECT id, user_id, rut, nombre, ip, user_agent, accion, entidad, entidad_id,
                       detalle_json, creado_en
                FROM logsys
                $whereSql
                ORDER BY id DESC
                LIMIT :off, :pp";
        $st = $this->pdo->prepare($sql);
        foreach ($bind as $k=>$v) $st->bindValue($k, $v);
        $st->bindValue(':off', $off, PDO::PARAM_INT);
        $st->bindValue(':pp',  $pp,  PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $this->view('logsys_index', [
            'pageTitle' => 'Auditoría',
            'rows'      => $rows,
            'q'         => $q,
            'acc'       => $acc,
            'ent'       => $ent,
            'f1'        => $f1,
            'f2'        => $f2,
            'p'         => $p,
            'pages'     => $pages,
            'total'     => $total,
        ]);
    }
}
