<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('usuarios/cambiar_estado.php');

require_once __DIR__ . '/../config/conexion.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Location: listar.php');
    exit;
}

$id=filter_input(INPUT_POST,'cod_usuario',FILTER_VALIDATE_INT);

if(!$id){
    $_SESSION['mensaje']='Usuario no válido.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$stmt=$conexion->prepare("SELECT estado,cod_rol FROM usuarios WHERE cod_usuario=? LIMIT 1");

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
if ((int)($_SESSION['cod_rol'] ?? 0) !== 1
    && ((int)$usuario['cod_rol'] === 1 || (int)$usuario['cod_rol'] !== (int)($_SESSION['cod_rol'] ?? 0))) {
    http_response_code(403);
    exit('Solo puedes cambiar el estado de cuentas de tu mismo rol.');
}

$nuevoEstado=$usuario['estado']==='Activo'?'Inactivo':'Activo';

$stmtActualizar=$conexion->prepare("UPDATE usuarios SET estado=? WHERE cod_usuario=?");

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

if (login_puede('usuarios/listar.php')) {
    header('Location: listar.php');
} else {
    login_ir(login_destino_ruta());
}
exit;
?>
