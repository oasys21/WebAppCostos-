<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';

class AuthController extends Controller
{
    public function __construct(PDO $pdo, array $cfg = [])
    {
        parent::__construct($pdo, $cfg);
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function normalizeRut(string $raw): string
    {
        $s = strtoupper(trim($raw));
        $s = preg_replace('/[^0-9K]/', '', $s);
        if ($s === null) $s = '';
        if (strlen($s) < 2) return '';
        return substr($s, 0, -1) . '-' . substr($s, -1);
    }

    // GET /auth/login
    public function login(): void
    {
        $this->view('auth_login', [
            'base'      => rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'),
            'recaptcha' => !empty($this->cfg['RECAPTCHA_ENABLED']),
            'sitekey'   => (string)($this->cfg['RECAPTCHA_SITE_KEY'] ?? ''),
        ]);
    }

    // GET /auth/checkrut?rut=11111111-1 -> {exists:bool}
    public function checkrut(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $rutIn = (string)($_GET['rut'] ?? '');
        $rut   = $this->normalizeRut($rutIn);
        if ($rut === '') { echo json_encode(['exists' => false]); return; }

        $st = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE rut = :rut LIMIT 1");
        $st->execute([':rut' => $rut]);
        echo json_encode(['exists' => (bool)$st->fetchColumn()]);
    }

    // POST /auth/dologin
    public function dologin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        $rutIn  = (string)($_POST['rut'] ?? '');
        $passIn = (string)($_POST['password'] ?? '');

        if ($rutIn === '' || $passIn === '') {
            $this->redirect('/auth/login?e=nouser');
        }

        if (!empty($this->cfg['RECAPTCHA_ENABLED'])) {
            $token  = (string)($_POST['g-recaptcha-response'] ?? '');
            $secret = (string)($this->cfg['RECAPTCHA_SECRET'] ?? '');
            if (!$this->verifyRecaptcha($token, $secret)) {
                $this->redirect('/auth/login?e=captcha');
            }
        }

        $rut = $this->normalizeRut($rutIn);
        if ($rut === '') {
            $this->redirect('/auth/login?e=rut');
        }

        $sql = "SELECT id, rut, nombre, perfil, subperfil, activo, pass_hash
                  FROM usuarios
                 WHERE rut = :rut
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':rut' => $rut]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            $this->redirect('/auth/login?e=nouser');
        }

        if (array_key_exists('activo', $u) && (string)$u['activo'] === '0') {
            $this->redirect('/auth/login?e=blocked');
        }

        $hash = (string)($u['pass_hash'] ?? '');
        if ($hash === '' || !password_verify($passIn, $hash)) {
            $this->redirect('/auth/login?e=pass');
        }

        $_SESSION['user'] = [
            'id'        => (int)$u['id'],
            'rut'       => (string)$u['rut'],
            'nombre'    => (string)($u['nombre'] ?? ''),
            'perfil'    => (string)($u['perfil'] ?? ''),
            'subperfil' => (string)($u['subperfil'] ?? ''),
        ];

        // ⬅️ Importante: sin base(), tu redirect ya antepone /costos => /costos/dashboard
        $this->redirect('/dashboard');
    }

    // GET /auth/logout
    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();

        // ⬅️ También sin base()
        $this->redirect('/auth/login?bye=1');
    }

    private function verifyRecaptcha(string $token, string $secret): bool
    {
        if ($secret === '' || $token === '') return false;
        try {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ])
            ]);
            $resp = curl_exec($ch);
            $ok   = false;
            if ($resp !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                $json = json_decode($resp, true);
                $ok   = !empty($json['success']);
            }
            curl_close($ch);
            return (bool)$ok;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
