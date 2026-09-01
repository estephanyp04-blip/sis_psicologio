<?php
require_once '../config/conexion.php';

if(session_status()===PHP_SESSION_NONE)session_start();

function regresarConError(string $mensaje):void{
    $_SESSION['mensaje']=$mensaje;
    $_SESSION['tipo_mensaje']='danger';
    header('Location: registrar.php');
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Location: listar.php');
    exit;
}

$nombre=trim($_POST['nombre']??'');
$apellido=trim($_POST['apellido']??'');
$usuario=trim($_POST['usuario']??'');
$correo=trim($_POST['correo']??'');
$idRol=filter_input(INPUT_POST,'id_rol',FILTER_VALIDATE_INT);
$estado=trim($_POST['estado']??'');
$password=$_POST['password']??'';
$confirmarPassword=$_POST['confirmar_password']??'';

if($nombre===''||$apellido===''||$usuario===''||!$idRol||$password===''||$confirmarPassword===''){
    regresarConError('Complete todos los campos obligatorios.');
}

if(strlen($nombre)>50){
    regresarConError('El nombre no puede superar los 50 caracteres.');
}

if(strlen($apellido)>50){
    regresarConError('El apellido no puede superar los 50 caracteres.');
}

if(strlen($usuario)>30){
    regresarConError('El usuario no puede superar los 30 caracteres.');
}

if($correo!==''&&!filter_var($correo,FILTER_VALIDATE_EMAIL)){
    regresarConError('El correo electrónico no es válido.');
}

if(strlen($correo)>100){
    regresarConError('El correo no puede superar los 100 caracteres.');
}

if(!in_array($estado,['Activo','Inactivo'],true)){
    regresarConError('El estado seleccionado no es válido.');
}

if(strlen($password)<6){
    regresarConError('La contraseña debe tener al menos 6 caracteres.');
}

if($password!==$confirmarPassword){
    regresarConError('Las contraseñas no coinciden.');
}

$stmtRol=$conexion->prepare("SELECT id_rol FROM roles WHERE id_rol=? LIMIT 1");

if(!$stmtRol){
    regresarConError('Error al verificar el rol: '.$conexion->error);
}

$stmtRol->bind_param('i',$idRol);
$stmtRol->execute();
$resultadoRol=$stmtRol->get_result();

if($resultadoRol->num_rows===0){
    regresarConError('El rol seleccionado no existe.');
}

$stmtUsuario=$conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario=? LIMIT 1");

if(!$stmtUsuario){
    regresarConError('Error al verificar el usuario: '.$conexion->error);
}

$stmtUsuario->bind_param('s',$usuario);
$stmtUsuario->execute();
$resultadoUsuario=$stmtUsuario->get_result();

if($resultadoUsuario->num_rows>0){
    regresarConError('El nombre de usuario ya está registrado.');
}

$passwordHash=password_hash($password,PASSWORD_DEFAULT);

$stmt=$conexion->prepare("INSERT INTO usuarios(nombre,apellido,usuario,password,correo,estado,id_rol) VALUES(?,?,?,?,?,?,?)");

if(!$stmt){
    regresarConError('Error al preparar el registro: '.$conexion->error);
}

$stmt->bind_param(
    'ssssssi',
    $nombre,
    $apellido,
    $usuario,
    $passwordHash,
    $correo,
    $estado,
    $idRol
);

if(!$stmt->execute()){
    regresarConError('No se pudo registrar el usuario: '.$stmt->error);
}

$_SESSION['mensaje']='Usuario registrado correctamente.';
$_SESSION['tipo_mensaje']='success';

header('Location: listar.php');
exit;
?>