<?php
declare(strict_types=1);

class ProyectoEtapasController extends Controller
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
            $line = date('Y-m-d H:i:s') . " [petapas:{$tag}] " . json_encode($data, JSON_UNESCAPED_UNICODE);
            error_log($line . PHP_EOL, 3, $dir . '/proyecto_etapas.log');
        } catch (\Throwable $e) {
            error_log("[petapas.rt.fail] ".$e->getMessage());
        }
    }

    private function flash(string $type, string $msg): void {
        if (class_exists('Session') && method_exists('Session', $type)) { @Session::$type($msg); return; }
        if (!isset($_SESSION)) @session_start();
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }

    private function isAdmin(): bool {
        if (!isset($_SESSION)) @session_start();

        try {
            if (class_exists('Acl')) {
                if (method_exists('Acl','isAdmin')) {
                    return (bool)Acl::isAdmin();
                }
                if (method_exists('Acl','check')) {
                    if (Acl::check('admin')) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // silencioso: resuelve por sesión
        }

        $u = $_SESSION['user'] ?? null;
        if (is_array($u)) {
            if (!empty($u['is_admin'])) return true;
            if (!empty($u['rol']) && strtoupper((string)$u['rol']) === 'ADMIN') return true;
            if (!empty($u['perfil']) && strtoupper((string)$u['perfil']) === 'ADMIN') return true;
        }
        return false;
    }

    /* ============== Listado ============== */
    public function index(){
        if (!isset($_SESSION)) @session_start();
        $currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $isAdmin       = $this->isAdmin();

        // Alcance
        $scope = 'mine';
        if ($isAdmin && isset($_GET['scope']) && $_GET['scope'] === 'all') {
            $scope = 'all';
        }

        // Si vino desde el botón "Dashboard" del formulario
        $accion = $_GET['accion'] ?? 'buscar';
        if ($accion === 'dashboard') {
            $pid = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
            if ($pid <= 0) {
                // No seleccionó proyecto: pedirlo
                $this->flash('error','Debe seleccionar un proyecto para ver el Dashboard.');
                $this->redirect('/proyecto-etapas');
            }

            // Redirigir al dashboard del proyecto seleccionado
            $params = ['proyecto_id' => $pid];
            if ($scope === 'all') {
                $params['scope'] = 'all';
            }
            $qs   = http_build_query($params);
            $base = $this->baseUrl();
            header('Location: ' . $base . '/proyecto-etapas/dashboard?' . $qs, true, 302);
            exit;
        }

        $filters = [
            'proyecto_id'     => $_GET['proyecto_id'] ?? null,
            'item_costo'      => $_GET['item_costo']  ?? null,
            'estado'          => $_GET['estado']      ?? null,
            'titulo'          => $_GET['titulo']      ?? null,
            // dejamos usuario_id por compatibilidad, aunque ahora uses RUT
            'usuario_id'      => isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int)$_GET['usuario_id'] : null,
            'usuario_rut'     => isset($_GET['usuario_rut']) && $_GET['usuario_rut'] !== '' ? trim((string)$_GET['usuario_rut']) : null,
            'current_user_id' => $currentUserId,
            'scope'           => $scope,
        ];

        $etapas    = ProyectoEtapas::buscar($filters, 200);
        $proyectos = ProyectoEtapas::listarProyectos($currentUserId, $isAdmin);
        $pageTitle = 'Etapas';

        $this->render('proyecto_etapas/index', compact('etapas','proyectos','pageTitle','isAdmin','scope'));
    }

    /**
     * Dashboard: métricas de las etapas de UN proyecto.
     * - Requiere proyecto_id > 0.
     * - Si no viene, solo muestra mensaje y formulario de selección.
     */
    public function dashboard(){
        if (!isset($_SESSION)) @session_start();
        $currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $isAdmin       = $this->isAdmin();

        $scope = 'mine';
        if ($isAdmin && isset($_GET['scope']) && $_GET['scope'] === 'all') {
            $scope = 'all';
        }

        $proyectoId = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;

        $totalEtapas = 0;
        $sumaValor   = 0.0;
        $sumaAvance  = 0.0;
        $porEstado   = [
            'borrador'    => 0,
            'planificado' => 0,
            'en_proceso'  => 0,
            'completado'  => 0,
            'anulado'     => 0,
        ];
        $promAvance  = 0.0;
        $mensaje     = '';

        if ($proyectoId > 0) {
            $filters = [
                'proyecto_id'     => $proyectoId,
                'item_costo'      => $_GET['item_costo']  ?? null,
                'estado'          => $_GET['estado']      ?? null,
                'titulo'          => $_GET['titulo']      ?? null,
                'usuario_id'      => isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int)$_GET['usuario_id'] : null,
                'usuario_rut'     => isset($_GET['usuario_rut']) && $_GET['usuario_rut'] !== '' ? trim((string)$_GET['usuario_rut']) : null,
                'current_user_id' => $currentUserId,
                'scope'           => $scope,
            ];

            $etapas = ProyectoEtapas::buscar($filters, 1000);

            $totalEtapas = count($etapas);
            foreach ($etapas as $e) {
                $sumaValor  += (float)($e['valor_total'] ?? 0);
                $sumaAvance += (float)($e['avance_pct'] ?? 0);
                $est = (string)($e['estado'] ?? '');
                if ($est !== '') {
                    if (!array_key_exists($est, $porEstado)) {
                        $porEstado[$est] = 0;
                    }
                    $porEstado[$est]++;
                }
            }
            $promAvance = $totalEtapas > 0 ? round($sumaAvance / $totalEtapas, 2) : 0.0;
        } else {
            // Sin proyecto seleccionado: dashboard "vacío" pero con aviso
            $etapas  = [];
            $mensaje = 'Debe seleccionar un proyecto para ver el Dashboard de etapas.';
        }

        $pageTitle = 'Dashboard de Etapas';
        $proyectos = ProyectoEtapas::listarProyectos($currentUserId, $isAdmin);

        $this->render('proyecto_etapas/dashboard', compact(
            'etapas',
            'pageTitle',
            'totalEtapas',
            'sumaValor',
            'promAvance',
            'porEstado',
            'isAdmin',
            'scope',
            'proyectos',
            'mensaje'
        ));
    }

    /* ============== Crear ============== */
    public function create(){
        if (!isset($_SESSION)) @session_start();
        $_SESSION['form_token'] = bin2hex(random_bytes(16));

        $currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $isAdmin       = $this->isAdmin();

        $proyectos = ProyectoEtapas::listarProyectos($currentUserId, $isAdmin);
        $etapa = [
            'id' => null,
            'proyecto_id' => null,
            'item_costo'  => '',
            'titulo'      => '',
            'estado'      => 'borrador',
            'fecha_inicio_prog' => '',
            'fecha_fin_prog'    => '',
            'fecha_inicio_real' => '',
            'fecha_fin_real'    => '',
        ];
        $items = [];
        $form_token = $_SESSION['form_token'];
        $pageTitle = 'Nuevo plan';
        $this->render('proyecto_etapas/form', compact('etapa','items','proyectos','form_token','pageTitle'));
    }
    public function nuevo(){ return $this->create(); }

    public function store(){
        $this->rt('store.enter', ['keys'=>array_keys($_POST ?: [])]);
        try{
            if (!isset($_SESSION)) @session_start();
            if (!empty($_SESSION['form_token']) && isset($_POST['form_token']) && hash_equals($_SESSION['form_token'], $_POST['form_token'])) {
                unset($_SESSION['form_token']);
            }

            $payload = $this->sanitizeCab($_POST);
            $items   = $this->sanitizeItems($_POST['items'] ?? []);

            if ($payload['proyecto_id'] <= 0 || $payload['item_costo'] === '') {
                throw new \Exception('Debes seleccionar un proyecto e ítem de costo.');
            }

            $id = ProyectoEtapas::crear($payload, $items);
            $this->flash('success','Plan de etapas creado.');
            $this->redirect('/proyecto-etapas/ver/'.$id);

        }catch(\PDOException $e){
            $this->rt('store.pdo_error', ['msg'=>$e->getMessage(),'info'=>$e->errorInfo ?? null,'post'=>$_POST]);
            $this->flash('error','Error SQL: '.$e->getMessage());
            $this->redirect('/proyecto-etapas/nuevo');
        }catch(\Throwable $e){
            $this->rt('store.error', ['msg'=>$e->getMessage(),'post'=>$_POST]);
            $this->flash('error','Error: '.$e->getMessage());
            $this->redirect('/proyecto-etapas/nuevo');
        }
    }
    public function guardar(){ return $this->store(); }

    /* ============== Ver / Editar ============== */
    public function show($id){
        $etapa = ProyectoEtapas::buscarPorId((int)$id);
        if(!$etapa){ http_response_code(404); echo 'No encontrada'; return; }
        $items = ProyectoEtapas::listarItems((int)$id);
        $proyecto_nombre = $etapa['proyecto_nombre'] ?? null;
        $pageTitle = 'Etapas #'.$id;
        $this->render('proyecto_etapas/show', compact('etapa','items','proyecto_nombre','pageTitle'));
    }
    public function ver($id){ return $this->show($id); }

    public function edit($id){
        if (!isset($_SESSION)) @session_start();
        $_SESSION['form_token'] = bin2hex(random_bytes(16));

        $etapa = ProyectoEtapas::buscarPorId((int)$id);
        if(!$etapa){ http_response_code(404); echo 'No encontrada'; return; }
        $items     = ProyectoEtapas::listarItems((int)$id);

        $currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $isAdmin       = $this->isAdmin();
        $proyectos     = ProyectoEtapas::listarProyectos($currentUserId, $isAdmin);

        $form_token = $_SESSION['form_token'];
        $pageTitle  = 'Editar plan';
        $this->render('proyecto_etapas/form', compact('etapa','items','proyectos','form_token','pageTitle'));
    }
    public function editar($id){ return $this->edit($id); }

    public function update($id){
        $this->rt('update.enter', ['id'=>(int)$id]);
        try{
            $etapa = ProyectoEtapas::buscarPorId((int)$id);
            if(!$etapa) throw new \Exception('Plan no encontrado');

            $payload = $this->sanitizeCab($_POST);
            $items   = $this->sanitizeItems($_POST['items'] ?? []);

            ProyectoEtapas::actualizar((int)$id, $payload, $items);
            $this->flash('success','Plan actualizado.');
            $this->redirect('/proyecto-etapas/ver/'.$id);

        }catch(\PDOException $e){
            $this->rt('update.pdo_error', ['msg'=>$e->getMessage(),'info'=>$e->errorInfo ?? null]);
            $this->flash('error','Error SQL: '.$e->getMessage());
            $this->redirect('/proyecto-etapas/editar/'.$id);
        }catch(\Throwable $e){
            $this->rt('update.error', ['msg'=>$e->getMessage()]);
            $this->flash('error','Error: '.$e->getMessage());
            $this->redirect('/proyecto-etapas/editar/'.$id);
        }
    }
    public function actualizar($id){ return $this->update($id); }

    public function destroy($id){
        try{
            ProyectoEtapas::eliminar((int)$id);
            $this->flash('success','Plan eliminado.');
            $this->redirect('/proyecto-etapas');
        }catch(\Throwable $e){
            $this->rt('destroy.error', ['id'=>(int)$id,'msg'=>$e->getMessage()]);
            $this->flash('error','No se pudo eliminar: '.$e->getMessage());
            $this->redirect('/proyecto-etapas/ver/'.$id);
        }
    }
    public function eliminar($id){ return $this->destroy($id); }

    /* ============== Helpers ============== */
    private function sanitizeCab(array $in): array {
        return [
            'proyecto_id'       => (int)($in['proyecto_id'] ?? 0),
            'item_costo'        => substr(trim((string)($in['item_costo'] ?? '')), 0, 10),
            'titulo'            => substr(trim((string)($in['titulo'] ?? '')), 0, 120),
            'estado'            => in_array(($in['estado'] ?? 'borrador'), ['borrador','planificado','en_proceso','completado','anulado']) ? $in['estado'] : 'borrador',
            'fecha_inicio_prog' => ($in['fecha_inicio_prog'] ?? '') !== '' ? $in['fecha_inicio_prog'] : null,
            'fecha_fin_prog'    => ($in['fecha_fin_prog'] ?? '') !== '' ? $in['fecha_fin_prog'] : null,
            'fecha_inicio_real' => ($in['fecha_inicio_real'] ?? '') !== '' ? $in['fecha_inicio_real'] : null,
            'fecha_fin_real'    => ($in['fecha_fin_real'] ?? '') !== '' ? $in['fecha_fin_real'] : null,
            'usuario_id'        => isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null,
        ];
    }

    private function sanitizeItems($items): array {
        $out = [];
        if (!is_array($items)) return $out;
        foreach($items as $i){
            $linea = isset($i['linea']) ? (int)$i['linea'] : null;
            $desc  = isset($i['descripcion']) ? trim((string)$i['descripcion']) : '';
            $um    = isset($i['unidad_med']) ? (string)$i['unidad_med'] : 'UN';
            $qty   = (float)($i['cantidad'] ?? 0);
            $val   = (float)($i['valor'] ?? 0);
            $porc  = (int)($i['porcentaje'] ?? 0);
            $estado= in_array(($i['estado_paso'] ?? 'pendiente'), ['pendiente','en_proceso','terminado','anulado']) ? $i['estado_paso'] : 'pendiente';
            $av    = (float)($i['avance_pct'] ?? 0);

            $fi_p  = ($i['fecha_inicio_prog'] ?? '') !== '' ? $i['fecha_inicio_prog'] : null;
            $ff_p  = ($i['fecha_fin_prog'] ?? '') !== '' ? $i['fecha_fin_prog'] : null;
            $fi_r  = ($i['fecha_inicio_real'] ?? '') !== '' ? $i['fecha_inicio_real'] : null;
            $ff_r  = ($i['fecha_fin_real'] ?? '') !== '' ? $i['fecha_fin_real'] : null;

            $out[] = [
                'id'                => isset($i['id']) && $i['id']!=='' ? (int)$i['id'] : null,
                'linea'             => $linea ?: null,
                'descripcion'       => $desc,
                'unidad_med'        => in_array($um, ['ML','M2','M3','UN','KG','TM','OT']) ? $um : 'UN',
                'cantidad'          => number_format($qty, 2, '.', ''),
                'valor'             => number_format($val, 2, '.', ''),
                'porcentaje'        => max(0, min(100, $porc)),
                'estado_paso'       => $estado,
                'avance_pct'        => number_format($av, 2, '.', ''),
                'fecha_inicio_prog' => $fi_p,
                'fecha_fin_prog'    => $ff_p,
                'fecha_inicio_real' => $fi_r,
                'fecha_fin_real'    => $ff_r,
            ];
        }
        // ordenar por linea asc si existe
        usort($out, function($a,$b){
            return (int)($a['linea'] ?? 0) <=> (int)($b['linea'] ?? 0);
        });
        return $out;
    }

    /* ===== Fallbacks ===== */
    protected function render(string $view, array $vars = []): void {
        extract($vars, EXTR_OVERWRITE);
        if (!isset($pageTitle)) $pageTitle = 'Etapas';
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
