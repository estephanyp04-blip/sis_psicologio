<?php
require_once __DIR__ . '/includes/autenticacion.php';
login_iniciar_sesion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Utiliza el botón Cerrar sesión del sistema.');
}
if (!login_validar_token($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('El formulario venció. Recarga la página e inténtalo de nuevo.');
}
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
login_ir('login.php?salida=1');
