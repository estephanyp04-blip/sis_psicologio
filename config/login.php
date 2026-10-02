<?php
// Configuración del inicio de sesión.
return [
    'base_url' => '/proyecto_vercionII',
    'host' => '127.0.0.1',
    'puerto' => 3306,
    'base_datos' => 'psicologia_db',
    'usuario_bd' => 'root',
    'clave_bd' => '',
    'cookie_segura' => false,
    'inactividad' => 1800,
    'duracion_maxima' => 28800,
    'destinos' => [
        1 => 'index.php',
        2 => 'index.php',
        3 => 'derivaciones/listar.php',
        4 => 'informes/listar.php',
    ],
];