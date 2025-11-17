<?php
declare(strict_types=1);

final class Acl
{
    // Mapea etiqueta => posición (1-based) en string subperfil
    private const MAP = [
        // USUARIOS
        'USR_CRE' => 1, 'USR_EDT' => 2, 'USR_DEL' => 3,
        // PROVEEDORES
        'PRO_CRE' => 4, 'PRO_EDT' => 5, 'PRO_DEL' => 6,
        // ADQUISICIONES
        'ADQ_CRE' => 7, 'ADQ_EDT' => 8, 'ADQ_DEL' => 9,
        // CLIENTES
        'CLI_CRE' => 10, 'CLI_EDT' => 11, 'CLI_DEL' => 12,
        // PROYECTOS
        'PRJ_CRE' => 13, 'PRJ_EDT' => 14, 'PRJ_DEL' => 15,
        // CATALOGO
        'CAT_CRE' => 16, 'CAT_EDT' => 17, 'CAT_DEL' => 18,
        // AVANCE
        'AVN_CRE' => 19, 'AVN_EDT' => 20, 'AVN_DEL' => 21,
        // ESTADOS PAGO
        'ESP_CRE' => 22, 'ESP_EDT' => 23, 'ESP_DEL' => 24,
        // DOCUMENTACION
        'DOX_CRE' => 25, 'DOX_EDT' => 26, 'DOX_DEL' => 27,
        // PRESUPUESTOS
        'PRE_CRE' => 28, 'PRE_EDT' => 29, 'PRE_DEL' => 30,
    ];

    public static function can(array $user, string $perm): bool {
        if (!$user) return false;
        if (($user['perfil'] ?? '') === 'ADM') return true; // ADM todo
        $sub = (string)($user['subperfil'] ?? '');
        $idx = self::MAP[$perm] ?? null;
        if (!$idx) return false;
        $pos = $idx - 1;
        return isset($sub[$pos]) && $sub[$pos] === '1';
    }
}
