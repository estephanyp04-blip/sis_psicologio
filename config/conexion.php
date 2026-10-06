<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

// Conectar nunca instala ni modifica el esquema. Ver database/README.md.
require_once __DIR__ . '/conexion_login.php';
if (!defined('BASE_URL')) {
    $configuracion = require __DIR__ . '/login.php';
    define('BASE_URL', $configuracion['base_url']);
}
try {
    $conexion = login_bd();
} catch (mysqli_sql_exception $error) {
    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Revise MySQL y config/login.php.');
}