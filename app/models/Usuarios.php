<?php
// /costos/app/models/Usuarios.php
declare(strict_types=1);

final class Usuarios
{
    private PDO $pdo;
    private string $table = 'usuarios';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /* =========================
       Helpers básicos
       ========================= */
    private function normRut(string $rut): string
    {
        $r = strtoupper(preg_replace('/[^0-9Kk-]/', '', $rut));
        // Normaliza con guion final si viene sin él: 11111111K -> 11111111-K
        if ($r && strpos($r, '-') === false && strlen($r) >= 2) {
            $r = substr($r, 0, -1) . '-' . substr($r, -1);
        }
        return $r;
    }

    private function toNull($v) {
        if ($v === '' || $v === null) return null;
        return $v;
    }

    /* =========================
       Lecturas
       ========================= */

    /** Devuelve un usuario por ID o null */
    public function getById(int $id): ?array
    {
        $st = $this->pdo->prepare("
            SELECT id, rut, nombre, email, perfil, subperfil, activo,
                   fono, fecnac, creado_en
            FROM {$this->table}
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Devuelve usuario por RUT (normalizado) o null */
    public function findByRut(string $rut): ?array
    {
        $rut = $this->normRut($rut);
        $st = $this->pdo->prepare("
            SELECT id, rut, nombre, email, perfil, subperfil, activo,
                   fono, fecnac, creado_en
            FROM {$this->table}
            WHERE REPLACE(UPPER(rut), '.', '') = REPLACE(:rut, '.', '')
            LIMIT 1
        ");
        $st->execute([':rut' => strtoupper($rut)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Listado (con búsqueda opcional por rut/nombre/email)
     * @return array<int, array>
     */
    public function listar(string $q = '', int $limit = 100, int $offset = 0): array
    {
        $q = trim($q);
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        if ($q !== '') {
            $st = $this->pdo->prepare("
                SELECT id, rut, nombre, email, perfil, subperfil, activo,
                       fono, fecnac, creado_en
                FROM {$this->table}
                WHERE (rut LIKE CONCAT('%', :q, '%')
                   OR  nombre LIKE CONCAT('%', :q, '%')
                   OR  email  LIKE CONCAT('%', :q, '%'))
                ORDER BY nombre ASC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $st->execute([':q' => $q]);
        } else {
            $st = $this->pdo->query("
                SELECT id, rut, nombre, email, perfil, subperfil, activo,
                       fono, fecnac, creado_en
                FROM {$this->table}
                ORDER BY nombre ASC
                LIMIT {$limit} OFFSET {$offset}
            ");
        }
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================
       Escrituras
       ========================= */

    /**
     * Crea usuario y devuelve ID insertado.
     * Campos esperados:
     *  - rut (req), nombre (req), email (req), perfil (req),
     *  - subperfil (opt), activo (opt, default 1),
     *  - fono (opt, char 15), fecnac (opt, 'Y-m-d' o null)
     */
    public function crear(array $data): int
    {
        $rut       = $this->normRut((string)($data['rut'] ?? ''));
        $nombre    = trim((string)($data['nombre'] ?? ''));
        $email     = trim((string)($data['email'] ?? ''));
        $perfil    = trim((string)($data['perfil'] ?? 'USR'));
        $subperfil = trim((string)($data['subperfil'] ?? ''));
        $activo    = (int)($data['activo'] ?? 1);

        $fono      = $this->toNull(isset($data['fono']) ? substr(trim((string)$data['fono']), 0, 15) : null);
        $fecnac    = $this->toNull(isset($data['fecnac']) ? trim((string)$data['fecnac']) : null); // 'YYYY-mm-dd' o null

        if ($rut === '' || $nombre === '' || $email === '' || $perfil === '') {
            throw new InvalidArgumentException('Faltan campos obligatorios');
        }

        // Unicidad de RUT o email (si aplica en tu esquema)
        $st = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE rut = :rut OR email = :email LIMIT 1");
        $st->execute([':rut' => $rut, ':email' => $email]);
        if ($st->fetch()) {
            throw new RuntimeException('RUT o Email ya existe');
        }

        $sql = "
            INSERT INTO {$this->table}
                (rut, nombre, email, perfil, subperfil, activo, fono, fecnac, creado_en)
            VALUES
                (:rut, :nombre, :email, :perfil, :subperfil, :activo, :fono, :fecnac, NOW())
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':rut',       $rut);
        $st->bindValue(':nombre',    $nombre);
        $st->bindValue(':email',     $email);
        $st->bindValue(':perfil',    $perfil);
        $st->bindValue(':subperfil', $subperfil !== '' ? $subperfil : null, $subperfil !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':activo',    $activo, PDO::PARAM_INT);
        $st->bindValue(':fono',      $fono,   $fono   !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':fecnac',    $fecnac, $fecnac !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Actualiza usuario.
     * Acepta mismos campos que crear(); cualquier campo omitido no se toca.
     * Para poner fono/fecnac en NULL, envía cadena vacía.
     */
    public function actualizar(int $id, array $data): void
    {
        $row = $this->getById($id);
        if (!$row) { throw new RuntimeException('Usuario no existe'); }

        $rut       = array_key_exists('rut', $data) ? $this->normRut((string)$data['rut']) : $row['rut'];
        $nombre    = array_key_exists('nombre', $data) ? trim((string)$data['nombre']) : $row['nombre'];
        $email     = array_key_exists('email', $data) ? trim((string)$data['email']) : $row['email'];
        $perfil    = array_key_exists('perfil', $data) ? trim((string)$data['perfil']) : $row['perfil'];
        $subperfil = array_key_exists('subperfil', $data) ? trim((string)$data['subperfil']) : (string)($row['subperfil'] ?? '');
        $activo    = array_key_exists('activo', $data) ? (int)$data['activo'] : (int)$row['activo'];

        $fono   = array_key_exists('fono', $data) ? $this->toNull(substr(trim((string)$data['fono']), 0, 15)) : $row['fono'];
        $fecnac = array_key_exists('fecnac', $data) ? $this->toNull(trim((string)$data['fecnac'])) : $row['fecnac'];

        // Chequeo unicidad rut/email si cambiaron
        if ($rut !== $row['rut'] || $email !== $row['email']) {
            $st = $this->pdo->prepare("
                SELECT 1 FROM {$this->table}
                WHERE (rut = :rut OR email = :email) AND id <> :id
                LIMIT 1
            ");
            $st->execute([':rut' => $rut, ':email' => $email, ':id' => $id]);
            if ($st->fetch()) { throw new RuntimeException('RUT o Email ya existe'); }
        }

        $sql = "
            UPDATE {$this->table}
            SET rut=:rut, nombre=:nombre, email=:email, perfil=:perfil,
                subperfil=:subperfil, activo=:activo,
                fono=:fono, fecnac=:fecnac
            WHERE id = :id
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':id',        $id, PDO::PARAM_INT);
        $st->bindValue(':rut',       $rut);
        $st->bindValue(':nombre',    $nombre);
        $st->bindValue(':email',     $email);
        $st->bindValue(':perfil',    $perfil);
        $st->bindValue(':subperfil', $this->toNull($subperfil));
        $st->bindValue(':activo',    $activo, PDO::PARAM_INT);
        $st->bindValue(':fono',      $this->toNull($fono),   $fono   !== null   ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':fecnac',    $this->toNull($fecnac), $fecnac !== null   ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
    }

    /** Soft delete (si tu esquema requiere hard delete, cambia aquí) */
    public function eliminar(int $id): void
    {
        // por seguridad, marcamos inactivo
        $st = $this->pdo->prepare("UPDATE {$this->table} SET activo=0 WHERE id=:id");
        $st->execute([':id' => $id]);
    }

    /**
     * Actualiza “Mi Perfil” del usuario logueado (sin tocar perfil/subperfil/activo).
     * Permite editar: nombre, email, fono, fecnac.
     */
    public function updatePerfil(int $id, array $data): void
    {
        $row = $this->getById($id);
        if (!$row) { throw new RuntimeException('Usuario no existe'); }

        $nombre = array_key_exists('nombre', $data) ? trim((string)$data['nombre']) : $row['nombre'];
        $email  = array_key_exists('email',  $data) ? trim((string)$data['email'])  : $row['email'];
        $fono   = array_key_exists('fono',   $data) ? $this->toNull(substr(trim((string)$data['fono']), 0, 15)) : $row['fono'];
        $fecnac = array_key_exists('fecnac', $data) ? $this->toNull(trim((string)$data['fecnac'])) : $row['fecnac'];

        if ($email !== $row['email']) {
            $st = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE email=:email AND id<>:id LIMIT 1");
            $st->execute([':email' => $email, ':id' => $id]);
            if ($st->fetch()) { throw new RuntimeException('Email ya existe'); }
        }

        $st = $this->pdo->prepare("
            UPDATE {$this->table}
            SET nombre=:nombre, email=:email, fono=:fono, fecnac=:fecnac
            WHERE id=:id
        ");
        $st->execute([
            ':id'     => $id,
            ':nombre' => $nombre,
            ':email'  => $email,
            ':fono'   => $fono,
            ':fecnac' => $fecnac
        ]);
    }
}
