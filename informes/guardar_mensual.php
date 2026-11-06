<?php
require_once __DIR__.'/../includes/autenticacion.php';requerir_acceso('informes/guardar_mensual.php');
require_once __DIR__.'/../config/conexion.php';require_once __DIR__.'/datos.php';
try {$id=informe_mensual_crear($conexion,$_POST,(int)$_SESSION['cod_usuario']);unset($_SESSION['mensual_entrada']);header('Location: ver_mensual.php?id='.$id,true,303);}
catch(Throwable $e){$_SESSION['mensual_error']=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo generar el informe mensual.';$_SESSION['mensual_entrada']=array_filter(array_intersect_key($_POST,array_flip(['mes','curso','estado_historia'])),'is_scalar');header('Location: mensual.php',true,303);}exit;
