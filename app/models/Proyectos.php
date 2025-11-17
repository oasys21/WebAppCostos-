<?php
// /costos/app/models/Proyectos.php
declare(strict_types=1);

final class Proyectos
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $pdo) { $this->db = $pdo; }

    /* ========================= BÁSICOS ========================= */

    public function get(int $id): ?array
    {
        $st = $this->db->prepare("SELECT p.*,
                                         COALESCE(p.owner_user_id, 0) AS owner_user_id
                                    FROM proyectos p
                                   WHERE p.id = :id
                                   LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function create(array $data): int
    {
        $st = $this->db->prepare("INSERT INTO proyectos
            (nombre, codigo_proy, descripcion, rut_cliente, fecha_inicio, fecha_termino, activo, owner_user_id)
            VALUES (:n,:cp,:d,:rc,:fi,:ft,:a,:o)");
        $ok = $st->execute([
            ':n' => (string)($data['nombre'] ?? ''),
            ':cp' => (string)($data['codigo_proy'] ?? ''),
            ':d' => (string)($data['descripcion'] ?? ''),
            ':rc'=> (string)($data['rut_cliente'] ?? ''),
            ':fi'=> ($data['fecha_inicio'] ?? null) ?: null,
            ':ft'=> ($data['fecha_termino'] ?? null) ?: null,
            ':a' => (int)($data['activo'] ?? 1),
            ':o' => ($data['owner_user_id'] ?? null) ? (int)$data['owner_user_id'] : null,
        ]);
        if (!$ok) { return 0; }
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $st = $this->db->prepare("UPDATE proyectos
                                     SET nombre=:n,
                                         descripcion=:d,
                                         rut_cliente=:rc,
                                         fecha_inicio=:fi,
                                         fecha_termino=:ft,
                                         activo=:a,
                                         codigo_proy=:cp
                                   WHERE id=:id");
        return $st->execute([
            ':n' => (string)($data['nombre'] ?? ''),
            ':cp' => (string)($data['codigo_proy'] ?? ''),
            ':d' => (string)($data['descripcion'] ?? ''),
            ':rc'=> (string)($data['rut_cliente'] ?? ''),
            ':fi'=> ($data['fecha_inicio'] ?? null) ?: null,
            ':ft'=> ($data['fecha_termino'] ?? null) ?: null,
            ':a' => (int)($data['activo'] ?? 1),
            ':id'=> (int)$id,
        ]);
    }

    public function toggleActivo(int $id, int $activo): bool
    {
        $st = $this->db->prepare("UPDATE proyectos SET activo = :a WHERE id = :id");
        return $st->execute([':a' => (int)$activo, ':id' => (int)$id]);
    }

    /* ========================= LISTAR ========================= */

    public function search(
        ?string $q = null,
        bool $soloActivos = true,
        ?string $rut_cliente = null,
        int $page = 1,
        int $perPage = 20,
        ?int $onlyForUserId = null
    ): array {
        $page = max(1, (int)$page);
        $perPage = max(1, min(200, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*
                  FROM proyectos p";
        $where = ["1=1"];
        $params = [];

        if ($onlyForUserId) {
            $sql .= " LEFT JOIN proyecto_usuarios pu
                           ON pu.proyecto_id = p.id AND pu.user_id = :uid";
            $where[] = "(p.owner_user_id = :uid OR pu.user_id IS NOT NULL)";
            $params[':uid'] = (int)$onlyForUserId;
        }

        if ($soloActivos) { $where[] = "p.activo = 1"; }
        if ($q !== null && $q !== '') {
            $where[] = "(p.nombre LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($rut_cliente) {
            $where[] = "p.rut_cliente = :rc";
            $params[':rc'] = $rut_cliente;
        }

        $sql .= " WHERE " . implode(' AND ', $where) . " 
                  ORDER BY p.id DESC
                  LIMIT :limit OFFSET :offset";

        $st = $this->db->prepare($sql);
        foreach ($params as $k=>$v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $st->bindValue($k, $v, $type);
        }
        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSearch(
        ?string $q = null,
        bool $soloActivos = true,
        ?string $rut_cliente = null,
        ?int $onlyForUserId = null
    ): int {
        $sql = "SELECT COUNT(*)
                  FROM proyectos p";
        $where = ["1=1"];
        $params = [];

        if ($onlyForUserId) {
            $sql .= " LEFT JOIN proyecto_usuarios pu
                           ON pu.proyecto_id = p.id AND pu.user_id = :uid";
            $where[] = "(p.owner_user_id = :uid OR pu.user_id IS NOT NULL)";
            $params[':uid'] = (int)$onlyForUserId;
        }

        if ($soloActivos) { $where[] = "p.activo = 1"; }
        if ($q !== null && $q !== '') {
            $where[] = "(p.nombre LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($rut_cliente) {
            $where[] = "p.rut_cliente = :rc";
            $params[':rc'] = $rut_cliente;
        }

        $sql .= " WHERE " . implode(' AND ', $where);

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    /* ========================= OWNER / MIEMBROS ========================= */

    public function setOwner(int $proyId, int $newOwnerUserId): bool
    {
        $st = $this->db->prepare("UPDATE proyectos SET owner_user_id = :o WHERE id = :p");
        return $st->execute([':o'=>$newOwnerUserId, ':p'=>$proyId]);
    }

    public function getMembers(int $proyId): array
    {
        // No referenciar columnas no garantizadas: traigo u.* y armo etiqueta en la vista.
        $st = $this->db->prepare("
            SELECT pu.user_id, pu.rol, u.*
              FROM proyecto_usuarios pu
              JOIN usuarios u ON u.id = pu.user_id
             WHERE pu.proyecto_id = :p
             ORDER BY pu.user_id ASC");
        $st->execute([':p'=>$proyId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addMember(int $proyId, int $userId, string $rol): bool
    {
        $r = strtoupper(trim($rol));
        // Normalizar sinónimos al ENUM real
        $map = [
            'OWNER'  => ['OWNER','DUEÑO','DUENO','ADMIN','PROPIETARIO'],
            'EDITOR' => ['EDITOR','AUTORIZADO','MIEMBRO','MEMBER','USUARIO','USER','COLABORADOR'],
            'VISOR'  => ['VISOR','VIEWER','LECTURA','READ','READ-ONLY'],
        ];
        $canon = null;
        foreach ($map as $k => $syns) {
            if (in_array($r, $syns, true)) { $canon = $k; break; }
        }
        if ($canon === null) { $canon = in_array($r, ['OWNER','EDITOR','VISOR'], true) ? $r : 'EDITOR'; }

        $st = $this->db->prepare("
            INSERT INTO proyecto_usuarios (proyecto_id,user_id,rol)
            VALUES (:p,:u,:r)
            ON DUPLICATE KEY UPDATE rol = VALUES(rol)");
        return $st->execute([':p'=>$proyId, ':u'=>$userId, ':r'=>$canon]);
    }

    public function removeMember(int $proyId, int $userId): bool
    {
        $st = $this->db->prepare("DELETE FROM proyecto_usuarios WHERE proyecto_id=:p AND user_id=:u");
        return $st->execute([':p'=>$proyId, ':u'=>$userId]);
    }

    public function isOwner(int $proyId, int $userId): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM proyectos WHERE id=:p AND owner_user_id=:u LIMIT 1");
        $st->execute([':p'=>$proyId, ':u'=>$userId]);
        return (bool)$st->fetchColumn();
    }

    public function isMember(int $proyId, int $userId): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM proyecto_usuarios WHERE proyecto_id=:p AND user_id=:u LIMIT 1");
        $st->execute([':p'=>$proyId, ':u'=>$userId]);
        return (bool)$st->fetchColumn();
    }
}
