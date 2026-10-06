<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Conexión para el inicio de sesión.
function login_bd(): mysqli
{
    static $bd = null;
    if ($bd instanceof mysqli) return $bd;
    $c = require __DIR__ . '/login.php';
    date_default_timezone_set($c['zona_horaria'] ?? 'America/La_Paz');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $bd = new mysqli(
        $c['host'],
        $c['usuario_bd'],
        $c['clave_bd'],
        $c['base_datos'],
        $c['puerto']
    );
    $bd->set_charset('utf8mb4');
    return $bd;
}
