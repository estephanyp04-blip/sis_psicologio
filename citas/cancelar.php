<?php
require_once '../config/conexion.php';

// Verificar que llegó el ID
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("Location: listar.php");
    exit();
}

$id = intval($_GET['id']);

// Cancelar la cita
$sql = "UPDATE citas
        SET estado = 'Cancelada'
        WHERE id_cita = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);

if($stmt->execute()){
    header("Location: listar.php?exito=3");
    exit();
} else {
    header("Location: listar.php?error=2");
    exit();
}
?>