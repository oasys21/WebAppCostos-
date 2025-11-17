<?php
class ImputacionesController extends Controller
{
    /* ===================== Infra ===================== */
private static function pdo(){
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) return $GLOBALS['pdo'];
    if (function_exists('db')) { $c = db(); if ($c instanceof \PDO) return $c; }
    if (isset($GLOBALS['cfg']['DB']['dsn'])) {
        $dbcfg = $GLOBALS['cfg']['DB'];
        $opts  = $dbcfg['options'] ?? [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];
        $pdo = new \PDO($dbcfg['dsn'], $dbcfg['user'] ?? '', $dbcfg['pass'] ?? '', $opts);
        // 馃敀 Asegura collation de la conexi贸n
 $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
        return $pdo;
    }
    throw new \Exception('Sin conexi贸n PDO');
}
    private function baseUrl(): string {
        if (!empty($GLOBALS['cfg']['BASE_URL'])) return rtrim($GLOBALS['cfg']['BASE_URL'], '/');
        $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\','/', dirname($sn)), '/');
        return ($base === '' || $base === '.') ? '' : $base;
    }
    private function flash(string $type, string $msg): void {
        if (class_exists('Session') && method_exists('Session',$type)) { @Session::$type($msg); return; }
        if (!isset($_SESSION)) @session_start();
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }
    private function trace(string $label, array $data = []): void {
        $root = dirname(__DIR__, 2);
        $file = $root . DIRECTORY_SEPARATOR . 'runtime_imputaciones.log';
        @file_put_contents($file, date('Y-m-d H:i:s')." [$label] ".json_encode($data,JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
    }

public function index(){
    $pdo = self::pdo();

    // === Filtros UI ===
    $filters = [
        'proveedor_id' => isset($_GET['proveedor_id']) && $_GET['proveedor_id'] !== '' ? (int)$_GET['proveedor_id'] : null,
        'proyecto_id'  => isset($_GET['proyecto_id'])   && $_GET['proyecto_id']   !== '' ? (int)$_GET['proyecto_id']   : null,
        'desde'        => isset($_GET['desde']) && $_GET['desde'] !== '' ? $_GET['desde'] : null,   // YYYY-MM-DD
        'hasta'        => isset($_GET['hasta']) && $_GET['hasta'] !== '' ? $_GET['hasta'] : null,
        'q'            => isset($_GET['q']) ? trim((string)$_GET['q']) : '',
    ];

    $where = " WHERE 1=1 ";
    $p = [];

    if ($filters['proveedor_id']) {
        $where .= " AND c.proveedor_id = :prov "; $p[':prov'] = $filters['proveedor_id'];
    }
    if ($filters['proyecto_id']) {
        $where .= " AND (imp.proyecto_id = :proy OR c.proyecto_id = :proy) "; $p[':proy'] = $filters['proyecto_id'];
    }
    if ($filters['desde']) { $where .= " AND c.fecha_doc >= :d1 "; $p[':d1'] = $filters['desde']; }
    if ($filters['hasta']) { $where .= " AND c.fecha_doc <= :d2 "; $p[':d2'] = $filters['hasta']; }
    if ($filters['q'] !== '') {
        $where .= " AND (c.folio LIKE :q OR it.descripcion LIKE :q OR imp.codigo LIKE :q) ";
        $p[':q'] = '%'.$filters['q'].'%';
    }

    $select = "
      SELECT
        imp.id,
        imp.compra_item_id,
        imp.proyecto_id,
        imp.proyecto_costo_id,
        imp.codigo,
        imp.cantidad_imputada,
        imp.monto_imputado,
        imp.monto_base,
        imp.fecha_imputacion,
        imp.origen,
        imp.estado_proceso,
        it.linea,
        it.codigo      AS item_codigo,
        it.descripcion AS item_desc,
        it.cantidad    AS it_cant,
        it.precio_unitario AS it_pu,
        c.id           AS compra_id,
        c.tipo_doc, c.folio, c.fecha_doc, c.moneda, c.tipo_cambio, c.estado AS compra_estado,
        prov.nombre    AS proveedor,
        prj.nombre     AS proyecto_nombre,
        pc.codigo      AS pcodigo,
        pc.costo_glosa AS pcosto_glosa,
        CASE WHEN imp.proyecto_id IS NOT NULL AND imp.proyecto_costo_id IS NOT NULL THEN 1 ELSE 0 END AS completo
      FROM compras_imputaciones imp
      JOIN compras_items it ON it.id = imp.compra_item_id
      JOIN compras c        ON c.id  = it.compra_id
      LEFT JOIN proveedores prov ON prov.id = c.proveedor_id
      LEFT JOIN proyectos   prj  ON prj.id  = imp.proyecto_id
      LEFT JOIN proyecto_costos pc ON pc.id = imp.proyecto_costo_id
    ";

    // PENDIENTES: según estado_proceso
    $sqlPend = $select . $where . " AND imp.estado_proceso = 'pendiente'
                 ORDER BY c.fecha_doc DESC, imp.id DESC LIMIT 500";
    $st = $pdo->prepare($sqlPend); $st->execute($p);
    $pendientes = $st->fetchAll(\PDO::FETCH_ASSOC);

    // REALIZADAS: según estado_proceso
    $sqlReal = $select . $where . " AND imp.estado_proceso = 'aplicada'
                 ORDER BY c.fecha_doc DESC, imp.id DESC LIMIT 500";
    $st = $pdo->prepare($sqlReal); $st->execute($p);
    $realizadas = $st->fetchAll(\PDO::FETCH_ASSOC);

    // combos de filtros
    $proveedores = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC);
    $proyectos   = $pdo->query("SELECT id, nombre FROM proyectos   ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC);

    $this->render('imputaciones/index', compact('pendientes','realizadas','filters','proveedores','proyectos'));
}

public function actualizar($impId){
    $pdo = self::pdo();
    $impId = (int)$impId;

    try{
        $proyecto_id       = isset($_POST['proyecto_id']) ? (int)$_POST['proyecto_id'] : null;
        $proyecto_costo_id = isset($_POST['proyecto_costo_id']) && $_POST['proyecto_costo_id'] !== ''
                           ? (int)$_POST['proyecto_costo_id'] : null;

        // Deriva 'codigo' desde proyecto_costos.id (si viene). Si no viene id, preserva el código actual.
        $codigo = null;
        if ($proyecto_costo_id) {
            $st = $pdo->prepare("SELECT codigo FROM proyecto_costos WHERE id = :id");
            $st->execute([':id'=>$proyecto_costo_id]);
            $codigo = $st->fetchColumn();
            if (!$codigo) throw new \Exception('ítem de costo inválido.');
        } else {
            // Mantener el código existente
            $st = $pdo->prepare("SELECT codigo FROM compras_imputaciones WHERE id = :id");
            $st->execute([':id'=>$impId]);
            $codigo = $st->fetchColumn();
        }

        // 1) Actualiza la imputación (id + codigo + proyecto)
        $pdo->prepare("
            UPDATE compras_imputaciones
               SET proyecto_id = :p,
                   proyecto_costo_id = :pc,
                   codigo = :cod
             WHERE id = :id
        ")->execute([
            ':p'  => $proyecto_id,
            ':pc' => $proyecto_costo_id,
            ':cod'=> $codigo,
            ':id' => $impId
        ]);

        // 2) Refleja en compras_items (para que el form de compras vea el cambio)
        $pdo->prepare("
            UPDATE compras_items it
            JOIN compras_imputaciones imp ON imp.compra_item_id = it.id
               SET it.imp_proyecto_id = :p,
                   it.imp_pcosto_id   = :pc
             WHERE imp.id = :imp
        ")->execute([
            ':p'   => $proyecto_id,
            ':pc'  => $proyecto_costo_id,
            ':imp' => $impId
        ]);

        Session::success('Imputación actualizada.');
        // vuelve al ver de la compra
        $cid = $pdo->query("SELECT compra_id FROM compras_items it JOIN compras_imputaciones imp ON imp.compra_item_id=it.id WHERE imp.id=".(int)$impId)->fetchColumn();
        $this->redirect('/compras/ver/'.(int)$cid);
    }catch(\Throwable $e){
        Session::error('No se pudo guardar: '.$e->getMessage());
        $this->redirect('/imputaciones'); // o vuelve a donde corresponda
    }
}

    /* ===================== Introspecci贸n ===================== */
    private static function dbCols(string $table): array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t'=>$table]);
        return array_map('strval',$st->fetchAll(\PDO::FETCH_COLUMN));
    }
    private static function tableExists(string $table): bool {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t'=>$table]);
        return (bool)$st->fetchColumn();
    }
    private static function pickFirstExistingCol(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) if (in_array($c,$cols,true)) return $c;
        return null;
    }

    /* ===================== Pantalla ===================== */
public function create($itemId){
    $pdo = self::pdo();
    $itemId = (int)$itemId;

    // ítem + compra
    $st = $pdo->prepare("
        SELECT ci.*, c.proyecto_id AS compra_proyecto_id, c.id AS compra_id
          FROM compras_items ci
          JOIN compras c ON c.id = ci.compra_id
         WHERE ci.id = :id
         LIMIT 1
    ");
    $st->execute([':id'=>$itemId]);
    $item = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$item) { http_response_code(404); echo 'ítem de compra no encontrado'; return; }

    // Imputación existente (pendiente o la última)
    $imp = $this->getImputacionPorItem($itemId);

    // Proyecto preseleccionado: querystring > imputación > compra > item.imp_proyecto_id
    $proyectoIdSel = isset($_GET['proyecto_id']) && $_GET['proyecto_id'] !== ''
        ? (int)$_GET['proyecto_id']
        : (
            $imp ? (int)($imp['proyecto_id'] ?? 0)
                 : ((int)($item['imp_proyecto_id'] ?? 0) ?: (int)($item['compra_proyecto_id'] ?? 0))
        );

    // ítem de costo preseleccionado: querystring > imputación > item.imp_pcosto_id (si existe)
    $pcostoIdSel = isset($_GET['proyecto_costo_id']) && $_GET['proyecto_costo_id'] !== ''
        ? (int)$_GET['proyecto_costo_id']
        : (
            $imp ? $this->resolverPcostoIdDesdeImputacion($imp, $proyectoIdSel)
                 : (int)($item['imp_pcosto_id'] ?? 0)
        );

    // Combos
    $proyectos = $pdo->query("SELECT id, nombre FROM proyectos ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC);
    $pcostos   = $proyectoIdSel ? $this->listarProyectoCostos($proyectoIdSel) : [];

    $this->render('imputaciones/form', [
        'pageTitle'     => 'Imputar ítem',
        'item'          => $item,
        'itemId'        => $itemId,
        'impId'         => $imp['id'] ?? null,
        'imputacion'    => $imp,
        'proyectos'     => $proyectos,
        'pcostos'       => $pcostos,
        'proyectoIdSel' => $proyectoIdSel,
        'pcostoIdSel'   => $pcostoIdSel
    ]);
}
public function edit($impId){
    $pdo = self::pdo();
    $impId = (int)$impId;

    $st = $pdo->prepare("SELECT * FROM compras_imputaciones WHERE id = :id");
    $st->execute([':id'=>$impId]);
    $imp = $st->fetch(\PDO::FETCH_ASSOC);
    if(!$imp){ http_response_code(404); echo 'Imputación no encontrada'; return; }

    $itemId = (int)$pdo->query("SELECT compra_item_id FROM compras_imputaciones WHERE id = ".(int)$impId)->fetchColumn();

    // datos para selects
    $proyectos = $pdo->query("SELECT id, nombre FROM proyectos ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC);
    $pcostos   = [];
    if (!empty($imp['proyecto_id'])) {
        $st = $pdo->prepare("SELECT id, codigo, costo_glosa AS nombre FROM proyecto_costos WHERE proyecto_id = :p ORDER BY codigo");
        $st->execute([':p'=>(int)$imp['proyecto_id']]);
        $pcostos = $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    $item = $pdo->prepare("SELECT id, compra_id, codigo, descripcion, cantidad, precio_unitario FROM compras_items WHERE id = :id");
    $item->execute([':id'=>$itemId]);
    $item = $item->fetch(\PDO::FETCH_ASSOC) ?: ['id'=>$itemId];

    $proyectoIdSel = (int)($imp['proyecto_id'] ?? 0);
    $pcostoIdSel   = (int)($imp['proyecto_costo_id'] ?? 0);
    $impId         = (int)$imp['id'];

    $this->render('imputaciones/form', compact('item','itemId','impId','proyectos','pcostos','proyectoIdSel','pcostoIdSel'));
}

// Alias en espa?ol si prefieres /imputaciones/editar/{id}
public function editar($id){ return $this->edit($id); }
    /* ===================== Guardar ===================== */
public function store($itemId){
    $pdo = self::pdo();
    $itemId = (int)$itemId;

    try{
        $proyecto_id = isset($_POST['proyecto_id']) ? (int)$_POST['proyecto_id'] : 0;
        $pcosto_id   = isset($_POST['proyecto_costo_id']) ? (int)$_POST['proyecto_costo_id'] : 0;
        $monto       = isset($_POST['monto']) && $_POST['monto']!=='' ? (float)$_POST['monto'] : null;

        if ($proyecto_id <= 0) throw new \Exception('Debes seleccionar un Proyecto.');
        if ($pcosto_id   <= 0) throw new \Exception('Debes seleccionar un ítem de costo.');

        // Item base
        $item = $this->getCompraItem($itemId);
        if (!$item) throw new \Exception('ítem de compra no existe.');

        // Monto por defecto = total del ítem
        $totalItem = (float)$item['cantidad'] * (float)$item['precio_unitario'];
        if ($monto === null) $monto = $totalItem;

        // Moneda/tipo de cambio → monto_base
        $mon = $this->getMonedaCompra((int)$item['compra_id']);
        $moneda      = $mon['moneda'] ?? null;
        $tipo_cambio = isset($mon['tipo_cambio']) ? (float)$mon['tipo_cambio'] : null;
        $monto_base  = $tipo_cambio ? round($monto * $tipo_cambio, 2) : $monto;

        $pdo->beginTransaction();

        // UPSERT imputación pendiente del item
        $imp = $this->getImputacionPorItem($itemId, /*soloPendiente*/true);

        $cols = self::dbCols('compras_imputaciones');
        $hasPcosto = in_array('proyecto_costo_id', $cols, true);
        $hasCodigo = in_array('codigo',            $cols, true);
        $hasCant   = in_array('cantidad_imputada', $cols, true);
        $hasMonImp = in_array('monto_imputado',    $cols, true);
        $hasMonBas = in_array('monto_base',        $cols, true);

        // Código del proyecto_costo (para columna 'codigo' si existe)
        $pcInfo = $this->fetchProyectoCostoInfo($pcosto_id); // ['codigo','costo_glosa']
        $codigo = $pcInfo['codigo'] ?? null;

        if ($imp) {
            // UPDATE
            $set = [];
            $par = [':id'=>$imp['id']];
            $set[] = "proyecto_id = :p";  $par[':p'] = $proyecto_id;
            if ($hasPcosto) { $set[] = "proyecto_costo_id = :pc"; $par[':pc'] = $pcosto_id; }
            if ($hasCodigo && $codigo !== null) { $set[] = "codigo = :cod"; $par[':cod'] = $codigo; }
            if ($hasCant)   { $set[] = "cantidad_imputada = :q"; $par[':q'] = (float)$item['cantidad']; }
            if ($hasMonImp) { $set[] = "monto_imputado = :m";     $par[':m'] = $monto; }
            if ($hasMonBas) { $set[] = "monto_base = :mb";        $par[':mb']= $monto_base; }
            $set[] = "moneda = :mon";         $par[':mon'] = $moneda;
            $set[] = "tipo_cambio = :tc";     $par[':tc']  = $tipo_cambio;
            $set[] = "fecha_imputacion = CURDATE()";

            $sql = "UPDATE compras_imputaciones SET ".implode(',', $set)." WHERE id = :id";
            $pdo->prepare($sql)->execute($par);
        } else {
            // INSERT
            $colsIns = ['compra_item_id','proyecto_id','fecha_imputacion','origen','usuario_id'];
            $marks   = [':ci',           ':p',          'CURDATE()',        ':o',    ':u'];
            $par     = [
                ':ci'=>$itemId, ':p'=>$proyecto_id,
                ':o'=>'manual', ':u'=>($_SESSION['user']['id']??null)
            ];
            if ($hasPcosto) { $colsIns[]='proyecto_costo_id'; $marks[]=':pc'; $par[':pc']=$pcosto_id; }
            if ($hasCodigo && $codigo !== null) { $colsIns[]='codigo'; $marks[]=':cod'; $par[':cod']=$codigo; }
            if ($hasCant)   { $colsIns[]='cantidad_imputada'; $marks[]=':q'; $par[':q']=(float)$item['cantidad']; }
            if ($hasMonImp) { $colsIns[]='monto_imputado';    $marks[]=':m'; $par[':m']=$monto; }
            if ($hasMonBas) { $colsIns[]='monto_base';        $marks[]=':mb';$par[':mb']=$monto_base; }
            $colsIns[]='moneda'; $marks[]=':mon'; $par[':mon']=$moneda;
            $colsIns[]='tipo_cambio'; $marks[]=':tc'; $par[':tc']=$tipo_cambio;

            $sql = "INSERT INTO compras_imputaciones (".implode(',',$colsIns).") VALUES (".implode(',',$marks).")";
            $pdo->prepare($sql)->execute($par);
        }

        // Sincroniza en compras_items
       Compra::updateCompraItemImputacion(
    (int)$imp['compra_item_id'],
    (int)$imp['proyecto_id'],
    isset($imp['proyecto_costo_id']) ? (int)$imp['proyecto_costo_id'] : null
);

        // ?Aplicar ahora?
        $aplicarAhora = isset($_POST['aplicar_ahora']) && $_POST['aplicar_ahora'] === '1';
        $uid = $_SESSION['user']['id'] ?? null;
        if ($aplicarAhora) {
            $impPend = $this->getImputacionPorItem($itemId, /*soloPendiente*/true);
            if (!$impPend) throw new \Exception('No hay imputación pendiente para aplicar.');
            $this->aplicarImputacion($impPend, $uid);
        }
// $imp tiene compra_item_id, proyecto_id y proyecto_costo_id

        $pdo->commit();

        if (class_exists('Session') && method_exists('Session','success')) {
            Session::success($aplicarAhora ? 'Imputación aplicada.' : 'Imputación guardada.');
        }

        // <<< NUEVO: redirección flexible (por defecto: Imputaciones)
        $next = isset($_POST['next']) && $_POST['next'] !== '' ? (string)$_POST['next'] : '/imputaciones/index';
        $this->redirect($next);

    }catch(\Throwable $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (class_exists('Session') && method_exists('Session','error')) {
            Session::error('No se pudo guardar la imputación: '.$e->getMessage());
        }
        // fallback: vuelve al form de imputación o al destino deseado si venía en el form
        $next = isset($_POST['next']) && $_POST['next'] !== '' ? (string)$_POST['next'] : null;
        $this->redirect($next ?: ('/imputaciones/create/'.$itemId));
    }
}


    public function store_get($itemId){ $_POST = $_GET; return $this->store($itemId); }
    public function guardar($itemId){ return $this->store($itemId); }

    /* ===================== Helpers dominio ===================== */
    private function getCompraItem(int $itemId): ?array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT * FROM compras_items WHERE id=:id");
        $st->execute([':id'=>$itemId]);
        $it=$st->fetch(\PDO::FETCH_ASSOC);
        return $it ?: null;
    }
    private function getImputadoAcumulado(int $itemId): float {
        $pdo = self::pdo();
        $st = $pdo->prepare("SELECT IFNULL(SUM(monto_imputado),0) FROM compras_imputaciones WHERE compra_item_id = :id");
        $st->execute([':id'=>$itemId]);
        return (float)$st->fetchColumn();
    }
    private function getMonedaCompra(int $compraId): array {
        $pdo = self::pdo();
        $st = $pdo->prepare("SELECT moneda, tipo_cambio FROM compras WHERE id = :id");
        $st->execute([':id'=>$compraId]);
        return $st->fetch() ?: [];
    }

 private function listarProyectoCostos(int $proyecto_id): array {
    $pdo=self::pdo();

    // Existe la tabla?
    if (!self::tableExists('proyecto_costos')) return [];

    $cols = self::dbCols('proyecto_costos');

    // columna de código
    $code = $this->pickFirstExistingCol($cols, ['codigo','code','item_codigo','item_code']);
    if (!$code) $code = 'id';

    // columna de “nombre”: prioriza costo_glosa
    $name = $this->pickFirstExistingCol($cols, ['costo_glosa','descripcion','nombre','concepto','item_desc','glosa']);
    if (!$name) $name = 'id';

    $sql = "SELECT id, {$code} AS codigo, {$name} AS nombre
              FROM proyecto_costos
             WHERE proyecto_id = :p
             ORDER BY {$code}, id";
    $st = $pdo->prepare($sql);
    $st->execute([':p'=>$proyecto_id]);
    return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}
    private function getProyectoCostoById(int $id): ?array {
        $pdo  = self::pdo();
        $cols = self::dbCols('proyecto_costos');

        $codeCol  = self::pickFirstExistingCol($cols, ['codigo','code','item_codigo','item_code']) ?: 'id';
        // para validaci贸n y copia de c贸digo basta con devolver proyecto_id y codigo
        $sql = "SELECT id, proyecto_id, {$codeCol} AS codigo FROM proyecto_costos WHERE id = :id";
        $st=$pdo->prepare($sql);
        $st->execute([':id'=>$id]);
        $r=$st->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function getProyectoCostoIdByCodigo(int $proyecto_id, string $codigo): int {
        $pdo=self::pdo();
        $cols  = self::dbCols('proyecto_costos');
        $code  = self::pickFirstExistingCol($cols, ['codigo','code','item_codigo','item_code']);
        if (!$code) return 0;
        $st=$pdo->prepare("SELECT id FROM proyecto_costos WHERE proyecto_id = :p AND {$code} = :c LIMIT 1");
        $st->execute([':p'=>$proyecto_id, ':c'=>$codigo]);
        $id=$st->fetchColumn();
        return $id ? (int)$id : 0;
    }

 private function sumarRealesProyectoCostoTX(\PDO $pdo, int $pcosto_id, float $cant, float $monto): void
{
    // Promedio ponderado del precio real y suma de cantidad real.
    // OJO: En MySQL los SET se eval煤an de izquierda a derecha.
    // Por eso calculamos primero el precio usando los valores "viejos" y al final sumamos la cantidad.

    $sql = "UPDATE proyecto_costos
            SET
              -- precio_unitario_real nuevo = (subtotal_real_viejo + monto_nuevo) / (cantidad_real_vieja + cant_nueva)
              precio_unitario_real = CASE
                  WHEN (cantidad_real + :cant) > 0
                    THEN ROUND(((cantidad_real * precio_unitario_real) + :monto) / (cantidad_real + :cant), 2)
                  ELSE precio_unitario_real
                END,
              -- reci茅n despu茅s sumamos la cantidad
              cantidad_real = cantidad_real + :cant
            WHERE id = :id";

    $pdo->prepare($sql)->execute([
        ':cant'  => $cant,
        ':monto' => $monto,
        ':id'    => $pcosto_id,
    ]);
}

    /* ===================== Render/Redirect ===================== */
    protected function render(string $view, array $vars = []): void {
        extract($vars, EXTR_OVERWRITE);
        if (!isset($pageTitle)) $pageTitle = 'Imputaciones';
        $viewsRoot = dirname(__DIR__) . '/views/';
        $viewFile  = $viewsRoot . $view . '.php';
        $headerFile= $viewsRoot . 'layout/header.php';
        $footerFile= $viewsRoot . 'layout/footer.php';
        if (is_file($headerFile)) require $headerFile;
        if (!is_file($viewFile)) { http_response_code(500); echo 'Vista no encontrada'; return; }
        require $viewFile;
        if (is_file($footerFile)) require $footerFile;
    }
    protected function redirect(string $path): void {
        $base = $this->baseUrl();
        if ($path === '' || $path[0] !== '/') $path = '/' . $path;
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
	
	/* ===================== Procesador de imputaciones ===================== */

/**
 * POST /imputaciones/procesar
 * - Si recibe ids[] → procesa solo esas imputaciones
 * - Si recibe scope=all → procesa todas las PENDIENTES y COMPLETAS (tienen proyecto_id y proyecto_costo_id)
 * Reglas de impacto a proyecto_costos:
 *   cantidad_real += cantidad_imputada
 *   precio_unitario_real = promedio ponderado (subtotal_prev + monto_base) / (cantidad_prev + cantidad_imputada)
 */
public function procesar(){
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Método no permitido'; return;
    }

    $pdo = self::pdo();
    $uid = $_SESSION['user']['id'] ?? null;

    // 1) Resolver universo a procesar
    $ids = [];
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $v) { $ids[] = (int)$v; }
        $ids = array_values(array_unique(array_filter($ids)));
    }
    $scopeAll = isset($_POST['scope']) && $_POST['scope'] === 'all';

    try{
        $pdo->beginTransaction();

        if ($scopeAll) {
            // Todas las PENDIENTES y COMPLETAS
            $toProcess = $this->fetchImputacionesElegiblesAll();
        } else {
            // Solo las seleccionadas
            if (!$ids) throw new \Exception('No seleccionaste imputaciones.');
            $toProcess = $this->fetchImputacionesElegiblesByIds($ids);
        }

        if (!$toProcess) {
            throw new \Exception('No hay imputaciones elegibles para procesar.');
        }

        foreach ($toProcess as $imp) {
            $this->aplicarImputacion($imp, $uid);
        }

        $pdo->commit();
        if (class_exists('Session') && method_exists('Session','success')) {
            Session::success('Imputaciones aplicadas correctamente: '.count($toProcess));
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (class_exists('Session') && method_exists('Session','error')) {
            Session::error('No se pudo procesar: '.$e->getMessage());
        } else {
            error_log('[imputaciones][procesar] '.$e->getMessage());
        }
    }

    $this->redirect('/imputaciones/index');
}

/** Imputaciones pendientes y completas por IDs específicos */
private function fetchImputacionesElegiblesByIds(array $ids): array {
    if (!$ids) return [];
    $pdo = self::pdo();
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
      SELECT imp.*
        FROM compras_imputaciones imp
       WHERE imp.id IN ($in)
         AND imp.estado_proceso = 'pendiente'
         AND imp.proyecto_id IS NOT NULL
         AND imp.proyecto_costo_id IS NOT NULL
    ";
    $st = $pdo->prepare($sql);
    foreach ($ids as $k=>$v) { $st->bindValue($k+1, (int)$v, \PDO::PARAM_INT); }
    $st->execute();
    return $st->fetchAll(\PDO::FETCH_ASSOC);
}

/** Todas las imputaciones pendientes y completas (lista para “Procesar todo listo”) */
private function fetchImputacionesElegiblesAll(): array {
    $pdo = self::pdo();
    $st = $pdo->query("
      SELECT imp.*
        FROM compras_imputaciones imp
       WHERE imp.estado_proceso = 'pendiente'
         AND imp.proyecto_id IS NOT NULL
         AND imp.proyecto_costo_id IS NOT NULL
       ORDER BY imp.id ASC
       LIMIT 5000
    ");
    return $st->fetchAll(\PDO::FETCH_ASSOC);
}


/* ===================== Reversión de imputaciones ===================== */
/**
 * POST /imputaciones/revertir
 * - ids[]: lista de imputaciones a revertir (obligatorio si no usas scope)
 * - motivo: texto obligatorio
 * Reglas:
 *   - Solo estado_proceso='aplicada'
 *   - Proyecto debe estar 'abierto' (si existe columna proyectos.estado)
 *   - Integridad: no permite dejar cantidades/montos negativos
 *   - Concurrencia: FOR UPDATE sobre proyecto_costos
 *   - Auditoría: revertido_at/_por/_motivo
 */
 /** Detecta si una columna es GENERATED (no editable) */
private function isGeneratedColumn(string $table, string $col): bool {
    $pdo = self::pdo();
    $st = $pdo->prepare("
        SELECT EXTRA, GENERATION_EXPRESSION
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
           AND COLUMN_NAME = :c
         LIMIT 1
    ");
    $st->execute([':t'=>$table, ':c'=>$col]);
    $r = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$r) return false;
    $extra = strtolower((string)($r['EXTRA'] ?? ''));
    $gen   = $r['GENERATION_EXPRESSION'] ?? null;
    return (strpos($extra,'generated') !== false) || ($gen !== null && $gen !== '');
}

/** Aplica UNA imputación sumando cantidad_real y subtotal_real; si subtotal_real es GENERATED, ajusta PU */
private function aplicarImputacion(array $imp, $uid): void {
    $pdo = self::pdo();

    $impId   = (int)$imp['id'];
    $pcosto  = (int)$imp['proyecto_costo_id'];
    $qty     = (float)($imp['cantidad_imputada'] ?? 0);
    $montoB  = (float)($imp['monto_base'] ?? $imp['monto_imputado'] ?? 0);

    if ($pcosto <= 0) throw new \Exception("Imputación {$impId}: proyecto_costo_id inválido.");
    if ($qty < 0)     throw new \Exception("Imputación {$impId}: cantidad_imputada inválida.");
    if ($montoB < 0)  throw new \Exception("Imputación {$impId}: monto_base inválido.");

    $st = $pdo->prepare("SELECT cantidad_real, precio_unitario_real, 
                                (CASE WHEN COLUMN_NAME IS NULL THEN NULL ELSE 0 END) AS sr_dummy
                           FROM proyecto_costos pc
                           LEFT JOIN INFORMATION_SCHEMA.COLUMNS ic
                             ON ic.TABLE_SCHEMA = DATABASE() AND ic.TABLE_NAME='proyecto_costos' AND ic.COLUMN_NAME='subtotal_real'
                          WHERE pc.id = :id
                          FOR UPDATE");
    $st->execute([':id'=>$pcosto]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$row) throw new \Exception("ítem de costo no encontrado: {$pcosto}");

    $qPrev  = (float)($row['cantidad_real'] ?? 0);
    $puPrev = (float)($row['precio_unitario_real'] ?? 0);
    $subPrev= $qPrev * $puPrev;

    $qNew   = $qPrev + $qty;
    $subNew = $subPrev + $montoB;

    // ?subtotal_real existe y es editable?
    $cols = self::dbCols('proyecto_costos');
    $hasSubtotal = in_array('subtotal_real',$cols,true);
    $subtotalEditable = $hasSubtotal && !$this->isGeneratedColumn('proyecto_costos','subtotal_real');

    if ($subtotalEditable) {
        // Actualizamos cantidad_real y subtotal_real; PU se deriva
        $puNew = $qNew > 0 ? round($subNew / $qNew, 2) : $puPrev;
        $pdo->prepare("
            UPDATE proyecto_costos
               SET cantidad_real = :q,
                   subtotal_real = :s,
                   precio_unitario_real = :pu
             WHERE id = :id
        ")->execute([
            ':q'=>$qNew, ':s'=>$subNew, ':pu'=>$puNew, ':id'=>$pcosto
        ]);
    } else {
        // No podemos tocar subtotal_real -> ajustamos PU para mantener el subtotal implícito
        $puNew = $qNew > 0 ? round($subNew / $qNew, 2) : $puPrev;
        $pdo->prepare("
            UPDATE proyecto_costos
               SET cantidad_real = :q,
                   precio_unitario_real = :pu
             WHERE id = :id
        ")->execute([
            ':q'=>$qNew, ':pu'=>$puNew, ':id'=>$pcosto
        ]);
    }

    // Marcar imputación aplicada
    $pdo->prepare("
        UPDATE compras_imputaciones
           SET estado_proceso='aplicada',
               procesado_at = NOW(),
               procesado_por = :u
         WHERE id = :id
           AND estado_proceso='pendiente'
    ")->execute([':u'=>$uid, ':id'=>$impId]);
}

/** Reversión: resta cantidad_real y subtotal_real; si subtotal_real es GENERATED, ajusta PU; evita negativos */
private function revertirUna(array $imp, ?int $uid, string $motivo): void {
    $pdo     = self::pdo();
    $impId   = (int)$imp['id'];
    $pcosto  = (int)$imp['proyecto_costo_id'];
    $proyId  = (int)$imp['proyecto_id'];
    $qty     = (float)($imp['cantidad_imputada'] ?? 0);
    $montoB  = (float)($imp['monto_base'] ?? $imp['monto_imputado'] ?? 0);

    if ($pcosto <= 0) throw new \Exception("Imputación {$impId}: proyecto_costo_id inválido.");
    if ($proyId <= 0) throw new \Exception("Imputación {$impId}: proyecto_id inválido.");
    if (!$this->isProyectoAbierto($proyId)) {
        throw new \Exception("Proyecto {$proyId} no está abierto; no se puede revertir.");
    }

    // Bloquea el item de costo
    $st = $pdo->prepare("SELECT cantidad_real, precio_unitario_real FROM proyecto_costos WHERE id = :id FOR UPDATE");
    $st->execute([':id'=>$pcosto]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$row) throw new \Exception("ítem de costo no encontrado: {$pcosto}");

    $qPrev  = (float)($row['cantidad_real'] ?? 0);
    $puPrev = (float)($row['precio_unitario_real'] ?? 0);
    $subPrev= $qPrev * $puPrev;

    $qNew   = $qPrev - $qty;
    $subNew = $subPrev - $montoB;

    if ($qNew < -0.00001) throw new \Exception("Reversión inválida (#{$impId}): la cantidad quedaría negativa.");
    if ($subNew < -0.01)  throw new \Exception("Reversión inválida (#{$impId}): el subtotal quedaría negativo.");

    // ?subtotal_real editable?
    $cols = self::dbCols('proyecto_costos');
    $hasSubtotal = in_array('subtotal_real',$cols,true);
    $subtotalEditable = $hasSubtotal && !$this->isGeneratedColumn('proyecto_costos','subtotal_real');

    if ($subtotalEditable) {
        $puNew = $qNew > 0 ? round($subNew / $qNew, 2) : $puPrev;
        $pdo->prepare("
            UPDATE proyecto_costos
               SET cantidad_real = :q,
                   subtotal_real = :s,
                   precio_unitario_real = :pu
             WHERE id = :id
        ")->execute([':q'=>$qNew, ':s'=>$subNew, ':pu'=>$puNew, ':id'=>$pcosto]);
    } else {
        $puNew = $qNew > 0 ? round($subNew / $qNew, 2) : $puPrev;
        $pdo->prepare("
            UPDATE proyecto_costos
               SET cantidad_real = :q,
                   precio_unitario_real = :pu
             WHERE id = :id
        ")->execute([':q'=>$qNew, ':pu'=>$puNew, ':id'=>$pcosto]);
    }

    // IMPORTANTE: la dejamos PENDIENTE (no 'revertida') y limpiamos 'procesado_*'
    $pdo->prepare("
        UPDATE compras_imputaciones
           SET estado_proceso = 'pendiente',
               procesado_at   = NULL,
               procesado_por  = NULL,
               revertido_at   = NOW(),
               revertido_por  = :u,
               revert_motivo  = :m
         WHERE id = :id
           AND estado_proceso = 'aplicada'
    ")->execute([':u'=>$uid, ':m'=>$motivo, ':id'=>$impId]);
}
 
public function pcostos(){
    header('Content-Type: application/json; charset=utf-8');
    try{
        $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
        if ($proyecto_id <= 0) {
            echo json_encode(['ok'=>false,'error'=>'proyecto_id requerido']); exit;
        }
        $rows = $this->listarProyectoCostos($proyecto_id);
        echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE); exit;
    }catch(\Throwable $e){
        error_log('[pcostos] '.$e->getMessage());
        // devolvemos 200 para que fetch no entre a "network error"
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
}
 
 
 
public function revertir(){
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405); echo 'Método no permitido'; return;
    }

    $pdo = self::pdo();
    $uid = $_SESSION['user']['id'] ?? null;
    $motivo = trim((string)($_POST['motivo'] ?? ''));

    if ($motivo === '') {
        if (class_exists('Session') && method_exists('Session','error')) {
            Session::error('Debes indicar un motivo de reversión.');
        }
        $this->redirect('/imputaciones/index'); return;
    }

    // universo a revertir
    $ids = [];
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $v) { $ids[] = (int)$v; }
        $ids = array_values(array_unique(array_filter($ids)));
    }
    if (!$ids) {
        if (class_exists('Session') && method_exists('Session','error')) {
            Session::error('No seleccionaste imputaciones a revertir.');
        }
        $this->redirect('/imputaciones/index'); return;
    }

    try{
        $pdo->beginTransaction();

        // Trae imputaciones aplicadas y sus PCosto
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("
            SELECT imp.*
              FROM compras_imputaciones imp
             WHERE imp.id IN ($in)
               AND imp.estado_proceso = 'aplicada'
             FOR UPDATE
        ");
        foreach ($ids as $k=>$v) $st->bindValue($k+1, $v, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        if (!$rows) { throw new \Exception('Ninguna imputación elegible para revertir.'); }

        foreach ($rows as $imp) {
            $this->revertirUna($imp, $uid, $motivo);
        }

        $pdo->commit();
        if (class_exists('Session') && method_exists('Session','success')) {
            Session::success('Reversiones aplicadas: '.count($rows));
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (class_exists('Session') && method_exists('Session','error')) {
            Session::error('No se pudo revertir: '.$e->getMessage());
        } else {
            error_log('[imputaciones][revertir] '.$e->getMessage());
        }
    }

    $this->redirect('/imputaciones/index');
}


/** Proyecto 'abierto' requerido para revertir. Si no existe columna 'estado', permite. */
private function isProyectoAbierto(int $proyectoId): bool {
    $pdo = self::pdo();
    // ?Existe columna 'estado'?
    $hasEstado = false;
    try{
        $chk = $pdo->prepare("
            SELECT COUNT(*) 
              FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'proyectos' 
               AND COLUMN_NAME = 'estado'
        ");
        $chk->execute();
        $hasEstado = ((int)$chk->fetchColumn() > 0);
    } catch (\Throwable $e) { /* sin schema -> asumir abierto */ }

    if (!$hasEstado) return true;

    $st = $pdo->prepare("SELECT estado FROM proyectos WHERE id = :id");
    $st->execute([':id'=>$proyectoId]);
    $estado = strtolower((string)($st->fetchColumn() ?? 'abierto'));
    return ($estado === 'abierto');
}
	
	/** Trae la imputación por compra_item_id. Si $soloPendiente=true, solo estado 'pendiente'. */
private function getImputacionPorItem(int $itemId, bool $soloPendiente=false): ?array {
    $pdo = self::pdo();
    $sql = "SELECT * FROM compras_imputaciones WHERE compra_item_id = :id ";
    if ($soloPendiente) $sql .= " AND estado_proceso = 'pendiente' ";
    $sql .= " ORDER BY id DESC LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':id'=>$itemId]);
    $r = $st->fetch(\PDO::FETCH_ASSOC);
    return $r ?: null;
}

/** Si la imputación trae proyecto_costo_id úsalo; si trae solo 'codigo', mapear a pcosto.id en ese proyecto. */
private function resolverPcostoIdDesdeImputacion(array $imp, int $proyectoIdSel): int {
    $pc = (int)($imp['proyecto_costo_id'] ?? 0);
    if ($pc > 0) return $pc;

    $codigo = $imp['codigo'] ?? null;
    if ($codigo && $proyectoIdSel > 0) {
        $pdo = self::pdo();
        $cols = self::dbCols('proyecto_costos');
        $cCol = in_array('codigo',$cols,true) ? 'codigo' : null;
        if ($cCol) {
            $st = $pdo->prepare("SELECT id FROM proyecto_costos WHERE proyecto_id = :p AND {$cCol} = :c LIMIT 1");
            $st->execute([':p'=>$proyectoIdSel, ':c'=>$codigo]);
            $id = $st->fetchColumn();
            if ($id) return (int)$id;
        }
    }
    return 0;
}

/** Datos de proyecto_costo (código y glosa) para sincronizar 'codigo' en imputaciones si existe */
private function fetchProyectoCostoInfo(int $pcosto_id): array {
    if ($pcosto_id <= 0) return [];
    $pdo = self::pdo();
    $st = $pdo->prepare("SELECT id, codigo, costo_glosa FROM proyecto_costos WHERE id = :id");
    $st->execute([':id'=>$pcosto_id]);
    return $st->fetch(\PDO::FETCH_ASSOC) ?: [];
}

/** Actualiza columnas de imputación guardadas en compras_items si existen (imp_proyecto_id, imp_pcosto_id) */
private function updateCompraItemImputacion(int $itemId, int $proyecto_id, int $pcosto_id): void {
    $pdo = self::pdo();
    $cols = self::dbCols('compras_items');
    $set  = []; $par = [':id'=>$itemId];
    if (in_array('imp_proyecto_id',$cols,true)) { $set[]="imp_proyecto_id=:p";  $par[':p']=$proyecto_id; }
    if (in_array('imp_pcosto_id',  $cols,true)) { $set[]="imp_pcosto_id=:pc";   $par[':pc']=$pcosto_id; }
    if (!$set) return;
    $sql = "UPDATE compras_items SET ".implode(',',$set)." WHERE id=:id";
    $pdo->prepare($sql)->execute($par);
}
private function aplicarPendienteDeItem(int $itemId, ?int $uid): void {
    $imp = $this->getImputacionPorItem($itemId, true);
    if (!$imp) throw new \Exception('No hay imputación pendiente para aplicar.');
    $this->aplicarImputacion($imp, $uid);
}
}
