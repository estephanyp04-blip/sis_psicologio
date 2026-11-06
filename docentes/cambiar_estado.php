<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/cambiar_estado.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';
try {
    docente_cambiar_estado($conexion, flujo_id($_POST['cod_docente'] ?? null), flujo_texto($_POST['estado'] ?? '',10), (int)$_SESSION['cod_usuario']);
    $_SESSION['mensaje']='Estado del perfil docente actualizado. El estado de su cuenta se administra en Usuarios.';
    $_SESSION['tipo_mensaje']='success';
} catch (Throwable $e) {
    $_SESSION['mensaje']=$e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo actualizar el perfil docente.';
    $_SESSION['tipo_mensaje']='danger';
}
header('Location: listar.php',true,303); exit;
