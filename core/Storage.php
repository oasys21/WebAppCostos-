<?php
declare(strict_types=1);

final class Storage
{
    /** Directorio base del proyecto. Evita terminar en /core */
    public static function basePath(): string
    {
        $here   = realpath(__DIR__);            // .../core
        $parent = realpath(dirname(__DIR__));   // .../ (raíz proyecto)
        if ($here && basename($here) === 'core' && $parent) {
            return rtrim(str_replace('\\','/',$parent),'/') . '/';
        }
        return rtrim(str_replace('\\','/',$parent ?: $here ?: getcwd()),'/') . '/';
    }

    /** Normaliza ruta a separadores del SO */
    public static function fsPath(string $path): string
    {
        $path = str_replace('\\','/',$path);
        $path = preg_replace('~/+~','/',$path);
        if (DIRECTORY_SEPARATOR === '\\') $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        return $path;
    }

    /** Crea recursivamente si no existe */
    public static function ensureDir(string $absDir): void
    {
        $absDir = rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (is_dir($absDir)) return;
        if (!@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException('No se pudo crear directorio: ' . $absDir);
        }
    }

    /**
     * Raíz de almacenamiento privado.
     * Preferencias: storage_private_root | STORAGE_PRIVATE_ROOT | BASE_PATH/APP_ROOT | auto <raiz>/storage/private/
     */
    public static function privateRoot(array $cfg = []): string
    {
        $conf = $cfg['storage_private_root'] ?? $cfg['STORAGE_PRIVATE_ROOT'] ?? null;
        if ($conf) {
            $p = rtrim(str_replace('\\','/', (string)$conf), '/') . '/';
        } else {
            $base = ($cfg['BASE_PATH'] ?? $cfg['APP_ROOT'] ?? self::basePath());
            $base = rtrim(str_replace('\\','/',$base),'/') . '/';
            if (substr($base,-5) === '/core/') $base = rtrim(substr($base,0,-5),'/') . '/';
            $p = $base . 'storage/private/';
        }
        $abs = self::fsPath($p);
        if (!is_dir($abs)) self::ensureDir($abs);
        return rtrim($abs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Construye paths de documento (relativo y absoluto)
     * Rel: /{proyecto}/{itemcosto_o_entidad}/{docId}/v{n}/
     * Retorna [absDir, relDir]
     */
    public static function buildDirs(array $cfg, string $proyecto, $itemOrEntidad, int $docId, int $nroVersion): array
    {
        $seg2 = (string)$itemOrEntidad; // puede ser itemcosto (texto) o entidad_id (número)
        $rel = '/' . rawurlencode($proyecto) . '/' . rawurlencode($seg2) . '/' . $docId . '/v' . $nroVersion . '/';
        $abs = rtrim(self::privateRoot($cfg), DIRECTORY_SEPARATOR);
        $absDir = self::fsPath($abs . DIRECTORY_SEPARATOR . ltrim($rel,'/'));
        return [$absDir, $rel];
    }

    /** Nombre físico único */
    public static function genStoredName(string $ext): string
    {
        $ext = ltrim(strtolower($ext), '.');
        $seed = bin2hex(random_bytes(8));
        return date('Ymd_His') . '_' . $seed . ($ext ? '.' . $ext : '');
    }

    /** SHA256 del archivo */
    public static function sha256(string $absFile): string
    {
        return @hash_file('sha256', $absFile) ?: '';
    }

    /** Tamaño máximo (opcional cfg['upload_max_bytes']) */
    public static function validateSize(array $cfg, int $bytes): void
    {
        $max = (int)($cfg['upload_max_bytes'] ?? 0);
        if ($bytes <= 0) throw new \RuntimeException('Archivo vacío');
        if ($max > 0 && $bytes > $max) throw new \RuntimeException('Archivo excede el tamaño permitido');
    }

    /** Bloquea ejecutables básicos */
    public static function validateExtMime(string $origName, string $mime): void
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','phtml','phar','cgi','exe','bat','cmd','ps1'], true)) {
            throw new \RuntimeException('Tipo de archivo no permitido');
        }
    }
}
