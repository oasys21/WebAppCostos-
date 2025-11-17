<?php
// /costos/app/models/AvanceCostos.php
declare(strict_types=1);

final class AvanceCostos
{
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    public function listByProyecto(int $proyecto_id, ?string $from=null, ?string $to=null): array {
        $where = "WHERE a.proyecto_id = :p";
        $par = [':p'=>$proyecto_id];
        if ($from) { $where .= " AND a.fecha_avance >= :f"; $par[':f']=$from; }
        if ($to)   { $where .= " AND a.fecha_avance <= :t"; $par[':t']=$to; }

        $st = $this->pdo->prepare("
            SELECT a.*, cc.descripcion
            FROM avance_costos a
            LEFT JOIN costos_catalogo cc ON cc.codigo=a.codigo
            $where
            ORDER BY a.fecha_avance DESC, a.id DESC
        ");
        $st->execute($par);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function get(int $id): ?array {
        $st = $this->pdo->prepare("
            SELECT a.*, cc.descripcion
            FROM avance_costos a
            LEFT JOIN costos_catalogo cc ON cc.codigo=a.codigo
            WHERE a.id=:id
        ");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function create(array $d): int {
        $st = $this->pdo->prepare("
            INSERT INTO avance_costos
              (proyecto_id, codigo, fecha_avance, cantidad_ejecutada, monto_ejecutado, usuario_id, observaciones)
            VALUES
              (:p, :c, :fa, :cant, :monto, :u, :obs)
        ");
        $st->execute([
            ':p'=>(int)$d['proyecto_id'],
            ':c'=>(string)$d['codigo'],
            ':fa'=>$d['fecha_avance'] ?? date('Y-m-d'),
            ':cant'=>(float)($d['cantidad_ejecutada'] ?? 0),
            ':monto'=>(float)($d['monto_ejecutado'] ?? 0),
            ':u'=>$d['usuario_id'] ?? null,
            ':obs'=>$d['observaciones'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): bool {
        $st = $this->pdo->prepare("
            UPDATE avance_costos SET
              codigo=:c, fecha_avance=:fa,
              cantidad_ejecutada=:cant, monto_ejecutado=:monto,
              observaciones=:obs
            WHERE id=:id
        ");
        return $st->execute([
            ':c'=>(string)$d['codigo'],
            ':fa'=>$d['fecha_avance'] ?? date('Y-m-d'),
            ':cant'=>(float)($d['cantidad_ejecutada'] ?? 0),
            ':monto'=>(float)($d['monto_ejecutado'] ?? 0),
            ':obs'=>$d['observaciones'] ?? null,
            ':id'=>$id
        ]);
    }

    public function delete(int $id): bool {
        $st = $this->pdo->prepare("DELETE FROM avance_costos WHERE id=:id");
        return $st->execute([':id'=>$id]);
    }
}
