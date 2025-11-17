<?php
// /costos/app/models/LogSys.php
declare(strict_types=1);

final class LogSys
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * add(...) soporta estas variantes:
     *  (9 args) userId, rut, nombre, ip, ua, accion, entidad, entidadId, detalle
     *  (7 args) userId, rut, nombre, accion, entidad, entidadId, detalle   (ip/ua se infieren)
     *  (8 args) userId, rut, nombre, ip, accion, entidad, entidadId, detalle (ua se infiere)
     *
     * - entidadId puede ser null
     * - detalle es texto libre (texto plano)
     */
    public function add(...$args): void
    {
        $userId = null; $rut = ''; $nombre = '';
        $ip = ''; $ua = '';
        $accion = ''; $entidad = '';
        $entidadId = null; $detalle = null;

        $n = count($args);

        if ($n === 9) {
            // userId, rut, nombre, ip, ua, accion, entidad, entidadId, detalle
            [$userId, $rut, $nombre, $ip, $ua, $accion, $entidad, $entidadId, $detalle] = $args;
        } elseif ($n === 7) {
            // userId, rut, nombre, accion, entidad, entidadId, detalle
            [$userId, $rut, $nombre, $accion, $entidad, $entidadId, $detalle] = $args;
            $ip = $this->clientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif ($n === 8) {
            // userId, rut, nombre, ip, accion, entidad, entidadId, detalle  (sin UA explícito)
            [$userId, $rut, $nombre, $ip, $accion, $entidad, $entidadId, $detalle] = $args;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } else {
            throw new InvalidArgumentException('LogSys::add() argumentos inválidos: se esperan 7, 8 o 9 parámetros.');
        }

        // Normalizaciones mínimas
        $userId    = isset($userId) ? (int)$userId : null;
        $rut       = (string)$rut;
        $nombre    = (string)$nombre;
        $ip        = (string)$ip;
        $ua        = (string)$ua;
        $accion    = (string)$accion;
        $entidad   = (string)$entidad;
        $entidadId = isset($entidadId) ? (int)$entidadId : null;
        $detalle   = $detalle === null ? null : (string)$detalle;

        $sql = "INSERT INTO logsys
                  (user_id, rut, nombre, ip, user_agent, accion, entidad, entidad_id, detalle_json)
                VALUES
                  (:uid, :rut, :nom, :ip, :ua, :acc, :ent, :eid, :det)";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':uid', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':rut', $rut);
        $st->bindValue(':nom', $nombre);
        $st->bindValue(':ip', $ip);
        $st->bindValue(':ua', $ua);
        $st->bindValue(':acc', $accion);
        $st->bindValue(':ent', $entidad);
        $entidadId === null
            ? $st->bindValue(':eid', null, PDO::PARAM_NULL)
            : $st->bindValue(':eid', $entidadId, PDO::PARAM_INT);
        $detalle === null
            ? $st->bindValue(':det', null, PDO::PARAM_NULL)
            : $st->bindValue(':det', $detalle);
        $st->execute();
    }

    /**
     * Diff en formato:
     *   "Campo" {antes} => {despues}; "Otro" {…} => {…}
     * Ignora claves en $ignoreKeys.
     */
    public static function diffArray(array $before, array $after, array $ignoreKeys = []): string
    {
        $ign = array_flip($ignoreKeys);
        $out = [];
        foreach ($after as $k => $v) {
            if (isset($ign[$k])) continue;
            $old = $before[$k] ?? null;
            if ($v != $old) {
                $out[] = self::lineFmt($k, $old, $v);
            }
        }
        return implode('; ', $out);
    }

    /**
     * Alias flexible:
     *  buildDiff($before, $after)
     *  buildDiff($before, $after, $ignoreKeys)
     *  buildDiff($before, $after, $labels, $ignoreKeys)
     * Donde $labels = ['campo'=>'Etiqueta legible'] (opcional)
     */
    public static function buildDiff(...$args): string
    {
        $before = $args[0] ?? [];
        $after  = $args[1] ?? [];
        $labels = [];
        $ignore = [];

        if (count($args) >= 3) {
            if (self::isAssocStringArray($args[2])) {
                $labels = $args[2];
                if (isset($args[3]) && is_array($args[3])) $ignore = $args[3];
            } elseif (is_array($args[2])) {
                $ignore = $args[2];
            }
        }

        $ign = array_flip($ignore);
        $out = [];
        foreach ($after as $k => $v) {
            if (isset($ign[$k])) continue;
            $old = $before[$k] ?? null;
            if ($v != $old) {
                $label = $labels[$k] ?? $k;
                $out[] = self::lineFmt($label, $old, $v);
            }
        }
        return implode('; ', $out);
    }

    /**
     * Formato de una línea suelta en el mismo estilo:
     *   "Label" {before} => {after}
     */
    public static function formatChange(string $label, mixed $before, mixed $after): string
    {
        return self::lineFmt($label, $before, $after);
    }

    /** ========= Privados ========= */

    private static function lineFmt(string $label, mixed $old, mixed $new): string
    {
        $oldS = self::scalarize($old);
        $newS = self::scalarize($new);
        return '"' . $label . '" {' . $oldS . '} => {' . $newS . '}';
    }

    private static function scalarize(mixed $v): string
    {
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_null($v)) return '';
        if (is_scalar($v)) return (string)$v;
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    private static function isAssocStringArray(mixed $a): bool
    {
        if (!is_array($a) || $a === []) return false;
        foreach ($a as $k => $v) {
            if (!is_string($k) || !is_string($v)) return false;
        }
        return true;
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
                if ($ip !== '') return $ip;
            }
        }
        return '0.0.0.0';
    }
}
