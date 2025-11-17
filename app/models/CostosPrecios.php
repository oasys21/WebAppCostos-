<?php
// /app/models/CostosPrecios.php
declare(strict_types=1);

final class CostosPrecios
{
    private PDO $db;
    public function __construct(PDO $pdo) { $this->db = $pdo; }

    /**
     * Último registro de costos_precios por código (por fecha_vigencia DESC, id DESC)
     */
    public function latestByCodigo(string $codigo): ?array {
        $codigo = $this->codigo10($codigo);
        $st = $this->db->prepare("
            SELECT *
            FROM costos_precios
            WHERE codigo = :c
            ORDER BY fecha_vigencia DESC, id DESC
            LIMIT 1
        ");
        $st->execute([':c'=>$codigo]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Devuelve el precio "efectivo" a usar en presupuesto:
     *  1) Toma campo preferido desde costos_precios (default: costo_venta).
     *  2) Si es NULL o 0, intenta con el alternativo (costo_directo / costo_venta).
     *  3) Si sigue 0, cae al valor del catálogo (costos_catalogo.valor).
     *  4) Si no hay valor en ningún lado, devuelve 0.0
     *
     * @param string $codigo Código 10 chars (FFF GGG IIII)
     * @param string $prefer Campo preferido de costos_precios: 'costo_venta' o 'costo_directo'
     * @return float
     */
    public function precioEfectivo(string $codigo, string $prefer='costo_venta'): float
    {
        $codigo = $this->codigo10($codigo);
        $prefer = ($prefer === 'costo_directo') ? 'costo_directo' : 'costo_venta';
        $alt    = ($prefer === 'costo_venta') ? 'costo_directo' : 'costo_venta';

        // 1) Buscar último precios
        $r = $this->latestByCodigo($codigo);
        $v = 0.0;

        if ($r) {
            $p1 = isset($r[$prefer]) ? (float)$r[$prefer] : 0.0;
            if ($p1 > 0) {
                $v = $p1;
            } else {
                $p2 = isset($r[$alt]) ? (float)$r[$alt] : 0.0;
                if ($p2 > 0) {
                    $v = $p2;
                }
            }
        }

        // 2) Si sigue 0, caer al catálogo
        if ($v <= 0) {
            $st = $this->db->prepare("SELECT valor FROM costos_catalogo WHERE codigo = :c LIMIT 1");
            $st->execute([':c'=>$codigo]);
            $cat = $st->fetch(PDO::FETCH_ASSOC);
            if ($cat && $cat['valor'] !== null && $cat['valor'] !== '') {
                $vv = (float)$cat['valor'];
                if ($vv > 0) { $v = $vv; }
            }
        }

        return $v > 0 ? $v : 0.0;
    }

    /* =================== Helpers internos =================== */

    /**
     * Normaliza a código válido de 10 chars (sin guiones/espacios), upper.
     * Lanza InvalidArgumentException si no cumple.
     */
    private function codigo10(string $codigo): string
    {
        $c = strtoupper(trim(preg_replace('/[^0-9A-Za-z]/', '', $codigo) ?? ''));
        if (strlen($c) !== 10) {
            throw new InvalidArgumentException('Código inválido, se esperan 10 caracteres (FFF GGG IIII).');
        }
        return $c;
    }
}
