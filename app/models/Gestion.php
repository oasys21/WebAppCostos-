<?php
declare(strict_types=1);

class Gestion
{
    /* ===== Infra ===== */
    private static function pdo(): \PDO {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) return $GLOBALS['pdo'];
        if (function_exists('db')) { $c = db(); if ($c instanceof \PDO) return $c; }
        if (isset($GLOBALS['cfg']['DB']['dsn'])) {
            $dbcfg = $GLOBALS['cfg']['DB'];
            $opts  = $dbcfg['options'] ?? [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            $pdo = new \PDO($dbcfg['dsn'], $dbcfg['user'] ?? '', $dbcfg['pass'] ?? '', $opts);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            return $pdo;
        }
        throw new \Exception('No hay PDO');
    }

    /* ===== Util ===== */
    public static function listarUsuarios(): array {
        $pdo = self::pdo();
        $st = $pdo->query("SELECT id, nombre FROM usuarios ORDER BY nombre");
        return $st->fetchAll() ?: [];
    }

    private static function nextNumero(\PDO $pdo): int {
        $pdo->query("SELECT GET_LOCK('seq_gestiones', 5)");
        try{
            $st = $pdo->query("SELECT MAX(numero_gestion) FROM gestiones");
            $n  = (int)($st->fetchColumn() ?: 0);
            return $n + 1;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('seq_gestiones')");
        }
    }

    /* ===== CRUD ===== */
    public static function crear(array $d): int {
        $pdo = self::pdo();
        $num = self::nextNumero($pdo);

        $sql = "INSERT INTO gestiones
            (numero_gestion, usuario_origen, usuario_destino, fecha_solicitud, fecha_termino,
             valor_asignados, text_asignados, text_tarea, text_respuesta, fecha_propuesta,
             text_requeridos, valor_requeridos, estado_gestion, deriva_gestion)
         VALUES
            (:ng, :uo, :ud, :fs, :ft, :va, :ta, :tt, :tr, :fp, :treq, :vr, :est, :der)";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':ng'  => $num,
            ':uo'  => (int)$d['usuario_origen'],
            ':ud'  => (int)$d['usuario_destino'],
            ':fs'  => $d['fecha_solicitud'],
            ':ft'  => $d['fecha_termino'],
            ':va'  => (float)$d['valor_asignados'],
            ':ta'  => $d['text_asignados'],
            ':tt'  => $d['text_tarea'],
            ':tr'  => $d['text_respuesta'],
            ':fp'  => $d['fecha_propuesta'],
            ':treq'=> $d['text_requeridos'],
            ':vr'  => (float)$d['valor_requeridos'],
            ':est' => $d['estado_gestion'],
            ':der' => $d['deriva_gestion'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function buscarPorId(int $id): ?array {
        $pdo = self::pdo();
        $st = $pdo->prepare("SELECT * FROM gestiones WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function actualizarOwner(int $id, int $ownerId, array $d): void {
        $pdo = self::pdo();
        $st  = $pdo->prepare("SELECT usuario_origen FROM gestiones WHERE id=:id");
        $st->execute([':id'=>$id]);
        $u = (int)($st->fetchColumn() ?: 0);
        if ($u !== $ownerId) throw new \Exception('No autorizado (owner).');

        $sql = "UPDATE gestiones SET
                  fecha_termino=:ft,
                  valor_asignados=:va,
                  text_asignados=:ta,
                  text_tarea=:tt,
                  estado_gestion=:est
                WHERE id=:id";
        $upd = $pdo->prepare($sql);
        $upd->execute([
            ':ft'  => $d['fecha_termino'],
            ':va'  => (float)$d['valor_asignados'],
            ':ta'  => $d['text_asignados'],
            ':tt'  => $d['text_tarea'],
            ':est' => $d['estado_gestion'],
            ':id'  => $id,
        ]);
    }

    public static function actualizarDestino(int $id, int $destId, array $d): void {
        $pdo = self::pdo();
        $st  = $pdo->prepare("SELECT usuario_destino FROM gestiones WHERE id=:id");
        $st->execute([':id'=>$id]);
        $u = (int)($st->fetchColumn() ?: 0);
        if ($u !== $destId) throw new \Exception('No autorizado (destino).');

        $sql = "UPDATE gestiones SET
                  text_respuesta=:tr,
                  fecha_propuesta=:fp,
                  text_requeridos=:treq,
                  valor_requeridos=:vr,
                  estado_gestion=:est
                WHERE id=:id";
        $upd = $pdo->prepare($sql);
        $upd->execute([
            ':tr'   => $d['text_respuesta'],
            ':fp'   => $d['fecha_propuesta'],
            ':treq' => $d['text_requeridos'],
            ':vr'   => (float)$d['valor_requeridos'],
            ':est'  => $d['estado_gestion'],
            ':id'   => $id,
        ]);
    }

    public static function actualizarAdmin(int $id, array $d): void {
        $pdo = self::pdo();
        $sql = "UPDATE gestiones SET
                  usuario_origen=:uo,
                  usuario_destino=:ud,
                  fecha_solicitud=:fs,
                  fecha_termino=:ft,
                  valor_asignados=:va,
                  text_asignados=:ta,
                  text_tarea=:tt,
                  text_respuesta=:tr,
                  fecha_propuesta=:fp,
                  text_requeridos=:treq,
                  valor_requeridos=:vr,
                  estado_gestion=:est,
                  deriva_gestion=:der
                WHERE id=:id";
        $upd = $pdo->prepare($sql);
        $upd->execute([
            ':uo'  => (int)$d['usuario_origen'],
            ':ud'  => (int)$d['usuario_destino'],
            ':fs'  => $d['fecha_solicitud'],
            ':ft'  => $d['fecha_termino'],
            ':va'  => (float)$d['valor_asignados'],
            ':ta'  => $d['text_asignados'],
            ':tt'  => $d['text_tarea'],
            ':tr'  => $d['text_respuesta'],
            ':fp'  => $d['fecha_propuesta'],
            ':treq'=> $d['text_requeridos'],
            ':vr'  => (float)$d['valor_requeridos'],
            ':est' => $d['estado_gestion'],
            ':der' => $d['deriva_gestion'],
            ':id'  => $id,
        ]);
    }

    public static function eliminar(int $id, int $byUserId, bool $isAdm = false): void {
        $pdo = self::pdo();
        if (!$isAdm) {
            $st = $pdo->prepare("SELECT usuario_origen FROM gestiones WHERE id=:id");
            $st->execute([':id'=>$id]);
            $u = (int)($st->fetchColumn() ?: 0);
            if ($u !== $byUserId) throw new \Exception('Solo el creador puede eliminar.');
        }
        $del = $pdo->prepare("DELETE FROM gestiones WHERE id=:id");
        $del->execute([':id'=>$id]);
    }

    /* ===== Listados (Pedidos y Solicitudes) ===== */
    public static function buscarPedidos(int $userId, ?string $estado = null, array $filters = [], int $limit = 200): array {
        $pdo = self::pdo();
        $w   = ["g.usuario_destino = :uid"];
        $p   = [':uid'=>$userId];
        if ($estado !== null && $estado !== '') { $w[]="g.estado_gestion = :est"; $p[':est']=$estado; }
        if (!empty($filters['q'])) { $w[]="(g.text_tarea LIKE :q OR g.text_respuesta LIKE :q)"; $p[':q'] = '%'.$filters['q'].'%'; }
        if (!empty($filters['desde'])) { $w[]="g.fecha_solicitud >= :d1"; $p[':d1']=$filters['desde']; }
        if (!empty($filters['hasta'])) { $w[]="g.fecha_solicitud <= :d2"; $p[':d2']=$filters['hasta']; }

        $sql = "SELECT g.*, uo.nombre AS origen_nombre, ud.nombre AS destino_nombre
                  FROM gestiones g
             LEFT JOIN usuarios uo ON uo.id = g.usuario_origen
             LEFT JOIN usuarios ud ON ud.id = g.usuario_destino
                 WHERE ".implode(' AND ',$w)."
              ORDER BY g.id DESC
                 LIMIT ".(int)$limit;
        $st = $pdo->prepare($sql); $st->execute($p);
        return $st->fetchAll() ?: [];
    }

    public static function buscarSolicitudes(int $userId, ?string $estado = null, array $filters = [], int $limit = 200): array {
        $pdo = self::pdo();
        $w   = ["g.usuario_origen = :uid"];
        $p   = [':uid'=>$userId];
        if ($estado !== null && $estado !== '') { $w[]="g.estado_gestion = :est"; $p[':est']=$estado; }
        if (!empty($filters['q'])) { $w[]="(g.text_tarea LIKE :q OR g.text_respuesta LIKE :q)"; $p[':q'] = '%'.$filters['q'].'%'; }
        if (!empty($filters['desde'])) { $w[]="g.fecha_solicitud >= :d1"; $p[':d1']=$filters['desde']; }
        if (!empty($filters['hasta'])) { $w[]="g.fecha_solicitud <= :d2"; $p[':d2']=$filters['hasta']; }

        $sql = "SELECT g.*, uo.nombre AS origen_nombre, ud.nombre AS destino_nombre
                  FROM gestiones g
             LEFT JOIN usuarios uo ON uo.id = g.usuario_origen
             LEFT JOIN usuarios ud ON ud.id = g.usuario_destino
                 WHERE ".implode(' AND ',$w)."
              ORDER BY g.id DESC
                 LIMIT ".(int)$limit;
        $st = $pdo->prepare($sql); $st->execute($p);
        return $st->fetchAll() ?: [];
    }
}
