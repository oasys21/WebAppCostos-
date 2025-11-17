<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Storage.php';
if (is_file(__DIR__ . '/../../core/Session.php')) { require_once __DIR__ . '/../../core/Session.php'; }
require_once __DIR__ . '/../models/Documentos.php';
if (is_file(__DIR__ . '/../models/LogSys.php')) { require_once __DIR__ . '/../models/LogSys.php'; }

final class DocumentosController extends Controller
{
    /* ===== Seguridad mínima ===== */
    private function u() {
        if (class_exists('Session') && method_exists('Session','user')) return Session::user();
        return $_SESSION['user'] ?? null;
    }
    private function can($u,$perm){ return !empty($u); }

    /* ===== Logging ===== */
    private function logAdd(string $accion, string $entidad, ?int $entidadId, $detalle = null): void
    {
        try {
            if (!class_exists('LogSys')) return;
            $u = $this->u() ?: [];
            $log = new \LogSys($this->pdo);
            $log->add(
                isset($u['id']) ? (int)$u['id'] : null,
                (string)($u['rut'] ?? ''),
                (string)($u['nombre'] ?? ''),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                $accion, $entidad, $entidadId,
                $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null
            );
        } catch (\Throwable $e) {}
    }

    /* ===== Helpers ===== */
    private function sanitizeExt(string $name): string { return strtolower((string)pathinfo($name, PATHINFO_EXTENSION)); }
    private function mimeOf(string $tmp): string {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $fi ? (string)finfo_file($fi, $tmp) : '';
        if ($fi) finfo_close($fi);
        return $mime ?: 'application/octet-stream';
    }
    private function enumModulos(): array {
        $vals = [];
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM documentos LIKE 'modulo'");
            $row = $st ? $st->fetch(\PDO::FETCH_ASSOC) : null;
            if ($row && !empty($row['Type']) && preg_match('/^enum\((.*)\)$/i', (string)$row['Type'], $m)) {
                $vals = array_map(static fn($v)=>trim($v," '\""), explode(',', $m[1]));
                $vals = array_values(array_filter($vals, static fn($x)=>$x!==''));
            }
            $st2 = $this->pdo->query("SELECT DISTINCT modulo FROM documentos WHERE modulo IS NOT NULL AND modulo<>''");
            $rows2 = $st2 ? $st2->fetchAll(\PDO::FETCH_COLUMN, 0) : [];
            foreach ($rows2 as $v) if ($v !== '' && !in_array($v, $vals, true)) $vals[] = (string)$v;
        } catch (\Throwable $e) {}
        if (empty($vals)) $vals = ['ADQ','ALM','DOC','FIN','PRY','RRHH'];
        sort($vals);
        return $vals;
    }

    /* ===== Localización real del archivo ===== */
    public function absFileFromDoc(array $doc): ?string
    {
        $base = Storage::privateRoot($this->cfg);
        $name = (string)($doc['nombre_almacenado'] ?? '');
        if ($name === '') return null;

        $proy = (string)($doc['proyecto'] ?? '');
        $seg2 = (string)($doc['itemcosto'] ?? '');
        if ($seg2 === '') $seg2 = (string)($doc['entidad_id'] ?? '');
        $did  = (int)($doc['id'] ?? ($doc['documento_id'] ?? 0));

        $cands = [];

        $rel = isset($doc['ruta_relativa']) ? str_replace('\\','/', (string)$doc['ruta_relativa']) : '';
        if ($rel !== '') {
            $rel1 = '/' . ltrim($rel,'/'); if (substr($rel1,-1)!=='/') $rel1.='/';
            $cands[] = $rel1;
            $cands[] = preg_replace('~/v0*([1-9]\d*)/~','/v$1/',$rel1);
            $cands[] = preg_replace_callback('~/v([1-9]\d*)/~', static fn($m)=>'/v'.str_pad((string)$m[1],3,'0',STR_PAD_LEFT).'/', $rel1);
        }

        if ($proy !== '' && $seg2 !== '' && $did > 0) {
            for ($v=50; $v>=1; $v--) $cands[] = "/$proy/$seg2/$did/v$v/";
            for ($v=50; $v>=1; $v--) $cands[] = "/$proy/$seg2/$did/v".str_pad((string)$v,3,'0',STR_PAD_LEFT)."/";
        }

        foreach ($cands as $relCand) {
            if (!$relCand) continue;
            $abs = rtrim($base, DIRECTORY_SEPARATOR)
                 . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relCand,'/'))
                 . $name;
            if (is_file($abs)) return $abs;
        }

        if ($proy!=='' && $seg2!=='' && $did>0) {
            $root = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                  . str_replace('/' , DIRECTORY_SEPARATOR, "$proy/$seg2/$did/");
            $hits = glob($root . 'v*' . DIRECTORY_SEPARATOR . $name, GLOB_NOSORT);
            if ($hits) foreach ($hits as $p) if (is_file($p)) return $p;
        }
        return null;
    }

    /* ===== Listado ===== */
    public function index(): void
    {
        $u = $this->u(); if (!$this->can($u,'index')) { http_response_code(403); exit('Prohibido'); }

        $q        = trim((string)($_GET['q'] ?? ''));
        $proyecto = trim((string)($_GET['proyecto'] ?? ''));
        $estado   = trim((string)($_GET['estado'] ?? ''));
        $modulo   = trim((string)($_GET['modulo'] ?? ''));
        $catId    = (int)($_GET['categoria_id'] ?? 0);

        $sql = "SELECT d.*, c.nombre AS categoria
                  FROM documentos d
             LEFT JOIN documentos_categorias c ON c.id = d.categoria_id
                 WHERE 1=1";
        $p = [];
        if ($q!==''){ $sql.=" AND (d.titulo LIKE :q OR d.nombre_original LIKE :q)"; $p[':q'] = '%'.$q.'%'; }
        if ($proyecto!==''){ $sql.=" AND d.proyecto = :proy"; $p[':proy'] = $proyecto; }
        if ($estado!==''){ $sql.=" AND d.estado = :e"; $p[':e'] = $estado; }
        if ($modulo!==''){ $sql.=" AND d.modulo = :m"; $p[':m'] = $modulo; }
        if ($catId>0){ $sql.=" AND d.categoria_id = :c"; $p[':c'] = $catId; }
        $sql .= " ORDER BY d.id DESC LIMIT 200";

        $st = $this->pdo->prepare($sql);
        foreach ($p as $k=>$v) $st->bindValue($k, is_int($v)?$v:(string)$v, is_int($v)?\PDO::PARAM_INT:\PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $cats = [];
        try {
            $cs = $this->pdo->query("SELECT id, nombre FROM documentos_categorias WHERE (activo=1 OR activo IS NULL) ORDER BY nombre");
            $cats = $cs ? $cs->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {}

        $proyectos = [];
        try {
            require_once __DIR__ . '/../models/Proyectos.php';
            $mdl = new \Proyectos($this->pdo);
            $proyectos = method_exists($mdl,'search')
                ? $mdl->search(null, true, null, 1, 500, (int)($this->u()['id'] ?? 0))
                : $mdl->searchLight('', 500);
        } catch (\Throwable $e) {}

        $this->view('documentos_index', [
            'rows'        => $rows,
            'categorias'  => $cats,
            'proyectos'   => $proyectos,
            'modulos'     => $this->enumModulos(),
            'proySel'     => $proyecto,
            'pageTitle'   => 'Documentos',
            'base'        => rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    /* ===== Crear ===== */
    public function create(): void
    {
        $u = $this->u(); if (!$this->can($u,'create')) { http_response_code(403); exit('Prohibido'); }

        $proyectos = [];
        try {
            require_once __DIR__ . '/../models/Proyectos.php';
            $mdl = new \Proyectos($this->pdo);
            $proyectos = method_exists($mdl,'search')
                ? $mdl->search(null, true, null, 1, 500, (int)($this->u()['id'] ?? 0))
                : $mdl->searchLight('', 500);
        } catch (\Throwable $e) {}

        $cats = [];
        try {
            $st = $this->pdo->query("SELECT id, nombre FROM documentos_categorias WHERE (activo=1 OR activo IS NULL) ORDER BY nombre");
            $cats = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {}

        $this->view('documentos_create', [
            'categorias' => $cats,
            'proyectos'  => $proyectos,
            'modulos'    => $this->enumModulos(),
            'pageTitle'  => 'Nuevo documento',
            'base'       => rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    public function store(): void
    {
        $u = $this->u(); if (!$this->can($u,'create')) { http_response_code(403); exit('Prohibido'); }

        $modulo      = trim((string)($_POST['modulo'] ?? ''));
        $proyecto    = trim((string)($_POST['proyecto'] ?? ''));
        $itemcosto   = trim((string)($_POST['itemcosto'] ?? ''));
        $entidadId   = (int)($_POST['entidad_id'] ?? 0);
        $titulo      = trim((string)($_POST['titulo'] ?? ''));
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $estado      = trim((string)($_POST['estado'] ?? 'vigente'));
        $privado     = !empty($_POST['privado']) ? 1 : 0;

        // Defaults (evitar NULL)
        if ($proyecto === '')  $proyecto  = 'Sin-Proyecto';
        if ($itemcosto === '') $itemcosto = 'Sin-Item-Costo';

        if ($modulo==='' || $proyecto==='') {
            $this->logAdd('DOC_CREATE_ERR', 'documento', null, ['error'=>'Campos obligatorios faltantes']);
            $this->redirect('/index.php?r=documentos/index&e='.rawurlencode('ERROR en ¡crear!: faltan campos'));
        }

        $file = $_FILES['archivo'] ?? null;
        if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->logAdd('DOC_CREATE_ERR', 'documento', null, ['error'=>'Archivo inválido']);
            $this->redirect('/index.php?r=documentos/index&e='.rawurlencode('ERROR en ¡crear!: archivo inválido'));
        }

        $orig = (string)$file['name'];
        $tmp  = (string)$file['tmp_name'];
        $size = (int)$file['size'];
        $mime = $this->mimeOf($tmp);
        $ext  = $this->sanitizeExt($orig);

        try {
            Storage::validateSize($this->cfg, $size);
            Storage::validateExtMime($orig, $mime);

            $m = new \Documentos($this->pdo);

            $docId = (int)$m->create([
                'modulo'            => $modulo,
                'proyecto'          => $proyecto,
                'itemcosto'         => $itemcosto,
                'entidad_id'        => $entidadId ?: null,
                'titulo'            => ($titulo !== '' ? $titulo : $orig),
                'categoria_id'      => $categoriaId ?: null,
                'estado'            => $estado,
                'privado'           => $privado,
                'nombre_original'   => $orig,
                'nombre_almacenado' => '',
                'ext'               => $ext,
                'mime'              => $mime,
                'tamanio'           => $size,
                'checksum_sha256'   => '',
                'ruta_relativa'     => '',
                'emitido_en'        => null,
                'vence_en'          => null,
                'creado_por'        => (int)($u['id'] ?? 0),
            ]);
            if ($docId <= 0) throw new \RuntimeException('No se pudo crear el documento');

            $seg2 = ($itemcosto !== '' ? $itemcosto : (string)$entidadId);
            [$absDir, $relDir] = Storage::buildDirs($this->cfg, $proyecto, $seg2, $docId, 1);
            Storage::ensureDir($absDir);

            $nombreAlm = Storage::genStoredName($ext);
            $destino   = rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreAlm;
            if (!@move_uploaded_file($tmp, $destino)) {
                $msg = (error_get_last()['message'] ?? 'move_uploaded_file falló');
                throw new \RuntimeException($msg);
            }

            $m->setRutaYNombre($docId, $relDir, $nombreAlm);
            $m->addVersion([
                'documento_id'      => $docId,
                'nro_version'       => 1,
                'nombre_original'   => $orig,
                'nombre_almacenado' => $nombreAlm,
                'ext'               => $ext,
                'mime'              => $mime,
                'tamanio'           => $size,
                'checksum_sha256'   => Storage::sha256($destino),
                'ruta_relativa'     => $relDir,
                'observacion'       => '',
                'subido_por'        => (int)($u['id'] ?? 0),
            ]);

            $this->logAdd('DOC_CREATE', 'documento', $docId, [
                'proyecto'  => $proyecto,
                'itemcosto' => $itemcosto,
                'titulo'    => ($titulo !== '' ? $titulo : $orig),
            ]);

            $this->redirect('/index.php?r=documentos/index&ok=created');
        } catch (\Throwable $ex) {
            $this->logAdd('DOC_CREATE_ERR', 'documento', null, ['error'=>$ex->getMessage()]);
            $this->redirect('/index.php?r=documentos/index&e='.rawurlencode('ERROR en ¡crear!: '.$ex->getMessage()));
        }
    }

    /* ===== Editar ===== */
    public function edit($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { http_response_code(403); exit('Prohibido'); }
        $docId = (int)$docId; if ($docId<=0) { $this->redirect('/index.php?r=documentos/index&e=Parametros'); }

        $m = new \Documentos($this->pdo);
        $doc = $m->get($docId);
        if (!$doc) { http_response_code(404); exit('Documento no encontrado'); }

        $proyectos = [];
        try {
            require_once __DIR__ . '/../models/Proyectos.php';
            $mdl = new \Proyectos($this->pdo);
            $proyectos = method_exists($mdl,'search')
                ? $mdl->search(null, true, null, 1, 500, (int)($this->u()['id'] ?? 0))
                : $mdl->searchLight('', 500);
        } catch (\Throwable $e) {}

        $cats = [];
        try {
            $st = $this->pdo->prepare("SELECT id, nombre FROM documentos_categorias WHERE (activo=1 OR activo IS NULL) AND (modulo=:m OR modulo IS NULL OR modulo='') ORDER BY nombre");
            $st->execute([':m'=>(string)$doc['modulo']]);
            $cats = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}

        $this->view('documentos_edit', [
            'doc'        => $doc,
            'categorias' => $cats,
            'proyectos'  => $proyectos,
            'modulos'    => $this->enumModulos(),
            'pageTitle'  => 'Editar documento',
            'base'       => rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    public function update($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'edit')) { http_response_code(403); exit('Prohibido'); }
        $docId = (int)$docId; if ($docId<=0) { $this->redirect('/index.php?r=documentos/index&e=Parametros'); }

        $modulo    = trim((string)($_POST['modulo'] ?? ''));
        $titulo    = trim((string)($_POST['titulo'] ?? ''));
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $estado    = trim((string)($_POST['estado'] ?? 'vigente'));
        $privado   = !empty($_POST['privado']) ? 1 : 0;
        $emitido   = $_POST['emitido_en'] ?? null;
        $vence     = $_POST['vence_en'] ?? null;

        // ¡ATENCIÓN!: Proyecto e Item de costo son INMUTABLES
        $m = new \Documentos($this->pdo);
        $before = $m->get((int)$docId) ?: [];
        $proyectoFijo  = (string)($before['proyecto']  ?? 'Sin-Proyecto');
        $itemFijo      = (string)($before['itemcosto'] ?? 'Sin-Item-Costo');

        $meta = [
            'modulo'       => ($modulo !== '' ? $modulo : ($before['modulo'] ?? null)),
            'proyecto'     => $proyectoFijo,     // inmutable
            'itemcosto'    => $itemFijo,         // inmutable
            'titulo'       => $titulo,
            'categoria_id' => $categoriaId ?: null,
            'estado'       => $estado,
            'privado'      => $privado,
            'emitido_en'   => $emitido,
            'vence_en'     => $vence,
        ];

        try {
            $this->pdo->beginTransaction();

            if (!$m->updateMeta((int)$docId, $meta)) {
                throw new \RuntimeException('No se pudo actualizar meta');
            }

            // Verificación bajo bloqueo (solo informativa; no cambia proy/item)
            $stLock = $this->pdo->prepare("SELECT proyecto, itemcosto FROM documentos WHERE id=:id FOR UPDATE");
            $stLock->execute([':id'=>$docId]);

            $this->pdo->commit();

            $this->logAdd('DOC_UPDATE', 'documento', (int)$docId, [
                'proyecto'  => $proyectoFijo,
                'itemcosto' => $itemFijo,
                'nota'      => 'proyecto e itemcosto inmutables en edición',
            ]);

            $this->redirect('/index.php?r=documentos/index&ok=edited');

        } catch (\Throwable $ex) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            $this->logAdd('DOC_UPDATE_ERR', 'documento', (int)$docId, ['error'=>$ex->getMessage()]);
            $this->redirect('/index.php?r=documentos/index&e='.rawurlencode('ERROR en ¡editar!: '.$ex->getMessage()));
        }
    }

    /* ===== Versiones ===== */
    public function versions($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'versions')) { http_response_code(403); exit('Prohibido'); }
        $docId=(int)$docId; if ($docId<=0) { http_response_code(400); exit('Parámetros'); }

        $m = new \Documentos($this->pdo);
        $doc = $m->get($docId); if (!$doc) { http_response_code(404); exit('No encontrado'); }
        $rows = method_exists($m,'versions') ? $m->versions($docId) : $m->listVersions($docId);

        $this->view('documentos_versions', [
            'doc'=>$doc,'rows'=>$rows,'pageTitle'=>'Versiones',
            'base'=>rtrim((string)($this->cfg['BASE_URL'] ?? $this->cfg['base'] ?? ''), '/'),
        ]);
    }

    public function version_add($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'versions')) { http_response_code(403); exit('Prohibido'); }
        $docId=(int)$docId; if ($docId<=0) { http_response_code(400); exit('Parámetros'); }

        $file = $_FILES['archivo'] ?? null;
        if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('/index.php?r=documentos/versions/'.$docId.'&e=Archivo+inv%C3%A1lido');
        }

        $m = new \Documentos($this->pdo);
        $doc = $m->get($docId); if (!$doc) { http_response_code(404); exit('No encontrado'); }

        $orig=(string)$file['name']; $tmp=(string)$file['tmp_name']; $size=(int)$file['size'];
        $mime=$this->mimeOf($tmp); $ext=$this->sanitizeExt($orig);
        $obs = trim((string)($_POST['observacion'] ?? ''));

        try{
            Storage::validateSize($this->cfg, $size);
            Storage::validateExtMime($orig, $mime);

            $v   = method_exists($m,'maxVersion') ? ($m->maxVersion($docId)+1) : 2;

            $proy = (string)($doc['proyecto'] ?? '');
            $seg2 = (string)($doc['itemcosto'] ?? '');
            if ($seg2 === '') $seg2 = (string)($doc['entidad_id'] ?? '');

            [$absDir, $relDir] = Storage::buildDirs($this->cfg, $proy, $seg2, $docId, $v);
            Storage::ensureDir($absDir);

            $nombreAlm = Storage::genStoredName($ext);
            $destino   = rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreAlm;
            if (!@move_uploaded_file($tmp, $destino)) { throw new \RuntimeException('No se pudo mover el archivo'); }

            $m->addVersion([
                'documento_id'=>$docId,'nro_version'=>$v,'nombre_original'=>$orig,'nombre_almacenado'=>$nombreAlm,
                'ext'=>$ext,'mime'=>$mime,'tamanio'=>$size,'checksum_sha256'=>Storage::sha256($destino),
                'ruta_relativa'=>$relDir,'observacion'=>$obs,'subido_por'=>(int)($u['id'] ?? 0),
            ]);

            $this->logAdd('DOC_VERSION', 'documento', $docId, ['nro_version'=>$v]);
            $this->redirect('/index.php?r=documentos/versions/'.$docId.'&ok=1');
        }catch(\Throwable $ex){
            $this->logAdd('DOC_VERSION_ERR', 'documento', $docId, ['error'=>$ex->getMessage()]);
            $this->redirect('/index.php?r=documentos/versions/'.$docId.'&e='.rawurlencode($ex->getMessage()));
        }
    }

    /* ===== Descargar / Ver ===== */
    public function download($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'download')) { http_response_code(403); exit('Prohibido'); }
        $docId=(int)$docId; if ($docId<=0){ http_response_code(400); exit('Parámetros'); }

        $m = new \Documentos($this->pdo);
        $doc = $m->get($docId); if (!$doc){ http_response_code(404); exit('No encontrado'); }

        $abs = $this->absFileFromDoc($doc);
        if (!$abs || !is_file($abs)) { http_response_code(404); exit('Archivo no existe'); }

        $mime = (string)($doc['mime'] ?? 'application/octet-stream');
        $name = (string)($doc['nombre_original'] ?? ('doc_'.$docId));
        if (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string)filesize($abs));
        $this->logAdd('DOC_DOWNLOAD', 'documento', $docId, ['mime'=>$mime,'bytes'=>filesize($abs)]);
        readfile($abs); exit;
    }

    public function preview($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'preview')) { http_response_code(403); exit('Prohibido'); }
        $docId=(int)$docId; if ($docId<=0){ http_response_code(400); exit('Parámetros'); }

        $m = new \Documentos($this->pdo);
        $doc = $m->get($docId); if (!$doc){ http_response_code(404); exit('No encontrado'); }

        $abs = $this->absFileFromDoc($doc);
        if (!$abs || !is_file($abs)) { http_response_code(404); exit('Archivo no existe'); }

        $mime = (string)($doc['mime'] ?? 'application/octet-stream');
        $name = (string)($doc['nombre_original'] ?? ('doc_'.$docId));
        if (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string)filesize($abs));
        $this->logAdd('DOC_PREVIEW', 'documento', $docId, ['mime'=>$mime,'bytes'=>filesize($abs)]);
        readfile($abs); exit;
    }

    /* ===== Eliminar (borra carpeta + BD) ===== */
    public function destroy($docId = null): void
    {
        $u = $this->u(); if (!$this->can($u,'delete')) { http_response_code(403); exit('Prohibido'); }
        $docId=(int)$docId; if ($docId<=0){ http_response_code(400); exit('Parámetros'); }

        $m   = new \Documentos($this->pdo);
        $doc = $m->get($docId); if (!$doc){ http_response_code(404); exit('No encontrado'); }

        $base = rtrim(Storage::privateRoot($this->cfg), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $docRootAbs = '';

        $rel = isset($doc['ruta_relativa']) ? str_replace('\\','/', (string)$doc['ruta_relativa']) : '';
        if ($rel !== '') {
            $rel1 = '/' . ltrim($rel,'/');
            $relDoc = preg_replace('~/v\d{1,3}/$~','/',$rel1);
            $docRootAbs = $base . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relDoc), DIRECTORY_SEPARATOR);
        }
        if ($docRootAbs === '') {
            $proy = (string)($doc['proyecto'] ?? '');
            $seg2 = (string)($doc['itemcosto'] ?? '');
            if ($seg2==='') $seg2 = (string)($doc['entidad_id'] ?? '');
            $docRootAbs = $base . str_replace('/', DIRECTORY_SEPARATOR, "$proy/$seg2/$docId/");
        }

        $this->rrmdir($docRootAbs);
        $m->deleteCascade($docId);

        $this->logAdd('DOC_DELETE', 'documento', $docId, [
            'ruta_relativa'     => (string)($doc['ruta_relativa'] ?? ''),
            'nombre_almacenado' => (string)($doc['nombre_almacenado'] ?? ''),
        ]);

        $this->redirect('/index.php?r=documentos/index&ok=deleted');
    }

    private function rrmdir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) return;
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }

    /* ===== APIs auxiliares ===== */
    public function categorias_api(): void
    {
        $u = $this->u();
        header('Content-Type: application/json; charset=utf-8');
        if (empty($u)) { http_response_code(403); echo json_encode(['ok'=>false,'e'=>'auth']); return; }

        $mod = trim((string)($_GET['modulo'] ?? ''));
        try {
            $sql = "SELECT id, nombre, IFNULL(modulo,'') AS modulo
                      FROM documentos_categorias
                     WHERE (activo=1 OR activo IS NULL)";
            $p = [];
            if ($mod !== '') { $sql .= " AND (modulo=:m OR modulo IS NULL OR modulo='')"; $p[':m']=$mod; }
            $sql .= " ORDER BY nombre";
            $st = $this->pdo->prepare($sql);
            foreach ($p as $k=>$v) $st->bindValue($k,$v,\PDO::PARAM_STR);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500); echo json_encode(['ok'=>false,'e'=>$e->getMessage()]);
        }
        exit;
    }

    public function proyectos_json(): void
    {
        $u = $this->u();
        header('Content-Type: application/json; charset=utf-8');
        if (empty($u)) { http_response_code(403); echo json_encode(['ok'=>false,'e'=>'auth']); return; }
        try {
            require_once __DIR__ . '/../models/Proyectos.php';
            $mdl = new \Proyectos($this->pdo);
            $rows = method_exists($mdl,'search')
                ? $mdl->search(null, true, null, 1, 500, (int)($this->u()['id'] ?? 0))
                : $mdl->searchLight('', 500);
            echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $ex) {
            http_response_code(500); echo json_encode(['ok'=>false,'e'=>$ex->getMessage()]);
        }
        exit;
    }

    /** Ítems de costo (nivel ítem) del proyecto seleccionado. GET: proyecto=codigo_proy */
    public function items_api(): void
    {
        $u = $this->u();
        header('Content-Type: application/json; charset=utf-8');
        if (empty($u)) { http_response_code(403); echo json_encode(['ok'=>false,'e'=>'auth']); return; }

        $cod = trim((string)($_GET['proyecto'] ?? ''));
        if ($cod === '' || $cod === 'Sin-Proyecto') { echo json_encode(['ok'=>true,'data'=>[]], JSON_UNESCAPED_UNICODE); return; }

        $pid = 0;
        try {
            $st = $this->pdo->prepare("SELECT id FROM proyectos WHERE codigo_proy=:c LIMIT 1");
            $st->execute([':c'=>$cod]);
            $pid = (int)($st->fetchColumn() ?: 0);
        } catch (\Throwable $e) { $pid = 0; }

        if ($pid <= 0) { echo json_encode(['ok'=>true,'data'=>[]], JSON_UNESCAPED_UNICODE); return; }

        try {
            $data = [];
            if (class_exists('ProyectoCostos')) {
                require_once __DIR__ . '/../models/ProyectoCostos.php';
                $mdl = new \ProyectoCostos($this->pdo);
                if (method_exists($mdl,'allItemsForProject')) {
                    $rows = $mdl->allItemsForProject((int)$pid);
                    foreach ($rows as $r) {
                        $item = (string)($r['item'] ?? '');
                        if ($item === '' || $item === '0000') continue;
                        $data[] = [
                            'codigo'      => (string)($r['codigo'] ?? ''),
                            'familia'     => (string)($r['familia'] ?? ''),
                            'grupo'       => (string)($r['grupo'] ?? ''),
                            'item'        => (string)($r['item'] ?? ''),
                            'descripcion' => (string)($r['descripcion'] ?? ''),
                            'unidad'      => (string)($r['unidad'] ?? ''),
                        ];
                    }
                }
            }
            echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $ex) {
            http_response_code(500); echo json_encode(['ok'=>false,'e'=>$ex->getMessage()]);
        }
        exit;
    }
}
