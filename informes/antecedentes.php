<?php
require_once __DIR__.'/../includes/autenticacion.php';
requerir_acceso('informes/antecedentes.php');
require_once __DIR__.'/../config/conexion.php';
require_once __DIR__.'/datos.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $id=flujo_id($_GET['cod_estudiante']??null);
    echo json_encode(informe_antecedentes($conexion,$id,flujo_texto($_GET['fecha']??'',10),flujo_id($_GET['cod_seguimiento']??0,true),flujo_id($_GET['cod_historia']??0,true)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(Throwable $e){http_response_code($e instanceof InvalidArgumentException?422:500);echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudieron consultar los antecedentes.']);}
