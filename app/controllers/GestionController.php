<?php
declare(strict_types=1);

class GestionController extends Controller
{
    /* ===== Infra ===== */
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
    private function flash(string $type, string $msg): void {
        if (class_exists('Session') && method_exists('Session', $type)) { @Session::$type($msg); return; }
        if (!isset($_SESSION)) @session_start();
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }
    private function rt(string $tag, $data): void {
        try {
            $root = dirname(__DIR__, 2);
            $dir  = $root . '/runtime';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $line = date('Y-m-d H:i:s') . " [{$tag}] " . json_encode($data, JSON_UNESCAPED_UNICODE);
            error_log($line . PHP_EOL, 3, $dir . '/gestion.log');
        } catch (\Throwable $e) { error_log("[gestion.rt.fail] ".$e->getMessage()); }
    }
    private function logsys(string $action, array $payload): void {
        try {
            if (class_exists('LogSys') && method_exists('LogSys','add')) {
                @LogSys::add('gestion', $action, $payload);
            }
        } catch (\Throwable $e) {
            $this->rt('logsys.fail', ['action'=>$action,'err'=>$e->getMessage()]);
        }
    }

    /* ===== Permisos ===== */
    private function user(): ?array { return class_exists('Session') ? (Session::user() ?? null) : ($_SESSION['user'] ?? null); }
    private function isADM(?array $u): bool { return !!$u && (($u['perfil'] ?? '') === 'ADM' || ($u['perfil'] ?? '') === 'ADMIN'); }
    private function sp_has(?array $u, int $pos): bool {
        if (!$u) return false;
        $sp = preg_replace('/[^01]/','0',(string)($u['subperfil'] ?? ''));
        $sp = str_pad($sp, 30, '0');
        $idx = $pos - 1;
        return isset($sp[$idx]) && $sp[$idx] === '1';
    }
    private function canCreate(?array $u): bool { return $this->isADM($u) || $this->sp_has($u,7); }  // ADQ_CRE
    private function canEdit(?array $u): bool   { return $this->isADM($u) || $this->sp_has($u,8); }  // ADQ_EDT
    private function canDelete(?array $u): bool { return $this->isADM($u) || $this->sp_has($u,9); }  // ADQ_DEL

    /* ===== Util: Fechas dd/mm/aaaa <-> yyyy-mm-dd ===== */
    private function dmy2iso(?string $s): ?string {
        $s = trim((string)$s);
        if ($s === '') return null;
        if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $s, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }
        return $s; // si ya viene yyyy-mm-dd
    }
    private function iso2dmy(?string $s): ?string {
        $s = trim((string)$s);
        if ($s === '' || $s === '0000-00-00') return null;
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $s, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }
        return $s;
    }

    /* ===== Listado ===== */
    public function index() {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }

        $filters = [
            'q'     => $_GET['q']     ?? null,
            'desde' => isset($_GET['desde']) ? $this->dmy2iso($_GET['desde']) : null,
            'hasta' => isset($_GET['hasta']) ? $this->dmy2iso($_GET['hasta']) : null,
        ];
        $estadoP = $_GET['estadoP'] ?? ''; // pendientes/realizadas para Pedidos
        $estadoS = $_GET['estadoS'] ?? ''; // pendientes/realizadas para Solicitudes

        $estadoMap = ['pendiente'=>'pendiente','realizada'=>'realizada','cerrada'=>'cerrada','anulada'=>'anulada'];
        $eP = isset($estadoMap[$estadoP]) ? $estadoMap[$estadoP] : null;
        $eS = isset($estadoMap[$estadoS]) ? $estadoMap[$estadoS] : null;

        $ped_pend = Gestion::buscarPedidos((int)$u['id'], $eP, $filters, 200);
        $sol_pend = Gestion::buscarSolicitudes((int)$u['id'], $eS, $filters, 200);

        $usuarios = Gestion::listarUsuarios();
        $pageTitle = 'Gestión de Tareas';
        $this->render('gestion/index', compact('pageTitle','filters','estadoP','estadoS','ped_pend','sol_pend','usuarios'));
    }

    /* ===== Crear ===== */
    public function create() {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }
        if (!$this->canCreate($u)) { $this->flash('error','No autorizado para crear.'); $this->redirect('/gestion'); return; }

        $g = [
            'id'=>null,
            'numero_gestion'=>'(auto)',
            'usuario_origen'=>$u['id'],
            'usuario_destino'=>'',
            'fecha_solicitud'=>$this->iso2dmy(date('Y-m-d')),
            'fecha_termino'=>null,
            'valor_asignados'=>'0,00',
            'text_asignados'=>'',
            'text_tarea'=>'',
            'text_respuesta'=>'',
            'fecha_propuesta'=>null,
            'text_requeridos'=>'',
            'valor_requeridos'=>'0,00',
            'estado_gestion'=>'pendiente',
            'deriva_gestion'=>null,
        ];
        $usuarios = Gestion::listarUsuarios();
        $pageTitle = 'Nueva Gestión';
        $this->render('gestion/form', compact('g','usuarios','pageTitle'));
    }
    public function nuevo(){ return $this->create(); }

    /* ===== Store ===== */
    public function store() {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }
        if (!$this->canCreate($u)) { $this->flash('error','No autorizado para crear.'); $this->redirect('/gestion'); return; }

        try{
            $d = $this->sanitize($_POST, 'create', (int)$u['id']);
            $id = Gestion::crear($d);
            $this->logsys('create', ['id'=>$id,'by'=>$u['id']]);
            $this->flash('success','Gestión creada.');
            $this->redirect('/gestion/ver/'.$id);
        }catch(\Throwable $e){
            $this->rt('store.error', ['msg'=>$e->getMessage()]);
            $this->flash('error','Error: '.$e->getMessage());
            $this->redirect('/gestion/nuevo');
        }
    }
    public function guardar(){ return $this->store(); }

    /* ===== Show ===== */
    public function show($id) {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }
        $g = Gestion::buscarPorId((int)$id);
        if (!$g) { http_response_code(404); echo 'No encontrada'; return; }

        // Enriquecer nombres
        $pdo = self::pdo();
        $uo = $g['usuario_origen'] ?? null; $ud = $g['usuario_destino'] ?? null;
        $nomO = $nomD = '';
        if ($uo) { $st = $pdo->prepare("SELECT nombre FROM usuarios WHERE id=:id"); $st->execute([':id'=>(int)$uo]); $nomO = (string)($st->fetchColumn() ?: ''); }
        if ($ud) { $st = $pdo->prepare("SELECT nombre FROM usuarios WHERE id=:id"); $st->execute([':id'=>(int)$ud]); $nomD = (string)($st->fetchColumn() ?: ''); }

        // Formatear fechas a dd/mm/aaaa
        foreach (['fecha_solicitud','fecha_termino','fecha_propuesta'] as $f) {
            $g[$f] = $this->iso2dmy($g[$f] ?? null);
        }

        $pageTitle = 'Ver Gestión';
        $this->render('gestion/show', compact('g','nomO','nomD','pageTitle'));
    }
    public function ver($id){ return $this->show($id); }

    /* ===== Edit ===== */
    public function edit($id) {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }
        $g = Gestion::buscarPorId((int)$id);
        if (!$g) { http_response_code(404); echo 'No encontrada'; return; }

        $isOwner = ((int)$g['usuario_origen'] === (int)$u['id']);
        $isDest  = ((int)$g['usuario_destino'] === (int)$u['id']);
        $isAdm   = $this->isADM($u);

        if (!$isOwner && !$isDest && !$isAdm) {
            $this->flash('error','No autorizado.');
            $this->redirect('/gestion'); return;
        }

        // Fechas dd/mm/aaaa
        foreach (['fecha_solicitud','fecha_termino','fecha_propuesta'] as $f) {
            $g[$f] = $this->iso2dmy($g[$f] ?? null);
        }
        $usuarios = Gestion::listarUsuarios();
        $pageTitle = 'Editar Gestión';
        $this->render('gestion/form', compact('g','usuarios','isOwner','isDest','isAdm','pageTitle'));
    }
    public function editar($id){ return $this->edit($id); }

    /* ===== Update ===== */
    public function update($id) {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }
        $id = (int)$id;
        $g  = Gestion::buscarPorId($id);
        if (!$g) { $this->flash('error','Gestión no encontrada.'); $this->redirect('/gestion'); return; }

        $isOwner = ((int)$g['usuario_origen'] === (int)$u['id']);
        $isDest  = ((int)$g['usuario_destino'] === (int)$u['id']);
        $isAdm   = $this->isADM($u);

        try{
            if ($isAdm) {
                $d = $this->sanitize($_POST, 'admin', (int)$u['id'], $g);
                Gestion::actualizarAdmin($id, $d);
            } else if ($isOwner && $this->canEdit($u)) {
                $d = $this->sanitize($_POST, 'owner', (int)$u['id'], $g);
                Gestion::actualizarOwner($id, (int)$u['id'], $d);
            } else if ($isDest && $this->canEdit($u)) {
                $d = $this->sanitize($_POST, 'dest', (int)$u['id'], $g);
                Gestion::actualizarDestino($id, (int)$u['id'], $d);
            } else {
                throw new \Exception('No autorizado para editar.');
            }
            $this->logsys('update', ['id'=>$id,'by'=>$u['id']]);
            $this->flash('success','Gestión actualizada.');
            $this->redirect('/gestion/ver/'.$id);
        }catch(\Throwable $e){
            $this->rt('update.error', ['id'=>$id,'msg'=>$e->getMessage()]);
            $this->flash('error','Error: '.$e->getMessage());
            $this->redirect('/gestion/editar/'.$id);
        }
    }
    public function actualizar($id){ return $this->update($id); }

    /* ===== Delete ===== */
    public function destroy($id) {
        $u = $this->user(); if (!$u) { $this->redirect('/auth/login'); return; }
        $id = (int)$id;
        try{
            $can = $this->canDelete($u);
            Gestion::eliminar($id, (int)$u['id'], $this->isADM($u));
            $this->logsys('delete', ['id'=>$id,'by'=>$u['id']]);
            $this->flash('success','Gestión eliminada.');
            $this->redirect('/gestion');
        }catch(\Throwable $e){
            $this->rt('destroy.error', ['id'=>$id,'msg'=>$e->getMessage()]);
            $this->flash('error','No se pudo eliminar: '.$e->getMessage());
            $this->redirect('/gestion/ver/'.$id);
        }
    }

    /* ===== Sanitización ===== */
    private function parseMoney2($v): float {
        $s = (string)$v;
        if ($s==='') return 0.0;
        $s = str_replace(['.', ' '], '', $s);     // miles
        $s = str_replace(',', '.', $s);           // decimal
        $s = preg_replace('/[^0-9.\-]/','',$s);
        return (float)$s;
    }

    private function sanitize(array $in, string $mode, int $userId, ?array $current = null): array {
        // Campos comunes
        $base = [
            'usuario_origen'   => $mode==='create' || $this->isADM($this->user()) ? (int)($in['usuario_origen'] ?? $userId) : ($current['usuario_origen'] ?? $userId),
            'usuario_destino'  => (int)($in['usuario_destino'] ?? 0),
            'fecha_solicitud'  => $this->dmy2iso($in['fecha_solicitud'] ?? date('Y-m-d')),
            'fecha_termino'    => $this->dmy2iso($in['fecha_termino'] ?? null),
            'valor_asignados'  => number_format($this->parseMoney2($in['valor_asignados'] ?? 0), 2, '.', ''),
            'text_asignados'   => (string)($in['text_asignados'] ?? ''),
            'text_tarea'       => (string)($in['text_tarea'] ?? ''),
            'text_respuesta'   => (string)($in['text_respuesta'] ?? ''),
            'fecha_propuesta'  => $this->dmy2iso($in['fecha_propuesta'] ?? null),
            'text_requeridos'  => (string)($in['text_requeridos'] ?? ''),
            'valor_requeridos' => number_format($this->parseMoney2($in['valor_requeridos'] ?? 0), 2, '.', ''),
            'estado_gestion'   => trim((string)($in['estado_gestion'] ?? 'pendiente')),
            'deriva_gestion'   => ($in['deriva_gestion'] ?? '') !== '' ? (int)$in['deriva_gestion'] : null,
        ];

        // Restringir según modo:
        if ($mode === 'owner') {
            // Solo owner puede tocar estos:
            return [
                'usuario_origen'   => (int)($current['usuario_origen'] ?? $userId),
                'usuario_destino'  => (int)($current['usuario_destino'] ?? 0),
                'fecha_solicitud'  => $current['fecha_solicitud'] ?? date('Y-m-d'),
                'fecha_termino'    => $base['fecha_termino'],
                'valor_asignados'  => $base['valor_asignados'],
                'text_asignados'   => $base['text_asignados'],
                'text_tarea'       => $base['text_tarea'],
                'text_respuesta'   => $current['text_respuesta'] ?? '',
                'fecha_propuesta'  => $current['fecha_propuesta'] ?? null,
                'text_requeridos'  => $current['text_requeridos'] ?? '',
                'valor_requeridos' => $current['valor_requeridos'] ?? '0.00',
                'estado_gestion'   => $base['estado_gestion'],
                'deriva_gestion'   => $base['deriva_gestion'],
            ];
        } elseif ($mode === 'dest') {
            // Solo destino puede tocar su respuesta:
            return [
                'usuario_origen'   => (int)($current['usuario_origen'] ?? $userId),
                'usuario_destino'  => (int)($current['usuario_destino'] ?? 0),
                'fecha_solicitud'  => $current['fecha_solicitud'] ?? date('Y-m-d'),
                'fecha_termino'    => $current['fecha_termino'] ?? null,
                'valor_asignados'  => $current['valor_asignados'] ?? '0.00',
                'text_asignados'   => $current['text_asignados'] ?? '',
                'text_tarea'       => $current['text_tarea'] ?? '',
                'text_respuesta'   => $base['text_respuesta'],
                'fecha_propuesta'  => $base['fecha_propuesta'],
                'text_requeridos'  => $base['text_requeridos'],
                'valor_requeridos' => $base['valor_requeridos'],
                'estado_gestion'   => $base['estado_gestion'],
                'deriva_gestion'   => $current['deriva_gestion'] ?? null,
            ];
        } elseif ($mode === 'admin') {
            // Admin puede todo
            return $base;
        } else { // create
            if ($base['usuario_destino'] <= 0) throw new \Exception('Debes seleccionar usuario destino.');
            if ($base['fecha_solicitud'] === null) $base['fecha_solicitud'] = date('Y-m-d');
            return $base;
        }
    }

    /* ===== Render / Redirect ===== */
    protected function render(string $view, array $vars = []): void {
        extract($vars, EXTR_OVERWRITE);
        if (!isset($pageTitle)) $pageTitle = 'Gestión';
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
