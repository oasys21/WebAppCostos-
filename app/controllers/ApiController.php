<?php
declare(strict_types=1);

class ApiController extends Controller
{
    protected PDO $pdo;
    protected array $cfg;

    public function __construct(PDO $pdo, array $cfg = [])
    {
        parent::__construct($pdo, $cfg);
        $this->pdo = $pdo;
        $this->cfg = $cfg;

        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        // Por política del Router, /api requiere login (slugs públicos: site, auth).
        if (empty($_SESSION['user']['id'])) {
            $this->jsonError('No autorizado', 401);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    private function jsonError(string $msg, int $code = 400): void
    {
        $this->jsonHeader();
        http_response_code($code);
        echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function jsonOK($payload): void
    {
        $this->jsonHeader();
        echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * GET /api/proyectos?term=
     * Respuesta: [{id, nombre, codigo_proy}]
     */
    public function proyectos(): void
    {
        $term = isset($_GET['term']) ? trim((string)$_GET['term']) : '';
        $sql  = "SELECT id, nombre, codigo_proy
                   FROM proyectos
                  WHERE activo = 1
                    AND (:term = '' OR nombre LIKE CONCAT('%',:term,'%') OR codigo_proy LIKE CONCAT('%',:term,'%'))
               ORDER BY nombre ASC
                  LIMIT 200";

        $st = $this->pdo->prepare($sql);
        $st->bindValue(':term', $term, PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll();

        // Normalizar campos (por si algún valor viene null)
        foreach ($rows as &$r) {
            $r['id']          = (int)$r['id'];
            $r['nombre']      = (string)($r['nombre'] ?? '');
            $r['codigo_proy'] = (string)($r['codigo_proy'] ?? '');
        }

        $this->jsonOK($rows);
    }

    /**
     * GET /api/proyectoitems?proyecto_id=&term=
     * Respuesta: [{proyecto_costo_id, codigo, glosa, label}]
     */
    public function proyectoitems(): void
    {
        $pid  = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
        $term = isset($_GET['term']) ? trim((string)$_GET['term']) : '';

        if ($pid <= 0) { $this->jsonError('proyecto_id requerido', 422); }

        $sql = "SELECT pc.id AS proyecto_costo_id,
                       pc.codigo,
                       COALESCE(pc.costo_glosa,'') AS glosa
                  FROM proyecto_costos pc
                 WHERE pc.proyecto_id = :pid
                   AND (:term = '' OR pc.codigo LIKE CONCAT(:term,'%') OR pc.costo_glosa LIKE CONCAT('%',:term,'%'))
              ORDER BY pc.codigo ASC
                 LIMIT 500";

        $st = $this->pdo->prepare($sql);
        $st->bindValue(':pid',  $pid,  PDO::PARAM_INT);
        $st->bindValue(':term', $term, PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll();

        foreach ($rows as &$r) {
            $r['proyecto_costo_id'] = (int)$r['proyecto_costo_id'];
            $r['codigo']            = (string)($r['codigo'] ?? '');
            $r['glosa']             = (string)($r['glosa'] ?? '');
            $r['label']             = trim($r['codigo'].' - '.$r['glosa'], ' -');
        }

        $this->jsonOK($rows);
    }
}
