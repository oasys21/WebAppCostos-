<?php
// /costos/app/controllers/ProveedoresController.php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
if (is_file(__DIR__ . '/../../core/Session.php')) require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../models/Proveedores.php';
if (is_file(__DIR__ . '/../models/LogSys.php')) require_once __DIR__ . '/../models/LogSys.php';

final class ProveedoresController extends Controller
{
    private function u() {
        if (class_exists('Session') && method_exists('Session','user')) return Session::user();
        return $_SESSION['user'] ?? null;
    }
    private function can($u,$perm){ return !empty($u); }

    private function logAdd(string $accion, string $entidad, ?int $entidadId, $detalle=null): void {
        try{
            if (!class_exists('LogSys')) return;
            $u = $this->u() ?: [];
            $log = new \LogSys($this->pdo);
            $log->add(
                isset($u['id'])?(int)$u['id']:null,
                (string)($u['rut']??''),(string)($u['nombre']??''),
                (string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??''),
                $accion,$entidad,$entidadId,$detalle?json_encode($detalle,JSON_UNESCAPED_UNICODE):null
            );
        }catch(\Throwable $e){}
    }

    public function index(): void
    {
        $u = $this->u(); if(!$this->can($u,'index')){ http_response_code(403); exit('Prohibido'); }

        $q = trim((string)($_GET['q'] ?? ''));
        $activo = $_GET['activo'] ?? '';
        $activo = ($activo === '1' ? 1 : ($activo === '0' ? 0 : null));

        $m = new \Proveedores($this->pdo);
        $rows = $m->searchPaged($q, $activo, 200, 0);

        $this->view('proveedores_index', [
            'rows'=>$rows,
            'q'=>$q,
            'activoSel'=>$activo,
            'pageTitle'=>'Proveedores',
            'base'=>rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    public function create(): void
    {
        $u = $this->u(); if(!$this->can($u,'create')){ http_response_code(403); exit('Prohibido'); }
        $this->view('proveedores_create', [
            'pageTitle'=>'Nuevo Proveedor',
            'base'=>rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    public function store(): void
    {
        $u = $this->u(); if(!$this->can($u,'create')){ http_response_code(403); exit('Prohibido'); }

        if (class_exists('Session') && method_exists('Session','checkCsrf')) {
            if (!Session::checkCsrf((string)($_POST['csrf'] ?? ''))) {
                $this->logAdd('PROV_CREATE_ERR','proveedor',null,['e'=>'csrf']);
                $this->redirect('/index.php?r=proveedores/index&e=csrf');
            }
        }

        $d = $this->readPost();
        if ($d['nombre'] === '') {
            $this->redirect('/index.php?r=proveedores/index&e=nombre');
        }

        try{
            $m = new \Proveedores($this->pdo);
            $id = $m->create($d);
            $this->logAdd('PROV_CREATE','proveedor',$id,['nombre'=>$d['nombre'],'rut'=>$d['rut']]);
            $this->redirect('/index.php?r=proveedores/index&ok=created');
        }catch(\Throwable $e){
            $this->logAdd('PROV_CREATE_ERR','proveedor',null,['e'=>$e->getMessage()]);
            $this->redirect('/index.php?r=proveedores/index&e='.rawurlencode($e->getMessage()));
        }
    }

    public function edit($id=null): void
    {
        $u = $this->u(); if(!$this->can($u,'edit')){ http_response_code(403); exit('Prohibido'); }
        $id=(int)$id; if($id<=0){ $this->redirect('/index.php?r=proveedores/index&e=param'); }

        $m = new \Proveedores($this->pdo);
        $row = $m->get($id);
        if(!$row){ http_response_code(404); exit('No encontrado'); }

        $this->view('proveedores_edit', [
            'row'=>$row,
            'pageTitle'=>'Editar Proveedor',
            'base'=>rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    public function update($id=null): void
    {
        $u = $this->u(); if(!$this->can($u,'edit')){ http_response_code(403); exit('Prohibido'); }
        $id=(int)$id; if($id<=0){ $this->redirect('/index.php?r=proveedores/index&e=param'); }

        if (class_exists('Session') && method_exists('Session','checkCsrf')) {
            if (!Session::checkCsrf((string)($_POST['csrf'] ?? ''))) {
                $this->logAdd('PROV_UPDATE_ERR','proveedor',$id,['e'=>'csrf']);
                $this->redirect('/index.php?r=proveedores/index&e=csrf');
            }
        }

        $m = new \Proveedores($this->pdo);
        $before = $m->get($id) ?: [];
        $d = $this->readPost();
        try{
            $ok = $m->update($id, $d);
            if(!$ok) throw new \RuntimeException('No se pudo actualizar');

            $campos = array_keys($d);
            $cambios = [];
            foreach ($campos as $k) {
                $a = $before[$k] ?? null; $b = $d[$k] ?? null;
                $aN = $a===null?null:(string)$a; $bN = $b===null?null:(string)$b;
                if ($aN !== $bN) $cambios[] = ['campo'=>$k,'antes'=>$a,'despues'=>$b];
            }

            $this->logAdd('PROV_UPDATE','proveedor',$id,['n'=>count($cambios),'cambios'=>$cambios]);
            $this->redirect('/index.php?r=proveedores/index&ok=edited');
        }catch(\Throwable $e){
            $this->logAdd('PROV_UPDATE_ERR','proveedor',$id,['e'=>$e->getMessage()]);
            $this->redirect('/index.php?r=proveedores/index&e='.rawurlencode($e->getMessage()));
        }
    }

    public function destroy($id=null): void
    {
        $u = $this->u(); if(!$this->can($u,'delete')){ http_response_code(403); exit('Prohibido'); }
        $id=(int)$id; if($id<=0){ $this->redirect('/index.php?r=proveedores/index&e=param'); }

        try{
            $m = new \Proveedores($this->pdo);
            $row = $m->get($id);
            $ok = $m->delete($id);
            if(!$ok) throw new \RuntimeException('No se pudo eliminar');
            $this->logAdd('PROV_DELETE','proveedor',$id,['nombre'=>$row['nombre'] ?? null,'rut'=>$row['rut'] ?? null]);
            $this->redirect('/index.php?r=proveedores/index&ok=deleted');
        }catch(\Throwable $e){
            $this->logAdd('PROV_DELETE_ERR','proveedor',$id,['e'=>$e->getMessage()]);
            $this->redirect('/index.php?r=proveedores/index&e='.rawurlencode($e->getMessage()));
        }
    }

    private function readPost(): array
    {
        $f = static function($k){ return trim((string)($_POST[$k] ?? '')); };
        return [
            'nombre'=>$f('nombre'),
            'rut'=>$f('rut'),
            'razon'=>$f('razon'),
            'rubro'=>$f('rubro'),
            'direccion'=>$f('direccion'),
            'comuna'=>$f('comuna'),
            'ciudad'=>$f('ciudad'),
            'con_nom'=>$f('con_nom'),
            'con_email'=>$f('con_email'),
            'con_fono'=>$f('con_fono'),
            'rl_rut'=>$f('rl_rut'),
            'rl_nom'=>$f('rl_nom'),
            'rl_email'=>$f('rl_email'),
            'rl_fono'=>$f('rl_fono'),
            'ep_nom'=>$f('ep_nom'),
            'ep_email'=>$f('ep_email'),
            'ep_fono'=>$f('ep_fono'),
            'activo'=> (isset($_POST['activo']) && $_POST['activo']=='1') ? 1 : 0,
        ];
    }
}
