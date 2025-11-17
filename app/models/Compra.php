<?php
class Compra
{
    private static function pdo(){
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) return $GLOBALS['pdo'];
        if (function_exists('db')) { $c = db(); if ($c instanceof \PDO) return $c; }
        if (isset($GLOBALS['cfg']['DB']['dsn'])) {
            $dbcfg   = $GLOBALS['cfg']['DB'];
            $dsn     = $dbcfg['dsn'];
            $user    = $dbcfg['user'] ?? '';
            $pass    = $dbcfg['pass'] ?? '';
            $options = $dbcfg['options'] ?? [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            $pdo = new \PDO($dsn, $user, $pass, $options);
        } else {
            throw new \Exception('Sin conexión PDO');
        }
        // asegurar collation
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }

    /* ===================== Listados base ===================== */
    public static function listarProveedores(){
        $pdo=self::pdo();
        return $pdo->query("SELECT id, nombre FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
    }
    public static function listarProyectos(){
        $pdo=self::pdo();
        return $pdo->query("SELECT id, nombre FROM proyectos WHERE 1 ORDER BY nombre")->fetchAll();
    }

    /** Lista ítems de costo de un proyecto (catálogo proyecto_costos) */
    public static function listarProyectoCostos(int $proyecto_id): array {
        $pdo=self::pdo();
        $cols = self::dbCols('proyecto_costos');
        $code = in_array('codigo',$cols,true) ? 'codigo'
              : (in_array('cod',$cols,true) ? 'cod'
              : (in_array('item_codigo',$cols,true) ? 'item_codigo'
              : (in_array('code',$cols,true) ? 'code' : 'codigo')));
        $name = in_array('costo_glosa',$cols,true) ? 'costo_glosa'
              : (in_array('glosa',$cols,true) ? 'glosa'
              : (in_array('descripcion',$cols,true) ? 'descripcion'
              : (in_array('nombre',$cols,true) ? 'nombre' : $code)));
        $sql = "SELECT id, {$code} AS codigo, {$name} AS glosa
                  FROM proyecto_costos
                 WHERE proyecto_id = :p
              ORDER BY {$code}, id";
        $st = $pdo->prepare($sql); $st->execute([':p'=>$proyecto_id]);
        return $st->fetchAll();
    }

    private static function dbCols(string $table): array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t'=>$table]);
        return array_map('strval',$st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /* ===================== Búsquedas ===================== */
    public static function buscar($filters=[],$limit=100){
        $pdo=self::pdo();
        $sql="SELECT c.*, p.nombre AS proveedor, pr.nombre AS proyecto
              FROM compras c
              JOIN proveedores p ON p.id=c.proveedor_id
              LEFT JOIN proyectos pr ON pr.id=c.proyecto_id
              WHERE 1=1";
        $params=[];
        if(!empty($filters['folio'])){$sql.=" AND c.folio LIKE :folio";$params[':folio']="%".$filters['folio']."%";}
        if(!empty($filters['proveedor_id'])){$sql.=" AND c.proveedor_id=:prov";$params[':prov']=(int)$filters['proveedor_id'];}
        if(!empty($filters['proyecto_id'])){$sql.=" AND c.proyecto_id=:proy";$params[':proy']=(int)$filters['proyecto_id'];}
        if(!empty($filters['tipo_doc'])){$sql.=" AND c.tipo_doc=:td";$params[':td']=$filters['tipo_doc'];}
        if(!empty($filters['estado'])){$sql.=" AND c.estado=:st";$params[':st']=$filters['estado'];}
        if(!empty($filters['desde'])){$sql.=" AND c.fecha_doc>=:d1";$params[':d1']=$filters['desde'];}
        if(!empty($filters['hasta'])){$sql.=" AND c.fecha_doc<=:d2";$params[':d2']=$filters['hasta'];}
        $sql.=" ORDER BY c.fecha_doc DESC, c.id DESC";
        if($limit>0){$sql.=" LIMIT ".(int)$limit;}
        $st=$pdo->prepare($sql); $st->execute($params);
        return $st->fetchAll();
    }

    public static function buscarPorId($id){
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT * FROM compras WHERE id=:id");
        $st->execute([':id'=>$id]);
        return $st->fetch();
    }
    public static function listarItems($compra_id){
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT * FROM compras_items WHERE compra_id=:id ORDER BY linea ASC, id ASC");
        $st->execute([':id'=>$compra_id]);
        return $st->fetchAll();
    }

    public static function buscarIdPorProvTipoFolio(int $provId, string $tipoDoc, string $folio): ?int {
        $pdo = self::pdo();
        $st = $pdo->prepare("SELECT id FROM compras WHERE proveedor_id=:prov AND tipo_doc=:td AND folio=:folio LIMIT 1");
        $st->execute([':prov'=>$provId, ':td'=>$tipoDoc, ':folio'=>$folio]);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }

    private static function existsById(string $table, int $id): bool {
        if ($id <= 0) return false;
        $pdo = self::pdo();
        $st = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = :id LIMIT 1");
        $st->execute([':id'=>$id]);
        return (bool)$st->fetchColumn();
    }

    private static function ensureFKsOrFail(array $c, array $items): void {
        if (empty($c['proveedor_id']) || !self::existsById('proveedores', (int)$c['proveedor_id'])) {
            throw new \Exception('Proveedor inválido.');
        }
        if (!empty($c['proyecto_id']) && !self::existsById('proyectos', (int)$c['proyecto_id'])) {
            throw new \Exception('Proyecto inválido.');
        }
        if (!empty($c['oc_id']) && !self::existsById('ordenes_compra', (int)$c['oc_id'])) {
            throw new \Exception('OC inválida.');
        }
    }

    /* ===================== Crear / Actualizar ===================== */
    public static function crear($c, $items){
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            self::ensureFKsOrFail($c, $items);

            // cabecera
            $st=$pdo->prepare("INSERT INTO compras
                (proveedor_id, proyecto_id, oc_id, tipo_doc, folio, fecha_doc, moneda, tipo_cambio,
                 estado, subtotal, descuento, impuesto, observaciones, usuario_id)
             VALUES
                (:prov,:proy,:oc,:td,:folio,:fdoc,:mon,:tc,'borrador',:subt,:desc,:imp,:obs,:uid)");
            $st->execute([
                ':prov'=>$c['proveedor_id'],
                ':proy'=>$c['proyecto_id'],
                ':oc'  =>$c['oc_id'],
                ':td'  =>$c['tipo_doc'],
                ':folio'=>$c['folio'],
                ':fdoc'=>$c['fecha_doc'],
                ':mon' =>$c['moneda'],
                ':tc'  =>$c['tipo_cambio'],
                ':subt'=>$c['subtotal'],
                ':desc'=>$c['descuento'],
                ':imp' =>$c['impuesto'],
                ':obs' =>$c['observaciones'],
                ':uid' =>$c['usuario_id'],
            ]);
            $compra_id = (int)$pdo->lastInsertId();

            // items
            $sti = $pdo->prepare("
                INSERT INTO compras_items
                  (compra_id, oc_item_id, linea, codigo, descripcion, unidad, tipo_costo, cantidad, precio_unitario,
                   imp_proyecto_id, imp_pcosto_id, fecha_servicio)
                VALUES
                  (:cid,:oc,:lin,:cod,:desc,:uni,:tcosto,:cant,:pu,:imp_proy,:imp_pc,:fserv)
            ");
            $linea = 1;
            foreach($items as $it){
                $sti->execute([
                    ':cid'    => $compra_id,
                    ':oc'     => $it['oc_item_id'] ?? null,
                    ':lin'    => $linea++,
                    ':cod'    => $it['codigo'],
                    ':desc'   => $it['descripcion'] ?? '',
                    ':uni'    => $it['unidad'] ?? 'UND',
                    ':tcosto' => $it['tipo_costo'] ?? 'MAT',
                    ':cant'   => $it['cantidad'],
                    ':pu'     => $it['precio_unitario'],
                    ':fserv'  => ($it['fecha_servicio'] ?? null) ?: null,
                    ':imp_proy'=>($it['imp_proyecto_id'] ?? null),
                    ':imp_pc'  =>($it['imp_pcosto_id']   ?? null),
                ]);
            }

            // subtotal desde items
            $pdo->prepare(
                "UPDATE compras c
                   LEFT JOIN (
                     SELECT compra_id, SUM(cantidad*precio_unitario) sm
                     FROM compras_items
                     WHERE compra_id = :cid
                   ) x ON x.compra_id = c.id
                 SET c.subtotal = IFNULL(x.sm,0)
                 WHERE c.id = :cid"
            )->execute([':cid'=>$compra_id]);

            // imputaciones iniciales
            self::syncImputacionesDesdeItems($compra_id, $c);

            $pdo->commit();
            return $compra_id;
        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function actualizar($id, $c, $items){
        $id  = (int)$id;
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            self::ensureFKsOrFail($c, $items);

            // actualizar cabecera (solo borrador)
            $pdo->prepare(
                "UPDATE compras SET
                    proveedor_id=:prov, proyecto_id=:proy, oc_id=:oc, tipo_doc=:td,
                    folio=:folio, fecha_doc=:fdoc, moneda=:moneda, tipo_cambio=:tc,
                    subtotal=:subt, descuento=:desc, impuesto=:imp, observaciones=:obs
                 WHERE id=:id AND estado='borrador' LIMIT 1"
            )->execute([
                ':prov'=>$c['proveedor_id'], ':proy'=>$c['proyecto_id'], ':oc'=>$c['oc_id'], ':td'=>$c['tipo_doc'],
                ':folio'=>$c['folio'], ':fdoc'=>$c['fecha_doc'], ':moneda'=>$c['moneda'], ':tc'=>$c['tipo_cambio'],
                ':subt'=>$c['subtotal'], ':desc'=>$c['descuento'], ':imp'=>$c['impuesto'], ':obs'=>$c['observaciones'],
                ':id'=>$id
            ]);

            // borrar items actuales e insertar nuevos
            $pdo->prepare("DELETE FROM compras_items WHERE compra_id=:cid")->execute([':cid'=>$id]);

            $sti = $pdo->prepare("
                INSERT INTO compras_items
                  (compra_id, oc_item_id, linea, codigo, descripcion, unidad, tipo_costo, cantidad, precio_unitario,
                   imp_proyecto_id, imp_pcosto_id, fecha_servicio)
                VALUES
                  (:cid,:oc,:lin,:cod,:desc,:uni,:tcosto,:cant,:pu,:imp_proy,:imp_pc,:fserv)
            ");
            $linea = 1;
            foreach($items as $it){
                $sti->execute([
                    ':cid'    => $id,
                    ':oc'     => $it['oc_item_id'] ?? null,
                    ':lin'    => $linea++,
                    ':cod'    => $it['codigo'],
                    ':desc'   => $it['descripcion'] ?? '',
                    ':uni'    => $it['unidad'] ?? 'UND',
                    ':tcosto' => $it['tipo_costo'] ?? 'MAT',
                    ':cant'   => $it['cantidad'],
                    ':pu'     => $it['precio_unitario'],
                    ':fserv'  => ($it['fecha_servicio'] ?? null) ?: null,
                    ':imp_proy'=>($it['imp_proyecto_id'] ?? null),
                    ':imp_pc'  =>($it['imp_pcosto_id']   ?? null),
                ]);
            }

            // recalcula subtotal
            $pdo->prepare(
                "UPDATE compras c
                   LEFT JOIN (
                     SELECT compra_id, SUM(cantidad*precio_unitario) sm
                     FROM compras_items
                     WHERE compra_id = :cid
                   ) x ON x.compra_id = c.id
                 SET c.subtotal = IFNULL(x.sm,0)
                 WHERE c.id = :cid"
            )->execute([':cid'=>$id]);

            // sincronizar imputaciones con nuevos items
            self::syncImputacionesDesdeItems((int)$id, $c);

            $pdo->commit();
        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function cambiarEstado($id, $estado){
        $pdo=self::pdo();
        $st=$pdo->prepare("UPDATE compras SET estado=:e WHERE id=:id");
        $st->execute([':e'=>$estado, ':id'=>(int)$id]);
    }

    public static function getCodigoDePcosto(int $pcostoId): ?string {
        $pdo = self::pdo();
        $st  = $pdo->prepare("SELECT codigo FROM proyecto_costos WHERE id = :id");
        $st->execute([':id'=>$pcostoId]);
        $c = $st->fetchColumn();
        return $c ? (string)$c : null;
    }

    private static function itemsDbList(int $compra_id): array {
        $pdo = self::pdo();
        $st  = $pdo->prepare("
            SELECT id, compra_id, linea, codigo, descripcion, unidad, tipo_costo, cantidad, precio_unitario,
                   imp_proyecto_id, imp_pcosto_id
              FROM compras_items
             WHERE compra_id = :cid
             ORDER BY linea ASC, id ASC
        ");
        $st->execute([':cid'=>$compra_id]);
        return $st->fetchAll();
    }

    /**
     * Sincroniza compras_imputaciones desde compras_items:
     *  - Borra imputaciones actuales de los ítems de la compra
     *  - Inserta 1 imputación por ítem con los destinos guardados en compras_items
     */
    private static function syncImputacionesDesdeItems(int $compra_id, array $c): void {
        $pdo = self::pdo();

        // borrar imputaciones actuales de los ítems de esta compra
        $pdo->prepare("
            DELETE imp
              FROM compras_imputaciones imp
              JOIN compras_items it ON it.id = imp.compra_item_id
             WHERE it.compra_id = :cid
        ")->execute([':cid'=>$compra_id]);

        // releer items (incluye imp_proyecto_id / imp_pcosto_id)
        $rows = self::itemsDbList($compra_id);
        if (!$rows) return;

        $ins = $pdo->prepare("
            INSERT INTO compras_imputaciones
               (compra_item_id, proyecto_id, proyecto_costo_id, codigo,
                cantidad_imputada, monto_imputado, monto_base,
                porcentaje_imputado, fecha_imputacion, origen, usuario_id)
            VALUES
               (:ci, :p, :pc, :cod, :cant, :mi, :mb, :porc, CURDATE(), 'manual', :u)
        ");

        $usuario_id = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        $tipo_cambio= (float)($c['tipo_cambio'] ?? 1);

        foreach ($rows as $row) {
            $cant  = (float)$row['cantidad'];
            $pu    = (float)$row['precio_unitario'];
            $monto = round($cant * $pu, 2);
            $mbase = round($monto * $tipo_cambio, 2);
            $porc  = ($monto > 0) ? 1.0 : null;

            $proyecto_id = $row['imp_proyecto_id'] ?: ($c['proyecto_id'] ?? null);
            $pcosto_id   = $row['imp_pcosto_id']   ?: null;
            $codigo      = $pcosto_id ? self::getCodigoDePcosto((int)$pcosto_id) : ($row['codigo'] ?? null);

            $ins->execute([
                ':ci'   => (int)$row['id'],
                ':p'    => $proyecto_id ?: null,
                ':pc'   => $pcosto_id   ?: null,
                ':cod'  => $codigo,
                ':cant' => $cant,
                ':mi'   => $monto,
                ':mb'   => $mbase,
                ':porc' => $porc,
                ':u'    => $usuario_id,
            ]);
        }
    }

    public static function updateCompraItemImputacion(int $itemId, ?int $proyectoId, ?int $pcostoId): void {
        $pdo = self::pdo();
        $st = $pdo->prepare("
            UPDATE compras_items
               SET imp_proyecto_id = :p,
                   imp_pcosto_id   = :pc
             WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            ':p'  => $proyectoId ?: null,
            ':pc' => $pcostoId   ?: null,
            ':id' => $itemId
        ]);
    }

    /** Devuelve true si una columna es GENERATED (no editable) */
    private static function isGeneratedColumn(string $table, string $col): bool {
        $pdo = self::pdo();
        $st = $pdo->prepare("
            SELECT EXTRA, GENERATION_EXPRESSION
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND COLUMN_NAME = :c
             LIMIT 1
        ");
        $st->execute([':t'=>$table, ':c'=>$col]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$r) return false;
        $extra = strtolower((string)($r['EXTRA'] ?? ''));
        $gen   = $r['GENERATION_EXPRESSION'] ?? null;
        return (strpos($extra,'generated') !== false) || ($gen !== null && $gen !== '');
    }

    /** Reversión de una imputación APLICADA: resta cantidad/subtotal en proyecto_costos y marca revertida */
    private static function revertirImputacionAplicada(array $imp, ?int $uid, string $motivo=''): void {
        $pdo     = self::pdo();
        $impId   = (int)$imp['id'];
        $pcosto  = (int)$imp['proyecto_costo_id'];
        $proyId  = (int)$imp['proyecto_id'];
        $qty     = (float)($imp['cantidad_imputada'] ?? 0);
        $montoB  = (float)($imp['monto_base'] ?? $imp['monto_imputado'] ?? 0);

        if ($pcosto > 0 && $proyId > 0) {
            // Bloquear fila del item de costo
            $st = $pdo->prepare("SELECT cantidad_real, precio_unitario_real, subtotal_real FROM proyecto_costos WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$pcosto]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $cols  = self::dbCols('proyecto_costos');
                $hasQ  = in_array('cantidad_real',$cols,true);
                $hasPU = in_array('precio_unitario_real',$cols,true);
                $hasS  = in_array('subtotal_real',$cols,true);

                $qPrev   = $hasQ  ? (float)$row['cantidad_real'] : 0;
                $puPrev  = $hasPU ? (float)$row['precio_unitario_real'] : 0;
                $subPrev = $hasS  ? (float)$row['subtotal_real'] : ($qPrev * $puPrev);

                $qNew   = $hasQ  ? $qPrev - $qty : $qPrev;
                $subNew = $subPrev - $montoB;
                if ($qNew < -0.00001) $qNew = 0;
                if ($subNew < -0.01)  $subNew = 0;

                $puNew  = ($qNew > 0) ? ($subNew / $qNew) : 0.00;

                if ($hasS) {
                    $pdo->prepare("
                        UPDATE proyecto_costos
                           SET ".($hasQ ? "cantidad_real = :q, " : "")."
                               subtotal_real = :s
                               ".($hasPU ? ", precio_unitario_real = :pu" : "")."
                         WHERE id = :id
                         LIMIT 1
                    ")->execute([':q'=>$qNew, ':s'=>$subNew, ':pu'=>$puNew, ':id'=>$pcosto]);
                } else {
                    $set = [];
                    $params = [':id'=>$pcosto];
                    if ($hasQ)  { $set[] = "cantidad_real = :q"; $params[':q'] = $qNew; }
                    if ($hasPU){ $set[] = "precio_unitario_real = :pu"; $params[':pu'] = $puNew; }
                    if ($set) $pdo->prepare("UPDATE proyecto_costos SET ".implode(',', $set)." WHERE id = :id LIMIT 1")->execute($params);
                }
            }
        }

        // Marcar imputación como revertida
        $pdo->prepare("
            UPDATE compras_imputaciones
               SET estado_proceso='revertida',
                   revertido_at   = NOW(),
                   revertido_por  = :u,
                   revert_motivo  = :m
             WHERE id = :id
               AND estado_proceso='aplicada'
        ")->execute([':u'=>$uid, ':m'=>$motivo, ':id'=>$impId]);
    }

    /** Elimina un ítem de compra revirtiendo imputaciones aplicadas y recalculando subtotal */
    public static function eliminarItem(int $compra_id, int $item_id, ?int $uid = null, string $motivo = ''): void {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            // Validar que el item pertenezca a la compra
            $st = $pdo->prepare("SELECT * FROM compras_items WHERE id = :it AND compra_id = :cid FOR UPDATE");
            $st->execute([':it'=>$item_id, ':cid'=>$compra_id]);
            $item = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$item) throw new \Exception('Ítem no encontrado en la compra.');

            // Traer imputaciones del ítem
            $rows = $pdo->prepare("SELECT * FROM compras_imputaciones WHERE compra_item_id = :it FOR UPDATE");
            $rows->execute([':it'=>$item_id]);
            $imps = $rows->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Revertir las aplicadas
            foreach ($imps as $imp) {
                if (($imp['estado_proceso'] ?? 'pendiente') === 'aplicada') {
                    self::revertirImputacionAplicada($imp, $uid, $motivo ?: 'Eliminación de ítem de compra');
                }
            }

            // Eliminar el ítem (imputaciones pendientes se van por cascada si existe FK; de lo contrario borramos explícito)
            $pdo->prepare("DELETE FROM compras_imputaciones WHERE compra_item_id = :it")->execute([':it'=>$item_id]);
            $pdo->prepare("DELETE FROM compras_items WHERE id = :it")->execute([':it'=>$item_id]);

            // Recalcular subtotal
            $pdo->prepare(
                "UPDATE compras c
                   LEFT JOIN (
                     SELECT compra_id, SUM(cantidad*precio_unitario) sm
                       FROM compras_items
                      WHERE compra_id = :cid
                   ) x ON x.compra_id = c.id
                 SET c.subtotal = IFNULL(x.sm,0)
                 WHERE c.id = :cid"
            )->execute([':cid'=>$compra_id]);

            $pdo->commit();
        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Elimina una compra completa revirtiendo previamente TODAS las imputaciones aplicadas */
    public static function eliminar(int $compra_id, ?int $uid = null, string $motivo = ''): void {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            // Revertir imputaciones aplicadas por ítem de la compra (lock)
            $rows = $pdo->prepare("
                SELECT imp.*
                  FROM compras_imputaciones imp
                  JOIN compras_items it ON it.id = imp.compra_item_id
                 WHERE it.compra_id = :cid
                 FOR UPDATE
            ");
            $rows->execute([':cid'=>$compra_id]);
            $imps = $rows->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($imps as $imp) {
                if (($imp['estado_proceso'] ?? 'pendiente') === 'aplicada') {
                    self::revertirImputacionAplicada($imp, $uid, $motivo ?: 'Eliminación de compra');
                }
            }

            // Eliminar imputaciones, ítems y cabecera
            $pdo->prepare("
                DELETE imp FROM compras_imputaciones imp
                JOIN compras_items it ON it.id = imp.compra_item_id
                WHERE it.compra_id = :cid
            ")->execute([':cid'=>$compra_id]);

            $pdo->prepare("DELETE FROM compras_items WHERE compra_id = :cid")->execute([':cid'=>$compra_id]);
            $pdo->prepare("DELETE FROM compras WHERE id = :cid")->execute([':cid'=>$compra_id]);

            $pdo->commit();
        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
