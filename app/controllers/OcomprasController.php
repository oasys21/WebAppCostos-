<?php
declare(strict_types=1);

class OcomprasController extends Controller
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
            error_log($line . PHP_EOL, 3, $dir . '/ocompras.log');
        } catch (\Throwable $e) { error_log("[ocompras.rt.fail] ".$e->getMessage()); }
    }
    private function flash(string $type, string $msg): void {
        if (class_exists('Session') && method_exists('Session', $type)) { @Session::$type($msg); return; }
        if (!isset($_SESSION)) @session_start();
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }
    private function isDev(): bool { $env = (string)($GLOBALS['cfg']['ENV'] ?? ''); return strtoupper($env) === 'DEV'; }

    /* ================= Utilidades de parsing ================= */
    private function parseMoneyInt($v): float {
        $s = (string)$v;
        if ($s === '') return 0.0;
        $s = preg_replace('/[^0-9]/', '', $s);
        if ($s === '' ) return 0.0;
        return (float)$s;
    }
    private function parseQty2($v): float {
        $s = (string)$v;
        if ($s === '') return 0.0;
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.]/', '', $s);
        if ($s === '' ) return 0.0;
        $p = strpos($s, '.');
        if ($p !== false) {
            $s = substr($s, 0, $p+1) . str_replace('.', '', substr($s, $p+1));
        }
        return (float)$s;
    }

    /* ============== Listado ============== */
    public function index(){
        $filters = [
            'oc_num'       => $_GET['oc_num']       ?? null,
            'proveedor_id' => $_GET['proveedor_id'] ?? null,
            'proyecto_id'  => $_GET['proyecto_id']  ?? null,
            'estado'       => $_GET['estado']       ?? null,
            'desde'        => $_GET['desde']        ?? null,
            'hasta'        => $_GET['hasta']        ?? null,
        ];
        $ocs         = Ocompras::buscar($filters, 200);
        $proveedores = Ocompras::listarProveedores();
        $proyectos   = Ocompras::listarProyectos();
        $pageTitle   = 'Órdenes de Compra';
        $this->render('ocompras/index', compact('ocs','filters','proveedores','proyectos','pageTitle'));
    }

    /* ============== Crear ============== */
    public function create(){
        if (!isset($_SESSION)) @session_start();
        $proveedores = Ocompras::listarProveedores();
        $proyectos   = Ocompras::listarProyectos();

        if (!empty($_SESSION['old_ocompras']) && is_array($_SESSION['old_ocompras'])) {
            $oc    = $_SESSION['old_ocompras']['oc']    ?? [];
            $items = $_SESSION['old_ocompras']['items'] ?? [];
            unset($_SESSION['old_ocompras']);
        } else {
            $oc = [
                'id'=>null,'oc_num'=>'','proyecto_id'=>null,'proveedor_id'=>null,'fecha'=>date('Y-m-d'),
                'moneda'=>'CLP','tipo_cambio'=>'1.000000','estado'=>'borrador',
                'condiciones_pago'=>'','observaciones'=>'','subtotal'=>0,'descuento'=>0,'impuesto'=>0,
            ];
            $items = [];
        }
        $pageTitle = 'Nueva Orden de Compra';
        $this->render('ocompras/form', compact('oc','items','proveedores','proyectos','pageTitle'));
    }
    public function nuevo(){ return $this->create(); }

    public function store(){
        $this->rt('store.enter', ['keys'=>array_keys($_POST ?: [])]);
        try{
            if (!isset($_SESSION)) @session_start();
            if (isset($_SESSION['form_token'], $_POST['form_token'])
                && hash_equals((string)$_SESSION['form_token'], (string)$_POST['form_token'])) {
                unset($_SESSION['form_token']);
            }
            $payload = $this->sanitizeOc($_POST);
            $items   = $this->sanitizeItems($_POST['items'] ?? []);
            $this->validateOc($payload, $items);

            if ($payload['oc_num'] === '' || Ocompras::buscarIdPorOcNum($payload['oc_num'])) {
                $payload['oc_num'] = $this->generateNextOcNum(self::pdo(), (int)($payload['proyecto_id'] ?? 0));
            }
            if (Ocompras::buscarIdPorOcNum($payload['oc_num'])) {
                $payload['oc_num'] = $this->generateNextOcNum(self::pdo(), (int)($payload['proyecto_id'] ?? 0));
            }

            $oc_id = Ocompras::crear($payload, $items);
            $this->flash('success','Orden de compra creada.');
            $this->redirect('/ocompras/ver/'.$oc_id);

        }catch(\PDOException $e){
            $ei = $e->errorInfo ?? [null,null,null];
            $this->rt('store.pdo_error', ['msg'=>$e->getMessage(), 'info'=>$ei, 'post'=>$_POST]);
            if (!isset($_SESSION)) @session_start();
            $_SESSION['old_ocompras'] = ['oc'=>$this->sanitizeOc($_POST), 'items'=>$this->sanitizeItems($_POST['items'] ?? [])];
            $msg = 'Error SQL al guardar la OC.'; if ($this->isDev()) { $msg .= sprintf(' SQLSTATE %s [%s]: %s', (string)$ei[0], (string)$ei[1], (string)$ei[2]); }
            $this->flash('error',$msg);
            $this->redirect('/ocompras/nuevo');
        }catch(\Throwable $e){
            $this->rt('store.error', ['msg'=>$e->getMessage(), 'post'=>$_POST]);
            if (!isset($_SESSION)) @session_start();
            $_SESSION['old_ocompras'] = ['oc'=>$this->sanitizeOc($_POST), 'items'=>$this->sanitizeItems($_POST['items'] ?? [])];
            $this->flash('error', $this->isDev() ? ('Error: '.$e->getMessage()) : 'Ocurrió un error al guardar.');
            $this->redirect('/ocompras/nuevo');
        }
    }
    public function guardar(){ return $this->store(); }

    /* ============== Ver / Editar ============== */
    public function show($id){
        $oc = Ocompras::buscarPorId((int)$id);
        if(!$oc){ http_response_code(404); echo 'No encontrada'; return; }
        $items = Ocompras::listarItems((int)$id);

        // --- NUEVO: cargar proveedor (razón/nombre y RUT) y proyecto (nombre)
        $prov = null; $proy = null;
        try{
            $pdo = self::pdo();

            if (!empty($oc['proveedor_id'])) {
                $stP = $pdo->prepare("SELECT id, nombre, razon, rut, direccion, comuna, ciudad FROM proveedores WHERE id=:id LIMIT 1");
                $stP->execute([':id'=>(int)$oc['proveedor_id']]);
                $prov = $stP->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
            if (!empty($oc['proyecto_id'])) {
                $stJ = $pdo->prepare("SELECT id, nombre FROM proyectos WHERE id=:id LIMIT 1");
                $stJ->execute([':id'=>(int)$oc['proyecto_id']]);
                $proy = $stJ->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
        }catch(\Throwable $e){
            $this->rt('show.fetch.warn', ['msg'=>$e->getMessage()]);
        }

        $pageTitle = 'OC '.$oc['oc_num'];
        $this->render('ocompras/show', compact('oc','items','prov','proy','pageTitle'));
    }
    public function ver($id){ return $this->show($id); }

    public function edit($id){
        $oc = Ocompras::buscarPorId((int)$id);
        if(!$oc){ http_response_code(404); echo 'No encontrada'; return; }
        if($oc['estado']!=='borrador'){
            $this->flash('error','Solo puedes editar OCs en BORRADOR.');
            $this->redirect('/ocompras/ver/'.$id);
            return;
        }
        $items       = Ocompras::listarItems((int)$id);
        $proveedores = Ocompras::listarProveedores();
        $proyectos   = Ocompras::listarProyectos();
        $pageTitle   = 'Editar OC';
        $this->render('ocompras/form', compact('oc','items','proveedores','proyectos','pageTitle'));
    }
    public function editar($id){ return $this->edit($id); }

    public function update($id){
        $this->rt('update.enter', ['id'=>(int)$id]);
        try{
            $oc = Ocompras::buscarPorId((int)$id);
            if(!$oc) throw new \Exception('OC no encontrada');
            if($oc['estado']!=='borrador') throw new \Exception('Solo se puede editar en BORRADOR');

            $payload = $this->sanitizeOc($_POST);
            $items   = $this->sanitizeItems($_POST['items'] ?? []);
            $this->validateOc($payload, $items);

            if (trim($payload['oc_num']) === '') {
                $payload['oc_num'] = $this->generateNextOcNum(self::pdo(), (int)($payload['proyecto_id'] ?? 0));
            }

            Ocompras::actualizar((int)$id, $payload, $items);
            $this->flash('success','OC actualizada.');
            $this->redirect('/ocompras/ver/'.$id);

        }catch(\PDOException $e){
            $ei = $e->errorInfo ?? [null,null,null];
            $this->rt('update.pdo_error', ['id'=>(int)$id, 'msg'=>$e->getMessage(), 'info'=>$ei, 'post'=>$_POST]);
            if (!isset($_SESSION)) @session_start();
            $_SESSION['old_ocompras'] = ['oc'=>$this->sanitizeOc($_POST), 'items'=>$this->sanitizeItems($_POST['items'] ?? [])];
            $msg = 'Error SQL al actualizar la OC.'; if ($this->isDev()) { $msg .= sprintf(' SQLSTATE %s [%s]: %s', (string)$ei[0], (string)$ei[1], (string)$ei[2]); }
            $this->flash('error',$msg);
            $this->redirect('/ocompras/editar/'.$id);
        }catch(\Throwable $e){
            $this->rt('update.error', ['id'=>(int)$id, 'msg'=>$e->getMessage(), 'post'=>$_POST]);
            if (!isset($_SESSION)) @session_start();
            $_SESSION['old_ocompras'] = ['oc'=>$this->sanitizeOc($_POST), 'items'=>$this->sanitizeItems($_POST['items'] ?? [])];
            $this->flash('error', $this->isDev() ? ('Error: '.$e->getMessage()) : 'Ocurrió un error al actualizar.');
            $this->redirect('/ocompras/editar/'.$id);
        }
    }
    public function actualizar($id){ return $this->update($id); }

    /* ============== Eliminar ============== */
    public function destroy($id){
        try{
            $u = $_SESSION['user'] ?? null;
            if (!$u || (($u['perfil'] ?? '') !== 'ADM')) {
                if (class_exists('Session') && method_exists('Session','error')) { Session::error('No autorizado para eliminar OCs.'); }
                $this->redirect('/ocompras'); return;
            }
            Ocompras::eliminar((int)$id);
            if (class_exists('Session') && method_exists('Session','success')) { Session::success('OC eliminada.'); }
            $this->redirect('/ocompras');
        }catch(\Throwable $e){
            $this->rt('destroy.error', ['id'=>(int)$id,'msg'=>$e->getMessage()]);
            if (class_exists('Session') && method_exists('Session','error')) { Session::error('No se pudo eliminar: '.$e->getMessage()); }
            $this->redirect('/ocompras/ver/'.$id);
        }
    }

    public function destroyItem($ocId, $itemId){
        $ocId=(int)$ocId; $itemId=(int)$itemId;
        try{
            $u = $_SESSION['user'] ?? null;
            if (!$u || (($u['perfil'] ?? '') !== 'ADM')) {
                if (class_exists('Session') && method_exists('Session','error')) { Session::error('No autorizado para eliminar ítems.'); }
                $this->redirect('/ocompras/ver/'.$ocId); return;
            }
            Ocompras::eliminarItem($ocId, $itemId);
            if (class_exists('Session') && method_exists('Session','success')) { Session::success('Ítem eliminado.'); }
            $this->redirect('/ocompras/ver/'.$ocId);
        }catch(\Throwable $e){
            $this->rt('destroyItem.error', ['oc'=>$ocId,'it'=>$itemId,'msg'=>$e->getMessage()]);
            if (class_exists('Session') && method_exists('Session','error')) { Session::error('No se pudo eliminar el ítem: '.$e->getMessage()); }
            $this->redirect('/ocompras/ver/'.$ocId);
        }
    }

    /* ============== AJAX ============== */
    public function pcostos($proyecto_id = null){
        header('Content-Type: application/json; charset=utf-8');
        try{
            $pid = $proyecto_id !== null ? (int)$proyecto_id : (int)($_GET['proyecto_id'] ?? 0);
            $this->rt('pcostos.enter', ['pid'=>$pid]);
            if ($pid <= 0) { echo json_encode([]); return; }

            $rows = Ocompras::listarProyectoCostos($pid);
            $this->rt('pcostos.rows', ['pid'=>$pid, 'count'=>is_array($rows)?count($rows):0]);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }catch(\Throwable $e){
            $this->rt('pcostos.error', ['msg'=>$e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error'=>'pcostos: '.$e->getMessage()]);
        }
    }
    public function nextnum(){
        header('Content-Type: application/json; charset=utf-8');
        try{
            $pid = isset($_GET['proyecto_id']) && $_GET['proyecto_id'] !== '' ? (int)$_GET['proyecto_id'] : 0;
            $num = $this->generateNextOcNum(self::pdo(), $pid);
            echo json_encode(['oc_num'=>$num]);
        }catch(\Throwable $e){
            http_response_code(500);
            echo json_encode(['error'=>$e->getMessage()]);
        }
    }

    /* ============== PRINT ============== */
    public function imprimir($id){
        $id = (int)$id;
        $oc = Ocompras::buscarPorId($id);
        if(!$oc){ http_response_code(404); echo 'No encontrada'; return; }
        $items = Ocompras::listarItems($id);

        $prov  = Ocompras::proveedorById((int)$oc['proveedor_id']);
        try{
            $pdo = self::pdo();
            $st = $pdo->prepare("
                SELECT
                    p.nombre AS _nombre, p.razon AS _razon, p.rut AS _rut,
                    p.direccion AS _direccion, p.comuna, p.ciudad, p.rubro AS _rubro,
                    p.con_nom, p.con_email, p.con_fon,
                    p.ep_nom,  p.ep_email,  p.ep_fono
                FROM proveedores p
                WHERE p.id = :id
                LIMIT 1
            ");
            $st->execute([':id'=>(int)$oc['proveedor_id']]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            if ($row) $prov = array_merge($prov ?: [], $row);
        }catch(\Throwable $e){
            $this->rt('print.prov.fetch.warn', ['msg'=>$e->getMessage()]);
        }

        $emisor = null;
        try{
            $pdo = isset($pdo) ? $pdo : self::pdo();
            $stE = $pdo->query("
                SELECT
                    id, nombre, razon, rut, rubro, direccion, comuna, ciudad,
                    ep_nom, ep_email, ep_fono
                FROM proveedores
                WHERE id = 1
                LIMIT 1
            ");
            $emisor = $stE->fetch(\PDO::FETCH_ASSOC) ?: null;
        }catch(\Throwable $e){
            $this->rt('print.emisor.fetch.warn', ['msg'=>$e->getMessage()]);
        }

        $proy  = null;
        if (!empty($oc['proyecto_id'])) {
            $pdo = isset($pdo) ? $pdo : self::pdo();
            $st2 = $pdo->prepare("SELECT nombre FROM proyectos WHERE id=:id");
            $st2->execute([':id'=>(int)$oc['proyecto_id']]);
            $proy = ['id'=>$oc['proyecto_id'], 'nombre'=>$st2->fetchColumn() ?: ''];
        }

        $pageTitle = 'Imprimir OC '.$oc['oc_num'];
        $this->render('ocompras/print', compact('oc','items','prov','proy','emisor','pageTitle'));
    }
    public function print($id){ return $this->imprimir($id); }

    /* ============== Helpers ============== */
    private function sanitizeOc(array $in): array {
        $sub = $this->parseMoneyInt($in['subtotal']  ?? 0);
        $des = $this->parseMoneyInt($in['descuento'] ?? 0);
        $imp = $this->parseMoneyInt($in['impuesto']  ?? 0);
        return [
            'oc_num'           => trim((string)($in['oc_num'] ?? '')),
            'proyecto_id'      => ($in['proyecto_id'] ?? '') !== '' ? (int)$in['proyecto_id'] : null,
            'proveedor_id'     => (int)($in['proveedor_id'] ?? 0),
            'fecha'            => trim((string)($in['fecha'] ?? date('Y-m-d'))),
            'moneda'           => strtoupper(trim((string)($in['moneda'] ?? 'CLP'))),
            'tipo_cambio'      => number_format((float)($in['tipo_cambio'] ?? 1), 6, '.', ''),
            'estado'           => 'borrador',
            'condiciones_pago' => trim((string)($in['condiciones_pago'] ?? '')),
            'observaciones'    => trim((string)($in['observaciones'] ?? '')),
            'subtotal'         => number_format($sub, 2, '.', ''),
            'descuento'        => number_format($des, 2, '.', ''),
            'impuesto'         => number_format($imp, 2, '.', ''),
            'usuario_id'       => isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0,
        ];
    }

    private function sanitizeItems($items): array {
        $out=[]; if(!is_array($items)) return $out;
        foreach($items as $i){
            $codigo = isset($i['codigo']) ? trim((string)$i['codigo']) : '';
            $tienePcosto = !empty($i['imp_pcosto_id']);
            if ($codigo === '' && !$tienePcosto) continue;

            $tipo_in = isset($i['tipo_costo']) ? strtoupper(trim((string)$i['tipo_costo'])) : 'MAT';
            $tipo    = in_array($tipo_in, ['MAT','MO','EQ','SUBC'], true) ? $tipo_in : 'MAT';

            $cant   = $this->parseQty2($i['cantidad'] ?? 0);
            $puInt  = $this->parseMoneyInt($i['precio_unitario'] ?? 0);

            $out[] = [
                'id'              => isset($i['id']) ? (int)$i['id'] : null,
                'linea'           => isset($i['linea']) ? (int)$i['linea'] : null,
                'codigo'          => $codigo,
                'descripcion'     => isset($i['descripcion']) ? trim((string)$i['descripcion']) : '',
                'unidad'          => isset($i['unidad']) ? trim((string)$i['unidad']) : 'UND',
                'tipo_costo'      => $tipo,
                'cantidad'        => number_format($cant, 2, '.', ''),
                'precio_unitario' => number_format($puInt, 2, '.', ''),
                'fecha_requerida' => null,
                'imp_proyecto_id' => (isset($i['imp_proyecto_id']) && $i['imp_proyecto_id']!=='') ? (int)$i['imp_proyecto_id'] : null,
                'imp_pcosto_id'   => (isset($i['imp_pcosto_id'])   && $i['imp_pcosto_id']  !=='') ? (int)$i['imp_pcosto_id']   : null,
            ];
        }
        return $out;
    }

    private function validateOc(array &$cab, array &$items): void {
        $errs = [];
        if (($cab['proveedor_id'] ?? 0) <= 0) $errs[] = 'Debes seleccionar un proveedor.';
        $items = array_values(array_filter($items, function($it){
            $q = (float)($it['cantidad'] ?? 0);
            $hasCodeOrPc = (trim((string)($it['codigo'] ?? '')) !== '') || !empty($it['imp_pcosto_id']);
            return $hasCodeOrPc && ($q > 0);
        }));
        if (!count($items)) $errs[] = 'Agrega al menos un ítem válido (código o ítem de costo) y cantidad > 0.';
        if ($errs) throw new \Exception('Validación: '.implode(' ', $errs));
    }

    private function generateNextOcNum(\PDO $pdo, int $proyectoId = 0): string {
        $year = date('Y'); $pref = "OC-{$year}-";
        $pdo->query("SELECT GET_LOCK('seq_ordenes_compra', 5)");
        try{
            $st = $pdo->prepare("SELECT oc_num FROM ordenes_compra WHERE oc_num LIKE :pfx ORDER BY oc_num DESC LIMIT 1");
            $st->execute([':pfx'=> $pref.'%']);
            $last = (string)($st->fetchColumn() ?: ''); $n=0;
            if ($last !== '') { $tail = substr($last, -5); if (ctype_digit($tail)) $n = (int)$tail; }
            $n++; return $pref . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
        } finally { $pdo->query("SELECT RELEASE_LOCK('seq_ordenes_compra' )"); }
    }

    /* ===== render/redirect ===== */
    protected function render(string $view, array $vars = []): void {
        extract($vars, EXTR_OVERWRITE);
        if (!isset($pageTitle)) $pageTitle = 'Órdenes de Compra';
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
}
