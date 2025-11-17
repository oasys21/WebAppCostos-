<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Caja.php';
@require_once __DIR__ . '/../models/Proyectos.php';
@require_once __DIR__ . '/../models/ProyectoCostos.php';
@require_once __DIR__ . '/../models/LogSys.php';
// Acl en /costos/core/acl.php (ruta correcta desde app/controllers)
(function(){
    $p = dirname(__DIR__, 2) . '/core/acl.php'; // /costos/core/acl.php
    if (is_file($p)) { require_once $p; }
})();

class CajaController extends Controller
{
    private Caja $cajaModel;
    private string $tz = 'America/Santiago';

    public function __construct(PDO $pdo, array $cfg = [])
    {
        parent::__construct($pdo, $cfg);
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['user']['id'])) { $this->redirect('/auth/login'); }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->cajaModel = new Caja($this->pdo);
        date_default_timezone_set($this->tz);
    }

    /* =========================
       Helpers
       ========================= */

    private function base(): string { return rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/'); }
    private function currentUserId(): int { return (int)($_SESSION['user']['id'] ?? 0); }

    private function nowCl(): DateTimeImmutable
    {
        $key = '_official_now_cl';
        if (isset($_SESSION[$key]['iso'], $_SESSION[$key]['ts']) && (time() - $_SESSION[$key]['ts'] < 60)) {
            return new DateTimeImmutable($_SESSION[$key]['iso'], new DateTimeZone($this->tz));
        }
        $iso = null;
        try {
            $ch = curl_init('https://worldtimeapi.org/api/timezone/America/Santiago');
            curl_setopt_array($ch, [ CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>2, CURLOPT_CONNECTTIMEOUT=>2 ]);
            $resp = curl_exec($ch);
            if ($resp !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                $json = json_decode($resp, true);
                if (!empty($json['datetime'])) { $iso = $json['datetime']; }
            }
            curl_close($ch);
        } catch (Throwable $e) {}
        if (!$iso) $iso = (new DateTimeImmutable('now', new DateTimeZone($this->tz)))->format('c');
        $_SESSION[$key] = ['iso'=>$iso,'ts'=>time()];
        return new DateTimeImmutable($iso, new DateTimeZone($this->tz));
    }

    /** Auditoría compat: intenta múltiples firmas comunes de LogSys; si no, error_log */
    private function logJson(string $accion, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (class_exists('LogSys')) {
            $candidatos = [
                ['method' => 'log',        'args' => function() use($accion,$json){ return ['caja_chica',$accion,$json]; }],
                ['method' => 'registrar',  'args' => function() use($accion,$json){ return ['caja_chica',$accion,$json]; }],
                ['method' => 'add',        'args' => function() use($accion,$json){ return ['caja_chica',$accion,$json]; }],
                ['method' => 'write',      'args' => function() use($accion,$json){ return ['caja_chica',$accion,$json]; }],
                ['method' => 'addLog',     'args' => function() use($accion,$json){ return ['caja_chica',$accion,$json,null]; }],
                ['method' => 'save',       'args' => function() use($accion,$json){ return ['caja_chica',$accion,$json,null]; }],
            ];
            foreach ($candidatos as $c) {
                if (method_exists('LogSys', $c['method'])) {
                    try { call_user_func_array(['LogSys',$c['method']], $c['args']()); return; } catch (\Throwable $e) {}
                }
            }
        }
        error_log("[caja_chica][$accion] $json");
    }

    /** Normaliza monto "latam" sin decimales: deja solo dígitos → float entero */
    private function parseMontoEntero(string $raw): float
    {
        $digits = preg_replace('/[^\d]/', '', (string)$raw);
        if ($digits === '' || $digits === null) return 0.0;
        return (float)$digits;
    }

    // ---- JSON helpers (APIs internas) ----
    private function jsonHeader(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        // evita respuestas cacheadas en DEV/PROD
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    private function jsonOut($payload, int $code = 200): void {
        $this->jsonHeader();
        http_response_code($code);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    private function jsonErr(string $msg, int $code = 400): void {
        $this->jsonOut(['error' => $msg], $code);
    }

    // ---- Permisos ----
    private function hasPerm(string $perm): bool
    {
        if (class_exists('Acl') && method_exists('Acl','can')) {
            $user = $_SESSION['user'] ?? [];
            return (bool)Acl::can($user, $perm);
        }
        $perfil = (string)($_SESSION['user']['perfil'] ?? '');
        if ($perfil === 'ADM') return true;
        if (!empty($_SESSION['permisos']) && is_array($_SESSION['permisos'])) {
            return in_array($perm, $_SESSION['permisos'], true);
        }
        return false;
    }
    private function isAdminOrCon(): bool
    {
        $perfil = (string)($_SESSION['user']['perfil'] ?? '');
        return in_array($perfil, ['ADM','CON'], true);
    }

    /* =========================
       Vistas
       ========================= */

    // GET /caja
    public function index(): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $periodo = $this->cajaModel->asegurarPeriodoAbierto($uid, $now);

        $anio = isset($_GET['anio']) ? max(2000, (int)$_GET['anio']) : (int)$now->format('Y');
        $mes  = isset($_GET['mes'])  ? min(12, max(1, (int)$_GET['mes'])) : (int)$now->format('n');
        $periodoSel = $this->cajaModel->getPeriodo($uid, $anio, $mes) ?? $periodo;

        $filters = [
            'q'        => trim((string)($_GET['q'] ?? '')),
            'tipo'     => strtoupper(trim((string)($_GET['tipo'] ?? ''))),
            'estado'   => strtoupper(trim((string)($_GET['estado'] ?? ''))),
            'doc_tipo' => strtoupper(trim((string)($_GET['doc_tipo'] ?? ''))),
            'orden'    => strtoupper(trim((string)($_GET['orden'] ?? 'DESC'))),
        ];

        $movs = $this->cajaModel->listarMovimientosFiltrado((int)$periodoSel['id'], $filters);

        $subIng = 0.0; $subEgr = 0.0;
        foreach ($movs as $r) {
            if (in_array($r['tipo'], ['INGRESO','TRASPASO_IN'], true)) { $subIng += (float)$r['monto']; }
            if (in_array($r['tipo'], ['EGRESO','TRASPASO_OUT'], true)) { $subEgr += (float)$r['monto']; }
        }
        $subSaldo = $subIng - $subEgr;

        // Usamos view() (sin layout) para no tocar el framework;
        // jQuery se inyecta en vistas create/edit donde hace falta.
        $this->view('caja_index', [
            'base'         => $this->base(),
            'periodo'      => $periodoSel,
            'movimientos'  => $movs,
            'editable'     => $this->cajaModel->periodoEditable($periodoSel, $now),
            'now_iso'      => $now->format('c'),
            'filters'      => $filters,
            'sub_ingresos' => $subIng,
            'sub_egresos'  => $subEgr,
            'sub_saldo'    => $subSaldo,
            'result_count' => count($movs),
            'can_ingresar' => ($this->isAdminOrCon() && $this->hasPerm('ADQ_DEL')),
        ]);
    }

    // GET /caja/imprimir
    public function imprimir(): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $anio = isset($_GET['anio']) ? max(2000, (int)$_GET['anio']) : (int)$now->format('Y');
        $mes  = isset($_GET['mes'])  ? min(12, max(1, (int)$_GET['mes'])) : (int)$now->format('n');

        $periodo = $this->cajaModel->getPeriodo($uid, $anio, $mes);
        if (!$periodo) { $_SESSION['flash_error'] = 'Periodo no encontrado.'; $this->redirect('/caja'); }

        $filters = [
            'q'        => trim((string)($_GET['q'] ?? '')),
            'tipo'     => strtoupper(trim((string)($_GET['tipo'] ?? ''))),
            'estado'   => strtoupper(trim((string)($_GET['estado'] ?? ''))),
            'doc_tipo' => strtoupper(trim((string)($_GET['doc_tipo'] ?? ''))),
            'orden'    => 'ASC',
        ];

        $movs = $this->cajaModel->listarMovimientosFiltrado((int)$periodo['id'], $filters);

        $subIng = 0.0; $subEgr = 0.0;
        foreach ($movs as $r) {
            if (in_array($r['tipo'], ['INGRESO','TRASPASO_IN'], true)) { $subIng += (float)$r['monto']; }
            if (in_array($r['tipo'], ['EGRESO','TRASPASO_OUT'], true)) { $subEgr += (float)$r['monto']; }
        }
        $subSaldo = $subIng - $subEgr;

        $this->view('caja_print', [
            'base'         => $this->base(),
            'periodo'      => $periodo,
            'movimientos'  => $movs,
            'filters'      => $filters,
            'sub_ingresos' => $subIng,
            'sub_egresos'  => $subEgr,
            'sub_saldo'    => $subSaldo,
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'usuario'      => $_SESSION['user'] ?? [],
        ]);
    }

    // GET /caja/create?caja_id=...
    public function create(): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $cajaId = (int)($_GET['caja_id'] ?? 0);
        if ($cajaId <= 0) {
            $periodo = $this->cajaModel->asegurarPeriodoAbierto($uid, $now);
            $cajaId  = (int)$periodo['id'];
        } else {
            $periodo = $this->cajaModel->getById($cajaId);
        }

        if (!$this->cajaModel->periodoEditable($periodo, $now)) {
            $_SESSION['flash_error'] = 'El periodo no es editable.'; $this->redirect('/caja');
        }

        $this->view('caja_form', [
            'base' => $this->base(),
            'mode' => 'create',
            'caja' => $periodo,
            'row'  => null,
            'now'  => $now->format('Y-m-d\TH:i'),
        ]);
    }

    // POST /caja/store
    public function store(): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $cajaId  = (int)($_POST['caja_id'] ?? 0);
        $periodo = $this->cajaModel->getById($cajaId);
        if (!$periodo || (int)$periodo['usuario_id'] !== $uid) {
            $_SESSION['flash_error'] = 'Caja inválida.'; $this->redirect('/caja');
        }
        if (!$this->cajaModel->periodoEditable($periodo, $now)) {
            $_SESSION['flash_error'] = 'El periodo no es editable.'; $this->redirect('/caja');
        }

        $tipo   = strtoupper(trim((string)($_POST['tipo'] ?? 'EGRESO')));
        $permit = ['INGRESO','EGRESO','TRASPASO_IN','TRASPASO_OUT','AJUSTE'];
        if (!in_array($tipo, $permit, true)) $tipo = 'EGRESO';

        $estado         = 'PENDIENTE';
        $fecha_mov      = $now->format('Y-m-d H:i:s');
        $fecha_doc      = !empty($_POST['fecha_doc']) ? $_POST['fecha_doc'] : null;
        $numero_doc     = trim((string)($_POST['numero_doc'] ?? '')) ?: null;
        $documento_tipo = strtoupper(trim((string)($_POST['documento_tipo'] ?? 'OTRO')));
        $monto          = $this->parseMontoEntero((string)($_POST['monto'] ?? '0'));
        if ($monto < 0) $monto = 0;
        $descripcion    = trim((string)($_POST['descripcion'] ?? '')) ?: null;
        $proyCostoId    = isset($_POST['proyecto_costo_id']) ? (int)$_POST['proyecto_costo_id'] : null;

        $medio_ingreso = null; $banco = null; $referencia = null;
        if (in_array($tipo, ['INGRESO','TRASPASO_IN'], true)) {
            $medio_ingreso = $_POST['medio_ingreso'] ?? null;
            $banco         = $_POST['banco'] ?? null;
            $referencia    = $_POST['referencia_pago'] ?? null;
        }

        $data = [
            'caja_id'           => $cajaId,
            'usuario_id'        => $uid,
            'proyecto_costo_id' => $proyCostoId,
            'tipo'              => $tipo,
            'estado'            => $estado,
            'fecha_mov'         => $fecha_mov,
            'fecha_doc'         => $fecha_doc,
            'numero_doc'        => $numero_doc,
            'documento_tipo'    => $documento_tipo,
            'monto'             => $monto,
            'descripcion'       => $descripcion,
            'medio_ingreso'     => $medio_ingreso,
            'banco'             => $banco,
            'referencia_pago'   => $referencia
        ];

        $id = $this->cajaModel->crearMovimiento($data);
        $this->logJson('insert', [[ 'tabla'=>'caja_chica_movimientos','id'=>$id,'antes'=>new stdClass(),'despues'=>$data ]]);

        if (!empty($_FILES['doc_file']['tmp_name'])) {
            $this->handleUpload((int)$id, $_FILES['doc_file']);
        }

        $_SESSION['flash_ok'] = 'Movimiento creado.';
        $this->redirect('/caja');
    }

    // GET /caja/edit/{id}
    public function edit(string $id): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $id  = (int)$id;
        $row = $this->cajaModel->obtenerMovimiento($id);
        if (!$row) { $_SESSION['flash_error']='Movimiento no encontrado.'; $this->redirect('/caja'); }

        // Preselección de proyecto en el combo
        if (!empty($row['proyecto_costo_id'])) {
            $st = $this->pdo->prepare("SELECT proyecto_id FROM proyecto_costos WHERE id = :id");
            $st->execute([':id' => (int)$row['proyecto_costo_id']]);
            $row['proyecto_id'] = (int)($st->fetchColumn() ?: 0);
        }

        $caja = $this->cajaModel->getById((int)$row['caja_id']);
        if ((int)$caja['usuario_id'] !== $uid || !$this->cajaModel->periodoEditable($caja, $now)) {
            $_SESSION['flash_error']='No editable.'; $this->redirect('/caja');
        }

        $this->view('caja_edit', [
            'base' => $this->base(),
            'mode' => 'edit',
            'caja' => $caja,
            'row'  => $row,
            'now'  => $now->format('Y-m-d\TH:i'),
        ]);
    }

    // POST /caja/update/{id}
    public function update(string $id): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $id = (int)$id;
        $before = $this->cajaModel->obtenerMovimiento($id);
        if (!$before) { $_SESSION['flash_error']='Movimiento no encontrado.'; $this->redirect('/caja'); }

        $caja = $this->cajaModel->getById((int)$before['caja_id']);
        if ((int)$caja['usuario_id'] !== $uid || !$this->cajaModel->periodoEditable($caja, $now)) {
            $_SESSION['flash_error']='No editable.'; $this->redirect('/caja');
        }

        $tipo = strtoupper(trim((string)($_POST['tipo'] ?? $before['tipo'])));
        $permit = ['INGRESO','EGRESO','TRASPASO_IN','TRASPASO_OUT','AJUSTE'];
        if (!in_array($tipo, $permit, true)) $tipo = $before['tipo'];

        $estado = strtoupper(trim((string)($_POST['estado'] ?? $before['estado'])));
        if (!in_array($estado, ['PENDIENTE','APROBADO','ANULADO'], true)) $estado = $before['estado'];

        $fecha_doc      = !empty($_POST['fecha_doc']) ? $_POST['fecha_doc'] : null;
        $numero_doc     = trim((string)($_POST['numero_doc'] ?? '')) ?: null;
        $documento_tipo = strtoupper(trim((string)($_POST['documento_tipo'] ?? $before['documento_tipo'])));
        $monto          = $this->parseMontoEntero((string)($_POST['monto'] ?? (string)$before['monto']));
        if ($monto < 0) $monto = 0;
        $descripcion    = trim((string)($_POST['descripcion'] ?? '')) ?: null;
        $proyCostoId    = isset($_POST['proyecto_costo_id']) ? (int)$_POST['proyecto_costo_id'] : ($before['proyecto_costo_id'] ?? null);

        $medio_ingreso = null; $banco = null; $referencia = null;
        if (in_array($tipo, ['INGRESO','TRASPASO_IN'], true)) {
            $medio_ingreso = $_POST['medio_ingreso'] ?? $before['medio_ingreso'];
            $banco         = $_POST['banco'] ?? $before['banco'];
            $referencia    = $_POST['referencia_pago'] ?? $before['referencia_pago'];
        }

        $data = [
            'proyecto_costo_id' => $proyCostoId,
            'tipo'              => $tipo,
            'estado'            => $estado,
            'fecha_mov'         => $before['fecha_mov'],
            'fecha_doc'         => $fecha_doc,
            'numero_doc'        => $numero_doc,
            'documento_tipo'    => $documento_tipo,
            'monto'             => $monto,
            'descripcion'       => $descripcion,
            'medio_ingreso'     => $medio_ingreso,
            'banco'             => $banco,
            'referencia_pago'   => $referencia
        ];

        $this->cajaModel->actualizarMovimiento($id, $data);
        $this->logJson('update', [[ 'tabla'=>'caja_chica_movimientos','id'=>$id,'antes'=>$before,'despues'=>$data ]]);

        if (!empty($_FILES['doc_file']['tmp_name'])) {
            $this->handleUpload((int)$id, $_FILES['doc_file']);
        }

        $_SESSION['flash_ok'] = 'Movimiento actualizado.';
        $this->redirect('/caja');
    }

    // GET /caja/show/{id}
    public function show(string $id): void
    {
        $uid = $this->currentUserId();
        $id  = (int)$id;
        $row = $this->cajaModel->obtenerMovimiento($id);
        if (!$row) { $_SESSION['flash_error']='Movimiento no encontrado.'; $this->redirect('/caja'); }
        $caja = $this->cajaModel->getById((int)$row['caja_id']);
        if ((int)$caja['usuario_id'] !== $uid) { $_SESSION['flash_error']='Sin permiso.'; $this->redirect('/caja'); }

        $adj = $this->cajaModel->listarAdjuntos($id);
        $this->view('caja_show', [
            'base'     => $this->base(),
            'caja'     => $caja,
            'row'      => $row,
            'adjuntos' => $adj
        ]);
    }

    // POST /caja/delete/{id}
    public function delete(string $id): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $id = (int)$id;
        $row = $this->cajaModel->obtenerMovimiento($id);
        if (!$row) { $_SESSION['flash_error']='Movimiento no encontrado.'; $this->redirect('/caja'); }

        $caja = $this->cajaModel->getById((int)$row['caja_id']);
        if ((int)$caja['usuario_id'] !== $uid || !$this->cajaModel->periodoEditable($caja, $now)) {
            $_SESSION['flash_error']='No editable.'; $this->redirect('/caja');
        }

        $this->cajaModel->eliminarMovimiento($id);
        $this->logJson('delete', [[ 'tabla'=>'caja_chica_movimientos','id'=>$id,'antes'=>$row,'despues'=>new stdClass() ]]);

        $_SESSION['flash_ok'] = 'Movimiento eliminado.';
        $this->redirect('/caja');
    }

    // POST /caja/cerrarDefinitivo
    public function cerrarDefinitivo(): void
    {
        $uid = $this->currentUserId();
        $now = $this->nowCl();

        $cajaId = (int)($_POST['caja_id'] ?? 0);
        $caja   = $this->cajaModel->getById($cajaId);
        if (!$caja || (int)$caja['usuario_id'] !== $uid) {
            $_SESSION['flash_error']='Caja inválida.'; $this->redirect('/caja');
        }

        $finMes = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', $caja['anio'].'-'.$caja['mes'].'-1 23:59:59')
                 ->modify('last day of this month')->setTimezone(new DateTimeZone($this->tz));
        if ($now < $finMes->modify('+30 days')) {
            $_SESSION['flash_error'] = 'Aún no corresponde cierre definitivo (+30 días).';
            $this->redirect('/caja');
        }

        $this->cajaModel->marcarCierreDefinitivo($cajaId);
        $this->logJson('cierre_definitivo', [[ 'caja_id' => $cajaId ]]);

        $_SESSION['flash_ok'] = 'Caja cerrada definitivamente.';
        $this->redirect('/caja');
    }

    // POST /caja/upload
    public function upload(): void
    {
        $uid   = $this->currentUserId();
        $movId = (int)($_POST['movimiento_id'] ?? 0);
        if ($movId <= 0 || empty($_FILES['doc_file'])) {
            http_response_code(400); echo 'Datos inválidos'; return;
        }
        $row = $this->cajaModel->obtenerMovimiento($movId);
        if (!$row) { http_response_code(404); echo 'Movimiento no existe'; return; }

        $caja = $this->cajaModel->getById((int)$row['caja_id']);
        if ((int)$caja['usuario_id'] !== $uid) { http_response_code(403); echo 'Sin permiso'; return; }

        $ok = $this->handleUpload((int)$movId, $_FILES['doc_file']);
        $this->logJson('upload', [[ 'movimiento_id'=>$movId, 'ok'=>$ok, 'archivo'=>($_FILES['doc_file']['name'] ?? '') ]]);

        $_SESSION['flash_'.($ok?'ok':'error')] = $ok ? 'Archivo subido.' : 'Error al subir archivo.';
        $this->redirect('/caja/show/'.$movId);
    }

    private function handleUpload(int $movId, array $file): bool
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;

        $tmp  = $file['tmp_name'];
        $size = (int)$file['size'];
        $mime = mime_content_type($tmp) ?: ($file['type'] ?? 'application/octet-stream');
        $allowed = ['application/pdf','image/jpeg','image/png'];

        if (!in_array($mime, $allowed, true)) return false;
        if ($size <= 0 || $size > 20 * 1024 * 1024) return false;

        $hash = hash_file('sha256', $tmp);

        $st = $this->pdo->prepare("SELECT m.*, pc.codigo
                                     FROM caja_chica_movimientos m
                                LEFT JOIN proyecto_costos pc ON pc.id = m.proyecto_costo_id
                                    WHERE m.id = :id");
        $st->execute([':id' => $movId]);
        $mov = $st->fetch();
        if (!$mov) return false;

        $usuarioId = (int)$mov['usuario_id'];
        $yyyymm    = date('Ym', strtotime($mov['fecha_mov']));
        $codigo    = $mov['codigo'] ?: 'SINCODE';
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!$ext) { $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/jpeg' ? 'jpg' : 'png'); }

        $relDir  = "cajachica/{$usuarioId}/{$yyyymm}/{$codigo}/";
        $baseDir = rtrim(dirname(__DIR__, 2) . '/storage', '/');
        $fullDir = $baseDir . '/' . $relDir;

        if (!is_dir($fullDir) && !mkdir($fullDir, 0775, true)) return false;

        $safe  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $fname = $safe . '_' . substr($hash, 0, 8) . '.' . $ext;
        $dest  = $fullDir . $fname;

        if (!move_uploaded_file($tmp, $dest)) return false;

        $this->cajaModel->insertarAdjunto([
            'movimiento_id' => $movId,
            'nombre_archivo'=> $fname,
            'ruta_relativa' => $relDir,
            'mime_type'     => $mime,
            'extension'     => $ext,
            'tamano_bytes'  => $size,
            'hash_sha256'   => $hash,
            'paginas'       => null
        ]);

        return true;
    }

    /* =========================
       APIs auxiliares del módulo (para selects)
       ========================= */
// GET /caja/apiproyectos?term=
public function apiproyectos(): void
{
    header('Content-Type: application/json; charset=utf-8');
    $term = trim((string)($_GET['term'] ?? ''));

    // SQL mínimo, sin joins ni modelos: lista proyectos activos
    $sql = "SELECT p.id, COALESCE(NULLIF(p.nombre,''), CONCAT('Proyecto ', p.id)) AS nombre
              FROM proyectos p
             WHERE p.activo = 1
               AND (:term = '' OR p.nombre LIKE CONCAT('%', :term, '%') OR p.codigo_proy LIKE CONCAT('%', :term, '%'))
          ORDER BY p.nombre ASC
             LIMIT 200";
    $st = $this->pdo->prepare($sql);
    $st->bindValue(':term', $term, PDO::PARAM_STR);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
 // GET /caja/apiproyectoitems?proyecto_id=&term=
public function apiproyectoitems(): void
{
    header('Content-Type: application/json; charset=utf-8');
    $pid  = (int)($_GET['proyecto_id'] ?? 0);
    $term = trim((string)($_GET['term'] ?? ''));
    if ($pid <= 0) { http_response_code(422); echo json_encode(['error'=>'proyecto_id requerido']); return; }

    $sql = "SELECT pc.id AS proyecto_costo_id,
                   pc.codigo,
                   COALESCE(pc.costo_glosa,'') AS glosa
              FROM proyecto_costos pc
             WHERE pc.proyecto_id = :pid
               AND (:term = '' OR pc.codigo LIKE CONCAT(:term, '%') OR pc.costo_glosa LIKE CONCAT('%', :term, '%'))
          ORDER BY pc.codigo ASC
             LIMIT 500";
    $st = $this->pdo->prepare($sql);
    $st->bindValue(':pid',  $pid, PDO::PARAM_INT);
    $st->bindValue(':term', $term, PDO::PARAM_STR);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['label'] = trim(($r['codigo'] ?? '') . ' - ' . ($r['glosa'] ?? ''), ' -');
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
    /* =========================
       Descarga/visualización segura de adjuntos
       ========================= */

    // GET /caja/archivo/{id}
    public function archivo(string $id): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if ($uid <= 0) { http_response_code(302); header('Location: '.$this->base().'/auth/login'); return; }

        $adjId = (int)$id;
        if ($adjId <= 0) { http_response_code(400); echo 'ID inválido'; return; }

        $sql = "SELECT a.id, a.movimiento_id, a.nombre_archivo, a.ruta_relativa,
                       a.mime_type, a.extension, a.tamano_bytes, a.hash_sha256,
                       m.usuario_id
                  FROM caja_chica_adjuntos a
                  JOIN caja_chica_movimientos m ON m.id = a.movimiento_id
                 WHERE a.id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id'=>$adjId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) { http_response_code(404); echo 'Archivo no existe'; return; }

        $isOwner = ((int)$a['usuario_id'] === $uid);
        $isAdmin = $this->hasPerm('ADQ_DEL');

        if (!$isOwner && !$isAdmin) {
            http_response_code(403); echo 'Forbidden'; return;
        }

        $baseDir = rtrim(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim((string)$a['ruta_relativa'], '/\\') . '/' . (string)$a['nombre_archivo']);
        $full = $baseDir . $relPath;

        $realBase = realpath($baseDir);
        $realFile = $full && file_exists($full) ? realpath($full) : false;
        if (!$realBase || !$realFile || strpos($realFile, $realBase) !== 0) {
            http_response_code(404); echo 'No encontrado'; return;
        }

        $etag = !empty($a['hash_sha256']) ? ('W/"'.substr($a['hash_sha256'],0,32).'"') : ('W/"'.md5($realFile.filemtime($realFile).filesize($realFile)).'"');
        header('ETag: '.$etag);
        header('Cache-Control: private, max-age=604800');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304); return;
        }

        $mime = $a['mime_type'] ?: 'application/octet-stream';
        $disposition = in_array($mime, ['application/pdf','image/jpeg','image/png'], true) ? 'inline' : 'attachment';
        $fname = basename((string)$a['nombre_archivo']);
        $size  = (int)@filesize($realFile);

        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: '.$disposition.'; filename="'.str_replace('"','',$fname).'"');
        if ($size > 0) header('Content-Length: '.$size);
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') { return; }

        $chunk = 8192;
        $fp = @fopen($realFile, 'rb');
        if (!$fp) { http_response_code(500); echo 'No se pudo abrir el archivo'; return; }
        while (!feof($fp)) {
            echo fread($fp, $chunk);
            @flush(); @ob_flush();
        }
        fclose($fp);
        exit;
    }

    /* =========================
       Ingresar a caja de OTRO usuario (ADM/CON con ADQ_DEL)
       ========================= */

    // GET /caja/ingresos
    public function ingresos(): void
    {
        if (!($this->isAdminOrCon() && $this->hasPerm('ADQ_DEL'))) {
            $_SESSION['flash_error'] = 'Sin permiso.'; $this->redirect('/caja');
        }

        // Listado simple de usuarios activos
        $usuarios = [];
        try {
            if (class_exists('Usuarios')) {
                $U = new Usuarios($this->pdo);
                if (method_exists($U,'listarActivosBasico')) {
                    $usuarios = $U->listarActivosBasico();
                }
            }
            if (!$usuarios) {
                $st = $this->pdo->query("SELECT id, nombre, email, rut, perfil FROM usuarios WHERE activo=1 ORDER BY nombre ASC");
                $usuarios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable $e) { $usuarios = []; }

        $this->view('caja_ingresos', [
            'base'     => $this->base(),
            'usuarios' => $usuarios,
            'today'    => (new DateTimeImmutable('now', new DateTimeZone($this->tz)))->format('Y-m-d'),
        ]);
    }

    // POST /caja/ingresosStore
    public function ingresosStore(): void
    {
        if (!($this->isAdminOrCon() && $this->hasPerm('ADQ_DEL'))) {
            $_SESSION['flash_error'] = 'Sin permiso.'; $this->redirect('/caja');
        }

        $destUser = (int)($_POST['usuario_id'] ?? 0);
        if ($destUser <= 0) { $_SESSION['flash_error'] = 'Usuario destino inválido.'; $this->redirect('/caja/ingresos'); }

        $now = $this->nowCl();
        $periodo = $this->cajaModel->asegurarPeriodoAbierto($destUser, $now);

        $tipo   = 'INGRESO';
        $estado = 'APROBADO';
        $fecha_mov      = $now->format('Y-m-d H:i:s');
        $fecha_doc      = !empty($_POST['fecha_doc']) ? $_POST['fecha_doc'] : null;
        $numero_doc     = trim((string)($_POST['numero_doc'] ?? '')) ?: null;
        $documento_tipo = strtoupper(trim((string)($_POST['documento_tipo'] ?? 'RECIBO')));
        $monto          = $this->parseMontoEntero((string)($_POST['monto'] ?? '0'));
        if ($monto <= 0) { $_SESSION['flash_error'] = 'Monto inválido.'; $this->redirect('/caja/ingresos'); }

        $medio_ingreso = $_POST['medio_ingreso'] ?? 'TRANSFERENCIA';
        $banco         = $_POST['banco'] ?? null;
        $referencia    = $_POST['referencia_pago'] ?? null;

        $data = [
            'caja_id'           => (int)$periodo['id'],
            'usuario_id'        => $destUser,
            'proyecto_costo_id' => null,
            'tipo'              => $tipo,
            'estado'            => $estado,
            'fecha_mov'         => $fecha_mov,
            'fecha_doc'         => $fecha_doc,
            'numero_doc'        => $numero_doc,
            'documento_tipo'    => $documento_tipo,
            'monto'             => $monto,
            'descripcion'       => 'Ingreso administrado por '.$_SESSION['user']['nombre'],
            'medio_ingreso'     => $medio_ingreso,
            'banco'             => $banco,
            'referencia_pago'   => $referencia
        ];

        $movId = $this->cajaModel->crearMovimiento($data);
        $this->logJson('ingreso_admin', [[ 'tabla'=>'caja_chica_movimientos','id'=>$movId,'despues'=>$data ]]);

        if (!empty($_FILES['doc_file']['tmp_name'])) {
            $this->handleUpload((int)$movId, $_FILES['doc_file']);
        }

        $_SESSION['flash_ok'] = 'Ingreso registrado.';
        $this->redirect('/caja');
    }
}
