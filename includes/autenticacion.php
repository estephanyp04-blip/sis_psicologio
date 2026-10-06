<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/conexion_login.php';
function login_config(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/../config/login.php';
}
function login_url(string $ruta): string
{
    return rtrim(login_config()['base_url'], '/') . '/' . ltrim($ruta, '/');
}
function login_ir(string $ruta): void
{
    header('Location: ' . login_url($ruta), true, 303);
    exit;
}
function login_html(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function login_iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'secure' => login_config()['cookie_segura'] || $https, 'samesite' => 'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store, private');
}
function login_token(): string
{
    return $_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
}
function login_validar_token($token): bool
{
    return is_string($token) && isset($_SESSION['login_csrf']) && hash_equals($_SESSION['login_csrf'], $token);
}
function login_rol_valido(array $u): bool
{
    return in_array((int) $u['id_rol'], [1, 2, 3, 4], true) && $u['rol_estado'] === 'Activo';
}
function login_buscar_usuario(string $usuario): ?array
{
    $s = login_bd()->prepare('SELECT u.id_usuario, u.usuario, p.nombres AS nombre, p.apellidos AS apellido, u.password, u.estado, u.id_rol, r.nombre AS rol, r.estado AS rol_estado
        FROM usuarios u
        INNER JOIN personas p ON p.id_persona = u.id_persona
        INNER JOIN roles r ON r.id_rol = u.id_rol
        WHERE u.usuario = ? LIMIT 2');
    $s->bind_param('s', $usuario);
    $s->execute();
    $r = $s->get_result();
    $u = $r->num_rows === 1 ? $r->fetch_assoc() : null;
    $s->close();
    return $u;
}
function login_docente(int $idUsuario): ?int
{
    $s = login_bd()->prepare('SELECT d.id_docente
        FROM docentes d
        INNER JOIN usuarios u ON u.id_persona = d.id_persona
        WHERE u.id_usuario = ? LIMIT 2');
    $s->bind_param('i', $idUsuario);
    $s->execute();
    $r = $s->get_result();
    $id = $r->num_rows === 1 ? (int) $r->fetch_assoc()['id_docente'] : null;
    $s->close();
    return $id;
}
// Límite por cuenta, compartido entre sesiones y protegido contra concurrencia.
function login_comprobar(string $usuario, string $clave): array
{
    $u = login_buscar_usuario($usuario);
    $claveLimite = $u ? 'id:' . $u['id_usuario'] : 'nombre:' . strtolower($usuario);
    $directorio = sys_get_temp_dir() . '/sis_psicologia_login_' . substr(hash('sha256', __DIR__), 0, 16);
    if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) throw new RuntimeException('No se pudo crear el directorio de intentos.');
    $archivo = $directorio . '/' . hash('sha256', $claveLimite) . '.json';
    $f = fopen($archivo, 'c+');
    if ($f === false) throw new RuntimeException('No se pudo abrir el registro de intentos.');
    if (!flock($f, LOCK_EX)) { fclose($f); throw new RuntimeException('No se pudo bloquear el registro de intentos.'); }
    try {
        $ahora = time();
        $estado = json_decode(stream_get_contents($f), true);
        if (!is_array($estado) || ($estado['inicio'] ?? 0) + 900 <= $ahora) $estado = ['inicio' => $ahora, 'fallos' => 0, 'bloqueado' => 0];
        $error = 'Usuario o contraseña incorrectos, o cuenta no disponible.';
        if (($estado['bloqueado'] ?? 0) > $ahora) return [null, 'Demasiados intentos. Espera cinco minutos e inténtalo de nuevo.'];
        if (($estado['bloqueado'] ?? 0) > 0) $estado = ['inicio' => $ahora, 'fallos' => 0, 'bloqueado' => 0];
        $hash = $u['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $correcta = password_verify($clave, $hash);
        $valido = $u && $correcta && $u['estado'] === 'Activo' && login_rol_valido($u);
        if ($valido && (int) $u['id_rol'] === 3) {
            $u['id_docente'] = login_docente((int) $u['id_usuario']);
            if (!$u['id_docente']) {
                $valido = false;
                $error = 'Tu usuario no tiene un único docente vinculado. Solicita al administrador que revise la relación.';
            }
        }
        if ($valido) {
            if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
                $nuevo = password_hash($clave, PASSWORD_DEFAULT);
                $s = login_bd()->prepare('UPDATE usuarios SET password=? WHERE id_usuario=? AND password=?');
                $s->bind_param('sis', $nuevo, $u['id_usuario'], $u['password']);
                $s->execute();
                if ($s->affected_rows === 1) $u['password'] = $nuevo;
                $s->close();
            }
            $estado = ['inicio' => $ahora, 'fallos' => 0, 'bloqueado' => 0];
        } else {
            $estado['fallos']++;
            if ($estado['fallos'] >= 5) $estado['bloqueado'] = $ahora + 300;
        }
        rewind($f);
        if (!ftruncate($f, 0) || fwrite($f, json_encode($estado)) === false || !fflush($f)) throw new RuntimeException('No se pudo guardar el registro de intentos.');
        return $valido ? [$u, null] : [null, $error];
    } finally {
        flock($f, LOCK_UN);
        fclose($f);
    }
}
function login_establecer(array $u): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION = [
        'autenticado' => true, 'id_usuario' => (int) $u['id_usuario'], 'id_rol' => (int) $u['id_rol'],
        'id_docente' => isset($u['id_docente']) ? (int) $u['id_docente'] : null,
        'usuario' => $u['usuario'], 'nombre' => $u['nombre'], 'apellido' => $u['apellido'],
        'nombre_completo' => trim($u['nombre'] . ' ' . $u['apellido']), 'rol' => $u['rol'],
        'login_inicio' => time(), 'login_actividad' => time(),
        'login_firma' => hash('sha256', $u['password']), 'login_csrf' => bin2hex(random_bytes(32)),
    ];
}
function login_usuario_actual(): ?array
{
    if (($_SESSION['autenticado'] ?? false) !== true) return null;
    $c = login_config();
    if (time() - ($_SESSION['login_actividad'] ?? 0) > $c['inactividad'] || time() - ($_SESSION['login_inicio'] ?? 0) > $c['duracion_maxima']) return null;
    $u = login_buscar_usuario((string) ($_SESSION['usuario'] ?? ''));
    if (!$u || $u['estado'] !== 'Activo' || !login_rol_valido($u)
        || (int) $u['id_usuario'] !== ($_SESSION['id_usuario'] ?? 0)
        || (int) $u['id_rol'] !== ($_SESSION['id_rol'] ?? 0)
        || !hash_equals($_SESSION['login_firma'] ?? '', hash('sha256', $u['password']))) return null;
    if ((int) $u['id_rol'] === 3 && login_docente((int) $u['id_usuario']) !== ($_SESSION['id_docente'] ?? null)) return null;
    $_SESSION['login_actividad'] = time();
    return $u;
}
function login_error_json(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function requerir_roles(array $permitidos = [], bool $json = false): void
{
    login_iniciar_sesion();
    try { $u = login_usuario_actual(); }
    catch (Throwable $e) {
        error_log('Autenticación: ' . $e->getMessage());
        if ($json) login_error_json(503, 'No se pudo verificar la sesión.');
        http_response_code(503); exit('No se pudo verificar la sesión. Inténtalo más tarde.');
    }
    if (!$u) {
        $_SESSION = []; session_regenerate_id(true);
        if ($json) login_error_json(401, 'La sesión venció. Inicie sesión nuevamente.');
        login_ir('login.php');
    }
    if ($permitidos && !in_array((int) $u['id_rol'], $permitidos, true)) {
        if ($json) login_error_json(403, 'No tiene permiso para realizar esta consulta.');
        http_response_code(403);
        exit('No tienes permiso para realizar esta acción.');
    }
}

function login_permisos(): array
{
    static $permisos = null;
    return $permisos ??= require __DIR__ . '/../config/permisos.php';
}

// Solo para presentar enlaces: el servidor siempre ejecuta requerir_acceso().
function login_puede(string $ruta): bool
{
    $permiso = login_permisos()[$ruta] ?? null;
    return $permiso !== null
        && ($_SESSION['autenticado'] ?? false) === true
        && in_array((int) ($_SESSION['id_rol'] ?? 0), $permiso['roles'], true);
}

function login_inicio_url(): string
{
    $destino = login_config()['destinos'][(int) ($_SESSION['id_rol'] ?? 0)] ?? 'login.php';
    return login_url($destino);
}

function login_campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . login_html(login_token()) . '">';
}

function requerir_csrf(): void
{
    if (!login_validar_token($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('El formulario venció o no es válido. Recarga la página e inténtalo de nuevo.');
    }
}

// Debe ejecutarse antes de conectar a la base de los módulos o producir HTML.
function requerir_acceso(string $ruta): void
{
    $permiso = login_permisos()[$ruta] ?? null;
    if ($permiso === null) {
        http_response_code(403);
        exit('Esta ruta no tiene permisos definidos.');
    }

    $json = ($permiso['formato'] ?? '') === 'json';
    requerir_roles($permiso['roles'], $json);
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, $permiso['metodos'], true)) {
        header('Allow: ' . implode(', ', $permiso['metodos']));
        if ($json) login_error_json(405, 'Método no permitido para esta consulta.');
        http_response_code(405);
        exit('Método no permitido para esta operación.');
    }
    if ($metodo === 'POST') {
        requerir_csrf();
    }
}
