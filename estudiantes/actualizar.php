<?php

require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el formulario fue enviado por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: listar.php');
    exit;
}

// Recibir y sanear datos del formulario
$id_estudiante = filter_input(INPUT_POST, 'id_estudiante', FILTER_VALIDATE_INT);
$ci            = trim($_POST['ci'] ?? '');
$nombres       = trim($_POST['nombres'] ?? '');
$apellidos     = trim($_POST['apellidos'] ?? '');
$curso         = trim($_POST['curso'] ?? '');
$paralelo      = trim($_POST['paralelo'] ?? '');
$estado        = trim($_POST['estado'] ?? '');

// Validar campos obligatorios
if (
    $id_estudiante === false ||
    empty($ci) ||
    empty($nombres) ||
    empty($apellidos) ||
    empty($curso) ||
    empty($paralelo) ||
    empty($estado)
) {
    echo "<script>
            alert('Debe completar todos los campos correctamente.');
            window.history.back();
          </script>";
    exit;
}

// Preparar la consulta para actualizar el estudiante
$sql = "UPDATE estudiantes
        SET ci = ?,
            nombres = ?,
            apellidos = ?,
            curso = ?,
            paralelo = ?,
            estado = ?
        WHERE id_estudiante = ?";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    echo "<script>
            alert('Error al preparar la actualización.');
            window.history.back();
          </script>";
    exit;
}

$stmt->bind_param(
    "ssssssi",
    $ci,
    $nombres,
    $apellidos,
    $curso,
    $paralelo,
    $estado,
    $id_estudiante
);

if ($stmt->execute()) {
    $_SESSION['mensaje'] = 'El estudiante se actualizó correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';

    header('Location: listar.php');
    exit;
}

// Si hubo un error en la ejecución
echo "<script>
        alert('Error al actualizar el estudiante.');
        window.history.back();
      </script>";

$stmt->close();
$conexion->close();
?>
