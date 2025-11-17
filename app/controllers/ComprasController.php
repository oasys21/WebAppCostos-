<?php
declare(strict_types=1);

class ComprasController extends Controller
{
    /* ================= Infra ================= */

    private static function pdo(): \PDO {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) return $GLOBALS['pdo'];
        if (function_exists('db')) { $c = db(); if ($c instanceof \PDO) return $c; }
        if (isset($GLOBALS['cfg']['DB']['dsn'])) {
            $dbcfg = $GLOBALS['cfg']['DB'];
            $opts  = $dbcfg['options'] ?? [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            $pdo = new \PDO($dbcfg['dsn'], $dbcfg['user'] ?? '', $dbcfg['pass'] ?? '', $opts);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            return $pdo;
        }
        throw new \Exception('No hay PDO');
    }
    private function baseUrl(): string {
        if (!empty($GLOBALS['cfg']['BASE_URL'])) return rtrim($GLOBALS['cfg']['BASE_URL'], '/');
        $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $b  = rtrim(str_replace('\\','/', dirname($sn)), '/');
        return ($b === '' || $b === '.') ? '' : $b;
    }
    private function rt(string $tag, $data): void {
        try {
            $root = dirname(__DIR__, 2);
            $dir  = $root . '/runtime';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $line = date('Y-m-d H:i:s') . " [{$tag}] " . json_encode($data, JSON_UNESCAPED_UNICODE);
            error_log($line . PHP_EOL, 3, $dir . '/compras.log');
        } catch (\Throwable $e) {
            error_log("[compras.rt.fail] ".$e->getMessage());
        }
    }
    private function flash(string $type, string $msg): void {
        if (class_exists('Session') && method_exists('Session', $type)) { @Session::$type($msg); return; }
        if (!isset($_SESSION)) @session_start();
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }

    /* ============== Listado ============== */

    public function index(){
        $filters = [
            'folio'        => $_GET['folio']        ?? null,
            'proveedor_id' => $_GET['proveedor_id'] ?? null,
            'proyecto_id'  => $_GET['proyecto_id']  ?? null,
            'tipo_doc'     => $_GET['tipo_doc']     ?? null,
            'estado'       => $_GET['estado']       ?? null,
            'desde'        => $_GET['desde']        ?? null,
            'hasta'        => $_GET['hasta']        ?? null,
        ];
        $compras     = Compra::buscar($filters, 200); // orden ya es DESC por fecha_doc,id
        $proveedores = Compra::listarProveedores();
        $proyectos   = Compra::listarProyectos();
        $pageTitle   = 'Compras';
        $this->render('compras/index', compact('compras','filters','proveedores','proyectos','pageTitle'));
    }

    /* ============== Crear ============== */

    public function create(){
        $proveedores = Compra::listarProveedores();
        $proyectos   = Compra::listarProyectos();

        $compra = [
            'id'            => null,
            'proveedor_id'  => null,
            'proyecto_id'   => null, // cabecera opcional; ítems pueden sobrescribir
            'oc_id'         => null,
            'tipo_doc'      => 'FAC',
            'folio'         => '',
            'fecha_doc'     => date('Y-m-d'),
            'moneda'        => 'CLP',
            'tipo_cambio'   => '1.000000',
            'estado'        => 'borrador',
            'subtotal'      => 0,
            'descuento'     => 0,
            'impuesto'      => 0,
            'observaciones' => ''
        ];
        $items = [];
        $pageTitle = 'Nueva compra';
        $this->render('compras/form', compact('compra','items','proveedores','proyectos','pageTitle'));
    }
    public function nuevo(){ return $this->create(); }

    public function store(){
        $this->rt('store.enter', [
            'REQUEST_METHOD'=> $_SERVER['REQUEST_METHOD'] ?? '',
            'has_POST'      => !empty($_POST),
            'keys'          => array_keys($_POST ?: []),
            'items_count'   => isset($_POST['items']) && is_array($_POST['items']) ? count($_POST['items']) : 0,
        ]);

        try{
            if (!isset($_SESSION)) @session_start();
            if (!empty($_SESSION['form_token']) && isset($_POST['form_token']) && hash_equals($_SESSION['form_token'], $_POST['form_token'])) {
                unset($_SESSION['form_token']);
            }

            $payload = $this->sanitizeCompra($_POST); // incluye __pcosto_defecto_id (solo para lógica, no BD)
            $items   = $this->sanitizeItems($_POST['items'] ?? []);
            $this->rt('store.sanitized', [
                'payload'=> $payload,
                'items0' => $items[0] ?? null,
                'items_n'=> count($items),
            ]);

            $dupId = Compra::buscarIdPorProvTipoFolio((int)$payload['proveedor_id'], (string)$payload['tipo_doc'], (string)$payload['folio']);
            if ($dupId) {
                $this->rt('store.duplicate', ['dupId'=>$dupId]);
                $this->flash('warning','Ese documento ya existía. Vamos a su edición.');
                $this->redirect('/compras/editar/'.$dupId);
                return;
            }

            $compra_id = Compra::crear($payload, $items);
            $this->rt('store.created', ['compra_id'=>$compra_id]);

            $this->generarImputacionesIniciales($compra_id, $items, $payload);
            $this->rt('store.imputaciones.done', ['compra_id'=>$compra_id]);

            $this->flash('success','Compra creada e imputaciones iniciales registradas.');
            $this->redirect('/compras/ver/'.$compra_id);

        }catch(\PDOException $e){
            $this->rt('store.pdo_error', ['msg'=>$e->getMessage(), 'info'=>$e->errorInfo ?? null]);
            $this->flash('error','Error SQL: '.$e->getMessage());
            $this->redirect('/compras/nuevo');
        }catch(\Throwable $e){
            $this->rt('store.error', ['msg'=>$e->getMessage()]);
            $this->flash('error','Error: '.$e->getMessage());
            $this->redirect('/compras/nuevo');
        }
    }
    public function guardar(){ return $this->store(); }

    /* ============== Ver / Editar ============== */

    public function show($id){
        $compra = Compra::buscarPorId((int)$id);
        if(!$compra){ http_response_code(404); echo 'No encontrada'; return; }
        $items = Compra::listarItems((int)$id);
        $pageTitle = 'Compra '.$compra['tipo_doc'].' '.$compra['folio'];
        $this->render('compras/show', compact('compra','items','pageTitle'));
    }
    public function ver($id){ return $this->show($id); }

    public function edit($id){
        $compra = Compra::buscarPorId((int)$id);
        if(!$compra){ http_response_code(404); echo 'No encontrada'; return; }
        if($compra['estado']!=='borrador'){
            $this->flash('error','Solo puedes editar compras en BORRADOR.');
            $this->redirect('/compras/ver/'.$id);
            return;
        }
        $items       = Compra::listarItems((int)$id);
        $proveedores = Compra::listarProveedores();
        $proyectos   = Compra::listarProyectos();
        $pageTitle   = 'Editar compra';
        $this->render('compras/form', compact('compra','items','proveedores','proyectos','pageTitle'));
    }
    public function editar($id){ return $this->edit($id); }

    public function update($id){
        $this->rt('update.enter', ['id'=>(int)$id,'keys'=>array_keys($_POST ?: [])]);
        try{
            $compra = Compra::buscarPorId((int)$id);
            if(!$compra) throw new \Exception('Compra no encontrada');
            if($compra['estado']!=='borrador') throw new \Exception('Solo se puede editar en BORRADOR');

            $payload = $this->sanitizeCompra($_POST);
            $items   = $this->sanitizeItems($_POST['items'] ?? []);
            $this->rt('update.sanitized', ['payload'=>$payload, 'items_n'=>count($items)]);

            Compra::actualizar((int)$id, $payload, $items);
            $this->rt('update.updated', ['id'=>(int)$id]);

            $this->generarImputacionesFaltantes((int)$id, $items, $payload);
            $this->rt('update.imputaciones.done', ['id'=>(int)$id]);

            $this->flash('success','Compra actualizada.');
            $this->redirect('/compras/ver/'.$id);

        }catch(\PDOException $e){
            $this->rt('update.pdo_error', ['msg'=>$e->getMessage(), 'info'=>$e->errorInfo ?? null]);
            $this->flash('error','Error SQL: '.$e->getMessage());
            $this->redirect('/compras/editar/'.$id);
        }catch(\Throwable $e){
            $this->rt('update.error', ['msg'=>$e->getMessage()]);
            $this->flash('error','Error: '.$e->getMessage());
            $this->redirect('/compras/editar/'.$id);
        }
    }
    public function actualizar($id){ return $this->update($id); }

    /* ============== AJAX: ítems de costo del proyecto ============== */

    public function pcostos($proyecto_id = null){
        $pdo = self::pdo();
        header('Content-Type: application/json; charset=utf-8');
        try{
            $pid = $proyecto_id !== null ? (int)$proyecto_id : (int)($_GET['proyecto_id'] ?? 0);
            if ($pid <= 0) { echo json_encode([]); return; }

            $cols = $this->dbCols('proyecto_costos');
            $code = in_array('codigo',$cols,true) ? 'codigo' : (in_array('code',$cols,true) ? 'code' : 'id');
            $name = in_array('costo_glosa',$cols,true) ? 'costo_glosa'
                   : (in_array('descripcion',$cols,true) ? 'descripcion'
                   : (in_array('nombre',$cols,true) ? 'nombre' : $code));

            $sql = "SELECT id, {$code} AS codigo, {$name} AS nombre
                      FROM proyecto_costos
                     WHERE proyecto_id = :p
                  ORDER BY {$code}, id";
            $st = $pdo->prepare($sql); $st->execute([':p'=>$pid]);
            echo json_encode($st->fetchAll());
        }catch(\Throwable $e){
            http_response_code(500);
            echo json_encode(['error'=>$e->getMessage()]);
        }
    }

    /* ============== Helpers ============== */

    private function dbCols(string $table): array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t'=>$table]);
        return array_map('strval',$st->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function sanitizeCompra(array $in): array {
        return [
            'proveedor_id'        => (int)($in['proveedor_id'] ?? 0),
            'proyecto_id'         => ($in['proyecto_id'] ?? '') !== '' ? (int)$in['proyecto_id'] : null,
            'oc_id'               => ($in['oc_id'] ?? '') !== '' ? (int)$in['oc_id'] : null,
            'tipo_doc'            => strtoupper(trim((string)($in['tipo_doc'] ?? 'FAC'))),
            'folio'               => trim((string)($in['folio'] ?? '')),
            'fecha_doc'           => trim((string)($in['fecha_doc'] ?? date('Y-m-d'))),
            'moneda'              => strtoupper(trim((string)($in['moneda'] ?? 'CLP'))),
            'tipo_cambio'         => number_format((float)($in['tipo_cambio'] ?? 1), 6, '.', ''),
            'estado'              => 'borrador',
            'subtotal'            => number_format((float)($in['subtotal'] ?? 0), 2, '.', ''),
            'descuento'           => number_format((float)($in['descuento'] ?? 0), 2, '.', ''),
            'impuesto'            => number_format((float)($in['impuesto'] ?? 0), 2, '.', ''),
            'observaciones'       => trim((string)($in['observaciones'] ?? '')),
            'usuario_id'          => isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0,
            // Solo para lógica de imputación por defecto (NO se guarda en compras)
            '__pcosto_defecto_id' => ($in['pcosto_defecto_id'] ?? '') !== '' ? (int)$in['pcosto_defecto_id'] : null,
        ];
    }

    private function sanitizeItems($items){
        $out = [];
        if (!is_array($items)) return $out;

        foreach($items as $i){
            $codigo = isset($i['codigo']) ? trim($i['codigo']) : '';
            if ($codigo === '') continue;

            $oc_item_id      = (isset($i['oc_item_id']) && $i['oc_item_id']!=='') ? (int)$i['oc_item_id'] : null;
            $linea           = isset($i['linea']) ? (int)$i['linea'] : null;
            $descripcion     = isset($i['descripcion']) ? trim($i['descripcion']) : '';
            $unidad          = isset($i['unidad']) ? trim($i['unidad']) : 'UND';
            $tipo_costo_in   = isset($i['tipo_costo']) ? $i['tipo_costo'] : 'MAT';
            $tipo_costo      = in_array($tipo_costo_in, ['MAT','MO','EQ','SUBC']) ? $tipo_costo_in : 'MAT';
            $cantidad        = number_format((float)($i['cantidad'] ?? 0), 2, '.', '');
            $precio_unitario = number_format((float)($i['precio_unitario'] ?? 0), 2, '.', '');
            $fecha_servicio  = isset($i['fecha_servicio']) ? trim($i['fecha_servicio']) : '';

            // ▼▼ NUEVO: proyecto/ítem de costo seleccionados en el formulario (pueden venir vacíos)
            $imp_proyecto_id = (isset($i['imp_proyecto_id']) && $i['imp_proyecto_id']!=='') ? (int)$i['imp_proyecto_id'] : null;
            $imp_pcosto_id   = (isset($i['imp_pcosto_id'])   && $i['imp_pcosto_id']  !=='') ? (int)$i['imp_pcosto_id']   : null;

            $out[] = [
                'id'              => isset($i['id']) ? (int)$i['id'] : null,
                'oc_item_id'      => $oc_item_id,
                'linea'           => $linea,
                'codigo'          => $codigo,
                'descripcion'     => $descripcion,
                'unidad'          => $unidad,
                'tipo_costo'      => $tipo_costo,
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio_unitario,
                'fecha_servicio'  => $fecha_servicio,

                // ▼▼ NUEVO
                'imp_proyecto_id' => $imp_proyecto_id,
                'imp_pcosto_id'   => $imp_pcosto_id,
            ];
        }
        return $out;
    }

    private function imputacionExiste(\PDO $pdo, int $compraItemId, ?int $proyectoId, ?int $pcostoId, float $monto): bool {
        // NULL-safe comparisons con <=> para que NULL == NULL
        $sql = "SELECT 1
                  FROM compras_imputaciones
                 WHERE compra_item_id = :ci
                   AND proyecto_id     <=> :p
                   AND proyecto_costo_id <=> :pc
                   AND ABS(monto_imputado - :m) < 0.005
                 LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':ci'=>$compraItemId, ':p'=>$proyectoId, ':pc'=>$pcostoId, ':m'=>$monto]);
        return (bool)$st->fetchColumn();
    }

    private function generarImputacionesIniciales(int $compra_id, array $itemsSanitizados, array $compraCab): void {
        $pdo = self::pdo();

        $itemsDb = Compra::listarItems($compra_id);
        if (!$itemsDb) { $this->rt('impu.skip.noitems', ['compra_id'=>$compra_id]); return; }

        $moneda      = $compraCab['moneda'] ?? null;
        $tipo_cambio = isset($compraCab['tipo_cambio']) ? (float)$compraCab['tipo_cambio'] : null;
        $usuario_id  = $compraCab['usuario_id'] ?? ($_SESSION['user']['id'] ?? 0);

        // Defaults desde cabecera
        $defProy   = $compraCab['proyecto_id'] ?? null;
        $defPcosto = $compraCab['__pcosto_defecto_id'] ?? null;

        $sql = "INSERT INTO compras_imputaciones
                    (compra_item_id, proyecto_id, proyecto_costo_id, codigo,
                     cantidad_imputada, monto_imputado, moneda, tipo_cambio, monto_base,
                     porcentaje_imputado, fecha_imputacion, origen, usuario_id)
                VALUES
                    (:ci, :p, :pc, :cod,
                     :cant, :mi, :mda, :tc, :mb,
                     :porc, CURDATE(), 'manual', :u)";
        $ins = $pdo->prepare($sql);

        foreach ($itemsDb as $idx => $row) {
            $san = $itemsSanitizados[$idx] ?? null;
            if (!$san) continue;

            $cant  = (float)$san['cantidad'];
            $pu    = (float)$san['precio_unitario'];
            $monto = round($cant * $pu, 2);
            $mbase = $tipo_cambio ? round($monto * (float)$tipo_cambio, 2) : $monto;
            $porc  = ($monto > 0) ? 1.0 : null;

            $pSel  = $san['imp_proyecto_id'] ?? $defProy;
            $pcSel = $san['imp_pcosto_id']   ?? $defPcosto;

            $codigo = substr((string)$row['codigo'], 0, 10);

            try{
                if ($this->imputacionExiste($pdo, (int)$row['id'], $pSel, $pcSel, $monto)) {
                    $this->rt('impu.skip.duplicate', ['compra_item_id'=>(int)$row['id'], 'p'=>$pSel, 'pc'=>$pcSel, 'm'=>$monto]);
                    continue;
                }

                $ins->execute([
                    ':ci'   => (int)$row['id'],
                    ':p'    => $pSel,
                    ':pc'   => $pcSel,
                    ':cod'  => $codigo,
                    ':cant' => $cant,
                    ':mi'   => $monto,
                    ':mda'  => $moneda,
                    ':tc'   => $tipo_cambio,
                    ':mb'   => $mbase,
                    ':porc' => $porc,
                    ':u'    => $usuario_id,
                ]);
            }catch(\PDOException $e){
                $this->rt('impu.insert.pdo_error', ['item_idx'=>$idx, 'msg'=>$e->getMessage()]);
                throw $e;
            }
        }
    }

    private function generarImputacionesFaltantes(int $compra_id, array $itemsSanitizados, array $compraCab): void {
        $pdo = self::pdo();
        $itemsDb = Compra::listarItems($compra_id);
        if (!$itemsDb) return;

        $moneda      = $compraCab['moneda'] ?? null;
        $tipo_cambio = isset($compraCab['tipo_cambio']) ? (float)$compraCab['tipo_cambio'] : null;
        $usuario_id  = $compraCab['usuario_id'] ?? ($_SESSION['user']['id'] ?? 0);
        $defProy     = $compraCab['proyecto_id'] ?? null;
        $defPcosto   = $compraCab['__pcosto_defecto_id'] ?? null;

        $hasImpu = $pdo->prepare("SELECT COUNT(*) FROM compras_imputaciones WHERE compra_item_id = :id");
        $ins = $pdo->prepare(
            "INSERT INTO compras_imputaciones
                (compra_item_id, proyecto_id, proyecto_costo_id, codigo,
                 cantidad_imputada, monto_imputado, moneda, tipo_cambio, monto_base,
                 porcentaje_imputado, fecha_imputacion, origen, usuario_id)
             VALUES
                (:ci, :p, :pc, :cod, :cant, :mi, :mda, :tc, :mb, :porc, CURDATE(), 'manual', :u)"
        );

        foreach ($itemsDb as $idx => $row) {
            $hasImpu->execute([':id'=>(int)$row['id']]);
            if ((int)$hasImpu->fetchColumn() > 0) continue;

            $san   = $itemsSanitizados[$idx] ?? null;
            $cant  = $san ? (float)$san['cantidad'] : (float)$row['cantidad'];
            $pu    = $san ? (float)$san['precio_unitario'] : (float)$row['precio_unitario'];
            $monto = round($cant * $pu, 2);
            $mbase = $tipo_cambio ? round($monto * (float)$tipo_cambio, 2) : $monto;

            $pSel  = $san['imp_proyecto_id'] ?? $defProy;
            $pcSel = $san['imp_pcosto_id']   ?? $defPcosto;

            $codigo= substr((string)$row['codigo'], 0, 10);
            $porc  = ($monto > 0) ? 1.0 : null;

            if ($this->imputacionExiste($pdo, (int)$row['id'], $pSel, $pcSel, $monto)) {
                $this->rt('impu.skip.duplicate', ['compra_item_id'=>(int)$row['id'], 'p'=>$pSel, 'pc'=>$pcSel, 'm'=>$monto]);
                continue;
            }

            $ins->execute([
                ':ci'   => (int)$row['id'],
                ':p'    => $pSel,
                ':pc'   => $pcSel,
                ':cod'  => $codigo,
                ':cant' => $cant,
                ':mi'   => $monto,
                ':mda'  => $moneda,
                ':tc'   => $tipo_cambio,
                ':mb'   => $mbase,
                ':porc' => $porc,
                ':u'    => $usuario_id,
            ]);
        }
    }

    /* ===== Fallbacks de render / redirect ===== */
    protected function render(string $view, array $vars = []): void {
        extract($vars, EXTR_OVERWRITE);
        if (!isset($pageTitle)) $pageTitle = 'Compras';
        $viewsRoot  = dirname(__DIR__) . '/views/';
        $viewFile   = $viewsRoot . $view . '.php';
        $headerFile = $viewsRoot . 'layout/header.php';
        $footerFile = $viewsRoot . 'layout/footer.php';
        if (is_file($headerFile)) require $headerFile;
        if (!is_file($viewFile)) { http_response_code(500); echo "Vista no encontrada: ".htmlspecialchars($viewFile); return; }
        require $viewFile;
        if (is_file($footerFile)) require $footerFile;
    }
    protected function redirect(string $path): void {
        $base = $this->baseUrl();
        if ($path === '' || $path[0] !== '/') $path = '/' . $path;
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    /* ============== Eliminaciones ============== */

    /** Recalcula compras.subtotal desde items */
    private function recalcSubtotal(int $compraId): void {
        $pdo = self::pdo();
        $pdo->prepare(
            "UPDATE compras c
               LEFT JOIN (
                 SELECT compra_id, SUM(cantidad*precio_unitario) sm
                   FROM compras_items
                  WHERE compra_id = :cid
               ) x ON x.compra_id = c.id
             SET c.subtotal = IFNULL(x.sm,0)
             WHERE c.id = :cid"
        )->execute([':cid'=>$compraId]);
    }

    /** Reversa una imputación aplicada sobre proyecto_costos y marca la imputación como revertida */
    private function revertirImputacionAplicada(\PDO $pdo, array $imp, int $uid = 0, string $motivo = ''): void {
        $impId  = (int)$imp['id'];
        $pcosto = (int)($imp['proyecto_costo_id'] ?? 0);
        $proyId = (int)($imp['proyecto_id'] ?? 0);
        $qty    = (float)($imp['cantidad_imputada'] ?? 0);
        $montoB = (float)($imp['monto_base'] ?? $imp['monto_imputado'] ?? 0);

        if ($pcosto > 0 && $proyId > 0) {
            // Lock del item de costo
            $st = $pdo->prepare("SELECT * FROM proyecto_costos WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$pcosto]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $cols = $this->dbCols('proyecto_costos');
                $hasCant = in_array('cantidad_real', $cols, true);
                $hasPU   = in_array('precio_unitario_real', $cols, true);
                $hasSub  = in_array('subtotal_real', $cols, true);

                $qPrev   = $hasCant ? (float)$row['cantidad_real'] : 0.0;
                $puPrev  = $hasPU   ? (float)$row['precio_unitario_real'] : 0.0;
                $subPrev = $hasSub  ? (float)$row['subtotal_real'] : ($qPrev*$puPrev);

                $qNew   = $hasCant ? max($qPrev - $qty, 0.0) : $qPrev;
                $subNew = max($subPrev - $montoB, 0.0);
                $puNew  = ($qNew > 0.000001) ? ($subNew / $qNew) : 0.0;

                try{
                    if ($hasSub) {
                        $pdo->prepare("
                            UPDATE proyecto_costos
                               SET ".($hasCant ? "cantidad_real = :q, " : "")."
                                   subtotal_real = :s
                                   ".($hasPU ? ", precio_unitario_real = :pu" : "")."
                             WHERE id = :id
                             LIMIT 1
                        ")->execute([
                            ':q'=>$qNew, ':s'=>$subNew, ':pu'=>$puNew, ':id'=>$pcosto
                        ]);
                    } else {
                        // Ajuste por cantidad y PU
                        $set = [];
                        $params = [ ':id'=>$pcosto ];
                        if ($hasCant) { $set[]="cantidad_real = :q"; $params[':q']=$qNew; }
                        if ($hasPU)   { $set[]="precio_unitario_real = :pu"; $params[':pu']=$puNew; }
                        if ($set) {
                            $pdo->prepare("UPDATE proyecto_costos SET ".implode(',', $set)." WHERE id = :id LIMIT 1")->execute($params);
                        }
                    }
                }catch(\Throwable $e){
                    $this->rt('revert.pcostos.fail', ['imp_id'=>$impId,'msg'=>$e->getMessage()]);
                }
            }
        }

        // Marcar imputación como revertida si está aplicada
        try{
            $pdo->prepare("
                UPDATE compras_imputaciones
                   SET estado_proceso = 'revertida',
                       revertido_at   = NOW(),
                       revertido_por  = :u,
                       revert_motivo  = :m
                 WHERE id = :id AND estado_proceso = 'aplicada'
            ")->execute([':u'=>$uid, ':m'=>$motivo, ':id'=>$impId]);
        }catch(\Throwable $e){
            // Si no existen esas columnas, degradar a solo estado_proceso
            $pdo->prepare("
                UPDATE compras_imputaciones
                   SET estado_proceso = 'revertida'
                 WHERE id = :id AND estado_proceso = 'aplicada'
            ")->execute([':id'=>$impId]);
        }
    }

    /** LogSys helper (seguro si la clase/método no existen) */
    private function logSys(string $accion, string $entidad, int $entidadId, array $detalle = []): void {
        try{
            if (class_exists('LogSys')) {
                $pdo = self::pdo();
                $log = new LogSys($pdo);
                if (method_exists($log, 'add')) {
                    $u   = $_SESSION['user'] ?? [];
                    $uid = (int)($u['id'] ?? 0);
                    $rut = (string)($u['rut'] ?? '');
                    $nom = (string)($u['nombre'] ?? ($u['username'] ?? ''));
                    $log->add($uid, $rut, $nom, $accion, $entidad, $entidadId, json_encode($detalle, JSON_UNESCAPED_UNICODE));
                }
            }
        }catch(\Throwable $e){
            $this->rt('logsys.fail', ['accion'=>$accion,'entidad'=>$entidad,'id'=>$entidadId,'msg'=>$e->getMessage()]);
        }
    }

    /** Eliminar Ítem (con reversión previa de imputaciones aplicadas) */
    public function destroyItem($compraId, $itemId){
        $compraId = (int)$compraId;
        $itemId   = (int)$itemId;

        try{
            // Autorización: login + perfil ADM
            $u = $_SESSION['user'] ?? null;
            if (!$u || (($u['perfil'] ?? '') !== 'ADM')) {
                if (class_exists('Session') && method_exists('Session','error')) {
                    Session::error('No autorizado para eliminar ítems de compras.');
                }
                $this->redirect('/compras/ver/'.$compraId);
                return;
            }

            $pdo = self::pdo();
            $pdo->beginTransaction();

            // Validar item pertenece a compra
            $st = $pdo->prepare("SELECT * FROM compras_items WHERE id = :it AND compra_id = :cid FOR UPDATE");
            $st->execute([':it'=>$itemId, ':cid'=>$compraId]);
            $item = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$item) throw new \Exception('Ítem no encontrado en la compra.');

            // Imputaciones del ítem
            $rows = $pdo->prepare("SELECT * FROM compras_imputaciones WHERE compra_item_id = :it FOR UPDATE");
            $rows->execute([':it'=>$itemId]);
            $imps = $rows->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($imps as $imp) {
                if (($imp['estado_proceso'] ?? 'pendiente') === 'aplicada') {
                    $this->revertirImputacionAplicada($pdo, $imp, (int)($u['id'] ?? 0), 'Eliminación de ítem de compra');
                }
            }

            // Borrar imputaciones del ítem (por si quedaron pendientes)
            $pdo->prepare("DELETE FROM compras_imputaciones WHERE compra_item_id = :it")->execute([':it'=>$itemId]);

            // Eliminar el ítem
            $pdo->prepare("DELETE FROM compras_items WHERE id = :it")->execute([':it'=>$itemId]);

            // Recalcular subtotal de la compra
            $this->recalcSubtotal($compraId);

            $pdo->commit();

            // LogSys
            $this->logSys('COMPRAS_ITEM_ELIMINAR', 'compras_items', $itemId, ['compra_id'=>$compraId,'item_id'=>$itemId]);

            if (class_exists('Session') && method_exists('Session','success')) {
                Session::success('Ítem eliminado correctamente.');
            }
            $this->redirect('/compras/ver/'.$compraId);

        }catch(\Throwable $e){
            $pdo = null;
            if (method_exists($this, 'rt')) { $this->rt('destroyItem.error', ['cid'=>$compraId,'iid'=>$itemId,'msg'=>$e->getMessage()]); }
            if (class_exists('Session') && method_exists('Session','error')) {
                Session::error('No se pudo eliminar el ítem: '.$e->getMessage());
            }
            $this->redirect('/compras/ver/'.$compraId);
        }
    }

    public function destroy($id){
        $id = (int)$id;
        try{
            // Autorización (login + perfil ADM)
            $u = $_SESSION['user'] ?? null;
            if (!$u || (($u['perfil'] ?? '') !== 'ADM')) {
                if (class_exists('Session') && method_exists('Session','error')) {
                    Session::error('No autorizado para eliminar compras.');
                }
                $this->redirect('/compras');
                return;
            }

            $pdo = self::pdo();
            $pdo->beginTransaction();

            // Revertir imputaciones APLICADAS de TODOS los ítems de la compra
            $q = $pdo->prepare("
                SELECT imp.*
                  FROM compras_imputaciones imp
                  JOIN compras_items it ON it.id = imp.compra_item_id
                 WHERE it.compra_id = :cid
                 FOR UPDATE
            ");
            $q->execute([':cid'=>$id]);
            $imps = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($imps as $imp) {
                if (($imp['estado_proceso'] ?? 'pendiente') === 'aplicada') {
                    $this->revertirImputacionAplicada($pdo, $imp, (int)($u['id'] ?? 0), 'Eliminación de compra');
                }
            }

            // Borrar imputaciones (todas) de ítems de esta compra
            $pdo->prepare("
                DELETE imp FROM compras_imputaciones imp
                JOIN compras_items it ON it.id = imp.compra_item_id
                WHERE it.compra_id = :cid
            ")->execute([':cid'=>$id]);

            // Borrar ítems de la compra
            $pdo->prepare("DELETE FROM compras_items WHERE compra_id = :cid")->execute([':cid'=>$id]);

            // Borrar cabecera
            $pdo->prepare("DELETE FROM compras WHERE id = :cid")->execute([':cid'=>$id]);

            $pdo->commit();

            // LogSys
            $this->logSys('COMPRAS_ELIMINAR', 'compras', $id, ['compra_id'=>$id]);

            if (class_exists('Session') && method_exists('Session','success')) {
                Session::success('Compra eliminada y reversiones aplicadas.');
            }
            $this->redirect('/compras');

        }catch(\Throwable $e){
            try{ if ($pdo && $pdo->inTransaction()) $pdo->rollBack(); }catch(\Throwable $e2){}
            if (method_exists($this, 'rt')) { $this->rt('destroy.error', ['id'=>$id,'msg'=>$e->getMessage()]); }
            if (class_exists('Session') && method_exists('Session','error')) {
                Session::error('No se pudo eliminar: '.$e->getMessage());
            }
            $this->redirect('/compras/ver/'.$id);
        }
    }
}
