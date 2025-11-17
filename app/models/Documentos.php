<?php
declare(strict_types=1);

final class Documentos
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $pdo) { $this->db = $pdo; }

    /* ===== CRUD principal ===== */

    public function create(array $d): int
    {
        $st = $this->db->prepare("
            INSERT INTO documentos
                (modulo, proyecto, itemcosto, entidad_id, titulo, categoria_id, estado, privado,
                 nombre_original, nombre_almacenado, ext, mime, tamanio, checksum_sha256, ruta_relativa,
                 emitido_en, vence_en, creado_por, creado_en)
            VALUES
                (:modulo, :proyecto, :itemcosto, :entidad_id, :titulo, :categoria_id, :estado, :privado,
                 :nombre_original, :nombre_almacenado, :ext, :mime, :tamanio, :checksum_sha256, :ruta_relativa,
                 :emitido_en, :vence_en, :creado_por, NOW())
        ");
        $st->execute([
            ':modulo'            => $d['modulo'],
            ':proyecto'          => $d['proyecto'] ?? null,
            ':itemcosto'         => $d['itemcosto'] ?? null,
            ':entidad_id'        => $d['entidad_id'] ?? null,
            ':titulo'            => $d['titulo'] ?? null,
            ':categoria_id'      => $d['categoria_id'] ?? null,
            ':estado'            => $d['estado'] ?? 'vigente',
            ':privado'           => (int)($d['privado'] ?? 0),
            ':nombre_original'   => $d['nombre_original'],
            ':nombre_almacenado' => $d['nombre_almacenado'] ?? '',
            ':ext'               => $d['ext'] ?? null,
            ':mime'              => $d['mime'] ?? null,
            ':tamanio'           => (int)($d['tamanio'] ?? 0),
            ':checksum_sha256'   => $d['checksum_sha256'] ?? '',
            ':ruta_relativa'     => $d['ruta_relativa'] ?? '',
            ':emitido_en'        => $d['emitido_en'] ?? null,
            ':vence_en'          => $d['vence_en'] ?? null,
            ':creado_por'        => (int)($d['creado_por'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function setRutaYNombre(int $docId, string $rutaRel, string $nombreAlm): bool
    {
        $st = $this->db->prepare("
            UPDATE documentos
               SET ruta_relativa = :r,
                   nombre_almacenado = :n
             WHERE id = :id
        ");
        return $st->execute([':r'=>$rutaRel, ':n'=>$nombreAlm, ':id'=>$docId]);
    }

    public function get(int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT d.*, c.nombre AS categoria
            FROM documentos d
            LEFT JOIN documentos_categorias c ON c.id = d.categoria_id
            WHERE d.id = :id
        ");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function updateMeta(int $id, array $d): bool
    {
        $st = $this->db->prepare("
            UPDATE documentos
               SET modulo = :modulo,
                   proyecto = :proyecto,
                   itemcosto = :itemcosto,
                   titulo = :titulo,
                   categoria_id = :categoria_id,
                   estado = :estado,
                   privado = :privado,
                   emitido_en = :emitido_en,
                   vence_en   = :vence_en
             WHERE id = :id
        ");
        return $st->execute([
            ':id'           => $id,
            ':modulo'       => $d['modulo'] ?? null,
            ':proyecto'     => $d['proyecto'] ?? null,
            ':itemcosto'    => $d['itemcosto'] ?? null,
            ':titulo'       => $d['titulo'] ?? null,
            ':categoria_id' => $d['categoria_id'] ?? null,
            ':estado'       => $d['estado'] ?? 'vigente',
            ':privado'      => (int)($d['privado'] ?? 0),
            ':emitido_en'   => $d['emitido_en'] ?? null,
            ':vence_en'     => $d['vence_en'] ?? null,
        ]);
    }

    /* ===== Versionado ===== */

    public function maxVersion(int $docId): int
    {
        $st = $this->db->prepare("SELECT MAX(nro_version) FROM documentos_versiones WHERE documento_id = :id");
        $st->execute([':id'=>$docId]);
        $v = $st->fetchColumn();
        return $v ? (int)$v : 0;
    }

    public function addVersion(array $v): int
    {
        $st = $this->db->prepare("
            INSERT INTO documentos_versiones
                (documento_id, nro_version, nombre_original, nombre_almacenado, ext, mime, tamanio,
                 checksum_sha256, ruta_relativa, observacion, subido_por, subido_en)
            VALUES
                (:documento_id, :nro_version, :nombre_original, :nombre_almacenado, :ext, :mime, :tamanio,
                 :checksum_sha256, :ruta_relativa, :observacion, :subido_por, NOW())
        ");
        $st->execute([
            ':documento_id'      => (int)$v['documento_id'],
            ':nro_version'       => (int)$v['nro_version'],
            ':nombre_original'   => $v['nombre_original'],
            ':nombre_almacenado' => $v['nombre_almacenado'],
            ':ext'               => $v['ext'],
            ':mime'              => $v['mime'],
            ':tamanio'           => (int)$v['tamanio'],
            ':checksum_sha256'   => $v['checksum_sha256'],
            ':ruta_relativa'     => $v['ruta_relativa'],
            ':observacion'       => $v['observacion'] ?? null,
            ':subido_por'        => (int)$v['subido_por'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function versions(int $docId): array
    {
        $st = $this->db->prepare("
            SELECT * FROM documentos_versiones
             WHERE documento_id = :id
             ORDER BY nro_version DESC
        ");
        $st->execute([':id'=>$docId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ===== Borrado en cascada (doc + versiones) ===== */
    public function deleteCascade(int $docId): void
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare("DELETE FROM documentos_versiones WHERE documento_id = :id");
            $st->execute([':id'=>$docId]);

            $st = $this->db->prepare("DELETE FROM documentos WHERE id = :id");
            $st->execute([':id'=>$docId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
