<?php
// Conexión para el inicio de sesión.
function login_bd(): mysqli
{
    static $bd = null;
    if ($bd instanceof mysqli) return $bd;
    $c = require __DIR__ . '/login.php';
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