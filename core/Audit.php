<?php
declare(strict_types=1);

/**
 * /costos/core/audit.php
 * Auditoría centralizada a tabla `logsys`.
 *
 * Convención:
 * - create: registra solo el ID (no datos del registro).
 * - update: registra únicamente los campos que cambiaron (old/new).
 * - delete: registra TODOS los campos del registro eliminado.
 * - event : eventos genéricos (login_ok, login_fail, logout_ok, etc.).
 */
final class Audit
{
    /** Campos que usualmente NO se auditan en diff */
    private const DEFAULT_IGNORE = [
        'password','pass','clave','hash','reset_token','token',
        'creado_en','actualizado_en','updated_at','created_at'
    ];

    /** Obtiene el actor desde sesión y contexto de red */
    private static function getContext(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $user = $_SESSION['user'] ?? [];
        $ip   = $_SERVER['HTTP_CLIENT_IP']
             ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return [
            'user_id'   => (int)($user['id'] ?? 0),
            'rut'       => (string)($user['rut'] ?? ''),
            'nombre'    => (string)($user['nombre'] ?? ''),
            'ip'        => (string)$ip,
            'userAgent' => (string)$ua,
        ];
    }

    /** Inserta fila en logsys (manejo silencioso de errores) */
    private static function insertRow(PDO $pdo, array $row): void
    {
        try {
            $sql = "INSERT INTO logsys
                      (user_id, rut, nombre, ip, user_agent, accion, entidad, entidad_id, detalle_json)
                    VALUES
                      (:user_id,:rut,:nombre,:ip,:ua,:accion,:entidad,:entidad_id,:detalle)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'    => $row['user_id'],
                ':rut'        => $row['rut'],
                ':nombre'     => $row['nombre'],
                ':ip'         => $row['ip'],
                ':ua'         => $row['user_agent'],
                ':accion'     => $row['accion'],
                ':entidad'    => $row['entidad'],
                ':entidad_id' => $row['entidad_id'],
                ':detalle'    => $row['detalle_json'],
            ]);
        } catch (\Throwable $e) {
            // último recurso: a error_log para no reventar el flujo
            @error_log('[AUDIT][FAIL] '.$e->getMessage());
        }
    }

    /** Normaliza un valor escalar para comparación */
    private static function norm($v)
    {
        if (is_null($v) || is_bool($v) || is_int($v) || is_float($v) || is_string($v)) return $v;
        // arrays/objetos → json compacta para comparación estable
        return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    /** Diff asociativo: solo claves modificadas (old/new) */
    private static function diffAssoc(array $old, array $new, array $ignore = []): array
    {
        $skip = array_fill_keys(array_map('strval', array_merge(self::DEFAULT_IGNORE, $ignore)), true);
        $out  = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($keys as $k) {
            if (isset($skip[$k])) continue;
            $o = self::norm($old[$k] ?? null);
            $n = self::norm($new[$k] ?? null);
            if ($o !== $n) {
                $out[] = ['field'=>$k, 'old'=>$old[$k] ?? null, 'new'=>$new[$k] ?? null];
            }
        }
        return $out;
    }

    /** Evento genérico (login_ok, login_fail, logout_ok, etc.) */
    public static function logEvent(PDO $pdo, string $accion, string $entidad, ?int $entidadId, ?array $extra = null): void
    {
        $ctx = self::getContext();
        $row = [
            'user_id'     => $ctx['user_id'],
            'rut'         => $ctx['rut'],
            'nombre'      => $ctx['nombre'],
            'ip'          => $ctx['ip'],
            'user_agent'  => $ctx['userAgent'],
            'accion'      => $accion,
            'entidad'     => $entidad,
            'entidad_id'  => $entidadId,
            'detalle_json'=> json_encode(['event'=>$accion,'extra'=>$extra ?? (object)[]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        self::insertRow($pdo, $row);
    }

    /** Alta: SOLO ID nuevo (sin datos del registro) */
    public static function logInsert(PDO $pdo, string $entidad, int $id, ?array $extra = null): void
    {
        $ctx = self::getContext();
        $row = [
            'user_id'     => $ctx['user_id'],
            'rut'         => $ctx['rut'],
            'nombre'      => $ctx['nombre'],
            'ip'          => $ctx['ip'],
            'user_agent'  => $ctx['userAgent'],
            'accion'      => strtoupper($entidad).'_CREATE',
            'entidad'     => $entidad,
            'entidad_id'  => $id,
            'detalle_json'=> json_encode([
                'new_id' => $id,
                'extra'  => $extra ?? (object)[],
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        self::insertRow($pdo, $row);
    }

    /** Modificación: solo campos cambiados (old/new) */
    public static function logUpdate(PDO $pdo, string $entidad, int $id, array $before, array $after, array $opts = []): void
    {
        $ignore  = (array)($opts['ignore'] ?? []);
        $changes = self::diffAssoc($before, $after, $ignore);
        if (!$changes) return; // evita ruido si no cambió nada

        $ctx = self::getContext();
        $row = [
            'user_id'     => $ctx['user_id'],
            'rut'         => $ctx['rut'],
            'nombre'      => $ctx['nombre'],
            'ip'          => $ctx['ip'],
            'user_agent'  => $ctx['userAgent'],
            'accion'      => strtoupper($entidad).'_UPDATE',
            'entidad'     => $entidad,
            'entidad_id'  => $id,
            'detalle_json'=> json_encode([
                'diff'  => $changes,
                'extra' => (object)($opts['extra'] ?? []),
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        self::insertRow($pdo, $row);
    }

    /** Baja: TODO el registro eliminado */
    public static function logDelete(PDO $pdo, string $entidad, int $id, array $before, ?array $extra = null): void
    {
        $ctx = self::getContext();
        $row = [
            'user_id'     => $ctx['user_id'],
            'rut'         => $ctx['rut'],
            'nombre'      => $ctx['nombre'],
            'ip'          => $ctx['ip'],
            'user_agent'  => $ctx['userAgent'],
            'accion'      => strtoupper($entidad).'_DELETE',
            'entidad'     => $entidad,
            'entidad_id'  => $id,
            'detalle_json'=> json_encode([
                'deleted' => $before,
                'extra'   => $extra ?? (object)[],
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        self::insertRow($pdo, $row);
    }
}
