<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function login_bd(): mysqli
{
    static $bd = null;

    if ($bd instanceof mysqli) {
        return $bd;
    }

    $config = require __DIR__ . '/login.php';

    date_default_timezone_set(
        $config['zona_horaria'] ?? 'America/La_Paz'
    );

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $bd = new mysqli(
        $config['host'],
        $config['usuario_bd'],
        $config['clave_bd'],
        $config['base_datos'],
        $config['puerto']
    );

    $bd->set_charset('utf8mb4');
    $bd->query("SET time_zone = '-04:00'");

    return $bd;
}
