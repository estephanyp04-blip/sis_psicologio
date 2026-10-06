<?php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$fixture = require __DIR__ . '/fixture.php';
// Solo la suite clínica habilita este arranque de navegador en su copia temporal.
if (!empty($fixture['navegador'])) {
    if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__prueba_navegador'
        && hash_equals($fixture['secreto'], $_GET['secreto'] ?? '')) {
        setcookie('prueba_navegador', $fixture['secreto'], ['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' data:; connect-src 'self'");
        readfile(__DIR__ . '/navegador.html');
        exit;
    }
    if (hash_equals($fixture['secreto'], $_COOKIE['prueba_navegador'] ?? '')) {
        $_SERVER['HTTP_X_PRUEBA_SECRETO'] = $fixture['secreto'];
        $_SERVER['HTTP_X_PRUEBA_USUARIO'] = 'prueba_admin';
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' data:; connect-src 'self'");
    }
}
if (!hash_equals($fixture['secreto'], $_SERVER['HTTP_X_PRUEBA_SECRETO'] ?? '')) { http_response_code(403); exit; }
$ruta = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$archivo = realpath($fixture['app'] . '/' . $ruta);
if (!$archivo || !str_starts_with($archivo, realpath($fixture['app']) . DIRECTORY_SEPARATOR)) { http_response_code(404); exit; }
$_SERVER['SCRIPT_FILENAME'] = $archivo;
ini_set('session.save_path', $fixture['sesiones']);
require $fixture['app'] . '/includes/autenticacion.php';
login_iniciar_sesion();
$usuario = $_SERVER['HTTP_X_PRUEBA_USUARIO'] ?? 'prueba_admin';
$usuariosPermitidos = ['prueba_admin', 'prueba_psicologa'];
if (!empty($fixture['cuatro_roles'])) $usuariosPermitidos = array_merge($usuariosPermitidos,['prueba_docente','prueba_director']);
if (!in_array($usuario, $usuariosPermitidos, true)) { http_response_code(403); exit; }
if (($_SESSION['usuario'] ?? '') !== $usuario) {
    $cuenta = login_buscar_usuario($usuario);
    if ((int)$cuenta['id_rol']===3) $cuenta['id_docente']=login_docente((int)$cuenta['id_usuario']);
    login_establecer($cuenta);
}
$_SESSION['login_csrf'] = str_repeat('c', 64);
login_bd()->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
chdir(dirname($archivo));
require $archivo;
