<?php
final class Router
{
    public static function dispatch(PDO $pdo, array $cfg = []): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        $BASE = rtrim((string)($cfg['BASE_URL'] ?? ($GLOBALS['cfg']['BASE_URL'] ?? '')), '/');
        $path = self::resolvePath($cfg);
        $path = self::normalizeSlashes($path);

        // Normalización típica
        if ($path === '/index.php') { $path = '/'; }
        elseif (self::startsWith($path, '/index.php/')) { $path = substr($path, 10); }

        // Segmentar
        $seg = array_values(array_filter(explode('/', $path), fn($s)=>$s!==''));

        // Controlador/acción por defecto
        $default = !empty($cfg['DEFAULT_CONTROLLER']) ? strtolower($cfg['DEFAULT_CONTROLLER']) : 'auth';
        $slug    = strtolower($seg[0] ?? $default);
        $action  = $seg[1] ?? 'index';
        $params  = array_slice($seg, 2);

        // --- NUEVO: rutas públicas permitidas (sin login) ---
        // 1) Página de entrada pública y contenido informativo
        //    - "/"               => SiteController@landing
        //    - "/site"           => SiteController@landing
        //    - "/site/index"     => SiteController@landing
        //    - "/site/landing"   => SiteController@landing
        //    - "/site/acerca"    => SiteController@acerca
        //    - "/site/contacto"  => SiteController@contacto
        $isRoot = ($path === '/' || $path === '');
        if ($isRoot) {
            // Forzamos raíz a la Landing pública
            $slug   = 'site';
            $action = 'landing';
            $params = [];
        }

        // Slugs que pueden verse sin autenticación
        $publicSlugs = ['site', 'auth'];

        // Si llega "/site" sin acción, lo tratamos como landing
        if ($slug === 'site' && ($action === '' || $action === 'index')) {
            $action = 'landing';
        }

        // --- Reglas de login ---
        $isLogged = !empty($_SESSION['user']['id']);
        if (!$isLogged) {
            // Permitir sólo slugs públicos cuando no hay sesión
            if (!in_array($slug, $publicSlugs, true)) {
                header('Location: ' . $BASE . '/auth/login', true, 302); exit;
            }
        }

        // Resolver clase del controlador (con excepción de "presupuestos" ya existente)
        $class    = ($slug === 'presupuestos') ? 'PresupuestosController' : self::slugToClass($slug);
        $ctrlFile = self::findControllerFile($class);
        if (!$ctrlFile) { self::http404("Controlador no encontrado: {$class}"); }
        require_once $ctrlFile;
        if (!class_exists($class)) { self::http404("Clase de controlador no declarada: {$class}"); }

        $instance = self::newController($class, $pdo, $cfg);

        // Alias de acciones
        $actionAliases = [
            'nuevo'      => 'create',
            'crear'      => 'create',
            'guardar'    => 'store',
            'ver'        => 'show',
            'mostrar'    => 'show',
            'editar'     => 'edit',
            'actualizar' => 'update',
            'eliminar'   => 'destroy',
            'borrar'     => 'destroy',
            'anular'     => 'anular',
        ];

        // Alias específicos para SiteController (por si alguien llama /site/index)
        if ($class === 'SiteController' && ($action === 'index' || $action === '')) {
            $action = 'landing';
        }

        if (!method_exists($instance, $action)) {
            if (isset($actionAliases[$action]) && method_exists($instance, $actionAliases[$action])) {
                $action = $actionAliases[$action];
            } elseif (method_exists($instance, 'index')) {
                $action = 'index';
            } else {
                self::http404("Acción no encontrada: {$class}::{$action}()");
            }
        }

        call_user_func_array([$instance, $action], $params);
    }

    private static function resolvePath(array $cfg): string
    {
        if (!empty($_GET['r'])) return '/' . ltrim((string)$_GET['r'], '/');

        $c = isset($_GET['controller']) ? trim((string)$_GET['controller']) : '';
        $a = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
        if ($c !== '' || $a !== '') {
            $c = $c !== '' ? $c : (!empty($cfg['DEFAULT_CONTROLLER']) ? $cfg['DEFAULT_CONTROLLER'] : 'auth');
            $a = $a !== '' ? $a : 'index';
            return '/' . ltrim($c, '/') . '/' . ltrim($a, '/');
        }

        $uriPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = rtrim((string)($cfg['BASE_URL'] ?? ($GLOBALS['cfg']['BASE_URL'] ?? '')), '/');
        if ($base !== '' && $base !== '/' && self::startsWith($uriPath, $base)) {
            $uriPath = substr($uriPath, strlen($base));
        }
        return '/' . ltrim($uriPath, '/');
    }

    private static function slugToClass(string $slug): string
    {
        $uc = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($slug))));
        return $uc . 'Controller';
    }

    private static function findControllerFile(string $class): ?string
    {
        $root = dirname(__DIR__);
        $p = $root . '/app/controllers/' . $class . '.php';
        return is_file($p) ? $p : null;
    }

    private static function newController(string $class, PDO $pdo, array $cfg)
    {
        try {
            $ref = new \ReflectionClass($class);
            $ctor = $ref->getConstructor();
            if ($ctor) {
                $n = $ctor->getNumberOfParameters();
                if ($n >= 2) return new $class($pdo, $cfg);
                if ($n === 1) return new $class($pdo);
            }
        } catch (\Throwable $e) {}
        return new $class($pdo, $cfg);
    }

    private static function http404(string $msg): void
    {
        http_response_code(404);
        echo "404 Not Found";
        if (!empty($_GET['debug'])) { echo "<pre>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</pre>"; }
        exit;
    }

    private static function startsWith(string $h, string $n): bool { return $n !== '' && substr($h, 0, strlen($n)) === $n; }
    private static function normalizeSlashes(string $p): string { return preg_replace('#/+#', '/', str_replace('\\', '/', $p)); }
}
