<?php
// /costos/index.php
declare(strict_types=1);

// 1) Cargar configuración del entorno
$cfg = require __DIR__ . '/config/env.php';

// 2) Resolver BASE_URL si está en 'auto' (ej. /costos en WAMP; '/' en raíz)
if (empty($cfg['BASE_URL']) || $cfg['BASE_URL'] === 'auto') {
    // dirname('/costos/index.php') => '/costos'
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base === '' || $base === '.') { $base = '/'; }
    $cfg['BASE_URL'] = $base;
}

// 2.1) Redirigir sólo si pidieron /index.php SIN ?r=...  (¡no romper legacy!)
$reqPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptRel = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$hasR      = isset($_GET['r']) && $_GET['r'] !== '';

if (( $reqPath === $scriptRel || preg_match('#/index\.php$#i', $reqPath) ) && !$hasR) {
    $to = rtrim($cfg['BASE_URL'], '/') . '/';
    header('Location: ' . $to, true, 302);
    exit;
}

// 3) Core
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Router.php';

// 3.1) Autoloader básico (controllers / models / core / helpers)
spl_autoload_register(function(string $class): void {
    // Sin namespaces: mapea por nombre de clase a rutas conocidas
    $paths = [
        __DIR__ . '/app/models/'      . $class . '.php',
        __DIR__ . '/app/controllers/' . $class . '.php',
        __DIR__ . '/app/helpers/'     . $class . '.php',
    ];
    foreach ($paths as $file) {
        if (is_file($file)) { require_once $file; return; }
    }
});

// 4) Sesión
Session::start($cfg);

// 5) PDO (tu database.php original prepara $pdo con $cfg)
require __DIR__ . '/config/database.php'; // deja $pdo y $cfg listos

// 5.1) (Opcional pero práctico) Exponer conexión/config a modelos sin DI explícita
$GLOBALS['pdo'] = $pdo;
$GLOBALS['cfg'] = $cfg;

// 6) Despachar rutas (?r=... o rutas limpias)
Router::dispatch($pdo, $cfg);
?>
