<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Aplica exclusivamente la ampliación aditiva 016, sin otras migraciones pendientes.
$o=getopt('',['base:','comprobar','aplicar']);
if (isset($o['aplicar'])===isset($o['comprobar'])) { fwrite(STDERR,"Use --comprobar o --aplicar.\n");exit(64); }
$c=require __DIR__.'/../config/login.php';$base=$o['base']??$c['base_datos'];
if(!is_string($base)||!preg_match('/^[a-zA-Z0-9_]+$/D',$base)){fwrite(STDERR,"Base no válida.\n");exit(64);}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
try {
    $bd=new mysqli($c['host'],$c['usuario_bd'],$c['clave_bd'],$base,$c['puerto']);$bd->set_charset('utf8mb4');
    $s=$bd->prepare('SELECT GET_LOCK(?,0)');$nombre='psicologia_migrar_'.$base;$s->bind_param('s',$nombre);$s->execute();
    if((int)$s->get_result()->fetch_row()[0]!==1)throw new RuntimeException('Hay otra migración en ejecución.');
    $planificar=require __DIR__.'/../database/migrations_normalizadas/016_auditoria_versiones.php';$plan=$planificar($bd);
    echo 'Operaciones aditivas 016 pendientes: '.count($plan).".\n";
    if(isset($o['aplicar'])) {
        foreach($plan as $sql)$bd->query($sql);
        if($planificar($bd)!==[])throw new RuntimeException('La verificación posterior encontró pendientes.');
        echo "Migración 016 verificada. No se ejecutaron otras migraciones.\n";
    }
}catch(Throwable $error){fwrite(STDERR,"No se completó 016; revise el esquema normalizado y permisos DDL. Código: ".$error->getCode()."\n");exit(1);}
