<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/cancelar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';
$volverPanel=($_POST['volver']??'')==='panel' && login_puede('index.php') && login_puede('citas/listar.php');
$destinoPanel=login_url('index.php').'#agenda-calendario';
if($volverPanel){
    try {$filtrosAgenda=cita_calendario_datos($conexion,array_filter(['mes'=>$_POST['agenda_mes']??null,'fecha'=>$_POST['agenda_fecha']??null],static fn($v)=>$v!==null));$destinoPanel=login_url('index.php?'.http_build_query(['mes'=>$filtrosAgenda['mes'],'fecha'=>$filtrosAgenda['seleccion']])).'#agenda-calendario';}
    catch(Throwable $error){ /* Un retorno inválido vuelve a hoy, sin aceptar URL externas. */ }
}
try {
    $id = flujo_id($_POST['cod_cita'] ?? null);
    $entrada=array_intersect_key($_POST,array_flip(['estado','fecha','hora']));
    $entrada['estado']=$entrada['estado']??'Cancelada';
    cita_guardar($conexion, $entrada, (int)$_SESSION['cod_usuario'], $id);
    if($volverPanel){$_SESSION['mensaje_cita_estado']='Estado/horario actualizado.';header('Location: '.$destinoPanel,true,303);}
    else header('Location: listar.php?exito='.($entrada['estado']==='Cancelada'?'3':'2'));
} catch (Throwable $error) {
    $_SESSION['mensaje_cita'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo cancelar la cita.';
    if($volverPanel){$_SESSION['mensaje_cita_estado']=$_SESSION['mensaje_cita'];unset($_SESSION['mensaje_cita']);header('Location: '.$destinoPanel,true,303);}
    else header('Location: listar.php?error=2');
}
exit;
