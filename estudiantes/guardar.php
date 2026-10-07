<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/guardar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$datos = estudiante_datos($_POST);
try {
    $academico = estudiante_validar($datos, estudiante_catalogo($conexion));
    estudiante_insertar($conexion, $datos, $academico);
    unset($_SESSION['datos_estudiante']);
    $_SESSION['mensaje'] = 'El estudiante se registró correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
} catch (Throwable $error) {
    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage()
        : ($error instanceof mysqli_sql_exception && $error->getCode() === 1062
            ? 'Ya existe un estudiante con ese código o CI.' : 'No se pudo guardar el estudiante.');
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
}
exit;
