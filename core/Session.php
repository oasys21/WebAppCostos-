<?php
// /costos/core/Session.php
declare(strict_types=1);

final class Session
{
    public static function start(array $cfg = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $name     = (string)($cfg['SESSION_NAME'] ?? 'COSTOSSESSID');
        $lifetime = (int)($cfg['SESSION_LIFETIME'] ?? 0);
        $secure   = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        // Path EXACTO según BASE_URL (ej: "/costos/")
        $path = rtrim((string)($cfg['BASE_URL'] ?? '/'), '/');
        $path = ($path === '') ? '/' : ($path . '/');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        @session_start();
    }

    public static function get(string $key, $default = null) { return $_SESSION[$key] ?? $default; }
    public static function set(string $key, $val): void { $_SESSION[$key] = $val; }
    public static function put(string $key, $val): void { $_SESSION[$key] = $val; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    public static function all(): array { return $_SESSION ?? []; }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
            }
            @session_destroy();
        }
    }

    public static function user(): ?array { return self::get('user') ?? null; }
	public static function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!isset($_SESSION['_flash'])) $_SESSION['_flash'] = [];
    $_SESSION['_flash'][] = [
        'type' => strtolower($type),   // success | error | warning | info
        'msg'  => (string)$message,
        'ts'   => time(),
    ];
}

public static function success(string $message): void { self::flash('success', $message); }
public static function error(string $message): void   { self::flash('error',   $message); }
public static function warning(string $message): void { self::flash('warning', $message); }
public static function info(string $message): void    { self::flash('info',    $message); }

/**
 * Obtiene y limpia los mensajes flash pendientes.
 * @return array<int, array{type:string,msg:string,ts:int}>
 */
public static function flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $out = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $out;
}
	
	
	
}
