<?php
/**
 * Controlador: CatalogoController
 * CRUD jerárquico para costos_catalogo (Familia/Grupo/Ítem)
 * ACL: ADM, CAT-CRE, CAT-EDT, CAT-DEL
 * CSRF en POST y logging a LogSys si está disponible.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Acl.php';
require_once __DIR__ . '/../models/CostosCatalogo.php';

class CatalogoController extends Controller
{
    private CostosCatalogo $model;
    private Acl $acl;

    public function __construct(PDO $pdo, array $cfg)
    {
        parent::__construct($pdo, $cfg);
        Session::start();

        // Si no hay sesión, mandar a login conservando "next"
        if (!Session::user()) {
            $base = rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/');      // ej: /costos
            $next = (string)($_SERVER['REQUEST_URI'] ?? '');                 // ej: /costos/catalogo/index
            $loginUrl = ($base === '' ? '' : $base) . '/index.php?r=auth/login&next=' . rawurlencode($next);
            header('Location: ' . $loginUrl);
            exit;
        }

        // Modelo (usa $pdo global internamente)
        $this->model = new CostosCatalogo();
        $this->acl   = new Acl();
    }

    /* ===================== VISTA PRINCIPAL ===================== */
    public function index(): void
    {
        $csrf = $this->ensureCsrf();
        $unidades = defined('CostosCatalogo::UNIDADES') ? \CostosCatalogo::UNIDADES : ['-'];

        // La vista es app/views/catalogo_index.php (sin subcarpeta)
        $this->view('catalogo_index', [
            'csrf'      => $csrf,
            'unidades'  => $unidades,
            'pageTitle' => 'Catálogo Costos',
        ]);
    }

    /* ===================== API JSON (lecturas) ===================== */
    public function familias(): void
    {
        $this->json($this->model->getFamilias());
    }

    public function grupos(): void
    {
        $familia = $_GET['familia'] ?? '';
        $this->json($this->model->getGrupos($familia));
    }

    public function items(): void
    {
        $familia = $_GET['familia'] ?? '';
        $grupo   = $_GET['grupo'] ?? '';
        $this->json($this->model->getItems($familia, $grupo));
    }

    public function get(): void
    {
        $codigo = $_GET['codigo'] ?? '';
        $row = $this->model->getByCodigo($codigo);
        if (!$row) { $this->json(['error' => 'No encontrado'], 404); return; }
        $this->json($row);
    }

    public function nextcode(): void
    {
        $type = $_GET['type'] ?? '';
        if ($type === 'grupo') {
            $familia = $_GET['familia'] ?? '';
            $this->json(['grupo' => $this->model->nextGrupoCode($familia)]);
            return;
        }
        if ($type === 'item') {
            $familia = $_GET['familia'] ?? '';
            $grupo   = $_GET['grupo'] ?? '';
            $this->json(['item' => $this->model->nextItemCode($familia, $grupo)]);
            return;
        }
        $this->json(['error' => 'type inválido'], 400);
    }

    /* ===================== CRUD (modificaciones) ===================== */
    public function create(): void
    {
        $this->requirePerm(['ADM','CAT-CRE']);
        $this->checkCsrf();

        try {
            $codigo      = $_POST['codigo']      ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $unidad      = $_POST['unidad']      ?? '-';

            // Solo ítems usan valor/moneda; el modelo ignora para familia/grupo
            $valor       = $_POST['valor']       ?? null;
            $moneda      = $_POST['moneda']      ?? null;

            $ok = $this->model->create($codigo, $descripcion, $unidad, $valor, $moneda);
            if ($ok) {
                $this->logChange('create', $codigo, null, [
                    'descripcion' => $descripcion,
                    'unidad'      => $unidad,
                    'valor'       => $valor,
                    'moneda'      => $moneda
                ]);
            }
            $this->json(['ok' => true, 'codigo' => $codigo]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function update(): void
    {
        $this->requirePerm(['ADM','CAT-EDT']);
        $this->checkCsrf();

        try {
            $codigo = $_POST['codigo'] ?? '';
            $actual = $this->model->getByCodigo($codigo);
            if (!$actual) { throw new RuntimeException('Registro no encontrado'); }

            $descripcion = $_POST['descripcion'] ?? $actual['descripcion'];
            $unidad      = $_POST['unidad']      ?? $actual['unidad'];
            // Tomar actuales por defecto; el modelo internamente forzará null si no es ítem
            $valor       = $_POST['valor']       ?? ($actual['valor']  ?? null);
            $moneda      = $_POST['moneda']      ?? ($actual['moneda'] ?? null);

            $ok = $this->model->update($codigo, $descripcion, $unidad, $valor, $moneda);
            if ($ok) {
                $before = [
                    'descripcion' => $actual['descripcion'],
                    'unidad'      => $actual['unidad'],
                    'valor'       => $actual['valor']  ?? null,
                    'moneda'      => $actual['moneda'] ?? null
                ];
                $after  = [
                    'descripcion' => $descripcion,
                    'unidad'      => $unidad,
                    'valor'       => $valor,
                    'moneda'      => $moneda
                ];
                $changes = $this->diffAssoc($before, $after);
                $this->logChange('update', $codigo, $actual, $changes);
            }
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function delete(): void
    {
        $this->requirePerm(['ADM','CAT-DEL']);
        $this->checkCsrf();

        try {
            $codigo = $_POST['codigo'] ?? '';
            $actual = $this->model->getByCodigo($codigo);
            if (!$actual) { throw new RuntimeException('Registro no encontrado'); }

            $ok = $this->model->delete($codigo);
            if ($ok) { $this->logChange('delete', $codigo, $actual, null); }
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /* ===================== HELPERS ===================== */
    // Mantener visibilidad compatible con Controller base
    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function ensureCsrf(): string
    {
        $token = Session::get('csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

    private function checkCsrf(): void
    {
        $tok = $_POST['csrf'] ?? '';
        if (!$tok || $tok !== Session::get('csrf_token')) {
            throw new RuntimeException('CSRF token inválido');
        }
    }

    private function requirePerm(array $perms): void
    {
        $perfil = (string) (Session::user()['perfil'] ?? '');
        if (strcasecmp($perfil, 'admin') === 0 || strcasecmp($perfil, 'ADM') === 0) { return; }

        foreach ($perms as $p) {
            if (method_exists($this->acl, 'check')    && $this->acl->check($p))    { return; }
            if (method_exists($this->acl, 'has')      && $this->acl->has($p))      { return; }
            if (method_exists($this->acl, 'contains') && $this->acl->contains($p)) { return; }
            if (method_exists($this->acl, 'can')      && $this->acl->can(Session::all(), $p)) { return; }
        }
        http_response_code(403);
        exit('Permiso denegado');
    }

    private function diffAssoc(array $old, array $new): array
    {
        $out = [];
        foreach ($new as $k=>$v) {
            $ov = $old[$k] ?? null;
            if ($ov !== $v) { $out[$k] = ['old'=>$ov,'new'=>$v]; }
        }
        return $out;
    }

    private function logChange(string $action, string $pk, ?array $old, $changes): void
    {
        $payload = [
            'user_id' => Session::user()['id'] ?? null,
            'module'  => 'costos_catalogo',
            'action'  => $action,
            'pk'      => $pk,
            'changes' => $changes,
            // guarda snapshot básico
            'old'     => $old ? array_intersect_key($old, [
                'codigo'=>1,'descripcion'=>1,'unidad'=>1,'valor'=>1,'moneda'=>1
            ]) : null,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        try {
            require_once __DIR__ . '/../models/LogSys.php';
            if (class_exists('LogSys')) {
                $log = new LogSys();
                if (method_exists($log, 'add'))       { $log->add($payload);       return; }
                if (method_exists($log, 'create'))    { $log->create($payload);    return; }
                if (method_exists($log, 'registrar')) { $log->registrar($payload); return; }
            }
        } catch (Throwable $e) { /* fallback */ }

        $dir = __DIR__ . '/../../storage/private';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $line = date('c') . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($dir . '/logsys_catalogo.log', $line, FILE_APPEND);
    }
}
