<?php
// /costos/app/models/Proveedores.php
declare(strict_types=1);

final class Proveedores
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $pdo) { $this->db = $pdo; }

    public function get(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM proveedores WHERE id = :id");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function searchPaged(?string $q=null, ?int $activo=null, int $limit=200, int $offset=0): array
    {
        $sql = "SELECT * FROM proveedores WHERE 1=1";
        $p = [];
        if ($q !== null && $q !== '') {
            $sql .= " AND (nombre LIKE :q OR rut LIKE :q OR razon LIKE :q OR rubro LIKE :q OR ciudad LIKE :q)";
            $p[':q'] = '%'.$q.'%';
        }
        if ($activo !== null) {
            $sql .= " AND activo = :a";
            $p[':a'] = (int)$activo;
        }
        $sql .= " ORDER BY activo DESC, id DESC LIMIT :l OFFSET :o";
        $st = $this->db->prepare($sql);
        foreach ($p as $k=>$v) { $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $st->bindValue(':l', $limit, PDO::PARAM_INT);
        $st->bindValue(':o', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $d): int
    {
        $st = $this->db->prepare("
            INSERT INTO proveedores
            (nombre, rut, razon, rubro, direccion, comuna, ciudad,
             con_nom, con_email, con_fono,
             rl_rut, rl_nom, rl_email, rl_fono,
             ep_nom, ep_email, ep_fono, activo)
            VALUES
            (:nombre, :rut, :razon, :rubro, :direccion, :comuna, :ciudad,
             :con_nom, :con_email, :con_fono,
             :rl_rut, :rl_nom, :rl_email, :rl_fono,
             :ep_nom, :ep_email, :ep_fono, :activo)
        ");
        $st->execute([
            ':nombre'=>$d['nombre'],
            ':rut'=>$d['rut'] ?: null,
            ':razon'=>$d['razon'] ?: null,
            ':rubro'=>$d['rubro'] ?: null,
            ':direccion'=>$d['direccion'] ?: null,
            ':comuna'=>$d['comuna'] ?: null,
            ':ciudad'=>$d['ciudad'] ?: null,
            ':con_nom'=>$d['con_nom'] ?: null,
            ':con_email'=>$d['con_email'] ?: null,
            ':con_fono'=>$d['con_fono'] ?: null,
            ':rl_rut'=>$d['rl_rut'] ?: null,
            ':rl_nom'=>$d['rl_nom'] ?: null,
            ':rl_email'=>$d['rl_email'] ?: null,
            ':rl_fono'=>$d['rl_fono'] ?: null,
            ':ep_nom'=>$d['ep_nom'] ?: null,
            ':ep_email'=>$d['ep_email'] ?: null,
            ':ep_fono'=>$d['ep_fono'] ?: null,
            ':activo'=>(int)($d['activo'] ?? 1),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): bool
    {
        $st = $this->db->prepare("
            UPDATE proveedores SET
            nombre=:nombre, rut=:rut, razon=:razon, rubro=:rubro,
            direccion=:direccion, comuna=:comuna, ciudad=:ciudad,
            con_nom=:con_nom, con_email=:con_email, con_fono=:con_fono,
            rl_rut=:rl_rut, rl_nom=:rl_nom, rl_email=:rl_email, rl_fono=:rl_fono,
            ep_nom=:ep_nom, ep_email=:ep_email, ep_fono=:ep_fono,
            activo=:activo
            WHERE id = :id
        ");
        return $st->execute([
            ':id'=>$id,
            ':nombre'=>$d['nombre'],
            ':rut'=>$d['rut'] ?: null,
            ':razon'=>$d['razon'] ?: null,
            ':rubro'=>$d['rubro'] ?: null,
            ':direccion'=>$d['direccion'] ?: null,
            ':comuna'=>$d['comuna'] ?: null,
            ':ciudad'=>$d['ciudad'] ?: null,
            ':con_nom'=>$d['con_nom'] ?: null,
            ':con_email'=>$d['con_email'] ?: null,
            ':con_fono'=>$d['con_fono'] ?: null,
            ':rl_rut'=>$d['rl_rut'] ?: null,
            ':rl_nom'=>$d['rl_nom'] ?: null,
            ':rl_email'=>$d['rl_email'] ?: null,
            ':rl_fono'=>$d['rl_fono'] ?: null,
            ':ep_nom'=>$d['ep_nom'] ?: null,
            ':ep_email'=>$d['ep_email'] ?: null,
            ':ep_fono'=>$d['ep_fono'] ?: null,
            ':activo'=>(int)($d['activo'] ?? 1),
        ]);
    }

    public function delete(int $id): bool
    {
        $st = $this->db->prepare("DELETE FROM proveedores WHERE id = :id");
        return $st->execute([':id'=>$id]);
    }
}
