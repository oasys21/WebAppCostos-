<?php
// /costos/app/controllers/MaestrosController.php
declare(strict_types=1);

final class MaestrosController
{
    private \PDO $pdo;
    private array $cfg;

    public function __construct(\PDO $pdo, array $cfg = [])
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    /* ========= Listado ========= */
    public function index(): void
    {
        $q       = trim((string)($_GET['q'] ?? ''));
        $tipo    = trim((string)($_GET['tipo'] ?? ''));
        $inact   = isset($_GET['inact']) ? (int)$_GET['inact'] : 0; // 0=solo activos

        $where = [];
        $p = [];
        if ($q !== '') { $where[]="(codigo LIKE :q OR descripcion LIKE :q)"; $p[':q']="%$q%"; }
        if ($tipo !== '') { $where[]="tipo_costo=:t"; $p[':t']=$tipo; }
        if (!$inact) { $where[]="activo=1"; }

        $sql = "SELECT * FROM maestros_catalogo";
        if ($where) $sql .= " WHERE ".implode(" AND ", $where);
        $sql .= " ORDER BY tipo_costo, codigo";

        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $items = $st->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('index', [
            'items'=>$items, 'q'=>$q, 'tipo'=>$tipo, 'inact'=>$inact,
            'pageTitle'=>'Maestro Catálogo',
        ]);
    }

    /* ========= Crear ========= */
    public function create(): void
    {
        $this->render('create', [
            'row'=>[
                'codigo'=>'','descripcion'=>'','tipo_costo'=>'MAT','subtipo_costo'=>'',
                'unidad'=>'UND','impuesto_regla'=>'IVA_RECUP','activo'=>1,
            ],
            'pageTitle'=>'Nuevo Item de Maestro',
        ]);
    }

    public function store(): void
    {
        $row = [
            'codigo'=>trim((string)($_POST['codigo'] ?? '')),
            'descripcion'=>trim((string)($_POST['descripcion'] ?? '')),
            'tipo_costo'=>trim((string)($_POST['tipo_costo'] ?? 'MAT')),
            'subtipo_costo'=>trim((string)($_POST['subtipo_costo'] ?? '')),
            'unidad'=>trim((string)($_POST['unidad'] ?? 'UND')),
            'impuesto_regla'=>trim((string)($_POST['impuesto_regla'] ?? 'IVA_RECUP')),
            'activo'=>isset($_POST['activo']) ? 1 : 0,
        ];

        if ($row['codigo']==='' || $row['descripcion']==='') {
            $this->flash('danger','Código y descripción son obligatorios.');
            $this->render('create',['row'=>$row,'pageTitle'=>'Nuevo Item de Maestro']);
            return;
        }

        try {
            $st = $this->pdo->prepare(
              "INSERT INTO maestros_catalogo
               (codigo,descripcion,tipo_costo,subtipo_costo,unidad,impuesto_regla,activo,created_by)
               VALUES (:c,:d,:t,:s,:u,:imp,:a,:uid)"
            );
            $st->execute([
                ':c'=>$row['codigo'], ':d'=>$row['descripcion'], ':t'=>$row['tipo_costo'],
                ':s'=>($row['subtipo_costo']!=='' ? $row['subtipo_costo'] : null),
                ':u'=>$row['unidad'], ':imp'=>$row['impuesto_regla'], ':a'=>$row['activo'],
                ':uid'=>$_SESSION['user']['id'] ?? null,
            ]);
            $this->flash('success','Ítem creado.');
            $this->redirect('/maestros/index');
        } catch (\PDOException $e) {
            $this->flash('danger', $e->getCode()==='23000' ? 'Código duplicado.' : ('Error al crear: '.$e->getMessage()));
            $this->render('create',['row'=>$row,'pageTitle'=>'Nuevo Item de Maestro']);
        }
    }

    /* ========= Editar ========= */
    public function edit($id): void
    {
        $row = $this->getRow((int)$id);
        if (!$row) { $this->http404('Ítem no encontrado'); }
        $this->render('edit',['row'=>$row,'pageTitle'=>'Editar Item de Maestro']);
    }

    public function update($id): void
    {
        $id = (int)$id;
        $row = [
            'codigo'=>trim((string)($_POST['codigo'] ?? '')),
            'descripcion'=>trim((string)($_POST['descripcion'] ?? '')),
            'tipo_costo'=>trim((string)($_POST['tipo_costo'] ?? 'MAT')),
            'subtipo_costo'=>trim((string)($_POST['subtipo_costo'] ?? '')),
            'unidad'=>trim((string)($_POST['unidad'] ?? 'UND')),
            'impuesto_regla'=>trim((string)($_POST['impuesto_regla'] ?? 'IVA_RECUP')),
            'activo'=>isset($_POST['activo']) ? 1 : 0,
        ];

        if ($row['codigo']==='' || $row['descripcion']==='') {
            $this->flash('danger','Código y descripción son obligatorios.');
            $existing = $this->getRow($id) ?: [];
            $this->render('edit',['row'=>array_merge($existing,$row),'pageTitle'=>'Editar Item de Maestro']);
            return;
        }

        try {
            $st = $this->pdo->prepare(
              "UPDATE maestros_catalogo
                 SET codigo=:c, descripcion=:d, tipo_costo=:t, subtipo_costo=:s,
                     unidad=:u, impuesto_regla=:imp, activo=:a, updated_by=:uid, updated_at=NOW()
               WHERE id=:id"
            );
            $st->execute([
                ':c'=>$row['codigo'], ':d'=>$row['descripcion'], ':t'=>$row['tipo_costo'],
                ':s'=>($row['subtipo_costo']!=='' ? $row['subtipo_costo'] : null),
                ':u'=>$row['unidad'], ':imp'=>$row['impuesto_regla'], ':a'=>$row['activo'],
                ':uid'=>$_SESSION['user']['id'] ?? null, ':id'=>$id,
            ]);
            $this->flash('success','Ítem actualizado.');
            $this->redirect('/maestros/index');
        } catch (\PDOException $e) {
            $this->flash('danger', $e->getCode()==='23000' ? 'Código duplicado.' : ('Error al actualizar: '.$e->getMessage()));
            $existing = $this->getRow($id) ?: [];
            $this->render('edit',['row'=>array_merge($existing,$row),'pageTitle'=>'Editar Item de Maestro']);
        }
    }

    /* ========= Activar / Desactivar ========= */
    public function toggle($id): void
    {
        $id = (int)$id;
        $row = $this->getRow($id);
        if (!$row) { $this->http404('Ítem no encontrado'); }
        $nuevo = (int)($row['activo'] ? 0 : 1);
        $st = $this->pdo->prepare("UPDATE maestros_catalogo SET activo=:a, updated_by=:u, updated_at=NOW() WHERE id=:id");
        $st->execute([':a'=>$nuevo, ':u'=>($_SESSION['user']['id'] ?? null), ':id'=>$id]);
        $this->flash('success', $nuevo ? 'Ítem activado.' : 'Ítem desactivado.');
        $this->redirect('/maestros/index');
    }

    /* ========= Helpers ========= */
    private function getRow(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM maestros_catalogo WHERE id=:id");
        $st->execute([':id'=>$id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function baseUrl(): string
    {
        $b = (string)($this->cfg['BASE_URL'] ?? '');
        $b = rtrim($b, '/');
        return $b === '' ? '' : $b;
    }

    private function render(string $view, array $vars=[]): void
    {
        extract($vars, EXTR_SKIP);
        $base = $this->baseUrl();
        $cfg  = $this->cfg;
        $u    = class_exists('Session') ? (Session::user() ?? null) : null;
        $viewFile = __DIR__ . '/../views/maestros/' . $view . '.php';
        $header   = __DIR__ . '/../views/layout/header.php';
        $footer   = __DIR__ . '/../views/layout/footer.php';
        if (is_file($header)) include $header;
        if (is_file($viewFile)) include $viewFile; else echo "<pre>Vista no encontrada: {$view}</pre>";
        if (is_file($footer)) include $footer;
    }

    private function flash(string $type, string $msg): void
    {
        if (!isset($_SESSION)) { @session_start(); }
        $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
    }

    private function redirect(string $path): void
    {
        $to = $this->baseUrl() . $path;
        header('Location: '.$to, true, 302);
        exit;
    }

    private function http404(string $msg): void
    {
        http_response_code(404);
        echo "404 Not Found";
        if (!empty($_GET['debug'])) echo "<pre>".htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')."</pre>";
        exit;
    }
}
