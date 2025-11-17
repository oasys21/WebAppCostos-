<?php
declare(strict_types=1);

/**
 * app/models/Caja.php
 * Lógica de negocio y acceso a datos para Caja Chica.
 */

class Caja
{
    /** @var PDO */
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /* =========================
       Periodos (caja_chica)
       ========================= */

    public function getPeriodo(int $usuarioId, int $anio, int $mes): ?array
    {
        $sql = "SELECT * FROM caja_chica WHERE usuario_id = :uid AND anio = :y AND mes = :m LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $st->bindValue(':y',   $anio, PDO::PARAM_INT);
        $st->bindValue(':m',   $mes,  PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getUltimoPeriodo(int $usuarioId): ?array
    {
        $sql = "SELECT * FROM caja_chica WHERE usuario_id = :uid ORDER BY anio DESC, mes DESC LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    public function crearPeriodo(int $usuarioId, int $anio, int $mes, float $saldoInicial = 0.0, ?int $traspasoDesdeId = null): int
    {
        $sql = "INSERT INTO caja_chica (usuario_id, anio, mes, saldo_inicial, traspaso_desde_id, fecha_apertura)
                VALUES (:uid, :y, :m, :si, :from_id, NOW())";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':uid',     $usuarioId, PDO::PARAM_INT);
        $st->bindValue(':y',       $anio,      PDO::PARAM_INT);
        $st->bindValue(':m',       $mes,       PDO::PARAM_INT);
        $st->bindValue(':si',      $saldoInicial);
        $st->bindValue(':from_id', $traspasoDesdeId, $traspasoDesdeId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function cerrarPeriodoNoDef(int $cajaId): void
    {
        $sql = "UPDATE caja_chica SET fecha_cierre = IFNULL(fecha_cierre, NOW()) WHERE id = :id";
        $st  = $this->pdo->prepare($sql);
        $st->bindValue(':id', $cajaId, PDO::PARAM_INT);
        $st->execute();
    }

    public function setTraspasoVinculos(int $desdeId, int $haciaId): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("UPDATE caja_chica SET traspaso_hacia_id = :hacia WHERE id = :id");
            $st->execute([':hacia' => $haciaId, ':id' => $desdeId]);

            $st = $this->pdo->prepare("UPDATE caja_chica SET traspaso_desde_id = :desde WHERE id = :id");
            $st->execute([':desde' => $desdeId, ':id' => $haciaId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function marcarCierreDefinitivo(int $cajaId): void
    {
        $sql = "UPDATE caja_chica SET cerrado_definitivo = 1, cerrado_definitivo_at = NOW() WHERE id = :id";
        $st  = $this->pdo->prepare($sql);
        $st->bindValue(':id', $cajaId, PDO::PARAM_INT);
        $st->execute();
    }

    public function asegurarPeriodoAbierto(int $usuarioId, DateTimeImmutable $nowCl): array
    {
        $anio = (int)$nowCl->format('Y');
        $mes  = (int)$nowCl->format('n');

        $actual = $this->getPeriodo($usuarioId, $anio, $mes);
        if ($actual) return $actual;

        $ultimo = $this->getUltimoPeriodo($usuarioId);
        $saldoInicial = 0.0;
        $traspDesdeId = null;

        if ($ultimo) {
            $saldoFinal = (float)$this->calcSaldoFinal((int)$ultimo['id']);
            $saldoInicial = $saldoFinal;
            $traspDesdeId = (int)$ultimo['id'];
            $this->cerrarPeriodoNoDef($traspDesdeId);
        }

        $nuevoId = $this->crearPeriodo($usuarioId, $anio, $mes, $saldoInicial, $traspDesdeId);
        if ($traspDesdeId) {
            $this->setTraspasoVinculos($traspDesdeId, $nuevoId);
        }
        return $this->getById($nuevoId);
    }

    public function getById(int $cajaId): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM caja_chica WHERE id = :id");
        $st->bindValue(':id', $cajaId, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch();
        return $r ?: null;
    }

    public function calcSaldoFinal(int $cajaId): float
    {
        $st = $this->pdo->prepare("SELECT saldo_inicial, total_ingresos, total_egresos FROM caja_chica WHERE id = :id");
        $st->execute([':id' => $cajaId]);
        $r = $st->fetch();
        if (!$r) return 0.0;
        return (float)$r['saldo_inicial'] + (float)$r['total_ingresos'] - (float)$r['total_egresos'];
    }

    /* =========================
       Movimientos
       ========================= */

    public function listarMovimientos(int $cajaId): array
    {
        $sql = "SELECT m.*,
                       pc.codigo AS cod_imputacion,
                       COALESCE(pc.costo_glosa, '') AS glosa_imputacion
                  FROM caja_chica_movimientos m
             LEFT JOIN proyecto_costos pc ON pc.id = m.proyecto_costo_id
                 WHERE m.caja_id = :cid
              ORDER BY m.fecha_mov DESC, m.id DESC";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':cid', $cajaId, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** NUEVO: Listar con filtros para búsqueda e impresión */
    public function listarMovimientosFiltrado(int $cajaId, array $f = []): array
    {
        $where = ["m.caja_id = :cid"];
        $params = [':cid' => $cajaId];

        if (!empty($f['tipo']) && in_array($f['tipo'], ['INGRESO','EGRESO','TRASPASO_IN','TRASPASO_OUT','AJUSTE'], true)) {
            $where[] = "m.tipo = :tipo";
            $params[':tipo'] = $f['tipo'];
        }
        if (!empty($f['estado']) && in_array($f['estado'], ['PENDIENTE','APROBADO','ANULADO'], true)) {
            $where[] = "m.estado = :estado";
            $params[':estado'] = $f['estado'];
        }
        if (!empty($f['doc_tipo'])) {
            $where[] = "m.documento_tipo = :doc_tipo";
            $params[':doc_tipo'] = strtoupper((string)$f['doc_tipo']);
        }
        if (!empty($f['q'])) {
            $where[] = "(m.numero_doc LIKE :q OR m.descripcion LIKE :q OR m.documento_tipo LIKE :q OR pc.codigo LIKE :q OR pc.costo_glosa LIKE :q)";
            $params[':q'] = '%'.trim((string)$f['q']).'%';
        }

        $orden = (!empty($f['orden']) && strtoupper($f['orden']) === 'ASC') ? 'ASC' : 'DESC';

        $sql = "SELECT m.*,
                       pc.codigo AS cod_imputacion,
                       COALESCE(pc.costo_glosa, '') AS glosa_imputacion
                  FROM caja_chica_movimientos m
             LEFT JOIN proyecto_costos pc ON pc.id = m.proyecto_costo_id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY m.fecha_mov {$orden}, m.id {$orden}";
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k=>$v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();
        return $st->fetchAll();
    }

    public function obtenerMovimiento(int $id): ?array
    {
        $sql = "SELECT m.*,
                       pc.codigo AS cod_imputacion,
                       COALESCE(pc.costo_glosa, '') AS glosa_imputacion
                  FROM caja_chica_movimientos m
             LEFT JOIN proyecto_costos pc ON pc.id = m.proyecto_costo_id
                 WHERE m.id = :id LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch();
        return $r ?: null;
    }

    public function crearMovimiento(array $data): int
    {
        $sql = "INSERT INTO caja_chica_movimientos
                (caja_id, usuario_id, proyecto_costo_id, tipo, estado, fecha_mov, fecha_doc,
                 numero_doc, documento_tipo, monto, descripcion, medio_ingreso, banco, referencia_pago)
                VALUES
                (:caja_id, :usuario_id, :proyecto_costo_id, :tipo, :estado, :fecha_mov, :fecha_doc,
                 :numero_doc, :documento_tipo, :monto, :descripcion, :medio_ingreso, :banco, :referencia_pago)";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':caja_id',           (int)$data['caja_id'], PDO::PARAM_INT);
        $st->bindValue(':usuario_id',        (int)$data['usuario_id'], PDO::PARAM_INT);
        $st->bindValue(':proyecto_costo_id', $data['proyecto_costo_id'] ? (int)$data['proyecto_costo_id'] : null, $data['proyecto_costo_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':tipo',              (string)$data['tipo'], PDO::PARAM_STR);
        $st->bindValue(':estado',            (string)($data['estado'] ?? 'PENDIENTE'), PDO::PARAM_STR);
        $st->bindValue(':fecha_mov',         (string)($data['fecha_mov'] ?? date('Y-m-d H:i:s')), PDO::PARAM_STR);
        $st->bindValue(':fecha_doc',         $data['fecha_doc'] ?? null, $data['fecha_doc'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':numero_doc',        $data['numero_doc'] ?? null, $data['numero_doc'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':documento_tipo',    $data['documento_tipo'] ?? 'OTRO', PDO::PARAM_STR);
        $st->bindValue(':monto',             (string)$data['monto']);
        $st->bindValue(':descripcion',       $data['descripcion'] ?? null, $data['descripcion'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':medio_ingreso',     $data['medio_ingreso'] ?? null, $data['medio_ingreso'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':banco',             $data['banco'] ?? null, $data['banco'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':referencia_pago',   $data['referencia_pago'] ?? null, $data['referencia_pago'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function actualizarMovimiento(int $id, array $data): void
    {
        $sql = "UPDATE caja_chica_movimientos
                   SET proyecto_costo_id = :proyecto_costo_id,
                       tipo              = :tipo,
                       estado            = :estado,
                       fecha_mov         = :fecha_mov,
                       fecha_doc         = :fecha_doc,
                       numero_doc        = :numero_doc,
                       documento_tipo    = :documento_tipo,
                       monto             = :monto,
                       descripcion       = :descripcion,
                       medio_ingreso     = :medio_ingreso,
                       banco             = :banco,
                       referencia_pago   = :referencia_pago
                 WHERE id = :id";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':proyecto_costo_id', $data['proyecto_costo_id'] ? (int)$data['proyecto_costo_id'] : null, $data['proyecto_costo_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':tipo', (string)$data['tipo'], PDO::PARAM_STR);
        $st->bindValue(':estado', (string)($data['estado'] ?? 'PENDIENTE'), PDO::PARAM_STR);
        $st->bindValue(':fecha_mov', (string)($data['fecha_mov'] ?? date('Y-m-d H:i:s')), PDO::PARAM_STR);
        $st->bindValue(':fecha_doc', $data['fecha_doc'] ?? null, $data['fecha_doc'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':numero_doc', $data['numero_doc'] ?? null, $data['numero_doc'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':documento_tipo', $data['documento_tipo'] ?? 'OTRO', PDO::PARAM_STR);
        $st->bindValue(':monto', (string)$data['monto']);
        $st->bindValue(':descripcion', $data['descripcion'] ?? null, $data['descripcion'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':medio_ingreso', $data['medio_ingreso'] ?? null, $data['medio_ingreso'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':banco', $data['banco'] ?? null, $data['banco'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':referencia_pago', $data['referencia_pago'] ?? null, $data['referencia_pago'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
    }

    public function eliminarMovimiento(int $id): void
    {
        $st = $this->pdo->prepare("DELETE FROM caja_chica_movimientos WHERE id = :id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
    }

    /* =========================
       Adjuntos
       ========================= */

    public function listarAdjuntos(int $movId): array
    {
        $st = $this->pdo->prepare("SELECT * FROM caja_chica_adjuntos WHERE movimiento_id = :id ORDER BY creado_en DESC, id DESC");
        $st->bindValue(':id', $movId, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function insertarAdjunto(array $row): int
    {
        $sql = "INSERT INTO caja_chica_adjuntos
                (movimiento_id, nombre_archivo, ruta_relativa, mime_type, extension, tamano_bytes, hash_sha256, paginas)
                VALUES
                (:mov, :nombre, :ruta, :mime, :ext, :size, :hash, :pag)";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':mov', (int)$row['movimiento_id'], PDO::PARAM_INT);
        $st->bindValue(':nombre', (string)$row['nombre_archivo'], PDO::PARAM_STR);
        $st->bindValue(':ruta', (string)$row['ruta_relativa'], PDO::PARAM_STR);
        $st->bindValue(':mime', (string)$row['mime_type'], PDO::PARAM_STR);
        $st->bindValue(':ext', (string)$row['extension'], PDO::PARAM_STR);
        $st->bindValue(':size', $row['tamano_bytes'] !== null ? (int)$row['tamano_bytes'] : null, $row['tamano_bytes'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':hash', (string)$row['hash_sha256'], PDO::PARAM_STR);
        $st->bindValue(':pag', $row['paginas'] !== null ? (int)$row['paginas'] : null, $row['paginas'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    /* =========================
       Reglas de edición
       ========================= */

    public function periodoEditable(array $caja, DateTimeImmutable $nowCl): bool
    {
        if ((int)$caja['cerrado_definitivo'] === 1) return false;

        $anio = (int)$nowCl->format('Y');
        $mes  = (int)$nowCl->format('n');

        $esActual   = ((int)$caja['anio'] === $anio && (int)$caja['mes'] === $mes);
        $dtPrev     = (clone $nowCl)->modify('first day of last month');
        $esAnterior = ((int)$caja['anio'] === (int)$dtPrev->format('Y') && (int)$caja['mes'] === (int)$dtPrev->format('n'));

        $finMes = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', $caja['anio'].'-'.$caja['mes'].'-1 23:59:59')
                    ->modify('last day of this month')
                    ->setTimezone($nowCl->getTimezone());
        $limite = $finMes->modify('+30 days');

        return ($esActual || $esAnterior) && ($nowCl <= $limite);
    }
}
