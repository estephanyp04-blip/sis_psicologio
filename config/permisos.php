<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Cada ruta se vincula a un módulo y a una acción guardada en la tabla permisos.
// Crear/editar/eliminar también habilita la pantalla correspondiente del formulario.
$get = ['GET', 'HEAD'];
$post = ['POST'];
$rolesBloqueadosDatosEstudiantiles = [3, 4];
$ruta = static function (string $modulo, string $accion, array $metodos, bool $soloAdmin = false, bool $json = false, ?array $rolesRestringidos = null, ?array $rolesBloqueados = null): array {
    return [
        'modulo' => $modulo,
        'accion' => $accion,
        'metodos' => $metodos,
        'solo_admin' => $soloAdmin,
        'formato' => $json ? 'json' : 'html',
        'roles_restringidos' => $rolesRestringidos,
        'roles_bloqueados' => $rolesBloqueados,
    ];
};

$catalogo = [
    // Los permisos dinámicos no amplían el acceso de Docente o Director a estos datos.
    'index.php' => $ruta('Dashboard', 'puede_ver', $get, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estadisticas/index.php' => $ruta('Estadísticas', 'puede_ver', $get, false, false, null, $rolesBloqueadosDatosEstudiantiles),

    'usuarios/listar.php' => $ruta('Usuarios', 'puede_ver', $get),
    'usuarios/ver.php' => $ruta('Usuarios', 'puede_ver', $get),
    'usuarios/registrar.php' => $ruta('Usuarios', 'puede_crear', $get),
    'usuarios/editar.php' => $ruta('Usuarios', 'puede_editar', $get),
    'usuarios/guardar.php' => $ruta('Usuarios', 'puede_crear', $post),
    'usuarios/actualizar.php' => $ruta('Usuarios', 'puede_editar', $post),
    'usuarios/cambiar_estado.php' => $ruta('Usuarios', 'puede_cambiar_estado', $post),

    'docentes/listar.php' => $ruta('Docentes', 'puede_ver', $get),
    'docentes/ver.php' => $ruta('Docentes', 'puede_ver', $get),
    'docentes/registrar.php' => $ruta('Docentes', 'puede_crear', $get),
    'docentes/editar.php' => $ruta('Docentes', 'puede_editar', $get),
    'docentes/guardar.php' => $ruta('Docentes', 'puede_crear', $post),
    'docentes/actualizar.php' => $ruta('Docentes', 'puede_editar', $post),

    'estudiantes/listar.php' => $ruta('Estudiantes', 'puede_ver', $get, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estudiantes/registrar.php' => $ruta('Estudiantes', 'puede_crear', $get, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estudiantes/editar.php' => $ruta('Estudiantes', 'puede_editar', $get, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estudiantes/guardar.php' => $ruta('Estudiantes', 'puede_crear', $post, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estudiantes/actualizar.php' => $ruta('Estudiantes', 'puede_editar', $post, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estudiantes/cambiar_estado.php' => $ruta('Estudiantes', 'puede_cambiar_estado', $post, false, false, null, $rolesBloqueadosDatosEstudiantiles),
    'estudiantes/importar.php' => $ruta('Estudiantes', 'puede_crear', $post, false, false, null, $rolesBloqueadosDatosEstudiantiles),

    'derivaciones/listar.php' => $ruta('Derivaciones', 'puede_ver', $get),
    'derivaciones/ver.php' => $ruta('Derivaciones', 'puede_ver', $get),
    'derivaciones/evidencia.php' => $ruta('Derivaciones', 'puede_ver', $get),
    'derivaciones/mis_derivaciones.php' => $ruta('Derivaciones', 'puede_ver', $get),
    'derivaciones/registrar.php' => $ruta('Derivaciones', 'puede_crear', $get),
    'derivaciones/editar.php' => $ruta('Derivaciones', 'puede_editar', $get),
    'derivaciones/guardar.php' => $ruta('Derivaciones', 'puede_crear', $post),
    'derivaciones/actualizar.php' => $ruta('Derivaciones', 'puede_editar', $post),
    'derivaciones/eliminar.php' => $ruta('Derivaciones', 'puede_eliminar', $post),
    'derivaciones/cambiar_estado.php' => $ruta('Derivaciones', 'puede_cambiar_estado', $post),

    'seguimientos/registrar.php' => $ruta('Seguimientos', 'puede_crear', $get),
    'seguimientos/guardar.php' => $ruta('Seguimientos', 'puede_crear', $post),
    'seguimientos/listar.php' => $ruta('Seguimientos', 'puede_ver', $get),
    'seguimientos/ver.php' => $ruta('Seguimientos', 'puede_ver', $get),

    'citas/listar.php' => $ruta('Citas', 'puede_ver', $get),
    'citas/registrar.php' => $ruta('Citas', 'puede_crear', $get),
    'citas/editar.php' => $ruta('Citas', 'puede_editar', $get),
    'citas/procesar_registrar.php' => $ruta('Citas', 'puede_crear', $post),
    'citas/procesar_editar.php' => $ruta('Citas', 'puede_editar', $post),
    'citas/cancelar.php' => $ruta('Citas', 'puede_cambiar_estado', $post),

    'historias_clinicas/listar.php' => $ruta('Historias clinicas', 'puede_ver', $get),
    'historias_clinicas/ver.php' => $ruta('Historias clinicas', 'puede_ver', $get),
    'historias_clinicas/registrar.php' => $ruta('Historias clinicas', 'puede_crear', $get),
    'historias_clinicas/editar.php' => $ruta('Historias clinicas', 'puede_editar', $get),
    'historias_clinicas/guardar.php' => $ruta('Historias clinicas', 'puede_crear', $post),
    'historias_clinicas/actualizar.php' => $ruta('Historias clinicas', 'puede_editar', $post),
    'historias_clinicas/ajax_derivaciones.php' => $ruta('Historias clinicas', 'puede_ver', $get, false, true),

    'informes/listar.php' => $ruta('Informes', 'puede_ver', $get),
    'informes/ver.php' => $ruta('Informes', 'puede_ver', $get),
    'informes/registrar.php' => $ruta('Informes', 'puede_crear', $get),
    'informes/editar.php' => $ruta('Informes', 'puede_editar', $get),
    'informes/guardar.php' => $ruta('Informes', 'puede_crear', $post),
    'informes/actualizar.php' => $ruta('Informes', 'puede_editar', $post),

    'roles/listar.php' => $ruta('Roles y permisos', 'puede_ver', $get, true),
    'roles/registrar.php' => $ruta('Roles y permisos', 'puede_crear', $get, true),
    'roles/guardar.php' => $ruta('Roles y permisos', 'puede_crear', $post, true),
    'roles/editar.php' => $ruta('Roles y permisos', 'puede_editar', $get, true),
    'roles/actualizar.php' => $ruta('Roles y permisos', 'puede_editar', $post, true),
];

// Derechos originales de los cuatro roles que ya existían antes de habilitar
// permisos configurables. La autenticación los reemplaza con los valores de la BD.
$rolesBase = [
    'index.php' => [1, 2], 'estadisticas/index.php' => [1, 2],
    'usuarios/listar.php' => [1], 'usuarios/ver.php' => [1], 'usuarios/registrar.php' => [1],
    'usuarios/editar.php' => [1], 'usuarios/guardar.php' => [1], 'usuarios/actualizar.php' => [1], 'usuarios/cambiar_estado.php' => [1],
    'docentes/listar.php' => [1], 'docentes/ver.php' => [1], 'docentes/registrar.php' => [1], 'docentes/editar.php' => [1],
    'docentes/guardar.php' => [1], 'docentes/actualizar.php' => [1],
    'estudiantes/listar.php' => [1, 2], 'estudiantes/registrar.php' => [1, 2], 'estudiantes/editar.php' => [1, 2],
    'estudiantes/guardar.php' => [1, 2], 'estudiantes/actualizar.php' => [1, 2], 'estudiantes/cambiar_estado.php' => [1, 2], 'estudiantes/importar.php' => [1, 2],
    'derivaciones/listar.php' => [1, 2, 3], 'derivaciones/ver.php' => [1, 2, 3], 'derivaciones/evidencia.php' => [1, 2, 3],
    'derivaciones/mis_derivaciones.php' => [1, 2, 3], 'derivaciones/registrar.php' => [1, 3], 'derivaciones/editar.php' => [1, 3],
    'derivaciones/guardar.php' => [1, 3], 'derivaciones/actualizar.php' => [1, 3], 'derivaciones/eliminar.php' => [1, 3], 'derivaciones/cambiar_estado.php' => [1, 2],
    'seguimientos/registrar.php' => [1, 2], 'seguimientos/guardar.php' => [1, 2], 'seguimientos/listar.php' => [1, 2], 'seguimientos/ver.php' => [1, 2],
    'citas/listar.php' => [1, 2], 'citas/registrar.php' => [1, 2], 'citas/editar.php' => [1, 2],
    'citas/procesar_registrar.php' => [1, 2], 'citas/procesar_editar.php' => [1, 2], 'citas/cancelar.php' => [1, 2],
    'historias_clinicas/listar.php' => [1, 2], 'historias_clinicas/ver.php' => [1, 2], 'historias_clinicas/registrar.php' => [1, 2],
    'historias_clinicas/editar.php' => [1, 2], 'historias_clinicas/guardar.php' => [1, 2], 'historias_clinicas/actualizar.php' => [1, 2], 'historias_clinicas/ajax_derivaciones.php' => [1, 2],
    'informes/listar.php' => [1, 2, 4], 'informes/ver.php' => [1, 2, 4], 'informes/registrar.php' => [1, 2],
    'informes/editar.php' => [1, 2], 'informes/guardar.php' => [1, 2], 'informes/actualizar.php' => [1, 2],
    'roles/listar.php' => [1], 'roles/registrar.php' => [1], 'roles/guardar.php' => [1], 'roles/editar.php' => [1], 'roles/actualizar.php' => [1],
];
foreach ($catalogo as $nombre => &$definicion) $definicion['roles'] = $rolesBase[$nombre] ?? [1];
unset($definicion);
return $catalogo;
