<?php
// /app/models/ProyectoCostos.php
declare(strict_types=1);

final class ProyectoCostos
{
    private PDO $db;

    public function __construct(PDO $pdo) { $this->db = $pdo; }

    /* =============== CONSULTAS BASE =============== */

    /** Retorna todos los ítems (nivel 3) del proyecto con desc de catálogo */
    public function allItemsForProject(int $proyecto_id): array
    {
        $sql = "SELECT pc.id, pc.proyecto_id, pc.codigo, pc.familia, pc.grupo, pc.item,
                       pc.cantidad_presupuestada, pc.precio_unitario_presupuestado,
                       pc.cantidad_real, pc.precio_unitario_real,
                       pc.subtotal_pres, pc.subtotal_real,
                       c.descripcion, c.unidad
                FROM proyecto_costos pc
                LEFT JOIN costos_catalogo c ON c.codigo = pc.codigo
                WHERE pc.proyecto_id = :p
                ORDER BY pc.familia, pc.grupo, pc.item";
        $st = $this->db->prepare($sql);
        $st->execute([':p'=>$proyecto_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Agregados por grupo dentro de familia */
    public function listGrupos(int $proyecto_id, string $familia): array
    {
        $sql = "SELECT pc.familia, pc.grupo,
                       CONCAT(pc.familia, pc.grupo, '0000') AS codigo,
                       SUM(pc.subtotal_pres) AS subtotal_pres,
                       SUM(pc.subtotal_real) AS subtotal_real,
                       MAX(CASE WHEN pc.item='0000' THEN c.descripcion END) AS descripcion
                FROM proyecto_costos pc
                LEFT JOIN costos_catalogo c ON c.codigo = pc.codigo
                WHERE pc.proyecto_id = :p AND pc.familia = :f
                GROUP BY pc.familia, pc.grupo
                ORDER BY pc.grupo";
        $st = $this->db->prepare($sql);
        $st->execute([':p'=>$proyecto_id, ':f'=>$familia]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Ítems de un grupo específico (SIN incluir la cabecera ...0000) */
    public function listItems(int $proyecto_id, string $familia, string $grupo): array
    {
        $sql = "SELECT pc.id, pc.codigo, pc.familia, pc.grupo, pc.item,
                       pc.cantidad_presupuestada, pc.precio_unitario_presupuestado,
                       pc.subtotal_pres,
                       c.descripcion, c.unidad
                FROM proyecto_costos pc
                LEFT JOIN costos_catalogo c ON c.codigo = pc.codigo
                WHERE pc.proyecto_id=:p AND pc.familia=:f AND pc.grupo=:g
                  AND pc.item <> '0000'
                ORDER BY pc.item";
        $st = $this->db->prepare($sql);
        $st->execute([':p'=>$proyecto_id, ':f'=>$familia, ':g'=>$grupo]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT pc.*, c.descripcion, c.unidad FROM proyecto_costos pc
                                  LEFT JOIN costos_catalogo c ON c.codigo=pc.codigo
                                  WHERE pc.id=:id");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function updateItem(int $id, float $cant, float $precio, int $uid): bool
    {
        $st = $this->db->prepare("UPDATE proyecto_costos
                                  SET cantidad_presupuestada=:c, precio_unitario_presupuestado=:p, usuario_id=:u
                                  WHERE id=:id");
        return $st->execute([':c'=>$cant, ':p'=>$precio, ':u'=>$uid, ':id'=>$id]);
    }

    public function deleteById(int $id): bool
    {
        $st = $this->db->prepare("DELETE FROM proyecto_costos WHERE id=:id");
        return $st->execute([':id'=>$id]);
    }

    /** Elimina por alcance (familia/grupo/ítem según código) */
    public function deleteScope(int $proyecto_id, string $codigo): bool
    {
        $codigo = strtoupper(preg_replace('/[^0-9A-Z]/','', $codigo));
        if (strlen($codigo) !== 10) return false;
        $f = substr($codigo,0,3);
        $g = substr($codigo,3,3);
        $i = substr($codigo,6,4);

        if ($g === '000' && $i === '0000') {
            $st = $this->db->prepare("DELETE FROM proyecto_costos WHERE proyecto_id=:p AND familia=:f");
            return $st->execute([':p'=>$proyecto_id, ':f'=>$f]);
        } elseif ($i === '0000') {
            $st = $this->db->prepare("DELETE FROM proyecto_costos WHERE proyecto_id=:p AND familia=:f AND grupo=:g");
            return $st->execute([':p'=>$proyecto_id, ':f'=>$f, ':g'=>$g]);
        } else {
            $st = $this->db->prepare("DELETE FROM proyecto_costos WHERE proyecto_id=:p AND codigo=:c");
            return $st->execute([':p'=>$proyecto_id, ':c'=>$codigo]);
        }
    }

    /* =============== CLONADOR =============== */

    public function familiasCatalogo(): array
    {
        $sql = "SELECT SUBSTR(codigo,1,3) AS fam,
                       MAX(CASE WHEN SUBSTR(codigo,4,3)='000' AND SUBSTR(codigo,7,4)='0000' THEN descripcion END) AS descripcion
                FROM costos_catalogo
                GROUP BY fam
                ORDER BY fam";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function gruposCatalogo(): array
    {
        $sql = "SELECT SUBSTR(codigo,1,3) AS fam, SUBSTR(codigo,4,3) AS grp,
                       MAX(CASE WHEN SUBSTR(codigo,7,4)='0000' THEN descripcion END) AS descripcion
                FROM costos_catalogo
                GROUP BY fam, grp
                HAVING grp <> '000'
                ORDER BY fam, grp";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function ensureHeaderFamilia(int $proyecto_id, string $fam, int $uid): void
    {
        $codigo = strtoupper($fam).'0000000';
        $sql = "INSERT IGNORE INTO proyecto_costos (proyecto_id, familia, grupo, item, codigo, cantidad_presupuestada, precio_unitario_presupuestado, usuario_id, fecha_carga)
                VALUES (:p,:f,'000','0000',:c,0,0,:u, CURRENT_DATE())";
        $st = $this->db->prepare($sql);
        $st->execute([':p'=>$proyecto_id, ':f'=>$fam, ':c'=>$codigo, ':u'=>$uid]);
    }

    private function ensureHeaderGrupo(int $proyecto_id, string $fam, string $grp, int $uid): void
    {
        $codigo = strtoupper($fam).strtoupper($grp).'0000';
        $sql = "INSERT IGNORE INTO proyecto_costos (proyecto_id, familia, grupo, item, codigo, cantidad_presupuestada, precio_unitario_presupuestado, usuario_id, fecha_carga)
                VALUES (:p,:f,:g,'0000',:c,0,0,:u, CURRENT_DATE())";
        $st = $this->db->prepare($sql);
        $st->execute([':p'=>$proyecto_id, ':f'=>$fam, ':g'=>$grp, ':c'=>$codigo, ':u'=>$uid]);
    }

    private function latestPrice(string $codigo, string $campo='costo_venta'): ?float
    {
        $campo = ($campo === 'costo_directo') ? 'costo_directo' : 'costo_venta';
        $st = $this->db->prepare("
            SELECT $campo AS precio
            FROM costos_precios
            WHERE codigo=:c
            ORDER BY fecha_vigencia DESC, id DESC
            LIMIT 1
        ");
        $st->execute([':c'=>$codigo]);
        $p = $st->fetchColumn();
        return $p !== false ? (float)$p : null;
    }

    /** Clona familias completas (incluyendo grupos e ítems) */
    public function cloneFamilias(int $proyecto_id, array $familias, string $precioCampo, int $uid, bool $ignoreDuplicates=false): array
    {
        $familias = array_values(array_unique(array_map(fn($x)=>strtoupper(substr(preg_replace('/\D/','',(string)$x).'000',0,3)), $familias)));
        $ins=0; $skip=0; $dups=[];
        foreach ($familias as $f) {
            if (!$f || strlen($f) !== 3) continue;
            $this->ensureHeaderFamilia($proyecto_id, $f, $uid);

            $st = $this->db->prepare("SELECT codigo FROM costos_catalogo WHERE SUBSTR(codigo,1,3)=:f ORDER BY codigo");
            $st->execute([':f'=>$f]);
            $codes = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($codes as $c) {
                $fam = substr($c,0,3); $grp = substr($c,3,3); $it = substr($c,6,4);
                if ($it === '0000') {
                    if ($grp !== '000') { $this->ensureHeaderGrupo($proyecto_id, $fam, $grp, $uid); }
                    continue;
                }
                $ins += $this->insertItemIfNotExists($proyecto_id, $c, $precioCampo, $uid, $ignoreDuplicates, $skip, $dups);
            }
        }
        return ['inserted'=>$ins,'skipped'=>$skip,'duplicates'=>$dups];
    }

    /** Clona grupos (familia/grupo) con sus ítems; crea cabeceras si faltan */
    public function cloneGrupos(int $proyecto_id, array $grupos, string $precioCampo, int $uid, bool $ignoreDuplicates=false): array
    {
        $parsed=[];
        foreach ($grupos as $g) {
            $g = strtoupper(preg_replace('/[^0-9A-Z-]/','',(string)$g));
            if (preg_match('/^([0-9A-Z]{3})-([0-9A-Z]{3})$/', $g, $m)) {
                $parsed[] = [$m[1], $m[2]];
            }
        }
        $ins=0; $skip=0; $dups=[];
        foreach ($parsed as [$f,$g]) {
            $this->ensureHeaderFamilia($proyecto_id, $f, $uid);
            $this->ensureHeaderGrupo($proyecto_id, $f, $g, $uid);
            $st = $this->db->prepare("SELECT codigo FROM costos_catalogo WHERE SUBSTR(codigo,1,3)=:f AND SUBSTR(codigo,4,3)=:g ORDER BY codigo");
            $st->execute([':f'=>$f, ':g'=>$g]);
            $codes = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($codes as $c) {
                if (substr($c,6,4) === '0000') continue; // cabecera ya creada
                $ins += $this->insertItemIfNotExists($proyecto_id, $c, $precioCampo, $uid, $ignoreDuplicates, $skip, $dups);
            }
        }
        return ['inserted'=>$ins,'skipped'=>$skip,'duplicates'=>$dups];
    }

    /** Clona ítems individuales (asegura cabeceras) */
    public function cloneItems(int $proyecto_id, array $codes, string $precioCampo, int $uid, bool $ignoreDuplicates=false): array
    {
        $norm = [];
        foreach ($codes as $c) {
            $c = strtoupper(preg_replace('/[^0-9A-Z]/','',(string)$c));
            if (strlen($c)===10) $norm[$c]=true;
        }
        $ins=0; $skip=0; $dups=[];
        foreach (array_keys($norm) as $c) {
            $f = substr($c,0,3); $g = substr($c,3,3); $i = substr($c,6,4);
            if ($i === '0000') continue; // ignorar cabeceras
            $this->ensureHeaderFamilia($proyecto_id, $f, $uid);
            if ($g !== '000') $this->ensureHeaderGrupo($proyecto_id, $f, $g, $uid);
            $ins += $this->insertItemIfNotExists($proyecto_id, $c, $precioCampo, $uid, $ignoreDuplicates, $skip, $dups);
        }
        return ['inserted'=>$ins,'skipped'=>$skip,'duplicates'=>$dups];
    }

    private function insertItemIfNotExists(int $proyecto_id, string $codigo, string $precioCampo, int $uid, bool $ignoreDuplicates, int &$skip, array &$dups): int
    {
        $chk = $this->db->prepare("SELECT id FROM proyecto_costos WHERE proyecto_id=:p AND codigo=:c LIMIT 1");
        $chk->execute([':p'=>$proyecto_id, ':c'=>$codigo]);
        $ex = $chk->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $skip++; 
            if (!$ignoreDuplicates) $dups[] = $codigo;
            return 0;
        }
        $cant = 1.00;
        $precio = $this->latestPrice($codigo, $precioCampo) ?? 0.00;
        $st = $this->db->prepare("INSERT INTO proyecto_costos
             (proyecto_id, familia, grupo, item, codigo, cantidad_presupuestada, precio_unitario_presupuestado, usuario_id, fecha_carga)
             VALUES (:p, SUBSTR(:c,1,3), SUBSTR(:c,4,3), SUBSTR(:c,7,4), :c, :cant, :precio, :u, CURRENT_DATE())");
        $ok = $st->execute([':p'=>$proyecto_id, ':c'=>$codigo, ':cant'=>$cant, ':precio'=>$precio, ':u'=>$uid]);
        return $ok ? 1 : 0;
    }
}
