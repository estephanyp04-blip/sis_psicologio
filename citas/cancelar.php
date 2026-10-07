<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/cancelar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';
try {
    $id = flujo_id($_POST['id_cita'] ?? null);
    cita_guardar($conexion, ['estado' => 'Cancelada'], (int)$_SESSION['id_usuario'], $id);
    header('Location: listar.php?exito=3');
} catch (Throwable $error) {
    $_SESSION['mensaje_cita'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo cancelar la cita.';
    header('Location: listar.php?error=2');
}
exit;
