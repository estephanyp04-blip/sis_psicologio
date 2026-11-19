<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/conexion_login.php';
$o=getopt('',['desde:','hasta:','limite:']);
$desde=$o['desde']??date('Y-m-d');$hasta=$o['hasta']??$desde;$limite=$o['limite']??100;
foreach([$desde,$hasta] as $fecha) {
    $d=is_string($fecha)?DateTimeImmutable::createFromFormat('!Y-m-d',$fecha):false;
    if(!$d||$d->format('Y-m-d')!==$fecha){fwrite(STDERR,"Use fechas AAAA-MM-DD.\n");exit(64);}
}
$limite=filter_var($limite,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);
if($desde>$hasta||!$limite){fwrite(STDERR,"Revise fechas y límite (1 a 1000).\n");exit(64);}
try {
    $fin=(new DateTimeImmutable($hasta))->modify('+1 day')->format('Y-m-d');
    $stmt=login_bd()->prepare('SELECT cod_auditoria,actor_codigo,actor_usuario,actor_rol,modulo,accion,registro_id,resultado,fecha FROM auditoria WHERE fecha>=? AND fecha<? ORDER BY cod_auditoria DESC LIMIT ?');
    $stmt->bind_param('ssi',$desde,$fin,$limite);$stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,"No se pudo consultar la bitácora. Revise configuración y migración 016.\n");exit(1);}
