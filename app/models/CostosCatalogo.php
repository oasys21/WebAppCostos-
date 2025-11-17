<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class CostosCatalogo
{
    private PDO $pdo;

    /** Unidades permitidas (deben calzar con tus TRIGGERs MySQL) */
    public const UNIDADES = ['m','m2','m3','ud','ML','kg','kW','par','jornada','mes','día','semana','-'];
    /** Monedas permitidas para ítems */
    public const MONEDAS  = ['CLP','UF','USD','EUR'];

    public function __construct()
    {
        global $pdo;
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO no inicializado. Revise config/database.php');
        }
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /* ===================== SELECTORES HIERÁRQUICOS ===================== */

    /**
     * Familias con descripción y total de grupos (no ítems).
     * @return array<int, array{familia:string, descripcion:string, total:int}>
     */
    public function getFamilias(): array
    {
        $sql = "
            SELECT
                familia,
                COALESCE(
                  MAX(CASE WHEN grupo='000' AND item='0000' THEN descripcion END),''
                ) AS descripcion,
                COUNT(DISTINCT CASE WHEN item='0000' AND grupo<>'000' THEN grupo END) AS total
            FROM costos_catalogo
            WHERE familia <> '000'
            GROUP BY familia
            ORDER BY familia
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Grupos de una familia con descripción y total de ítems.
     * @return array<int, array{grupo:string, descripcion:string, total:int}>
     */
    public function getGrupos(string $familia): array
    {
        $familia = $this->pad3($familia);
        $st = $this->pdo->prepare("
            SELECT
                grupo,
                COALESCE(MAX(CASE WHEN item='0000' THEN descripcion END),'') AS descripcion,
                COUNT(CASE WHEN item<>'0000' THEN 1 END) AS total
            FROM costos_catalogo
            WHERE familia = :fam AND grupo <> '000'
            GROUP BY grupo
            ORDER BY grupo
        ");
        $st->execute([':fam' => $familia]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ítems de un grupo (incluye valor y moneda).
     * @return array<int, array{codigo:string, descripcion:string, unidad:string, valor:?string, moneda:?string}>
     */
    public function getItems(string $familia, string $grupo): array
    {
        $familia = $this->pad3($familia);
        $grupo   = $this->pad3($grupo);
        $st = $this->pdo->prepare("
            SELECT codigo, descripcion, unidad, valor, moneda
            FROM costos_catalogo
            WHERE familia = :fam AND grupo = :gru AND item <> '0000'
            ORDER BY item
        ");
        $st->execute([':fam' => $familia, ':gru' => $grupo]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ===================== OBTENER UNO ===================== */

    /**
     * @return array{
     *   codigo:string, descripcion:string, unidad:string,
     *   familia:string, grupo:string, item:string, tipo_nivel:int,
     *   valor:?string, moneda:?string
     * }|null
     */
    public function getByCodigo(string $codigo): ?array
    {
        $codigo = $this->codigo10($codigo);
        $st = $this->pdo->prepare("SELECT * FROM costos_catalogo WHERE codigo = :c");
        $st->execute([':c' => $codigo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ===================== CREATE ===================== */

    /**
     * Crea familia/grupo/ítem. En ítems acepta valor/moneda.
     */
    public function create(string $codigo, string $descripcion, string $unidad = '-', ?string $valor = null, ?string $moneda = null) : bool
    {
        $codigo      = $this->codigo10($codigo);
        $descripcion = $this->safeText($descripcion, 255);

        $fam = substr($codigo, 0, 3);
        $gru = substr($codigo, 3, 3);
        $itm = substr($codigo, 6, 4);

        $isItem = ($itm !== '0000');

        if ($isItem) {
            // Ítem: validar unidad libre, valor/moneda opcionales
            $unidad = $this->unidadValida($unidad);
            [$valor, $moneda] = $this->sanitizePrecio($valor, $moneda);
        } else {
            // Familia o grupo: fijar unidad en '-'; valor/moneda NULL
            $unidad = '-';
            $valor = null; $moneda = null;
        }

        $st = $this->pdo->prepare("
            INSERT INTO costos_catalogo (codigo, descripcion, unidad, valor, moneda)
            VALUES (:c, :d, :u, :v, :m)
        ");
        return $st->execute([
            ':c'=>$codigo, ':d'=>$descripcion, ':u'=>$unidad,
            ':v'=>$valor, ':m'=>$moneda
        ]);
    }

    /* ===================== UPDATE ===================== */

    /**
     * Actualiza descripción/unidad y, si es ítem, también valor/moneda.
     */
    public function update(string $codigo, string $descripcion, string $unidad, ?string $valor = null, ?string $moneda = null) : bool
    {
        $codigo      = $this->codigo10($codigo);
        $descripcion = $this->safeText($descripcion, 255);

        $itm = substr($codigo, 6, 4);
        $isItem = ($itm !== '0000');

        if ($isItem) {
            $unidad = $this->unidadValida($unidad);
            [$valor, $moneda] = $this->sanitizePrecio($valor, $moneda);

            $st = $this->pdo->prepare("
                UPDATE costos_catalogo
                   SET descripcion = :d,
                       unidad      = :u,
                       valor       = :v,
                       moneda      = :m
                 WHERE codigo = :c
            ");
            return $st->execute([
                ':d'=>$descripcion, ':u'=>$unidad, ':v'=>$valor, ':m'=>$moneda, ':c'=>$codigo
            ]);
        } else {
            // Familia/Grupo: no toques valor/moneda; fuerza unidad '-'
            $unidad = '-';
            $st = $this->pdo->prepare("
                UPDATE costos_catalogo
                   SET descripcion = :d,
                       unidad      = :u,
                       valor       = NULL,
                       moneda      = NULL
                 WHERE codigo = :c
            ");
            return $st->execute([':d'=>$descripcion, ':u'=>$unidad, ':c'=>$codigo]);
        }
    }

    /* ===================== DELETE ===================== */

    public function delete(string $codigo): bool
    {
        $codigo = $this->codigo10($codigo);
        $row = $this->getByCodigo($codigo);
        if (!$row) { return false; }

        // No borrar familia si tiene grupos; ni grupo si tiene ítems.
        if ($row['item'] === '0000') {
            if ($row['grupo'] === '000') {
                // familia
                $child = $this->pdo->prepare(
                    "SELECT 1 FROM costos_catalogo
                      WHERE familia = :f AND grupo <> '000'
                      LIMIT 1"
                );
                $child->execute([':f' => $row['familia']]);
                if ($child->fetch()) { throw new RuntimeException('No se puede eliminar familia con grupos asociados.'); }
            } else {
                // grupo
                $child = $this->pdo->prepare(
                    "SELECT 1 FROM costos_catalogo
                      WHERE familia = :f AND grupo = :g AND item <> '0000'
                      LIMIT 1"
                );
                $child->execute([':f' => $row['familia'], ':g' => $row['grupo']]);
                if ($child->fetch()) { throw new RuntimeException('No se puede eliminar grupo con ítems asociados.'); }
            }
        }

        $st = $this->pdo->prepare("DELETE FROM costos_catalogo WHERE codigo = :c");
        return $st->execute([':c' => $codigo]);
    }

    /* ===================== UTILIDADES DE CÓDIGO ===================== */

    /** Siguiente GGG dentro de la familia */
    public function nextGrupoCode(string $familia): string
    {
        $familia = $this->pad3($familia);
        $st = $this->pdo->prepare(
            "SELECT MAX(grupo) AS mg
               FROM costos_catalogo
              WHERE familia = :f AND grupo <> '000' AND item = '0000'"
        );
        $st->execute([':f'=>$familia]);
        $mg = $st->fetchColumn();
        $n  = $mg ? (int)$mg : 0;
        $n++;
        return str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }

    /** Siguiente XXXX dentro del grupo */
    public function nextItemCode(string $familia, string $grupo): string
    {
        $familia = $this->pad3($familia);
        $grupo   = $this->pad3($grupo);
        $st = $this->pdo->prepare(
            "SELECT MAX(item) AS mi
               FROM costos_catalogo
              WHERE familia = :f AND grupo = :g AND item <> '0000'"
        );
        $st->execute([':f'=>$familia, ':g'=>$grupo]);
        $mi = $st->fetchColumn();
        $n  = $mi ? (int)$mi : 0;
        $n++;
        return str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    /* ===================== HELPERS ===================== */

    private function safeText(string $s, int $max): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max);
        }
        return $s;
    }

    private function codigo10(string $c): string
    {
        $c = preg_replace('/\D/', '', $c);
        if (strlen($c) !== 10) {
            throw new InvalidArgumentException('codigo debe tener 10 dígitos (FFFGGGXXXX)');
        }
        return $c;
    }

    private function pad3(string $v): string
    {
        $v = preg_replace('/\D/', '', $v);
        if ($v === '') { $v = '0'; }
        return str_pad(substr($v, 0, 3), 3, '0', STR_PAD_LEFT);
    }

    private function unidadValida(string $u): string
    {
        $u = trim($u);
        if (!in_array($u, self::UNIDADES, true)) {
            throw new InvalidArgumentException('Unidad no permitida.');
        }
        return $u;
    }

    /** Normaliza valor/moneda para ítems */
    private function sanitizePrecio(?string $valor, ?string $moneda): array
    {
        $v = null;
        if ($valor !== null && $valor !== '') {
            // admite coma/punto
            $v = str_replace(',', '.', trim($valor));
            if (!is_numeric($v)) { throw new InvalidArgumentException('Valor inválido.'); }
            $v = (string)$v;
        }
        $m = null;
        if ($moneda !== null && $moneda !== '') {
            $moneda = strtoupper(trim($moneda));
            if (!in_array($moneda, self::MONEDAS, true)) {
                throw new InvalidArgumentException('Moneda no permitida.');
            }
            $m = $moneda;
        }
        return [$v, $m];
    }
}
