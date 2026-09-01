<?php
require_once '../config/conexion.php';

if(session_status()===PHP_SESSION_NONE)session_start();

if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Location: listar.php');
    exit;
}

$id=filter_input(INPUT_POST,'id_usuario',FILTER_VALIDATE_INT);

if(!$id){
    $_SESSION['mensaje']='Usuario no válido.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$stmt=$conexion->prepare("SELECT estado FROM usuarios WHERE id_usuario=? LIMIT 1");

if(!$stmt){
    $_SESSION['mensaje']='Error al consultar el usuario.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$stmt->bind_param('i',$id);
$stmt->execute();
$resultado=$stmt->get_result();
$usuario=$resultado->fetch_assoc();

if(!$usuario){
    $_SESSION['mensaje']='Usuario no encontrado.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$nuevoEstado=$usuario['estado']==='Activo'?'Inactivo':'Activo';

$stmtActualizar=$conexion->prepare("UPDATE usuarios SET estado=? WHERE id_usuario=?");

if(!$stmtActualizar){
    $_SESSION['mensaje']='Error al preparar la actualización.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$stmtActualizar->bind_param('si',$nuevoEstado,$id);

if(!$stmtActualizar->execute()){
    $_SESSION['mensaje']='No se pudo cambiar el estado del usuario.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$_SESSION['mensaje']='Estado del usuario actualizado correctamente.';
$_SESSION['tipo_mensaje']='success';

header('Location: listar.php');
exit;
?>