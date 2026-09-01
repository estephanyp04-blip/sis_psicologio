<?php
require_once '../config/conexion.php';

if($_SERVER['REQUEST_METHOD'] != 'POST'){
    header("Location: listar.php");
    exit();
}

$id_cita       = intval($_POST['id_cita']);
$fecha         = $_POST['fecha'];
$hora          = $_POST['hora'];
$estado        = $_POST['estado'];
$observaciones = trim($_POST['observaciones']);

$estadosPermitidos = ['Pendiente', 'Atendida', 'Cancelada', 'Reprogramada'];

if ($id_cita <= 0 || !in_array($estado, $estadosPermitidos, true)) {
    header("Location: listar.php?error=2");
    exit();
}

$fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
    header("Location: editar.php?id=$id_cita&error=2");
    exit();
}

// Verificar duplicado excluyendo la cita actual
$sqlVerificar = "SELECT id_cita FROM citas
                 WHERE fecha = ?
                 AND hora = ?
                 AND id_cita != ?
                 AND estado != 'Cancelada'";

$stmt = $conexion->prepare($sqlVerificar);
$stmt->bind_param("ssi", $fecha, $hora, $id_cita);
$stmt->execute();
$resVerificar = $stmt->get_result();

if($resVerificar->num_rows > 0){
    $stmt->close();
    header("Location: editar.php?id=$id_cita&error=1");
    exit();
}
$stmt->close();

// Actualizar cita
$sqlActualizar = "UPDATE citas
                  SET fecha = ?,
                      hora = ?,
                      estado = ?,
                      observaciones = ?
                  WHERE id_cita = ?";

$stmt2 = $conexion->prepare($sqlActualizar);
$stmt2->bind_param(
    "ssssi",
    $fecha,
    $hora,
    $estado,
    $observaciones,
    $id_cita
);

if($stmt2->execute()){
    header("Location: listar.php?exito=2");
    exit();
} else {
    header("Location: listar.php?error=2");
    exit();
}
?>
