<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/conexion_login.php';

if (!defined('BASE_URL')) {
    $configuracion = require __DIR__ . '/login.php';
    define('BASE_URL', rtrim($configuracion['base_url'], '/'));
}

try {
    $conexion = login_bd();
} catch (mysqli_sql_exception $error) {
    error_log('Error de conexión a la base de datos: ' . $error->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos.');
}
