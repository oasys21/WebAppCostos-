<?php
declare(strict_types=1);

class SiteController extends Controller
{
    /* ================= Infra ================= */
    private function baseUrl(): string {
        if (!empty($GLOBALS['cfg']['BASE_URL'])) return rtrim($GLOBALS['cfg']['BASE_URL'], '/');
        $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $b  = rtrim(str_replace('\\','/', dirname($sn)), '/');
        return ($b === '' || $b === '.') ? '' : $b;
    }
    private function flash(string $type, string $msg): void {
        if (class_exists('Session') && method_exists('Session', $type)) { @Session::$type($msg); return; }
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }

    /* ================= Rutas públicas ================= */
    public function landing(): void {
        $pageTitle = 'Bienvenido';
        $this->render('site/landing', compact('pageTitle'));
    }
    public function acerca(): void {
        $pageTitle = 'Acerca de';
        $this->render('site/acerca', compact('pageTitle'));
    }
    public function contacto(): void {
        $pageTitle = 'Contactos';
        $this->render('site/contacto', compact('pageTitle'));
    }

    /* ============== NUEVO: Formulario de contacto ============== */
    public function form_contacto(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['csrf_contact'])) {
            $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
        }
        $pageTitle = 'Formulario de Contacto';
        $this->render('site/form_contacto', compact('pageTitle'));
    }

    /* ============== NUEVO: Procesar envío ============== */
    public function contacto_enviar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('site/form_contacto'); // VOID FIX
            return;
        }

        // Helpers de saneamiento
        $cleanHeader = static function (string $v): string {
            $v = trim($v);
            $v = str_replace(["\r", "\n"], ' ', $v);   // anti inyección de cabeceras
            return substr($v, 0, 200);
        };
        $cleanText = static function (string $v): string {
            $v = trim($v);
            $v = preg_replace('/[^\P{C}\t\n\r]/u', '', $v) ?? $v; // quita chars de control
            return $v;
        };

        // CSRF
        $csrf = (string)($_POST['csrf'] ?? '');
        if (empty($_SESSION['csrf_contact']) || !hash_equals($_SESSION['csrf_contact'], $csrf)) {
            $this->respondJsonOrRedirect(
                ['ok'=>false,'msg'=>'CSRF inválido, recargue la página.'],
                400,
                'site/form_contacto?error=1'
            );  // VOID FIX
            return;
        }

        // Datos
        $nombre  = $cleanText((string)($_POST['nombre'] ?? ''));
        $email   = $cleanHeader((string)($_POST['email'] ?? ''));
        $fono    = $cleanText((string)($_POST['fono'] ?? ''));
        $ciudad  = $cleanText((string)($_POST['ciudad'] ?? ''));
        $mensaje = $cleanText((string)($_POST['mensaje'] ?? ''));

        // Validación
        $err = [];
        if ($nombre === '' || mb_strlen($nombre) < 2) { $err['nombre'] = 'Ingrese su nombre.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err['email'] = 'Email inválido.'; }
        if ($fono === '' || mb_strlen($fono) < 6) { $err['fono'] = 'Ingrese un teléfono válido.'; }
        if ($ciudad === '') { $err['ciudad'] = 'Ingrese su ciudad.'; }
        if ($mensaje === '' || mb_strlen($mensaje) < 5) { $err['mensaje'] = 'Escriba su mensaje.'; }

        if (!empty($err)) {
            $this->respondJsonOrRedirect(
                ['ok'=>false,'errors'=>$err,'msg'=>'Revise los datos.'],
                422,
                'site/form_contacto?error=1'
            );  // VOID FIX
            return;
        }

        // Construcción del correo
        $to = 'admin@rhglobal.cl';
        $subject = 'Nuevo contacto desde WebApp';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $body = "Se ha recibido un nuevo contacto desde el sitio web:\n\n"
              . "Nombre : {$nombre}\n"
              . "Email  : {$email}\n"
              . "Fono   : {$fono}\n"
              . "Ciudad : {$ciudad}\n"
              . "-------\n"
              . "Mensaje:\n{$mensaje}\n\n"
              . "-------\n"
              . "IP: {$ip}\nUA: {$ua}\nFecha: " . date('Y-m-d H:i:s');

        $fromDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $from = "no-reply@{$fromDomain}";

        $headers = [];
        $headers[] = "From: " . $cleanHeader($from);
        $headers[] = "Reply-To: " . $cleanHeader($email);
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
        $headers = implode("\r\n", $headers);

        $sent = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $headers);

        // Evitar reenvíos
        unset($_SESSION['csrf_contact']);

        if ($sent) {
            $this->respondJsonOrRedirect(
                ['ok'=>true,'msg'=>'Mensaje enviado. ¡Gracias por contactarnos!'],
                200,
                'site/form_contacto?ok=1'
            );  // VOID FIX
            return;
        }

        $this->respondJsonOrRedirect(
            ['ok'=>false,'msg'=>'No se pudo enviar el correo en este momento.'],
            500,
            'site/form_contacto?error=1'
        );  // VOID FIX
        return;
    }

    /* ===== Fallbacks base ===== */
    protected function render(string $view, array $vars = []): void {
        extract($vars, EXTR_OVERWRITE);
        if (!isset($pageTitle)) $pageTitle = 'Costos';
        $viewsRoot  = dirname(__DIR__) . '/views/';
        $viewFile   = $viewsRoot . $view . '.php';
        $headerFile = $viewsRoot . 'layout/header.php';
        $footerFile = $viewsRoot . 'layout/footer.php';
        if (is_file($headerFile)) require $headerFile;
        if (!is_file($viewFile)) { http_response_code(500); echo "Vista no encontrada: ".htmlspecialchars($viewFile); return; }
        require $viewFile;
        if (is_file($footerFile)) require $footerFile;
    }

    protected function redirect(string $path): void {
        $base = $this->baseUrl();
        if ($path === '' || $path[0] !== '/') $path = '/' . $path;
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    /* ===== Utilitarios internos ===== */
    private function wantsJson(): bool {
        $x = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($x === 'xmlhttprequest') return true;
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        return stripos($accept, 'application/json') !== false;
    }

    private function respondJsonOrRedirect(array $payload, int $status, string $redirectPath): void {
        if ($this->wantsJson()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        $this->redirect($redirectPath);
        // redirect() hace exit;
    }
}
