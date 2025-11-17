<?php
// /costos/app/controllers/AvancesController.php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Acl.php';
require_once __DIR__ . '/../models/AvanceCostos.php';
$__logsysModel = __DIR__ . '/../models/LogSys.php';
if (is_file($__logsysModel)) { require_once $__logsysModel; }

final class AvancesController extends Controller
{
    private ?string $projColCodigo = null;
    private ?string $projColDesc   = null;

    private ?string $puColProy = null;
    private ?string $puColUser = null;
    private ?bool   $puExists  = null;

    private function u(): array { return Session::user() ?? []; }
    private function tokEnsure(): string {
        $tok = Session::get('csrf_token');
        if (!$tok) { $tok = bin2hex(random_bytes(16)); Session::set('csrf_token', $tok); }
        return $tok;
    }
    private function tokCheck(string $postTok): void {
        $tok = (string)Session::get('csrf_token');
        if (!$postTok || !hash_equals($tok, $postTok)) { throw new RuntimeException('CSRF token inválido'); }
    }
    private function log(string $accion, array $data=[]): void {
        if (class_exists('LogSys')) {
            $lg = new LogSys($this->pdo);
            $u  = $this->u();
            $lg->add($u['id'] ?? 0, $u['idRUT'] ?? '', $u['nameuser'] ?? '',
                     $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '',
                     $accion, 'avance_costos', (int)($data['id'] ?? 0), json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }

    private function tableExists(string $name): bool {
        $st = $this->pdo->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
        ");
        $st->execute([':t'=>$name]);
        return ((int)$st->fetchColumn()) > 0;
    }
    private function columnsOf(string $table): array {
        $st = $this->pdo->prepare("
            SELECT LOWER(COLUMN_NAME)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
        ");
        $st->execute([':t'=>$table]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function detectProyectoCols(): void
    {
        if ($this->projColCodigo !== null || $this->projColDesc !== null) return;
        $cols = $this->columnsOf('proyectos');
        $candCodigo = ['codigo','codproy','cod','code','clave','id_codigo','codigo_proyecto','cod_proyecto'];
        $candDesc   = ['descripcion','nombre','titulo','detalle','glosa','descrip','desc','descripcion_proyecto'];
        foreach ($candCodigo as $c) if (in_array($c,$cols,true)) { $this->projColCodigo=$c; break; }
        foreach ($candDesc   as $c) if (in_array($c,$cols,true)) { $this->projColDesc=$c; break; }
    }
    private function detectProyectoUsuariosCols(): void
    {
        if ($this->puExists !== null) return;
        $this->puExists = $this->tableExists('proyecto_usuarios');
        if (!$this->puExists) { $this->puColProy = null; $this->puColUser = null; return; }
        $cols = $this->columnsOf('proyecto_usuarios');
        $candProy = ['proyecto_id','id_proyecto','proy_id','project_id','id_project','proyecto'];
        $candUser = ['usuario_id','user_id','id_usuario','id_user','empleado_id','persona_id','usuario'];
        foreach ($candProy as $c) if (in_array($c,$cols,true)) { $this->puColProy=$c; break; }
        foreach ($candUser as $c) if (in_array($c,$cols,true)) { $this->puColUser=$c; break; }
        if (!$this->puColProy) { foreach ($cols as $c) { if (strpos($c,'proy')!==false || strpos($c,'project')!==false) { $this->puColProy=$c; break; } } }
        if (!$this->puColUser) { foreach ($cols as $c) { if (strpos($c,'usua')!==false || strpos($c,'user')!==false || strpos($c,'emple')!==false) { $this->puColUser=$c; break; } } }
    }

    private function sqlBaseProyectos(): array
    {
        $this->detectProyectoCols();
        $codeExpr = $this->projColCodigo ? "NULLIF(p.`{$this->projColCodigo}`,'')" : "NULL";
        $descExpr = $this->projColDesc   ? "NULLIF(p.`{$this->projColDesc}`,'')"   : "NULL";
        $sql = "
            SELECT p.id,
                   COALESCE($codeExpr, CONCAT('ID ', p.id)) AS codigo,
                   COALESCE($descExpr, '') AS descripcion
            FROM proyectos p
        ";
        return [$sql, $codeExpr, $descExpr];
    }
    private function whereBusqueda(string $q): array
    {
        $this->detectProyectoCols();
        $parts = []; $par = [];
        if ($q !== '') {
            $par[':q'] = '%'.$q.'%';
            if ($this->projColCodigo) $parts[] = "p.`{$this->projColCodigo}` LIKE :q";
            if ($this->projColDesc)   $parts[] = "p.`{$this->projColDesc}` LIKE :q";
        }
        return [$parts ? (' WHERE '.implode(' OR ', $parts)) : '', $par];
    }

    private function listProyectos(string $q=''): array
    {
        [$sqlBase] = $this->sqlBaseProyectos();
        [$where, $par] = $this->whereBusqueda($q);

        $uid = (int)($this->u()['id'] ?? 0);
        $this->detectProyectoUsuariosCols();
        $canJoinPU = ($this->puExists === true) && $uid > 0 && $this->puColProy && $this->puColUser;

        $rows = [];
        if ($canJoinPU) {
            $sql  = $sqlBase . " INNER JOIN proyecto_usuarios pu ON pu.`{$this->puColProy}` = p.id AND pu.`{$this->puColUser}` = :uid"
                 . $where . " ORDER BY codigo, p.id LIMIT 200";
            $par[':uid'] = $uid;
            $st = $this->pdo->prepare($sql); $st->execute($par);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        if (empty($rows)) {
            $sql2 = $sqlBase . $where . " ORDER BY codigo, p.id LIMIT 200";
            $st2  = $this->pdo->prepare($sql2); $st2->execute($par);
            $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return array_map(fn($r)=>[
            'id'=>(int)$r['id'],
            'txt'=>trim(($r['codigo'] ?? ('ID '.$r['id'])) . ' — ' . ($r['descripcion'] ?? ''))
        ], $rows);
    }
    private function proyInfo(int $id): ?array
    {
        [$sqlBase] = $this->sqlBaseProyectos();
        $sql = "SELECT t.* FROM (".$sqlBase.") t WHERE t.id=:id LIMIT 1";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        return ['id'=>(int)$r['id'], 'codigo'=>$r['codigo'], 'descripcion'=>$r['descripcion']];
    }

    /** GET /?r=avances/index&proyecto_id=XX&from=YYYY-MM-DD&to=YYYY-MM-DD */
    public function index(): void
    {
        $proyId = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
        $from   = $_GET['from'] ?? null;
        $to     = $_GET['to']   ?? null;
        $rows   = [];
        $proy   = null;
        if ($proyId>0) {
            $mdl = new AvanceCostos($this->pdo);
            $rows = $mdl->listByProyecto($proyId, $from, $to);
            $proy = $this->proyInfo($proyId);
        }
        $this->view('avances_index', [
            'proyecto_id'=>$proyId,
            'proyecto'=>$proy,
            'rows'=>$rows,
            'from'=>$from,
            'to'=>$to,
            'proy_list'=>$this->listProyectos(''),
            'csrf'=>$this->tokEnsure()
        ]);
    }

    public function create(): void
    {
        if ($this->u()===[]) { http_response_code(403); exit('No autorizado'); }
        $proyId = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
        if ($proyId<=0) { $this->redirect('/?r=avances/index&e=Seleccione+un+proyecto'); }
        $this->view('avances_create', ['proyecto_id'=>$proyId,'csrf'=>$this->tokEnsure()]);
    }

    public function store(): void
    {
        if ($this->u()===[]) { http_response_code(403); exit('No autorizado'); }
        $this->tokCheck($_POST['csrf_token'] ?? '');
        $d = [
            'proyecto_id'       => (int)($_POST['proyecto_id'] ?? 0),
            'codigo'            => strtoupper(preg_replace('/[^0-9A-Z]/','', (string)($_POST['codigo'] ?? ''))),
            'fecha_avance'      => $_POST['fecha_avance'] ?? date('Y-m-d'),
            'cantidad_ejecutada'=> (float)($_POST['cantidad_ejecutada'] ?? 0),
            'monto_ejecutado'   => (float)($_POST['monto_ejecutado'] ?? 0),
            'usuario_id'        => $this->u()['id'] ?? null,
            'observaciones'     => $_POST['observaciones'] ?? null,
        ];
        if ($d['proyecto_id']<=0 || strlen($d['codigo'])!==10) {
            $this->redirect('/?r=avances/index&e=Datos+inv%C3%A1lidos');
        }
        $mdl = new AvanceCostos($this->pdo);
        $id  = $mdl->create($d);
        $this->log('avance.creado',['id'=>$id,'proyecto_id'=>$d['proyecto_id'],'codigo'=>$d['codigo']]);
        $this->redirect('/?r=avances/index&proyecto_id='.$d['proyecto_id'].'&ok=1');
    }

    public function edit(?int $id=null): void
    {
        if ($this->u()===[]) { http_response_code(403); exit('No autorizado'); }
        if (!$id) { $this->redirect('/?r=avances/index&e=ID+inv%C3%A1lido'); }
        $mdl = new AvanceCostos($this->pdo);
        $row = $mdl->get($id);
        if (!$row) { $this->redirect('/?r=avances/index&e=No+encontrado'); }
        $this->view('avances_edit', ['row'=>$row,'csrf'=>$this->tokEnsure()]);
    }

    public function update(?int $id=null): void
    {
        if ($this->u()===[]) { http_response_code(403); exit('No autorizado'); }
        if (!$id) { $this->redirect('/?r=avances/index&e=ID+inv%C3%A1lido'); }
        $this->tokCheck($_POST['csrf_token'] ??
