<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

$dbPass = getenv('DB_PASS');

return [
    'zona_horaria' => 'America/La_Paz',
    'base_url' => getenv('APP_BASE_URL') ?: '/proyecto_vercionII',

    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'puerto' => (int) (getenv('DB_PORT') ?: 3306),
    'base_datos' => getenv('DB_NAME') ?: 'psicologia_db',
    'usuario_bd' => getenv('DB_USER') ?: 'root',
    'clave_bd' => $dbPass === false ? '' : $dbPass,

    'cookie_segura' => filter_var(
        getenv('COOKIE_SEGURA') ?: false,
        FILTER_VALIDATE_BOOL
    ),

    'inactividad' => 1800,
    'duracion_maxima' => 28800,

    'destinos' => [
        1 => 'index.php',
        2 => 'index.php',
        3 => 'derivaciones/listar.php',
        4 => 'informes/listar.php',
    ],
];
