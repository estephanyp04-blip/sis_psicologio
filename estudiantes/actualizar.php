<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/actualizar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$id = filter_var($_POST['id_estudiante'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { header('Location: listar.php'); exit; }
try {
    estudiante_actualizar($conexion, $id, $_POST);
    unset($_SESSION['edicion_estudiante']);
    $_SESSION['mensaje'] = 'El estudiante se actualizó correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
} catch (Throwable $error) {
    $datos = [];
    foreach (['ci', 'nombres', 'apellidos', 'curso', 'paralelo', 'estado'] as $campo) {
        if (is_string($_POST[$campo] ?? null)) $datos[$campo] = $_POST[$campo];
    }
    $_SESSION['edicion_estudiante'] = ['id' => $id, 'datos' => $datos];
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException
        ? $error->getMessage()
        : ($error instanceof mysqli_sql_exception && $error->getCode() === 1062
            ? 'El CI ya está registrado.' : 'No se pudo actualizar el estudiante.');
    $_SESSION['tipo_mensaje'] = 'danger';
    if (!$error instanceof InvalidArgumentException) error_log('Error al actualizar estudiante: ' . $error->getMessage());
    header('Location: editar.php?id=' . $id);
}
exit;
