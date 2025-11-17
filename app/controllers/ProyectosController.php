<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
if (is_file(__DIR__ . '/../../core/Session.php')) { require_once __DIR__ . '/../../core/Session.php'; }
require_once __DIR__ . '/../models/Proyectos.php';

final class ProyectosController extends Controller
{
    /* ===== Helpers sesión/ACL ===== */
    private function u() {
        if (class_exists('Session') && method_exists('Session','user')) return Session::user();
        return $_SESSION['user'] ?? null;
    }
    private function can($u, string $perm): bool { return !empty($u); }
    private function baseUrl(): string {
        return rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/');
    }
    private function isADM($u): bool {
        $r = strtoupper((string)($u['rol'] ?? ''));
        return in_array($r, ['ADM','ADMIN','SUPER','ROOT'], true) || !empty($u['is_admin']);
    }

    /* ===== Listado ===== */
    public function index(): void
    {
        $u = $this->u(); if (!$this->can($u,'index')) { header('Location: '.$this->baseUrl().'/'); return; }

        $q = trim((string)($_GET['q'] ?? ''));
        $soloActivosBool = isset($_GET['activos']) ? ((int)$_GET['activos'] === 1) : false;

        $page = max(1, (int)($_GET['p'] ?? 1));
        $pageSize = 50;

        $m = new \Proyectos($this->pdo);
        if (method_exists($m, 'search')) {
            $rows = $m->search($q !== '' ? $q : null, $soloActivosBool, null, $page, $pageSize, (int)($u['id'] ?? 0));
        } else {
            $rows = $m->searchLight($q, $pageSize);
        }

        $this->view('proyectos_index', [
            'rows' => $rows,
            'q'    => $q,
            'pageTitle' => 'Proyectos',
            'base' => $this->baseUrl(),
        ]);
    }

    /* ===== Crear ===== */
    public function create(): void
    {
        $u = $this->u(); if (!$this->can($u,'create')) { header('Location: '.$this->baseUrl().'/'); return; }

        // Select simple de clientes
        $clientes = [];
        try {
            $st = $this->pdo->query("SELECT rut, nombre FROM clientes ORDER BY nombre");
            $clientes = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) { $clientes = []; }

        $this->view('proyectos_create', [
            'ownerIdDefault' => (int)($u['id'] ?? 0),
            'clientes'       => $clientes,
            'pageTitle'      => 'Nuevo proyecto',
            'base'           => $this->baseUrl(),
        ]);
    }

    public function store(): void
    {
        $u = $this->u(); if (!$this->can($u,'create')) { header('Location: '.$this->baseUrl().'/'); return; }

        $data = [
            'nombre'        => trim((string)($_POST['nombre'] ?? '')),
            'descripcion'   => trim((string)($_POST['descripcion'] ?? '')),
            'rut_cliente'   => trim((string)($_POST['rut_cliente'] ?? '')),
            'fecha_inicio'  => ($_POST['fecha_inicio'] ?? null) ?: null,
            'fecha_termino' => ($_POST['fecha_termino'] ?? null) ?: null,
            'activo'        => (int)($_POST['activo'] ?? 1),
            'owner_user_id' => (int)($_POST['owner_user_id'] ?? ($u['id'] ?? 0)),
            'codigo_proy'   => (function(){
                $v = strtoupper((string)($_POST['codigo_proy'] ?? ''));
                $v = preg_replace('/\s+/', '-', $v);
                $v = preg_replace('/[^A-Z0-9_.-]/', '', $v);
                return $v;
            })(),
        ];

        $data['nombre'] = substr($data['nombre'], 0, 160);
        if ($data['nombre'] === '' || $data['codigo_proy'] === '') {
            $this->redirect('/index.php?r=proyectos/create&err=campos');
            return;
        }

        try {
            $m = new \Proyectos($this->pdo);
            $id = (int)$m->create($data);
            if ($id <= 0) { throw new \RuntimeException('No se pudo crear el proyecto'); }
            $this->redirect('/index.php?r=proyectos/index&ok=created');
        } catch (\Throwable $e) {
            $this->redirect('/index.php?r=proyectos/create&err='.rawurlencode($e->getMessage()));
        }
    }

    /* ===== Editar ===== */
    public function edit($id = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { header('Location: '.$this->baseUrl().'/'); return; }
        $id = (int)$id; if ($id<=0) { $this->redirect('/index.php?r=proyectos/index&err=param'); return; }

        $m = new \Proyectos($this->pdo);
        $p = $m->get($id);
        if (!$p) { $this->redirect('/index.php?r=proyectos/index&err=noexist'); return; }

        // Clientes (select simple)
        $clientes = [];
        try {
            $st = $this->pdo->prepare("SELECT rut, nombre FROM clientes ORDER BY nombre");
            $st->execute();
            $clientes = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { $clientes = []; }

        // Usuarios (para selects en ACL)
        $usuarios = [];
        try {
            $st = $this->pdo->prepare("SELECT id, nombre AS nameuser, email FROM usuarios ORDER BY nombre");
            $st->execute();
            $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { $usuarios = []; }

        // Miembros actuales del proyecto
        $miembros = [];
        try {
            $sql = "SELECT pu.user_id, pu.rol, u.nombre, u.email
                      FROM proyecto_usuarios pu
                 LEFT JOIN usuarios u ON u.id = pu.user_id
                     WHERE pu.proyecto_id = :id
                  ORDER BY u.nombre";
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $id, \PDO::PARAM_INT);
            $st->execute();
            $miembros = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { $miembros = []; }

        // Permisos para administrar ACL del proyecto
        $isADM = $this->isADM($u);
        $canManageACL = $isADM || ((int)($u['id'] ?? 0) === (int)($p['owner_user_id'] ?? 0));

        // Pasar alias 'p' y 'proyecto' para compatibilidad de vistas
        $this->view('proyectos_edit', [
            'p'            => $p,
            'proyecto'     => $p,
            'clientes'     => $clientes,
            'usuarios'     => $usuarios,
            'miembros'     => $miembros,
            'canManageACL' => $canManageACL,
            'isADM'        => $isADM,
            'pageTitle'    => 'Editar proyecto',
            'base'         => $this->baseUrl(),
        ]);
    }

    public function update($id = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { header('Location: '.$this->baseUrl().'/'); return; }
        $id = (int)$id; if ($id<=0) { $this->redirect('/index.php?r=proyectos/index&err=param'); return; }

        $m = new \Proyectos($this->pdo);
        $before = $m->get($id);
        if (!$before) { $this->redirect('/index.php?r=proyectos/index&err=noexist'); return; }

        $patch = [
            'nombre'        => trim((string)($_POST['nombre'] ?? '')),
            'descripcion'   => trim((string)($_POST['descripcion'] ?? '')),
            'rut_cliente'   => trim((string)($_POST['rut_cliente'] ?? '')),
            'fecha_inicio'  => ($_POST['fecha_inicio'] ?? null) ?: null,
            'fecha_termino' => ($_POST['fecha_termino'] ?? null) ?: null,
            'activo'        => (int)($_POST['activo'] ?? 1),
            'codigo_proy'   => (function(){
                $v = strtoupper((string)($_POST['codigo_proy'] ?? ''));
                $v = preg_replace('/\s+/', '-', $v);
                $v = preg_replace('/[^A-Z0-9_.-]/', '', $v);
                return $v;
            })(),
        ];

        $patch['nombre'] = substr($patch['nombre'], 0, 160);
        if ($patch['nombre'] === '' || $patch['codigo_proy'] === '') {
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&err=campos');
            return;
        }

        $data = $patch; // compatibilidad

        try {
            $ok = $m->update($id, $data);
            if (!$ok) { throw new \RuntimeException('No se pudo actualizar'); }
            $this->redirect('/index.php?r=proyectos/index&ok=edited');
        } catch (\Throwable $e) {
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&err='.rawurlencode($e->getMessage()));
        }
    }

    /* ===== Eliminar ===== */
    public function destroy($id = null): void
    {
        $u = $this->u(); if (!$this->can($u,'delete')) { header('Location: '.$this->baseUrl().'/'); return; }
        $id = (int)$id; if ($id<=0) { $this->redirect('/index.php?r=proyectos/index&err=param'); return; }

        try {
            $m = new \Proyectos($this->pdo);
            $ok = $m->delete($id);
            if (!$ok) { throw new \RuntimeException('No se pudo eliminar'); }
            $this->redirect('/index.php?r=proyectos/index&ok=deleted');
        } catch (\Throwable $e) {
            $this->redirect('/index.php?r=proyectos/index&err='.rawurlencode($e->getMessage()));
        }
    }

    /* ===== AJAX: usuarios (dueños) ===== */
    public function ajaxusuarios(): void
    {
        $u = $this->u();
        header('Content-Type: application/json; charset=utf-8');
        if (empty($u)) { http_response_code(403); echo json_encode([]); return; }

        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $sql = "SELECT id, nombre AS label FROM usuarios WHERE 1=1";
            $p = [];
            if ($q !== '') { $sql .= " AND (nombre LIKE :q OR email LIKE :q)"; $p[':q'] = '%'.$q.'%'; }
            $sql .= " ORDER BY nombre LIMIT 100";
            $st = $this->pdo->prepare($sql);
            foreach ($p as $k=>$v) { $st->bindValue($k, $v, \PDO::PARAM_STR); }
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /* ===== ACL del proyecto ===== */

    // Ruta: /proyectos/setowner/{id}
    public function setowner($id = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { header('Location: '.$this->baseUrl().'/'); return; }
        $id = (int)$id; $newOwner = (int)($_POST['owner_user_id'] ?? 0);
        if ($id<=0 || $newOwner<=0) { $this->redirect('/index.php?r=proyectos/index&err=param'); return; }

        try {
            $this->pdo->beginTransaction();

            // Cambiar dueño en proyectos
            $st = $this->pdo->prepare("UPDATE proyectos SET owner_user_id=:o WHERE id=:id");
            $st->execute([':o'=>$newOwner, ':id'=>$id]);

            // Asegurar fila OWNER en proyecto_usuarios
            $sql = "INSERT INTO proyecto_usuarios (proyecto_id,user_id,rol)
                    VALUES (:p,:u,'OWNER')
                    ON DUPLICATE KEY UPDATE rol='OWNER'";
            $st = $this->pdo->prepare($sql);
            $st->execute([':p'=>$id, ':u'=>$newOwner]);

            $this->pdo->commit();
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&ok=owner_set');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&err='.rawurlencode($e->getMessage()));
        }
    }

    // Ruta: /proyectos/addmember/{id}
    public function addmember($id = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { header('Location: '.$this->baseUrl().'/'); return; }
        $id = (int)$id; $userId = (int)($_POST['user_id'] ?? 0); $rol = (string)($_POST['rol'] ?? 'EDITOR');
        if ($id<=0 || $userId<=0) { $this->redirect('/index.php?r=proyectos/index&err=param'); return; }

        try {
            $m = new \Proyectos($this->pdo);
            // El modelo ya normaliza a ENUM OWNER/EDITOR/VISOR
            $ok = $m->addMember($id, $userId, $rol);
            if (!$ok) throw new \RuntimeException('No se pudo agregar miembro');
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&ok=member_added');
        } catch (\Throwable $e) {
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&err='.rawurlencode($e->getMessage()));
        }
    }

    // Ruta: /proyectos/removemember/{id}
    public function removemember($id = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { header('Location: '.$this->baseUrl().'/'); return; }
        $id = (int)$id; $userId = (int)($_POST['user_id'] ?? 0);
        if ($id<=0 || $userId<=0) { $this->redirect('/index.php?r=proyectos/index&err=param'); return; }

        // Proteger al dueño actual
        try {
            $st = $this->pdo->prepare("SELECT owner_user_id FROM proyectos WHERE id=:id");
            $st->execute([':id'=>$id]);
            $owner = (int)($st->fetchColumn() ?: 0);
            if ($owner === $userId) {
                $this->redirect('/index.php?r=proyectos/edit/'.$id.'&err=no_remove_owner');
                return;
            }
        } catch (\Throwable $e) { /* continuar removiendo si no se puede leer */ }

        try {
            $m = new \Proyectos($this->pdo);
            if (method_exists($m, 'removeMember')) {
                $ok = $m->removeMember($id, $userId);
            } else {
                // Fallback directo
                $st = $this->pdo->prepare("DELETE FROM proyecto_usuarios WHERE proyecto_id=:p AND user_id=:u");
                $ok = $st->execute([':p'=>$id, ':u'=>$userId]);
            }
            if (!$ok) throw new \RuntimeException('No se pudo quitar miembro');
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&ok=member_removed');
        } catch (\Throwable $e) {
            $this->redirect('/index.php?r=proyectos/edit/'.$id.'&err='.rawurlencode($e->getMessage()));
        }
    }
}
