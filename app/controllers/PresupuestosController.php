<?php
// /app/controllers/PresupuestosController.php
declare(strict_types=1);

class PresupuestosController extends Controller
{
    /* ===================== INDEX ===================== */
    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['user']['id'])) { header('Location: /auth/login'); exit; }

        $uid = (int)$_SESSION['user']['id'];
        $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);

        $this->safeRequireModel('Proyectos');
        $proy_list = $this->getProyectosByUser($uid);
        $proyecto  = $proyecto_id > 0 ? ($this->getProyectoById($proyecto_id) ?? []) : [];

        $rows = [];
        if ($proyecto_id > 0) {
            $st = $this->pdo->prepare("
                SELECT
                  pc.id, pc.codigo,
                  COALESCE(cc.descripcion, '') AS descripcion,
                  COALESCE(pc.cantidad_presupuestada, 0)        AS cantidad_presupuestada,
                  COALESCE(pc.precio_unitario_presupuestado, 0) AS precio_unitario_presupuestado,
                  COALESCE(pc.subtotal_pres, 0)                 AS subtotal_pres,
                  COALESCE(pc.subtotal_real, 0)                 AS subtotal_real
                FROM proyecto_costos pc
                LEFT JOIN costos_catalogo cc ON cc.codigo = pc.codigo
                WHERE pc.proyecto_id = :p
                ORDER BY pc.codigo
            ");
            $st->execute([':p'=>$proyecto_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

        $this->render('presupuestos_index', [
            'rows'        => $rows,
            'proyecto_id' => $proyecto_id,
            'proyecto'    => $proyecto,
            'proy_list'   => $proy_list,
            'csrf'        => $_SESSION['csrf'],
        ]);
    }

    /* ===================== AJAX PRESUPUESTO (INDEX) ===================== */

public function ajaxgrupos(): void
{
    $this->jsonStart();
    try {
        $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
        $f = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['f'] ?? '')));
        if ($proyecto_id <= 0 || strlen($f) !== 3) {
            throw new InvalidArgumentException('Parámetros inválidos');
        }

        $sql = "
            SELECT
              pc.familia AS familia,
              pc.grupo   AS `grupo`,
              CONCAT(pc.familia, pc.grupo, '0000') AS codigo,
              /* descripción tomada de la cabecera (item '0000') si existe */
              MAX(CASE WHEN pc.item='0000' THEN COALESCE(cc.descripcion,'') END) AS descripcion,

              /* SUMA SOLO ÍTEMS (item <> '0000') */
              SUM(CASE WHEN pc.item<>'0000' THEN COALESCE(pc.subtotal_pres,0) ELSE 0 END) AS subtotal_pres,
              SUM(CASE WHEN pc.item<>'0000' THEN COALESCE(pc.subtotal_real,0) ELSE 0 END) AS subtotal_real

            FROM proyecto_costos pc
            LEFT JOIN costos_catalogo cc ON cc.codigo = pc.codigo
            WHERE pc.proyecto_id = :p
              AND pc.familia     = :f
            GROUP BY pc.familia, pc.grupo
            HAVING SUM(CASE WHEN pc.item<>'0000' THEN 1 ELSE 0 END) > 0
            ORDER BY pc.grupo
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':p'=>$proyecto_id, ':f'=>$f]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->jsonOK($rows);
    } catch (Throwable $e) {
        $this->jsonErr($e->getMessage());
    }
}

public function ajaxitems(): void
{
    $this->jsonStart();
    try {
        $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
        $f = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['f'] ?? '')));
        $g = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['g'] ?? '')));
        if ($proyecto_id <= 0 || strlen($f)!==3 || strlen($g)!==3) {
            throw new InvalidArgumentException('Parámetros inválidos');
        }

        $sql = "
            SELECT
              pc.id,
              pc.codigo,
              COALESCE(cc.descripcion, '') AS descripcion,
              COALESCE(pc.cantidad_presupuestada,0)        AS cantidad_presupuestada,
              COALESCE(pc.precio_unitario_presupuestado,0) AS precio_unitario_presupuestado,
              COALESCE(pc.subtotal_pres,0)                  AS subtotal_pres,

              /* también devolvemos el “real” por ítem */
              COALESCE(pc.cantidad_real,0)                  AS cantidad_real,
              COALESCE(pc.precio_unitario_real,0)           AS precio_unitario_real,
              COALESCE(pc.subtotal_real,0)                  AS subtotal_real

            FROM proyecto_costos pc
            LEFT JOIN costos_catalogo cc ON cc.codigo = pc.codigo
            WHERE pc.proyecto_id = :p
              AND pc.familia     = :f
              AND pc.grupo       = :g
              AND pc.item       <> '0000'
            ORDER BY pc.codigo
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':p'=>$proyecto_id, ':f'=>$f, ':g'=>$g]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->jsonOK($rows);
    } catch (Throwable $e) {
        $this->jsonErr($e->getMessage());
    }
}

    public function ajaxdelscope(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $this->jsonStart();
        try {
            if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
                throw new RuntimeException('CSRF inválido');
            }
            $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
            $codigo = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_POST['codigo'] ?? '')));
            if ($proyecto_id <= 0 || strlen($codigo)!==10) { throw new InvalidArgumentException('Parámetros inválidos'); }

            $f = substr($codigo,0,3);
            $g = substr($codigo,3,3);
            $i = substr($codigo,6,4);

            if ($g === '000' && $i === '0000') {
                $st = $this->pdo->prepare("DELETE FROM proyecto_costos WHERE proyecto_id=:p AND familia=:f");
                $st->execute([':p'=>$proyecto_id, ':f'=>$f]);
            } elseif ($i === '0000') {
                $st = $this->pdo->prepare("DELETE FROM proyecto_costos WHERE proyecto_id=:p AND familia=:f AND grupo=:g");
                $st->execute([':p'=>$proyecto_id, ':f'=>$f, ':g'=>$g]);
            } else {
                $st = $this->pdo->prepare("DELETE FROM proyecto_costos WHERE proyecto_id=:p AND codigo=:c");
                $st->execute([':p'=>$proyecto_id, ':c'=>$codigo]);
            }

            $this->recalcHeaders($proyecto_id, (int)($_SESSION['user']['id'] ?? 0));
            $this->jsonOK(['ok'=>1]);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    public function ajaxgetitem(): void
    {
        $this->jsonStart();
        try {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { throw new InvalidArgumentException('ID inválido'); }
            $st = $this->pdo->prepare("
                SELECT pc.id, pc.codigo,
                       COALESCE(cc.descripcion,'') AS descripcion,
                       COALESCE(pc.cantidad_presupuestada,0)        AS cantidad_presupuestada,
                       COALESCE(pc.precio_unitario_presupuestado,0) AS precio_unitario_presupuestado
                FROM proyecto_costos pc
                LEFT JOIN costos_catalogo cc ON cc.codigo=pc.codigo
                WHERE pc.id=:id
                LIMIT 1
            ");
            $st->execute([':id'=>$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new RuntimeException('Ítem no encontrado'); }
            $this->jsonOK($row);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    public function ajaxsaveitem(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $this->jsonStart();
        try {
            if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
                throw new RuntimeException('CSRF inválido');
            }
            $id     = (int)($_POST['id'] ?? 0);
            $cant   = (float)($_POST['cantidad_presupuestada'] ?? 0);
            $precio = (float)($_POST['precio_unitario_presupuestado'] ?? 0);
            if ($id<=0) { throw new InvalidArgumentException('ID inválido'); }

            $st = $this->pdo->prepare("
                UPDATE proyecto_costos
                SET cantidad_presupuestada=:cant,
                    precio_unitario_presupuestado=:precio
                WHERE id=:id
                  AND item <> '0000'
                LIMIT 1
            ");
            $st->execute([':cant'=>$cant, ':precio'=>$precio, ':id'=>$id]);

            $st = $this->pdo->prepare("SELECT proyecto_id FROM proyecto_costos WHERE id=:id LIMIT 1");
            $st->execute([':id'=>$id]);
            $pid = (int)($st->fetchColumn() ?: 0);
            if ($pid > 0) { $this->recalcHeaders($pid, (int)($_SESSION['user']['id'] ?? 0)); }

            $this->jsonOK(['ok'=>1]);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    public function ajaxdelitem(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $this->jsonStart();
        try {
            if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
                throw new RuntimeException('CSRF inválido');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id<=0) { throw new InvalidArgumentException('ID inválido'); }

            $st = $this->pdo->prepare("SELECT proyecto_id FROM proyecto_costos WHERE id=:id LIMIT 1");
            $st->execute([':id'=>$id]);
            $pid = (int)($st->fetchColumn() ?: 0);

            $st = $this->pdo->prepare("DELETE FROM proyecto_costos WHERE id=:id AND item<>'0000' LIMIT 1");
            $st->execute([':id'=>$id]);

            if ($pid > 0) { $this->recalcHeaders($pid, (int)($_SESSION['user']['id'] ?? 0)); }

            $this->jsonOK(['ok'=>1]);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    public function ajaxprecio(): void
    {
        $this->jsonStart();
        try {
            $codigo = strtoupper(preg_replace('/[^0-9A-Za-z]/','', (string)($_GET['codigo'] ?? '')));
            if (strlen($codigo)!==10) { throw new InvalidArgumentException('Código inválido'); }

            $sql = "
                SELECT COALESCE(
                    NULLIF(p.costo_venta,   0),
                    NULLIF(p.costo_directo, 0),
                    NULLIF(CAST(c.valor AS DECIMAL(14,2)), 0),
                    0
                ) AS precio
                FROM costos_catalogo c
                LEFT JOIN costos_precios p ON p.id = (
                    SELECT p2.id
                    FROM costos_precios p2
                    WHERE p2.codigo = :c
                    ORDER BY p2.fecha_vigencia DESC, p2.id DESC
                    LIMIT 1
                )
                WHERE c.codigo = :c
                LIMIT 1
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([':c'=>$codigo]);
            $precio = (float)($st->fetchColumn() ?: 0);
            $this->jsonOK(['precio'=>$precio]);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    /* ===================== CLONER (VISTA) ===================== */
    public function cloner(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['user']['id'])) { header('Location: /auth/login'); exit; }

        $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
        if ($proyecto_id <= 0) {
            header('Location: ' . ($this->cfg['BASE_URL'] ?? '/') . '/presupuestos?e=Proyecto+no+válido'); exit;
        }

        $this->safeRequireModel('Proyectos');
        $proy_list = $this->getProyectosByUser((int)$_SESSION['user']['id']);
        $proyecto  = $this->getProyectoById($proyecto_id) ?? [];

        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

        $this->render('presupuestos_cloner', [
            'proyecto_id' => $proyecto_id,
            'proyecto'    => $proyecto,
            'proy_list'   => $proy_list,
            'csrf'        => $_SESSION['csrf'],
        ]);
    }

    /* ===================== AJAX CATALOGO (CLONER) ===================== */

    public function ajaxcatfamilias(): void
    {
        $this->jsonStart();
        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $params = [];
            $where  = '';
            if ($q !== '') {
                $where = "WHERE c.descripcion LIKE :q OR SUBSTR(c.codigo,1,3) LIKE :q2";
                $params[':q']  = "%{$q}%";
                $params[':q2'] = "%{$q}%";
            }
            $sql = "
                SELECT fam.familia,
                       COALESCE(fh.descripcion, '') AS descripcion
                FROM (
                  SELECT DISTINCT SUBSTR(c.codigo,1,3) AS familia
                  FROM costos_catalogo c
                  $where
                ) fam
                LEFT JOIN costos_catalogo fh
                  ON SUBSTR(fh.codigo,1,3)=fam.familia
                 AND SUBSTR(fh.codigo,4,3)='000'
                 AND SUBSTR(fh.codigo,7,4)='0000'
                ORDER BY fam.familia
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->jsonOK($rows);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    public function ajaxcatgrupos(): void
    {
        $this->jsonStart();
        try {
            $f = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['f'] ?? '')));
            if (strlen($f) !== 3) { throw new InvalidArgumentException('Familia inválida'); }

            $st = $this->pdo->prepare("
                SELECT
                    SUBSTR(c.codigo,4,3) AS `grupo`,
                    MAX(CASE WHEN SUBSTR(c.codigo,7,4)='0000' THEN c.descripcion END) AS descripcion
                FROM costos_catalogo c
                WHERE SUBSTR(c.codigo,1,3)=:f
                  AND SUBSTR(c.codigo,4,3) <> '000'
                GROUP BY SUBSTR(c.codigo,4,3)
                ORDER BY `grupo`
            ");
            $st->execute([':f'=>$f]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->jsonOK($rows);
        } catch (Throwable $e) { $this->jsonErr($e->message()); }
    }

    public function ajaxcatitems(): void
    {
        $this->jsonStart();
        try {
            $f = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['f'] ?? '')));
            $g = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['g'] ?? '')));
            if (strlen($f)!==3 || strlen($g)!==3) { throw new InvalidArgumentException('Familia/Grupo inválidos'); }

            $q = trim((string)($_GET['q'] ?? ''));
            $params = [':f'=>$f, ':g'=>$g];
            $whereQ = '';
            if ($q !== '') {
                $whereQ = " AND (c.descripcion LIKE :q OR c.codigo LIKE :q) ";
                $params[':q'] = "%{$q}%";
            }

            $st = $this->pdo->prepare("
                SELECT c.codigo, c.descripcion, c.unidad, c.valor
                FROM costos_catalogo c
                WHERE SUBSTR(c.codigo,1,3)=:f
                  AND SUBSTR(c.codigo,4,3)=:g
                  AND SUBSTR(c.codigo,7,4)<>'0000'
                  $whereQ
                ORDER BY c.codigo
            ");
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->jsonOK($rows);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    /* ===================== ACTION: CLONAR ===================== */
    public function do_clone(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $this->jsonStart();
        try {
            if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
                throw new RuntimeException('CSRF inválido');
            }
            $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
            if ($proyecto_id <= 0) { throw new InvalidArgumentException('Proyecto inválido'); }

            $scope    = (string)($_POST['scope'] ?? '');
            $user_id  = (int)($_SESSION['user']['id'] ?? 0);
            $cant_def = (float)($_POST['cantidad_default'] ?? 1);
            if (!in_array($scope, ['familias','grupos','items'], true)) {
                throw new InvalidArgumentException('Ámbito de clonación no soportado');
            }

            $normList = function($v, int $len): array {
                if (is_string($v)) { $v = preg_split('/[,\s]+/', trim($v), -1, PREG_SPLIT_NO_EMPTY); }
                if (!is_array($v)) $v = [];
                $v = array_map(fn($x)=>strtoupper(preg_replace('/[^0-9A-Za-z]/','', (string)$x)), $v);
                return array_values(array_unique(array_filter($v, fn($x)=>strlen($x) === $len)));
            };

            $codigos = [];
            if ($scope === 'familias') {
                $familias = $normList($_POST['familias'] ?? [], 3);
                if (!$familias) { throw new InvalidArgumentException('Seleccione al menos una familia'); }
                $in = implode(',', array_fill(0, count($familias), '?'));
                $st = $this->pdo->prepare("SELECT codigo FROM costos_catalogo WHERE SUBSTR(codigo,1,3) IN ($in)");
                $st->execute($familias);
                $codigos = array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'codigo');

            } elseif ($scope === 'grupos') {
                $famArr = $normList($_POST['familia'] ?? '', 3);
                if (count($famArr)!==1) { throw new InvalidArgumentException('Familia inválida'); }
                $fam = $famArr[0];
                $grupos = $normList($_POST['grupos'] ?? [], 3);
                if (!$grupos) { throw new InvalidArgumentException('Seleccione al menos un grupo'); }
                $in = implode(',', array_fill(0, count($grupos), '?'));
                $params = array_merge([$fam], $grupos);
                $sql = "SELECT codigo FROM costos_catalogo WHERE SUBSTR(codigo,1,3)=? AND SUBSTR(codigo,4,3) IN ($in)";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
                $codigos = array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'codigo');
                $codigos[] = $fam . '0000000';

            } else { // items
                $items = $normList($_POST['items'] ?? [], 10);
                if (!$items) { throw new InvalidArgumentException('Seleccione al menos un ítem'); }
                $codigos = $items;
                foreach ($items as $c) {
                    $f = substr($c,0,3); $g = substr($c,3,3);
                    $codigos[] = $f . '0000000';
                    $codigos[] = $f . $g . '0000';
                }
            }

            $codigos = array_values(array_unique(array_map(fn($c)=>strtoupper(preg_replace('/[^0-9A-Za-z]/','', (string)$c)), $codigos)));
            if (!$codigos) { throw new RuntimeException('No hay códigos resueltos para clonar'); }

            $this->cloneInsert($proyecto_id, $codigos, $cant_def, $user_id);
            $this->recalcHeaders($proyecto_id, $user_id);

            $this->jsonOK(['ok'=>1, 'clonados'=>count($codigos)]);
        } catch (Throwable $e) { $this->jsonErr($e->getMessage()); }
    }

    private function cloneInsert(int $proyecto_id, array $codigos, float $cant_def, int $user_id): void
    {
        $chunkSize = 500;
        $this->pdo->beginTransaction();
        try {
            for ($i=0; $i<count($codigos); $i += $chunkSize) {
                $slice = array_slice($codigos, $i, $chunkSize);
                $in = [];
                $params = [':proyecto_id'=>$proyecto_id, ':cant'=>$cant_def, ':usuario_id'=>$user_id];
                foreach ($slice as $k=>$c) { $ph=":c{$k}"; $in[]=$ph; $params[$ph]=$c; }

                $sql = "
                INSERT INTO proyecto_costos
                  (proyecto_id, familia, grupo, item, codigo,
                   cantidad_presupuestada, precio_unitario_presupuestado, fecha_carga, usuario_id)
                SELECT
                  :proyecto_id,
                  SUBSTR(c.codigo,1,3) AS familia,
                  SUBSTR(c.codigo,4,3) AS grupo,
                  SUBSTR(c.codigo,7,4) AS item,
                  c.codigo,
                  CASE WHEN SUBSTR(c.codigo,7,4)='0000' THEN 0 ELSE :cant END,
                  CASE WHEN SUBSTR(c.codigo,7,4)='0000' THEN 0
                       ELSE COALESCE(NULLIF(p.costo_venta,0), NULLIF(p.costo_directo,0), NULLIF(CAST(c.valor AS DECIMAL(14,2)),0), 0)
                  END,
                  CURRENT_DATE,
                  :usuario_id
                FROM costos_catalogo c
                LEFT JOIN costos_precios p ON p.id = (
                    SELECT p2.id FROM costos_precios p2
                    WHERE p2.codigo = c.codigo
                    ORDER BY p2.fecha_vigencia DESC, p2.id DESC
                    LIMIT 1
                )
                WHERE c.codigo IN (" . implode(',', $in) . ")
                ON DUPLICATE KEY UPDATE
                  precio_unitario_presupuestado = CASE
                    WHEN proyecto_costos.precio_unitario_presupuestado = 0
                    THEN VALUES(precio_unitario_presupuestado)
                    ELSE proyecto_costos.precio_unitario_presupuestado
                  END,
                  cantidad_presupuestada = CASE
                    WHEN proyecto_costos.cantidad_presupuestada = 0
                    THEN VALUES(cantidad_presupuestada)
                    ELSE proyecto_costos.cantidad_presupuestada
                  END
                ";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
            }
            $this->pdo->commit();
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    /* ===================== Recalcular Cabeceras ===================== */
    private function recalcHeaders(int $proyecto_id, int $user_id): void
    {
        // Cabeceras de grupo: item='0000', cantidad=1, precio = SUM(subtotal_pres ítems del grupo)
        $sqlGrp = "
            INSERT INTO proyecto_costos
              (proyecto_id, familia, grupo, item, codigo,
               cantidad_presupuestada, precio_unitario_presupuestado, fecha_carga, usuario_id)
            SELECT
              :p, pc.familia, pc.grupo, '0000',
              CONCAT(pc.familia, pc.grupo, '0000') AS codigo,
              1,
              SUM(COALESCE(pc.subtotal_pres,0)),
              CURRENT_DATE, :u
            FROM proyecto_costos pc
            WHERE pc.proyecto_id = :p
              AND pc.item <> '0000'
            GROUP BY pc.familia, pc.grupo
            ON DUPLICATE KEY UPDATE
              cantidad_presupuestada        = VALUES(cantidad_presupuestada),
              precio_unitario_presupuestado = VALUES(precio_unitario_presupuestado)
        ";
        $st = $this->pdo->prepare($sqlGrp);
        $st->execute([':p'=>$proyecto_id, ':u'=>$user_id]);

        // Cabeceras de familia: grupo='000', item='0000', precio = SUM(subtotal_pres de todos los ítems de la familia)
        $sqlFam = "
            INSERT INTO proyecto_costos
              (proyecto_id, familia, grupo, item, codigo,
               cantidad_presupuestada, precio_unitario_presupuestado, fecha_carga, usuario_id)
            SELECT
              :p, pc.familia, '000', '0000',
              CONCAT(pc.familia, '000', '0000') AS codigo,
              1,
              SUM(COALESCE(pc.subtotal_pres,0)),
              CURRENT_DATE, :u
            FROM proyecto_costos pc
            WHERE pc.proyecto_id = :p
              AND pc.item <> '0000'
            GROUP BY pc.familia
            ON DUPLICATE KEY UPDATE
              cantidad_presupuestada        = VALUES(cantidad_presupuestada),
              precio_unitario_presupuestado = VALUES(precio_unitario_presupuestado)
        ";
        $st = $this->pdo->prepare($sqlFam);
        $st->execute([':p'=>$proyecto_id, ':u'=>$user_id]);
    }

    /* ===================== Utils ===================== */
    private function jsonStart(): void { header('Content-Type: application/json; charset=utf-8'); }
    private function jsonOK($data): void { echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private function jsonErr(string $msg, int $code=400): void { http_response_code($code); echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }

    private function safeRequireModel(string $name): void
    {
        $paths = [
            __DIR__ . '/../models/' . $name . '.php',
            dirname(__DIR__, 2) . '/app/models/' . $name . '.php',
            dirname(__DIR__, 2) . '/' . $name . '.php',
        ];
        foreach ($paths as $f) { if (is_file($f)) { require_once $f; return; } }
    }

    protected function render(string $view, array $vars = []): void
    {
        $view = ltrim($view, '/');
        $file = dirname(__DIR__) . '/views/' . $view . '.php';
        if (!is_file($file)) { http_response_code(500); echo "Vista no encontrada: " . htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); exit; }
        if (!empty($vars)) { extract($vars, EXTR_SKIP); }
        include $file;
    }

    private function getProyectosByUser(int $uid): array
    {
        if (class_exists('Proyectos')) {
            $m = new Proyectos($this->pdo);
            if (method_exists($m, 'listByUser')) { $rows = $m->listByUser($uid) ?: []; return is_array($rows) ? $rows : []; }
        }
        $st = $this->pdo->prepare("
            SELECT DISTINCT p.id, p.codigo_proy, p.nombre, p.descripcion
            FROM proyectos p
            LEFT JOIN proyecto_usuarios pu ON pu.proyecto_id = p.id
            WHERE p.activo = 1 AND (p.owner_user_id = :u OR pu.user_id = :u)
            ORDER BY p.id DESC
        ");
        $st->execute([':u'=>$uid]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getProyectoById(int $id): ?array
    {
        if (class_exists('Proyectos')) {
            $m = new Proyectos($this->pdo);
            if (method_exists($m, 'findById')) { $r = $m->findById($id); if ($r) return $r; }
            if (method_exists($m, 'get'))      { $r = $m->get($id);      if ($r) return $r; }
        }
        $st = $this->pdo->prepare("SELECT * FROM proyectos WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        return $r ?: null;
    }
	
	public function imprimir(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (empty($_SESSION['user']['id'])) { header('Location: /auth/login'); exit; }

    $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
    if ($proyecto_id <= 0) {
        header('Location: ' . ($this->cfg['BASE_URL'] ?? '/') . '/presupuestos?e=Proyecto+no+válido');
        exit;
    }

    // Proyecto
    $this->safeRequireModel('Proyectos');
    $proyecto = $this->getProyectoById($proyecto_id) ?? [];

    // Descripciones de cabeceras (familias y grupos)
    $famDesc = []; $grpDesc = [];

    $st = $this->pdo->prepare("
        SELECT DISTINCT pc.familia, COALESCE(cf.descripcion,'') AS descripcion
        FROM proyecto_costos pc
        LEFT JOIN costos_catalogo cf
            ON cf.codigo = CONCAT(pc.familia,'000','0000')
        WHERE pc.proyecto_id = :p
    ");
    $st->execute([':p'=>$proyecto_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $famDesc[$r['familia']] = $r['descripcion'] ?? '';
    }

    $st = $this->pdo->prepare("
        SELECT DISTINCT pc.familia, pc.grupo, COALESCE(cg.descripcion,'') AS descripcion
        FROM proyecto_costos pc
        LEFT JOIN costos_catalogo cg
            ON cg.codigo = CONCAT(pc.familia, pc.grupo, '0000')
        WHERE pc.proyecto_id = :p
    ");
    $st->execute([':p'=>$proyecto_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $grpDesc[$r['familia'] . $r['grupo']] = $r['descripcion'] ?? '';
    }

    // Ítems (solo item <> '0000')
    $st = $this->pdo->prepare("
        SELECT
          pc.familia, pc.grupo, pc.item, pc.codigo,
          COALESCE(cc.descripcion, '') AS descripcion,
          COALESCE(cc.unidad, '')       AS unidad,
          COALESCE(pc.cantidad_presupuestada,0)        AS cant_pres,
          COALESCE(pc.precio_unitario_presupuestado,0) AS punit_pres,
          COALESCE(pc.subtotal_pres,0)                  AS sub_pres,
          COALESCE(pc.cantidad_real,0)                  AS cant_real,
          COALESCE(pc.precio_unitario_real,0)           AS punit_real,
          COALESCE(pc.subtotal_real,0)                  AS sub_real
        FROM proyecto_costos pc
        LEFT JOIN costos_catalogo cc ON cc.codigo = pc.codigo
        WHERE pc.proyecto_id = :p
          AND pc.item <> '0000'
        ORDER BY pc.familia, pc.grupo, pc.codigo
    ");
    $st->execute([':p'=>$proyecto_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Armar jerarquía y totales
    $familias = [];
    $total_proy_pres = 0.0;
    $total_proy_real = 0.0;

    foreach ($rows as $r) {
        $f = $r['familia']; $g = $r['grupo'];
        if (!isset($familias[$f])) {
            $familias[$f] = [
                'familia' => $f,
                'descripcion' => $famDesc[$f] ?? '',
                'pres' => 0.0,
                'real' => 0.0,
                'grupos' => []
            ];
        }
        if (!isset($familias[$f]['grupos'][$g])) {
            $familias[$f]['grupos'][$g] = [
                'grupo' => $g,
                'descripcion' => $grpDesc[$f.$g] ?? '',
                'pres' => 0.0,
                'real' => 0.0,
                'items' => []
            ];
        }

        // Item
        $familias[$f]['grupos'][$g]['items'][] = $r;
        // Acumulados
        $familias[$f]['grupos'][$g]['pres'] += (float)$r['sub_pres'];
        $familias[$f]['grupos'][$g]['real'] += (float)$r['sub_real'];
        $familias[$f]['pres']               += (float)$r['sub_pres'];
        $familias[$f]['real']               += (float)$r['sub_real'];
        $total_proy_pres                    += (float)$r['sub_pres'];
        $total_proy_real                    += (float)$r['sub_real'];
    }

    $this->render('presupuestos_print', [
        'proyecto_id'     => $proyecto_id,
        'proyecto'        => $proyecto,
        'familias'        => $familias,
        'total_proy_pres' => $total_proy_pres,
        'total_proy_real' => $total_proy_real,
    ]);
}
	
	
}
