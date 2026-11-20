<?php
/** Migración de datos heredados y dependencias, exclusivamente en una base ficticia. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz=dirname(__DIR__);
require_once __DIR__.'/datos_sinteticos.php';
require_once $raiz.'/database/retiro_lib.php';
require_once $raiz.'/informes/datos.php';
require_once $raiz.'/historias_clinicas/datos.php';
$c=require $raiz.'/config/login.php';
$base='psicologia_test_retiro_'.bin2hex(random_bytes(6)); $total=0; $admin=null;
function prueba_retiro_verificar(bool $ok,string $mensaje):void {global $total;++$total;if(!$ok)throw new RuntimeException($mensaje);}
function bloquear(callable $fn,string $mensaje):void {try{$fn();}catch(RuntimeException $e){prueba_retiro_verificar(true,$mensaje);return;}prueba_retiro_verificar(false,$mensaje);}
function comprobarMantenimiento(mysqli $bd,string $base,array $config):void {
    global $raiz;
    $ruta=sys_get_temp_dir().'/psicologia-retiro-'.bin2hex(random_bytes(6));
    mkdir($ruta,0700);mkdir($ruta.'/config',0700);mkdir($ruta.'/database',0700);
    copy($raiz.'/database/migrar.php',$ruta.'/database/migrar.php');
    file_put_contents($ruta.'/config/login.php','<?php return '.var_export($config,true).';');
    try {
        $p=proc_open([PHP_BINARY,$ruta.'/database/migrar.php','--base='.$base,'--aplicar'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$salida=stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$codigo=proc_close($p);
        prueba_retiro_verificar($codigo===1 && str_contains($error,'Active .mantenimiento'),'El ejecutor permite retiro o reanudación sin mantenimiento.');
    } finally {
        unlink($ruta.'/config/login.php');unlink($ruta.'/database/migrar.php');rmdir($ruta.'/config');rmdir($ruta.'/database');rmdir($ruta);
    }
}
try {
    $admin=new mysqli($c['host'],$c['usuario_bd'],$c['clave_bd'],'',$c['puerto']);
    $admin->query("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $bd=new mysqli($c['host'],$c['usuario_bd'],$c['clave_bd'],$base,$c['puerto']);$bd->set_charset('utf8mb4');
    // Reconstruir nombres anteriores a 017 manteniendo todas las columnas clínicas.
    $sql=str_replace(['historia_anexos','cod_anexo_historico','cod_anexo'],['seguimientos','cod_seguimiento','cod_seguimiento'],file_get_contents($raiz.'/database/psicologia_db.sql'));
    $bd->multi_query($sql);do{if($r=$bd->store_result())$r->free();}while($bd->more_results()&&$bd->next_result());
    foreach(glob($raiz.'/database/migrations_normalizadas/*.php') as $archivo) {
        if(str_starts_with(basename($archivo),'017_'))continue;
        $plan=require $archivo;foreach($plan($bd) as $orden)$bd->query($orden);
    }
    prueba_autores($bd);prueba_estudiantes($bd,2);
    $hoy=date('Y-m-d');
    $bd->query("INSERT INTO derivaciones(cod_derivacion,fecha,cod_estudiante,cod_docente,motivo) VALUES(31,'$hoy',1,201,'Origen ficticio')");
    $bd->query("INSERT INTO citas(cod_cita,cod_estudiante,cod_psicologa,cod_derivacion,fecha,hora,estado) VALUES(41,1,102,31,'$hoy','08:00','Atendida'),(42,1,102,31,'$hoy','09:00','Pendiente')");
    $h=historia_guardar($bd,['cod_estudiante'=>1,'cod_derivacion'=>31,'cod_cita'=>41,'fecha_apertura'=>$hoy,'motivo_consulta'=>'Motivo original','estado'=>'Activa','formulario_completo'=>'1'],102);
    $bd->query('ALTER TABLE seguimientos ADD metadato_local VARCHAR(40) NULL');
    $bd->query("INSERT INTO seguimientos(cod_seguimiento,cod_historia,cod_psicologa,cod_cita,cod_derivacion,cod_proxima_cita,fecha,descripcion,tecnicas_aplicadas,acuerdos,recomendaciones,proxima_sesion,metadato_local)
        VALUES(51,$h,102,41,31,42,'$hoy','Texto histórico <privado>','Técnica ñ','Acuerdo histórico','Recomendación histórica','$hoy','Extensión conservada')");
    $bd->query("INSERT INTO informes(cod_informe,numero_ficha,cod_elaborado_por,tipo,titulo,fecha_inicio,fecha_fin,estado) VALUES(61,'INF-0061',102,'Individual','Informe anterior','$hoy','$hoy','Borrador')");
    $bd->query("INSERT INTO informe_individual(cod_informe,cod_estudiante,cod_historia,cod_derivacion,cod_seguimiento,numero_atenciones) VALUES(61,1,$h,31,51,7)");
    $bd->query("INSERT INTO modulos(cod_modulo,nombre,ruta,icono,orden) VALUES(99,'Seguimientos','/seguimientos/listar.php','bi-journal-medical',10)");
    $bd->query('INSERT INTO permisos(cod_rol,cod_modulo,puede_ver,puede_crear,puede_editar) VALUES(2,99,1,1,1)');
    $plan=require $raiz.'/database/migrations_normalizadas/017_retirar_seguimientos.php';
    $antes=[retiro_huella($bd,'seguimientos','cod_seguimiento'),retiro_huella($bd,'informe_individual','cod_informe')];
    $historias=retiro_huella($bd,'historias_clinicas','cod_historia');
    $ordenes=$plan($bd);
    prueba_retiro_verificar($antes===[retiro_huella($bd,'seguimientos','cod_seguimiento'),retiro_huella($bd,'informe_individual','cod_informe')],'La planificación escribe datos.');
    foreach([
        ['CREATE VIEW dependencia_prueba AS SELECT cod_seguimiento FROM seguimientos','DROP VIEW dependencia_prueba'],
        ["CREATE PROCEDURE dependencia_prueba() SELECT COUNT(*) FROM seguimientos",'DROP PROCEDURE dependencia_prueba'],
        ["CREATE TRIGGER dependencia_prueba BEFORE INSERT ON informes FOR EACH ROW SET NEW.descripcion=(SELECT descripcion FROM seguimientos LIMIT 1)",'DROP TRIGGER dependencia_prueba'],
        ["CREATE EVENT dependencia_prueba ON SCHEDULE EVERY 1 DAY DISABLE DO DELETE FROM seguimientos",'DROP EVENT dependencia_prueba'],
        ['CREATE TABLE dependencia_prueba(id INT UNSIGNED, FOREIGN KEY(id) REFERENCES seguimientos(cod_seguimiento)) ENGINE=InnoDB','DROP TABLE dependencia_prueba'],
    ] as [$crear,$borrar]) {
        $bd->query($crear);try{bloquear(fn()=>$plan($bd),'No detecta una dependencia SQL adicional.');}finally{$bd->query($borrar);}
        prueba_retiro_verificar($antes[0]===retiro_huella($bd,'seguimientos','cod_seguimiento'),'El bloqueo altera datos.');
    }
    bloquear(fn()=>retiro_verificar_respaldo([], $base),'Acepta aplicar sin un respaldo válido.');
    comprobarMantenimiento($bd,$base,$c);
    // Replanificar después de CADA sentencia comprueba reanudación ante DDL no transaccional.
    $limite=30;
    while($pendientes=$plan($bd)) {
        if(--$limite===0)throw new RuntimeException('Migración no converge.');$bd->query($pendientes[0]);
        if(str_starts_with($pendientes[0],'RENAME TABLE'))comprobarMantenimiento($bd,$base,$c);
    }
    prueba_retiro_verificar($plan($bd)===[],'La migración no es idempotente.');
    prueba_retiro_verificar(!$bd->query("SHOW TABLES LIKE 'seguimientos'")->num_rows,'Persiste la tabla retirada.');
    prueba_retiro_verificar($antes===[retiro_huella($bd,'historia_anexos','cod_anexo'),retiro_huella($bd,'informe_individual','cod_informe')],'Se perdió contenido o vínculos históricos.');
    prueba_retiro_verificar($historias===retiro_huella($bd,'historias_clinicas','cod_historia'),'Se alteró o inventó una historia.');
    prueba_retiro_verificar(!$bd->query("SELECT 1 FROM modulos WHERE nombre='Seguimientos'")->num_rows && !$bd->query('SELECT 1 FROM permisos WHERE cod_modulo=99')->num_rows,'Quedan módulo o permisos retirados.');
    prueba_retiro_verificar((int)$bd->query('SELECT numero_atenciones FROM informe_individual WHERE cod_informe=61')->fetch_row()[0]===7,'Se reescribió el total histórico.');
    prueba_retiro_verificar(informe_total_atenciones($bd,1,$hoy)===1,'El archivo aumenta las nuevas atenciones.');
    prueba_retiro_verificar(retiro_verificar_integridad($bd)>0,'No comprueba integridad referencial.');
    try {$bd->query("INSERT INTO historia_anexos(cod_historia,cod_psicologa,fecha,descripcion) VALUES(999999,102,'$hoy','Huérfano')");prueba_retiro_verificar(false,'Se perdió la FK a historia.');}
    catch(mysqli_sql_exception $e){prueba_retiro_verificar($e->getCode()===1452,'Error inesperado al comprobar FK.');}
    $rutas=require $raiz.'/config/permisos.php';
    prueba_retiro_verificar(!is_dir($raiz.'/seguimientos'),'La carpeta retirada todavía existe.');
    foreach($rutas as $ruta=>$definicion)prueba_retiro_verificar(!str_starts_with($ruta,'seguimientos/'),'Permanece una ruta autorizada.');
    prueba_retiro_verificar(!function_exists('seguimiento_guardar'),'Persiste la operación independiente.');
    echo "OK: $total comprobaciones de retiro, conservación exacta, dependencias SQL, permisos y reanudación. Base sintética.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");$fallo=true;}
finally{if($admin instanceof mysqli && preg_match('/^psicologia_test_retiro_[a-f0-9]{12}$/D',$base))$admin->query("DROP DATABASE IF EXISTS `$base`");}
exit(isset($fallo)?1:0);
