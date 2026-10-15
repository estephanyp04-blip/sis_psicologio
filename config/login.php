<?php

// Evitar el acceso directo a este archivo
if (
    realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__
) {
    http_response_code(404);
    exit;
}

// Zona horaria de Bolivia
date_default_timezone_set('America/La_Paz');

// Obtener variables de entorno
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

$appBaseUrl = getenv('APP_BASE_URL');
$cookieSegura = getenv('COOKIE_SEGURA');

// Configuración general
return [

    // Zona horaria
    'zona_horaria' => 'America/La_Paz',


    'base_url' => $appBaseUrl !== false
        ? rtrim($appBaseUrl, '/')
        : '/proyecto_vercionII',

    // Configuración de MySQL
    'host' => $dbHost !== false && $dbHost !== ''
        ? $dbHost
        : '127.0.0.1',

    'puerto' => $dbPort !== false && $dbPort !== ''
        ? (int) $dbPort
        : 3306,

    'base_datos' => $dbName !== false && $dbName !== ''
        ? $dbName
        : 'psicologia_db',

    'usuario_bd' => $dbUser !== false && $dbUser !== ''
        ? $dbUser
        : 'root',

    'clave_bd' => $dbPass !== false
        ? $dbPass
        : '',

    // Seguridad de las cookies de sesión
    // En producción con HTTPS debe ser true
    'cookie_segura' => filter_var(
        $cookieSegura !== false
            ? $cookieSegura
            : false,
        FILTER_VALIDATE_BOOL
    ),

    // Tiempo máximo de inactividad: 30 minutos
    'inactividad' => 1800,

    // Duración máxima de sesión: 8 horas
    'duracion_maxima' => 28800,

    // Destinos según el tipo de usuario
    'destinos' => [
        1 => 'index.php',
        2 => 'index.php',
        3 => 'derivaciones/listar.php',
        4 => 'informes/listar.php',
    ],

];