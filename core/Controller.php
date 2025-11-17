<?php
// /costos/core/Controller.php
declare(strict_types=1);

class Controller
{
    protected PDO $pdo;
    protected array $cfg;

    public function __construct(PDO $pdo, array $cfg)
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    /**
     * Render de vista SIN ambigüedades (solo archivos planos).
     * - Se espera un nombre tipo "auth_login" / "usuarios_index" / "documentos_create".
     * - Si llega "auth/login" por error, lo convertimos a "auth_login" (una sola regla clara).
     * - Layout fijo en app/views/layout/header.php y .../footer.php
     */
    public function view(string $viewName, array $data = []): void
    {
        // Normaliza por si alguien pasó "auth/login": lo convertimos a "auth_login"
        $viewName = trim($viewName);
        $viewName = str_replace('/', '_', $viewName);

        // Validación rígida: solo minúsculas, dígitos y guion bajo
        if (!preg_match('/^[a-z0-9_]+$/', $viewName)) {
            http_response_code(500);
            echo "Vista inválida: use 'modulo_accion' (ej: auth_login).";
            exit;
        }

        $viewsRoot = rtrim(realpath(__DIR__ . '/../app/views') ?: (__DIR__ . '/../app/views'), '/\\') . DIRECTORY_SEPARATOR;
        $fileView  = $viewsRoot . $viewName . '.php';
        $fileHead  = $viewsRoot . 'layout' . DIRECTORY_SEPARATOR . 'header.php';
        $fileFoot  = $viewsRoot . 'layout' . DIRECTORY_SEPARATOR . 'footer.php';

        if (!is_file($fileView)) {
            http_response_code(500);
            echo "Vista no encontrada: {$fileView}";
            exit;
        }
        if (!is_file($fileHead) || !is_file($fileFoot)) {
            http_response_code(500);
            echo "Layout no encontrado. Debe existir app/views/layout/header.php y footer.php";
            exit;
        }

        extract($data, EXTR_SKIP);
        require $fileHead;
        require $fileView;
        require $fileFoot;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $path): void
    {
        $base = rtrim($this->cfg['BASE_URL'] ?? '', '/'); // inyectado en index.php
        header('Location: ' . $base . $path);
        exit;
    }
}
