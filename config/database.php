<?php
declare(strict_types=1);
$cfg = $cfg ?? require __DIR__ . '/env.php';
try {
    /** @var PDO $pdo */
    $pdo = new PDO(
        $cfg['DB']['dsn'],
        $cfg['DB']['user'],
        $cfg['DB']['pass'],
        $cfg['DB']['options']
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error de conexión a la base de datos.";
    if (!empty($cfg['DEBUG'])) {
        echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
    }
    exit;
}
