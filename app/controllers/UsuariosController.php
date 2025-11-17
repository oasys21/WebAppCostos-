<?php
declare(strict_types=1);

// Ruta exacta al Controller base (sin búsquedas)
require_once __DIR__ . '/../../core/Controller.php';

final class UsuariosController extends Controller
{
    public function __construct(PDO $pdo, array $cfg = [])
    {
        parent::__construct($pdo, $cfg);
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['user']['id'])) {
            $this->redirect('/auth/login');
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /* ========== LISTADO ========== */
    public function index(): void
    {
        $st = $this->pdo->query("
            SELECT id, rut, nombre, email, perfil, activo, fono, fecnac
              FROM usuarios
          ORDER BY nombre ASC
        ");
        $rows = $st->fetchAll();

        $this->view('usuarios_index', [
            'base' => rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'),
            'rows' => $rows,
        ]);
    }

    /* ========== CREAR ========== */
    public function create(): void
    {
        $this->view('usuarios_create', [
            'base' => rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'),
        ]);
    }

    public function store(): void
    {
        $rut     = trim((string)($_POST['rut'] ?? ''));
        $nombre  = trim((string)($_POST['nombre'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $perfil  = trim((string)($_POST['perfil'] ?? 'USR'));
        $activo  = (int)($_POST['activo'] ?? 1);
        $fono    = trim((string)($_POST['fono'] ?? ''));
        $fecnac  = !empty($_POST['fecnac']) ? $_POST['fecnac'] : null;

        $password = (string)($_POST['password'] ?? '');
        $passHash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : null;

        $sql = "INSERT INTO usuarios (rut, nombre, email, perfil, activo, pass_hash, fono, fecnac)
                VALUES (:rut, :nombre, :email, :perfil, :activo, :pass_hash, :fono, :fecnac)";
        $st  = $this->pdo->prepare($sql);
        $st->execute([
            ':rut'       => $rut,
            ':nombre'    => $nombre,
            ':email'     => $email,
            ':perfil'    => $perfil,
            ':activo'    => $activo,
            ':pass_hash' => $passHash,
            ':fono'      => ($fono !== '' ? $fono : null),
            ':fecnac'    => $fecnac ?: null,
        ]);

        $_SESSION['flash_ok'] = 'Usuario creado.';
        $this->redirect('/usuarios');
    }

    /* ========== EDITAR ========== */
    public function edit(string $id): void
    {
        $id = (int)$id;
        $st = $this->pdo->prepare("
            SELECT id, rut, nombre, email, perfil, activo, fono, fecnac
              FROM usuarios
             WHERE id = :id
        ");
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        if (!$row) {
            $_SESSION['flash_error'] = 'Usuario no encontrado.';
            $this->redirect('/usuarios');
        }

        $this->view('usuarios_edit', [
            'base' => rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'),
            'row'  => $row,
        ]);
    }

    public function update(string $id): void
    {
        $id      = (int)$id;
        $rut     = trim((string)($_POST['rut'] ?? ''));
        $nombre  = trim((string)($_POST['nombre'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $perfil  = trim((string)($_POST['perfil'] ?? 'USR'));
        $activo  = (int)($_POST['activo'] ?? 1);
        $fono    = trim((string)($_POST['fono'] ?? ''));
        $fecnac  = !empty($_POST['fecnac']) ? $_POST['fecnac'] : null;

        $password = (string)($_POST['password'] ?? '');

        if ($password !== '') {
            $sql = "UPDATE usuarios
                       SET rut=:rut, nombre=:nombre, email=:email, perfil=:perfil, activo=:activo,
                           pass_hash=:pass_hash, fono=:fono, fecnac=:fecnac
                     WHERE id=:id";
            $params = [
                ':rut'       => $rut,
                ':nombre'    => $nombre,
                ':email'     => $email,
                ':perfil'    => $perfil,
                ':activo'    => $activo,
                ':pass_hash' => password_hash($password, PASSWORD_BCRYPT),
                ':fono'      => ($fono !== '' ? $fono : null),
                ':fecnac'    => $fecnac ?: null,
                ':id'        => $id,
            ];
        } else {
            $sql = "UPDATE usuarios
                       SET rut=:rut, nombre=:nombre, email=:email, perfil=:perfil, activo=:activo,
                           fono=:fono, fecnac=:fecnac
                     WHERE id=:id";
            $params = [
                ':rut'     => $rut,
                ':nombre'  => $nombre,
                ':email'   => $email,
                ':perfil'  => $perfil,
                ':activo'  => $activo,
                ':fono'    => ($fono !== '' ? $fono : null),
                ':fecnac'  => $fecnac ?: null,
                ':id'      => $id,
            ];
        }

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $_SESSION['flash_ok'] = 'Usuario actualizado.';
        $this->redirect('/usuarios');
    }

    /* ========== BORRAR ========== */
    public function delete(string $id): void
    {
        $id = (int)$id;
        $st = $this->pdo->prepare("DELETE FROM usuarios WHERE id = :id");
        $st->execute([':id' => $id]);

        $_SESSION['flash_ok'] = 'Usuario eliminado.';
        $this->redirect('/usuarios');
    }

    /* ========== MI PERFIL (ver) ========== */
    public function miperfil(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if ($uid <= 0) { $this->redirect('/auth/login'); }

        $st = $this->pdo->prepare("
            SELECT id, rut, nombre, email, perfil, activo, fono, fecnac, foto
              FROM usuarios
             WHERE id = :id
        ");
        $st->execute([':id' => $uid]);
        $row = $st->fetch();

        $this->view('usuarios_miperfil', [
            'base' => rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'),
            'row'  => $row,
            'csrf' => $_SESSION['csrf'] ?? '',
        ]);
    }

    /* ========== MI PERFIL (actualizar) ========== */
    public function miperfilUpdate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if ($uid <= 0) { $this->redirect('/auth/login'); }

        // Registro actual (para conservar rut y foto si no cambian)
        $st = $this->pdo->prepare("SELECT id, rut, nombre, email, fono, fecnac, foto FROM usuarios WHERE id=:id");
        $st->execute([':id' => $uid]);
        $cur = $st->fetch();
        if (!$cur) { $_SESSION['flash_error'] = 'Usuario no encontrado.'; $this->redirect('/usuarios/miperfil'); }

        $nombre  = trim((string)($_POST['nombre'] ?? $cur['nombre']));
        $email   = trim((string)($_POST['email']  ?? $cur['email']));
        $fono    = trim((string)($_POST['fono']   ?? (string)$cur['fono']));
        $fecnac  = !empty($_POST['fecnac']) ? $_POST['fecnac'] : $cur['fecnac'];
        $pass1   = (string)($_POST['password']  ?? '');
        $pass2   = (string)($_POST['password2'] ?? '');

        if ($pass1 !== '' && $pass1 !== $pass2) {
            $_SESSION['flash_error'] = 'La confirmación de contraseña no coincide.';
            $this->redirect('/usuarios/miperfil');
        }

        // Subida de foto a /public/images/usuarios/  → guardamos SOLO el nombre de archivo
        $fotoFile = null;
        if (!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
            $fotoFile = $this->saveProfilePhotoPublic($uid, $_FILES['foto']); // devuelve "u{id}_YYYYmmddHHMMSS.ext" o null
        }

        // Construir SET (no toca RUT/Perfil/Activo)
        $set = "nombre=:nombre, email=:email, fono=:fono, fecnac=:fecnac";
        $params = [
            ':nombre' => $nombre,
            ':email'  => $email,
            ':fono'   => ($fono !== '' ? $fono : null),
            ':fecnac' => ($fecnac ?: null),
            ':id'     => $uid,
        ];

        if ($pass1 !== '') {
            $set .= ", pass_hash=:pass_hash";
            $params[':pass_hash'] = password_hash($pass1, PASSWORD_BCRYPT);
        }
        if ($fotoFile) {
            $set .= ", foto=:foto";
            $params[':foto'] = $fotoFile; // solo nombre de archivo
        }

        $sql = "UPDATE usuarios SET {$set} WHERE id=:id";
        $up  = $this->pdo->prepare($sql);
        $up->execute($params);

        // Refrescar datos en sesión (sin borrar rut/perfil/subperfil)
        $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], [
            'nombre' => $nombre,
            'email'  => $email,
            'fono'   => ($fono !== '' ? $fono : null),
            'fecnac' => ($fecnac ?: null),
        ]);
        if ($fotoFile) { $_SESSION['user']['foto'] = $fotoFile; }

        $_SESSION['flash_ok'] = 'Perfil actualizado.';
        $this->redirect('/usuarios/miperfil');
    }

    /* Alias por compatibilidad si alguna ruta antigua apunta aquí */
    public function updatePerfil(): void
    {
        $this->miperfilUpdate();
    }

    /* ========== Utilidades ========== */

    /**
     * Sube la foto a /public/images/usuarios/ y devuelve el NOMBRE DE ARCHIVO (p.ej. "u5_20251110_153012.jpg")
     * o null si falla validación/subida.
     */
    private function saveProfilePhotoPublic(int $uid, array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

        $tmp  = $file['tmp_name'];
        $size = (int)$file['size'];

        // Validar tipo y tamaño (máx 2MB)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp) ?: 'application/octet-stream';
        finfo_close($finfo);

        $ext = null;
        if ($mime === 'image/jpeg') $ext = 'jpg';
        if ($mime === 'image/png')  $ext = 'png';
        if (!$ext) return null;
        if ($size <= 0 || $size > 2 * 1024 * 1024) return null;

        $dir = rtrim(dirname(__DIR__, 2) . '/public/images/usuarios', '/\\');
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        $fname = sprintf('u%d_%s.%s', $uid, date('Ymd_His'), $ext);
        $dest  = $dir . DIRECTORY_SEPARATOR . $fname;

        if (!move_uploaded_file($tmp, $dest)) return null;

        return $fname; // la vista arma: $base/public/images/usuarios/{fname}
    }
}
