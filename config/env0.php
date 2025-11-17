<?php
declare(strict_types=1);

return [
    // 'DEV' o 'PROD'
    'ENV' => 'DEV',
    'DEBUG' => true,

    // Detecta automáticamente el prefijo de carpeta (ej. /costos)
    'BASE_URL' => '/costos',

    // reCAPTCHA (habilitar sólo en PROD)
    'RECAPTCHA_ENABLED' => false, // en PROD => true
    'RECAPTCHA_SITE_KEY' => '',
    'RECAPTCHA_SECRET' => '',

    // Base de datos (ajusta credenciales)
    'DB' => [
        'dsn'  => 'mysql:host=localhost;dbname=bd_costos;charset=utf8mb4',
        'user' => 'root',      					  // cPanel:x
        'pass' => 'xxxxxxxxxxx',          		  // cPanel: x
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    ],

    // Sesión
    'SESSION_NAME' => 'COSTOS_SESSID',
    'SESSION_SECURE' => false,
    'SESSION_HTTPONLY' => true,
    'SESSION_SAMESITE' => 'Lax',

    // Seguridad login
    'LOGIN_MAX_FAILS' => 5,          // bloquea tras 5 fallos
    'LOGIN_FAIL_WINDOW_MIN' => 15,   // ventana (min) para contar fallos
];
