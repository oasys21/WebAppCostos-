<?php
declare(strict_types=1);

class Ocompras
{
    /* =============== Infra =============== */
    private static function pdo(): \PDO {
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
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            return $pdo;
        }
        throw new \Exception('No hay PDO');
    }

    private static function dbCols(string $table): array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t'=>$table]);
        return array_map('strval',$st->fetchAll(\PDO::FETCH_COLUMN));
    }
    private static function hasCol(string $table, string $col): bool {
        static $cache = [];
        $k = $table.'|'.strtolower($col);
        if (!array_key_exists($k,$cache)) {
            $cols = array_map('strtolower', self::dbCols($table));
            $cache[$k] = in_array(strtolower($col), $cols, true);
        }
        return $cache[$k];
    }
    private static function existsById(string $table, int $id): bool {
        if ($id <= 0) return false;
        $pdo = self::pdo();
        $st  = $pdo->prepare("SELECT 1 FROM {$table} WHERE id=:id LIMIT 1");
        $st->execute([':id'=>$id]);
        return (bool)$st->fetchColumn();
    }
    private static function ensureFKsOrFail(array $c): void {
        if (empty($c['proveedor_id']) || !self::existsById('proveedores', (int)$c['proveedor_id'])) {
            throw new \Exception('Proveedor inválido.');
        }
        if (!empty($c['proyecto_id']) && !self::existsById('proyectos', (int)$c['proyecto_id'])) {
            throw new \Exception('Proyecto inválido.');
        }
    }

    /* =============== Util =============== */
    private static function safeStr($v, int $max): string {
        $s = (string)($v ?? '');
        if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
        return substr($s, 0, $max);
    }

    /* =============== Catálogos =============== */
    public static function listarProveedores(): array {
        $pdo=self::pdo();
        return $pdo->query("SELECT id, nombre FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
    }
    public static function listarProyectos(): array {
        $pdo=self::pdo();
        return $pdo->query("SELECT id, nombre FROM proyectos ORDER BY nombre")->fetchAll();
    }
    public static function listarProyectoCostos(int $proyecto_id): array {
        $pdo=self::pdo();
        $cols = self::dbCols('proyecto_costos');
        $code = in_array('codigo',$cols,true) ? 'codigo' : (in_array('code',$cols,true) ? 'code' : 'id');
        $name = in_array('costo_glosa',$cols,true) ? 'costo_glosa'
               : (in_array('descripcion',$cols,true) ? 'descripcion'
               : (in_array('nombre',$cols,true) ? 'nombre' : $code));
        $sql = "SELECT id, {$code} AS codigo, {$name} AS nombre
                  FROM proyecto_costos
                 WHERE proyecto_id = :p
              ORDER BY {$code}, id";
        $st = $pdo->prepare($sql); $st->execute([':p'=>$proyecto_id]);
        return $st->fetchAll();
    }

    /* Helper: trae código de proyecto_costos por id */
    private static function codigoPcostoById(\PDO $pdo, int $pcostoId): ?string {
        if ($pcostoId <= 0) return null;
        $st = $pdo->prepare("SELECT codigo FROM proyecto_costos WHERE id=:id");
        $st->execute([':id'=>$pcostoId]);
        $c = $st->fetchColumn();
        return $c ? (string)$c : null;
    }

    /* PROVEEDOR completo para impresión */
    public static function proveedorById(int $id): array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT * FROM proveedores WHERE id=:id");
        $st->execute([':id'=>$id]);
        $p = $st->fetch() ?: [];
        // Normalizamos algunas claves comunes (sin asumir nombres exactos de tu schema)
        $out = $p;
        $out['_nombre']    = $p['nombre']        ?? ($p['razon_social'] ?? '');
        $out['_rut']       = $p['rut']           ?? ($p['ruc'] ?? ($p['tax_id'] ?? ''));
        $out['_direccion'] = $p['direccion']     ?? ($p['address'] ?? '');
        $out['_rubro']     = $p['rubro']         ?? ($p['giro'] ?? '');
        $out['_contacto']  = $p['contacto']      ?? ($p['contact_name'] ?? '');
        $out['_telefono']  = $p['telefono']      ?? ($p['fono'] ?? ($p['phone'] ?? ''));
        $out['_email']     = $p['email']         ?? ($p['correo'] ?? '');
        return $out;
    }

    /* =============== Consultas =============== */
    public static function buscar(array $filters=[], int $limit=200): array {
        $pdo=self::pdo();
        $sql="SELECT oc.*, p.nombre AS proveedor, pr.nombre AS proyecto
                FROM ordenes_compra oc
                JOIN proveedores p ON p.id=oc.proveedor_id
                LEFT JOIN proyectos pr ON pr.id=oc.proyecto_id
               WHERE 1=1";
        $params=[];
        if(!empty($filters['oc_num']))       { $sql.=" AND oc.oc_num LIKE :oc_num";         $params[':oc_num']="%".$filters['oc_num']."%"; }
        if(!empty($filters['proveedor_id'])) { $sql.=" AND oc.proveedor_id=:prov";          $params[':prov']=(int)$filters['proveedor_id']; }
        if(!empty($filters['proyecto_id']))  { $sql.=" AND oc.proyecto_id=:proy";           $params[':proy']=(int)$filters['proyecto_id']; }
        if(!empty($filters['estado']))       { $sql.=" AND oc.estado=:st";                  $params[':st']=$filters['estado']; }
        if(!empty($filters['desde']))        { $sql.=" AND oc.fecha>=:d1";                  $params[':d1']=$filters['desde']; }
        if(!empty($filters['hasta']))        { $sql.=" AND oc.fecha<=:d2";                  $params[':d2']=$filters['hasta']; }
        $sql.=" ORDER BY oc.fecha DESC, oc.id DESC";
        if($limit>0){ $sql.=" LIMIT ".(int)$limit; }
        $st=$pdo->prepare($sql); $st->execute($params);
        return $st->fetchAll();
    }

    public static function buscarPorId(int $id): ?array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT * FROM ordenes_compra WHERE id=:id");
        $st->execute([':id'=>$id]);
        $r=$st->fetch();
        return $r ?: null;
    }

    public static function listarItems(int $oc_id): array {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT * FROM ordenes_compra_items WHERE oc_id=:id ORDER BY linea, id");
        $st->execute([':id'=>$oc_id]);
        return $st->fetchAll();
    }

    public static function buscarIdPorOcNum(string $oc_num): ?int {
        $pdo=self::pdo();
        $st=$pdo->prepare("SELECT id FROM ordenes_compra WHERE oc_num=:n LIMIT 1");
        $st->execute([':n'=>$oc_num]);
        $id=$st->fetchColumn();
        return $id ? (int)$id : null;
    }

    /* =============== Crear / Actualizar =============== */

    public static function crear(array $c, array $items): int {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            self::ensureFKsOrFail($c);

            // Cabecera
            $sql = "INSERT INTO ordenes_compra
                        (oc_num, proyecto_id, proveedor_id, fecha, moneda, tipo_cambio,
                         estado, condiciones_pago, observaciones, subtotal, descuento, impuesto, usuario_id)
                    VALUES
                        (:oc, :proy, :prov, :fecha, :mon, :tc,
                         :estado, :condp, :obs, :subt, :desc, :imp, :usr)";
            $pdo->prepare($sql)->execute([
                ':oc'    => self::safeStr($c['oc_num'], 30),
                ':proy'  => $c['proyecto_id'],
                ':prov'  => $c['proveedor_id'],
                ':fecha' => $c['fecha'],
                ':mon'   => self::safeStr($c['moneda'], 8),
                ':tc'    => $c['tipo_cambio'],
                ':estado'=> $c['estado'] ?? 'borrador',
                ':condp' => self::safeStr($c['condiciones_pago'] ?? '', 120),
                ':obs'   => (string)($c['observaciones'] ?? ''),
                ':subt'  => (float)($c['subtotal'] ?? 0),
                ':desc'  => (float)($c['descuento'] ?? 0),
                ':imp'   => (float)($c['impuesto'] ?? 0),
                ':usr'   => (int)($c['usuario_id'] ?? 0),
            ]);
            $oc_id = (int)$pdo->lastInsertId();

            // Ítems
            if (!empty($items)) {
                $cols = ['oc_id','linea','codigo','descripcion','unidad','tipo_costo','cantidad','precio_unitario','fecha_requerida'];
                $hasImpProy   = self::hasCol('ordenes_compra_items','imp_proyecto_id');
                $hasImpPcosto = self::hasCol('ordenes_compra_items','imp_pcosto_id');
                if ($hasImpProy)   $cols[] = 'imp_proyecto_id';
                if ($hasImpPcosto) $cols[] = 'imp_pcosto_id';

                $fields = implode(',', $cols);
                $params = implode(',', array_map(fn($cname)=>':'.$cname, $cols));
                $sti = $pdo->prepare("INSERT INTO ordenes_compra_items ({$fields}) VALUES ({$params})");

                $linea=1;
                foreach($items as $it){
                    // Determinar código final
                    $codigo = (string)($it['codigo'] ?? '');
                    $pcId   = isset($it['imp_pcosto_id']) ? (int)$it['imp_pcosto_id'] : 0;
                    if ($pcId > 0) {
                        $pcCodigo = self::codigoPcostoById($pdo, $pcId);
                        if ($pcCodigo) $codigo = $pcCodigo;
                    }
                    $codigo = self::safeStr($codigo, 10);

                    $row = [
                        ':oc_id'          => $oc_id,
                        ':linea'          => $linea++,
                        ':codigo'         => $codigo,
                        ':descripcion'    => self::safeStr(($it['descripcion'] ?? ''), 255),
                        ':unidad'         => self::safeStr(($it['unidad'] ?? 'UND'), 10),
                        ':tipo_costo'     => in_array(($it['tipo_costo'] ?? 'MAT'), ['MAT','MO','EQ','SUBC'], true) ? $it['tipo_costo'] : 'MAT',
                        ':cantidad'       => (float)($it['cantidad'] ?? 0),
                        ':precio_unitario'=> (float)($it['precio_unitario'] ?? 0),
                        ':fecha_requerida'=> !empty($it['fecha_requerida']) ? (string)$it['fecha_requerida'] : null,
                    ];
                    if ($hasImpProy)   $row[':imp_proyecto_id'] = !empty($it['imp_proyecto_id']) ? (int)$it['imp_proyecto_id'] : null;
                    if ($hasImpPcosto) $row[':imp_pcosto_id']   = $pcId > 0 ? $pcId : null;

                    $exec=[]; foreach($cols as $cname){ $exec[':'.$cname] = $row[':'.$cname]; }
                    $sti->execute($exec);
                }
            }

            self::recalcSubtotal($oc_id);
            $pdo->commit();
            return $oc_id;

        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function actualizar(int $id, array $c, array $items): void {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try{
            self::ensureFKsOrFail($c);

            $pdo->prepare("UPDATE ordenes_compra SET
                oc_num=:oc, proyecto_id=:proy, proveedor_id=:prov, fecha=:fecha, moneda=:mon, tipo_cambio=:tc,
                condiciones_pago=:condp, observaciones=:obs, subtotal=:subt, descuento=:desc, impuesto=:imp
                WHERE id=:id AND estado='borrador' LIMIT 1")
                ->execute([
                    ':oc'    => self::safeStr($c['oc_num'], 30),
                    ':proy'  => $c['proyecto_id'],
                    ':prov'  => $c['proveedor_id'],
                    ':fecha' => $c['fecha'],
                    ':mon'   => self::safeStr($c['moneda'], 8),
                    ':tc'    => $c['tipo_cambio'],
                    ':condp' => self::safeStr(($c['condiciones_pago'] ?? ''), 120),
                    ':obs'   => (string)($c['observaciones'] ?? ''),
                    ':subt'  => (float)($c['subtotal'] ?? 0),
                    ':desc'  => (float)($c['descuento'] ?? 0),
                    ':imp'   => (float)($c['impuesto'] ?? 0),
                    ':id'    => $id
                ]);

            $pdo->prepare("DELETE FROM ordenes_compra_items WHERE oc_id=:id")->execute([':id'=>$id]);

            if (!empty($items)) {
                $cols = ['oc_id','linea','codigo','descripcion','unidad','tipo_costo','cantidad','precio_unitario','fecha_requerida'];
                $hasImpProy   = self::hasCol('ordenes_compra_items','imp_proyecto_id');
                $hasImpPcosto = self::hasCol('ordenes_compra_items','imp_pcosto_id');
                if ($hasImpProy)   $cols[] = 'imp_proyecto_id';
                if ($hasImpPcosto) $cols[] = 'imp_pcosto_id';

                $fields = implode(',', $cols);
                $params = implode(',', array_map(fn($cname)=>':'.$cname, $cols));
                $sti = $pdo->prepare("INSERT INTO ordenes_compra_items ({$fields}) VALUES ({$params})");

                $linea=1;
                foreach($items as $it){
                    $codigo = (string)($it['codigo'] ?? '');
                    $pcId   = isset($it['imp_pcosto_id']) ? (int)$it['imp_pcosto_id'] : 0;
                    if ($pcId > 0) {
                        $pcCodigo = self::codigoPcostoById($pdo, $pcId);
                        if ($pcCodigo) $codigo = $pcCodigo;
                    }
                    $codigo = self::safeStr($codigo, 10);

                    $row = [
                        ':oc_id'          => $id,
                        ':linea'          => $linea++,
                        ':codigo'         => $codigo,
                        ':descripcion'    => self::safeStr(($it['descripcion'] ?? ''), 255),
                        ':unidad'         => self::safeStr(($it['unidad'] ?? 'UND'), 10),
                        ':tipo_costo'     => in_array(($it['tipo_costo'] ?? 'MAT'), ['MAT','MO','EQ','SUBC'], true) ? $it['tipo_costo'] : 'MAT',
                        ':cantidad'       => (float)($it['cantidad'] ?? 0),
                        ':precio_unitario'=> (float)($it['precio_unitario'] ?? 0),
                        ':fecha_requerida'=> !empty($it['fecha_requerida']) ? (string)$it['fecha_requerida'] : null,
                    ];
                    if ($hasImpProy)   $row[':imp_proyecto_id'] = !empty($it['imp_proyecto_id']) ? (int)$it['imp_proyecto_id'] : null;
                    if ($hasImpPcosto) $row[':imp_pcosto_id']   = $pcId > 0 ? $pcId : null;

                    $exec=[]; foreach($cols as $cname){ $exec[':'.$cname] = $row[':'.$cname]; }
                    $sti->execute($exec);
                }
            }

            self::recalcSubtotal($id);
            $pdo->commit();

        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function cambiarEstado(int $id, string $estado): void {
        $pdo=self::pdo();
        $pdo->prepare("UPDATE ordenes_compra SET estado=:e WHERE id=:id")->execute([':e'=>$estado, ':id'=>$id]);
    }

    /* =============== Eliminar =============== */
    public static function eliminarItem(int $oc_id, int $item_id): void {
        $pdo=self::pdo();
        $pdo->beginTransaction();
        try{
            $pdo->prepare("DELETE FROM ordenes_compra_items WHERE id=:it AND oc_id=:oc")->execute([':it'=>$item_id, ':oc'=>$oc_id]);
            self::recalcSubtotal($oc_id);
            $pdo->commit();
        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function eliminar(int $id): void {
        $pdo=self::pdo();
        $pdo->beginTransaction();
        try{
            $st = $pdo->prepare("SELECT estado FROM ordenes_compra WHERE id=:id");
            $st->execute([':id'=>$id]);
            $est = (string)($st->fetchColumn() ?: '');
            if ($est !== 'borrador') throw new \Exception('Solo se puede eliminar una OC en estado BORRADOR.');

            $pdo->prepare("DELETE FROM ordenes_compra_items WHERE oc_id=:id")->execute([':id'=>$id]);
            $pdo->prepare("DELETE FROM ordenes_compra WHERE id=:id LIMIT 1")->execute([':id'=>$id]);

            $pdo->commit();
        }catch(\Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /* =============== Totales =============== */
    private static function recalcSubtotal(int $id): void {
        $pdo=self::pdo();
        $st = $pdo->prepare("SELECT IFNULL(SUM(cantidad * precio_unitario),0) AS s FROM ordenes_compra_items WHERE oc_id=:id");
        $st->execute([':id'=>$id]);
        $subt = (float)$st->fetchColumn();
        $pdo->prepare("UPDATE ordenes_compra SET subtotal=:s WHERE id=:id")->execute([':s'=>$subt, ':id'=>$id]);
        // total es STORED GENERATED en tu DDL.
    }
}
