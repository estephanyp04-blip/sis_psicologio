<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/eliminar.php');

require_once '../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['mensaje'] = 'El estudiante seleccionado no es válido.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

try {
    $stmt = $conexion->prepare("
        UPDATE estudiantes
        SET estado = 'Retirado'
        WHERE id_estudiante = ? AND estado <> 'Retirado'
    ");

    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        $_SESSION['mensaje'] = 'El estudiante se retiró correctamente.';
        $_SESSION['tipo_mensaje'] = 'success';
    } else {
        $_SESSION['mensaje'] = 'El estudiante no existe o ya estaba retirado.';
        $_SESSION['tipo_mensaje'] = 'warning';
    }

    $stmt->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al retirar estudiante: ' . $error->getMessage());
    $_SESSION['mensaje'] = 'No se pudo retirar el estudiante.';
    $_SESSION['tipo_mensaje'] = 'danger';
}

header('Location: listar.php');
exit;
?>
