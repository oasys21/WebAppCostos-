<?php
// /costos/app/models/DocCategorias.php
declare(strict_types=1);

final class DocCategorias
{
    private PDO $db;

    /** Módulos válidos (mantén sincronizado con tu ENUM documentos.modulo) */
    public const MODULOS = [
        'PROY','RRHH','PRES','CAT','PREC','AVN','ADQ','CLI','PROV','ESP',
        'GEAR-VEH','GEAR-MAQ','GEAR-TOOL'
    ];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /* ============ Lecturas ============ */

    public function find(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM documentos_categorias WHERE id = :id");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Alias para compatibilidad si el controlador usa get($id) */
    public function get($id): ?array
    {
        return $this->find((int)$id);
    }

    /**
     * Lista con filtros y paginación simple.
     * Acepta strings en $limit/$offset (p.ej. desde $_GET).
     */
    public function list($limit = 100, $offset = 0, ?string $q = null, ?string $modulo = null, ?int $activo = null): array
    {
        $limit  = is_numeric($limit)  ? max(1, min(500, (int)$limit)) : 100;
        $offset = is_numeric($offset) ? max(0, (int)$offset)          : 0;

        $sql = "SELECT * FROM documentos_categorias WHERE 1=1";
        $p   = [];

        if ($q !== null && $q !== '') {
            $sql .= " AND (nombre LIKE :q OR descripcion LIKE :q)";
            $p[':q'] = '%'.$q.'%';
        }
        if ($modulo !== null && $modulo !== '') {
            $sql .= " AND modulo = :m";
            $p[':m'] = $modulo;
        }
        if ($activo !== null) {
            $sql .= " AND activo = :a";
            $p[':a'] = (int)$activo;
        }

        $sql .= " ORDER BY modulo ASC, nombre ASC LIMIT :limit OFFSET :offset";
        $st = $this->db->prepare($sql);

        foreach ($p as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);

        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalCount(?string $q = null, ?string $modulo = null, ?int $activo = null): int
    {
        $sql = "SELECT COUNT(*) FROM documentos_categorias WHERE 1=1";
        $p = [];

        if ($q !== null && $q !== '') {
            $sql .= " AND (nombre LIKE :q OR descripcion LIKE :q)";
            $p[':q'] = '%'.$q.'%';
        }
        if ($modulo !== null && $modulo !== '') {
            $sql .= " AND modulo = :m";
            $p[':m'] = $modulo;
        }
        if ($activo !== null) {
            $sql .= " AND activo = :a";
            $p[':a'] = (int)$activo;
        }

        $st = $this->db->prepare($sql);
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();
        return (int)$st->fetchColumn();
    }

    /* ============ Mutaciones ============ */

    /** Crea una categoría */
    public function create(array $data): int
    {
        $modulo = (string)($data['modulo'] ?? '');
        $nombre = trim((string)($data['nombre'] ?? ''));
        $desc   = trim((string)($data['descripcion'] ?? ''));
        $activo = isset($data['activo']) ? (int)(bool)$data['activo'] : 1;

        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (!in_array($modulo, self::MODULOS, true)) {
            throw new InvalidArgumentException('Módulo inválido.');
        }

        // Unicidad por (modulo, nombre)
        $st = $this->db->prepare("SELECT 1 FROM documentos_categorias WHERE modulo=:m AND nombre=:n LIMIT 1");
        $st->execute([':m'=>$modulo, ':n'=>$nombre]);
        if ($st->fetchColumn()) {
            throw new RuntimeException('Ya existe una categoría con ese nombre en el módulo.');
        }

        $st = $this->db->prepare(
            "INSERT INTO documentos_categorias (modulo, nombre, descripcion, activo)
             VALUES (:m, :n, :d, :a)"
        );
        $st->execute([
            ':m'=>$modulo, ':n'=>$nombre, ':d'=>$desc, ':a'=>$activo
        ]);

        return (int)$this->db->lastInsertId();
    }

    /** Actualiza una categoría */
    public function update(int $id, array $data): bool
    {
        $row = $this->find($id);
        if (!$row) throw new RuntimeException('Categoría no encontrada.');

        $modulo = isset($data['modulo']) ? (string)$data['modulo'] : (string)$row['modulo'];
        $nombre = array_key_exists('nombre',$data) ? trim((string)$data['nombre']) : (string)$row['nombre'];
        $desc   = array_key_exists('descripcion',$data) ? trim((string)$data['descripcion']) : (string)($row['descripcion'] ?? '');
        $activo = isset($data['activo']) ? (int)(bool)$data['activo'] : (int)$row['activo'];

        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (!in_array($modulo, self::MODULOS, true)) {
            throw new InvalidArgumentException('Módulo inválido.');
        }

        // Unicidad (modulo, nombre) excluyendo el propio id
        $st = $this->db->prepare(
            "SELECT 1 FROM documentos_categorias WHERE modulo=:m AND nombre=:n AND id<>:id LIMIT 1"
        );
        $st->execute([':m'=>$modulo, ':n'=>$nombre, ':id'=>$id]);
        if ($st->fetchColumn()) {
            throw new RuntimeException('Ya existe una categoría con ese nombre en el módulo.');
        }

        $st = $this->db->prepare(
            "UPDATE documentos_categorias
               SET modulo=:m, nombre=:n, descripcion=:d, activo=:a
             WHERE id=:id"
        );
        return $st->execute([
            ':m'=>$modulo, ':n'=>$nombre, ':d'=>$desc, ':a'=>$activo, ':id'=>$id
        ]);
    }

    /** ¿Se puede borrar? (si no está usada por documentos) */
    public function canDelete(int $id): bool
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM documentos WHERE categoria_id = :id");
        $st->execute([':id'=>$id]);
        return ((int)$st->fetchColumn()) === 0;
    }

    /** Borra una categoría si no tiene documentos asociados */
    public function delete(int $id): bool
    {
        if (!$this->canDelete($id)) return false;
        $st = $this->db->prepare("DELETE FROM documentos_categorias WHERE id = :id");
        return $st->execute([':id'=>$id]);
    }
}
