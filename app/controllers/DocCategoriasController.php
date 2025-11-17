<?php
// /costos/app/controllers/DocCategoriasController.php
declare(strict_types=1);

require_once __DIR__ . '/../models/DocCategorias.php';
require_once __DIR__ . '/../models/LogSys.php';

final class DocCategoriasController extends Controller
{
    /* ======= Auth & permisos ======= */

    private function requireAuth(): array
    {
        $u = Session::user();
        if (!$u) $this->redirect('/auth/login');
        return $u;
    }

    /** Verifica si el usuario puede realizar acción sobre DOX:
     *  DOX-CRE=pos 25, DOX-EDT=pos 26, DOX-DEL=pos 27
     */
    private function can(array $u, string $accion): bool
    {
        if (($u['perfil'] ?? '') === 'ADM') return true;

        $sp = (string)($u['subperfil'] ?? '');
        $sp = str_pad(preg_replace('/[^01]/', '0', $sp), 30, '0'); // 30 posiciones
        $map = [
            'view'   => 25, // para listar/consultar usamos el mismo grupo DOX
            'create' => 25,
            'edit'   => 26,
            'delete' => 27,
        ];
        $pos = $map[$accion] ?? 25;
        $idx = $pos - 1; // 0-based
        return isset($sp[$idx]) && $sp[$idx] === '1';
    }

    private function forbid(): void
    {
        http_response_code(403);
        echo "403 Prohibido";
        exit;
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
        }
        return '0.0.0.0';
    }

    /* ======= Acciones ======= */

    /** GET /doc-categorias/index */
    public function index(): void
    {
        $u = $this->requireAuth();
        if (!$this->can($u, 'view')) $this->forbid();

        $m  = new DocCategorias($this->pdo);
        $q  = isset($_GET['q'])   ? trim((string)$_GET['q'])   : null;
        $mod= isset($_GET['mod']) ? trim((string)$_GET['mod']) : null;
        $act= isset($_GET['a'])   ? (($_GET['a']===''? null : (int)$_GET['a'])) : null;

        $limit  = $_GET['limit']  ?? 50;
        $offset = $_GET['offset'] ?? 0;

        $rows = $m->list($limit, $offset, $q, $mod, $act);
        $tot  = $m->totalCount($q, $mod, $act);

        // VISTAS: doc_categorias_index.php
        $this->view('doc_categorias_index', [
            'pageTitle' => 'Categorías de Documentos',
            'rows'      => $rows,
            'total'     => $tot,
            'q'         => $q,
            'mod'       => $mod,
            'a'         => $act,
            'limit'     => (int)(is_numeric($limit)?$limit:50),
            'offset'    => (int)(is_numeric($offset)?$offset:0),
            'modulos'   => DocCategorias::MODULOS
        ]);
    }

    /** GET /doc-categorias/create */
    public function create(): void
    {
        $u = $this->requireAuth();
        if (!$this->can($u, 'create')) $this->forbid();

        // VISTAS: doc_categorias_create.php
        $this->view('doc_categorias_create', [
            'pageTitle' => 'Nueva Categoría',
            'modulos'   => DocCategorias::MODULOS
        ]);
    }

    /** POST /doc-categorias/store */
    public function store(): void
    {
        $u = $this->requireAuth();
        if (!$this->can($u, 'create')) $this->forbid();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/doc-categorias/index');
        }

        $data = [
            'modulo'      => (string)($_POST['modulo'] ?? ''),
            'nombre'      => trim((string)($_POST['nombre'] ?? '')),
            'descripcion' => trim((string)($_POST['descripcion'] ?? '')),
            'activo'      => isset($_POST['activo']) ? 1 : 0
        ];

        try {
            $m = new DocCategorias($this->pdo);
            $id = $m->create($data);

            // Log
            (new LogSys($this->pdo))->add(
                (int)$u['id'], (string)$u['rut'], (string)$u['nombre'],
                $this->clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '',
                'CREATE', 'documentos_categorias', (int)$id,
                'creado: '.json_encode($data, JSON_UNESCAPED_UNICODE)
            );

            $this->redirect('/doc-categorias/index?ok=1');
        } catch (Throwable $e) {
            $this->redirect('/doc-categorias/create?e='.urlencode($e->getMessage()));
        }
    }

    /** GET /doc-categorias/edit/{id} */
    public function edit(int $id): void
    {
        $u = $this->requireAuth();
        if (!$this->can($u, 'edit')) $this->forbid();

        $m = new DocCategorias($this->pdo);
        $row = $m->find($id);
        if (!$row) $this->redirect('/doc-categorias/index?e=No+existe');

        // VISTAS: doc_categorias_edit.php
        $this->view('doc_categorias_edit', [
            'pageTitle' => 'Editar Categoría',
            'row'       => $row,
            'modulos'   => DocCategorias::MODULOS
        ]);
    }

    /** POST /doc-categorias/update/{id} */
    public function update(int $id): void
    {
        $u = $this->requireAuth();
        if (!$this->can($u, 'edit')) $this->forbid();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/doc-categorias/index');
        }

        $data = [
            'modulo'      => (string)($_POST['modulo'] ?? ''),
            'nombre'      => trim((string)($_POST['nombre'] ?? '')),
            'descripcion' => trim((string)($_POST['descripcion'] ?? '')),
            'activo'      => isset($_POST['activo']) ? 1 : 0
        ];

        try {
            $m = new DocCategorias($this->pdo);
            $before = $m->find($id);
            if (!$before) $this->redirect('/doc-categorias/index?e=No+existe');

            $m->update($id, $data);
            $after = $m->find($id);

            // Diff
            $diff = [];
            foreach (['modulo','nombre','descripcion','activo'] as $k) {
                $b = $before[$k] ?? null;
                $a = $after[$k]  ?? null;
                if ($b != $a) $diff[$k] = ['before'=>$b, 'after'=>$a];
            }

            (new LogSys($this->pdo))->add(
                (int)$u['id'], (string)$u['rut'], (string)$u['nombre'],
                $this->clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '',
                'UPDATE', 'documentos_categorias', (int)$id,
                json_encode($diff, JSON_UNESCAPED_UNICODE)
            );

            $this->redirect('/doc-categorias/index?ok=1');
        } catch (Throwable $e) {
            $this->redirect('/doc-categorias/edit/'.$id.'?e='.urlencode($e->getMessage()));
        }
    }

    /** POST /doc-categorias/delete/{id} */
    public function delete(int $id): void
    {
        $u = $this->requireAuth();
        if (!$this->can($u, 'delete')) $this->forbid();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/doc-categorias/index');
        }

        try {
            $m = new DocCategorias($this->pdo);

            if (!$m->canDelete($id)) {
                $this->redirect('/doc-categorias/index?e=No+se+puede+borrar%3A+categor%C3%ADa+en+uso');
            }

            $ok = $m->delete($id);

            (new LogSys($this->pdo))->add(
                (int)$u['id'], (string)$u['rut'], (string)$u['nombre'],
                $this->clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '',
                'DELETE', 'documentos_categorias', (int)$id,
                $ok ? 'borrado' : 'sin cambios'
            );

            $this->redirect('/doc-categorias/index?ok=1');
        } catch (Throwable $e) {
            $this->redirect('/doc-categorias/index?e='.urlencode($e->getMessage()));
        }
    }
}
