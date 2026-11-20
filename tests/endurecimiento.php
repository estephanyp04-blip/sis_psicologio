<?php
/** Seguridad y recuperación; exclusivamente bases y archivos sintéticos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/herramientas/respaldo_lib.php';
require __DIR__.'/datos_sinteticos.php';
$raiz=dirname(__DIR__);$config=require $raiz.'/config/login.php';
$sufijo=bin2hex(random_bytes(6));$base='psicologia_test_seguridad_'.$sufijo;
$restaurada='psicologia_restore_'.$sufijo;$fallida='psicologia_restore_'.bin2hex(random_bytes(6));
$temporal=sys_get_temp_dir().'/psicologia-recuperacion-'.$sufijo;
$evidencia=$raiz.'/derivaciones/evidencia_'.bin2hex(random_bytes(16)).'.png';
$total=0;$admin=null;$fallo=false;
function prueba_endurecimiento_verificar(bool $ok,string $mensaje):void {global $total;++$total;if(!$ok)throw new RuntimeException($mensaje);}
function prueba_endurecimiento_rechaza(callable $accion,string $mensaje):void {try{$accion();}catch(Throwable $e){prueba_endurecimiento_verificar(true,$mensaje);return;}prueba_endurecimiento_verificar(false,$mensaje);}
function proceso(array $argumentos,bool $exito=true):string {
    global $temporal;
    $p=proc_open($argumentos,[0=>['pipe','r'],1=>['file',$temporal.'/salida.tmp','w'],2=>['file',$temporal.'/error.tmp','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('No inició el subproceso.');fclose($pipes[0]);
    $codigo=proc_close($p);$salida=file_get_contents($temporal.'/salida.tmp');
    prueba_endurecimiento_verificar($exito?($codigo===0):($codigo!==0),'Resultado inesperado del subproceso: '.file_get_contents($temporal.'/error.tmp'));
    return $salida;
}
function huella(mysqli $bd):array {
    $foto=[];foreach($bd->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetch_all(MYSQLI_NUM) as $fila) {
        $tabla=$fila[0];$foto[$tabla]=hash('sha256',json_encode($bd->query("SELECT * FROM `$tabla` ORDER BY 1")->fetch_all(MYSQLI_ASSOC),JSON_THROW_ON_ERROR));
    }ksort($foto);return $foto;
}
try {
    prueba_endurecimiento_verificar(extension_loaded('sodium'),'Ejecute con -d extension=sodium.');
    mkdir($temporal,0700);mkdir($temporal.'/claves',0700);mkdir($temporal.'/copias',0700);mkdir($temporal.'/archivos',0700);mkdir($temporal.'/sesiones',0700);
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $admin=new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],'',$config['puerto']);
    proceso([PHP_BINARY,$raiz.'/database/migrar.php','--base='.$base,'--instalar','--aplicar']);
    putenv('DB_NAME='.$base);ini_set('session.save_path',$temporal.'/sesiones');
    require $raiz.'/includes/autenticacion.php';require $raiz.'/historias_clinicas/datos.php';require $raiz.'/estadisticas/datos.php';
    $bd=login_bd();prueba_autores($bd);prueba_estudiantes($bd,2);
    $plan=require $raiz.'/database/migrations_normalizadas/016_auditoria_versiones.php';
    prueba_endurecimiento_verificar($plan($bd)===[],'016 no es idempotente.');
    // Recrear únicamente en la base ficticia una instalación anterior a 016.
    foreach(['auditoria_identidad','version_identidad','auditoria_update_prohibido','auditoria_delete_prohibido','historia_versiones_update_prohibido','historia_versiones_delete_prohibido','usuarios_revocar_sesiones','roles_revocar_sesiones'] as $trigger)$bd->query("DROP TRIGGER `$trigger`");
    $bd->query('DROP TABLE historia_versiones');
    $bd->query('ALTER TABLE auditoria DROP actor_codigo,DROP actor_usuario,DROP actor_rol,DROP resultado');
    $bd->query('ALTER TABLE usuarios DROP sesion_version');$bd->query('ALTER TABLE roles DROP sesion_version');
    $bd->query("DELETE FROM esquema_migraciones_normalizadas WHERE version='016_auditoria_versiones'");
    $bd->query("INSERT INTO auditoria(cod_usuario,modulo,accion,registro_id,detalle) VALUES(101,'prueba','Anterior',1,'Metadato ficticio')");
    $anterior=$bd->query('SELECT * FROM auditoria')->fetch_assoc();
    $pasos=$plan($bd);$bd->query($pasos[0]); // Reanudar una aplicación interrumpida.
    foreach($plan($bd) as $sql)$bd->query($sql);
    $posterior=$bd->query('SELECT * FROM auditoria')->fetch_assoc();
    prueba_endurecimiento_verificar(array_intersect_key($posterior,$anterior)===$anterior&&$posterior['actor_usuario']===null,'016 modifica eventos históricos o inventa identidad previa.');
    prueba_endurecimiento_verificar($posterior['resultado']===null,'016 inventa un resultado para eventos anteriores.');
    prueba_endurecimiento_verificar($plan($bd)===[],'016 no reanuda de forma idempotente.');
    $bd->query("UPDATE permisos SET puede_ver=1,puede_crear=1,puede_editar=1,puede_eliminar=1,puede_cambiar_estado=1 WHERE cod_rol IN (1,3,4)");
    $bd->query("INSERT INTO roles(cod_rol,nombre) VALUES(9,'Prueba personalizada')");
    $bd->query('INSERT INTO permisos(cod_rol,cod_modulo,puede_ver,puede_crear,puede_editar,puede_eliminar,puede_cambiar_estado) SELECT 9,cod_modulo,1,1,1,1,1 FROM modulos');
    login_iniciar_sesion();
    foreach([1,3,4,9] as $rol) {
        $_SESSION=['autenticado'=>true,'cod_rol'=>$rol];
        foreach(['index.php','estadisticas/index.php','historias_clinicas/ver.php','seguimientos/ver.php','informes/ver.php','informes/ver_mensual.php','informes/antecedentes.php','citas/listar.php'] as $ruta) prueba_endurecimiento_verificar(!login_puede($ruta),'Permiso clínico excesivo para rol '.$rol);
    }
    $cuenta=login_buscar_usuario('prueba_psicologa');login_establecer($cuenta);
    prueba_endurecimiento_verificar(login_usuario_actual()!==null,'Sesión profesional válida rechazada.');
    $bd->query("UPDATE usuarios SET estado='Inactivo' WHERE cod_usuario=102");$bd->query("UPDATE usuarios SET estado='Activo' WHERE cod_usuario=102");
    prueba_endurecimiento_verificar(login_usuario_actual()===null,'Reactivación revive sesión antigua.');
    login_establecer(login_buscar_usuario('prueba_psicologa'));
    $bd->query("UPDATE roles SET estado='Inactivo' WHERE cod_rol=2");$bd->query("UPDATE roles SET estado='Activo' WHERE cod_rol=2");
    prueba_endurecimiento_verificar(login_usuario_actual()===null,'Reactivar rol revive sesión antigua.');
    $hash=password_hash('Clave ficticia larga 2026',PASSWORD_BCRYPT,['cost'=>4]);$stmt=$bd->prepare('UPDATE usuarios SET password=? WHERE cod_usuario=102');$stmt->bind_param('s',$hash);$stmt->execute();
    $_SERVER['REMOTE_ADDR']='192.0.2.10';
    for($i=0;$i<5;$i++)prueba_endurecimiento_verificar(login_comprobar('prueba_psicologa','Incorrecta')[0]===null,'Clave incorrecta aceptada.');
    prueba_endurecimiento_verificar(login_comprobar('prueba_psicologa','Clave ficticia larga 2026')[0]===null,'No limita intentos del mismo origen.');
    $_SERVER['REMOTE_ADDR']='192.0.2.11';
    $autenticada=login_comprobar('prueba_psicologa','Clave ficticia larga 2026')[0];
    prueba_endurecimiento_verificar($autenticada!==null,'Un origen bloquea globalmente la cuenta.');
    login_establecer($autenticada);prueba_endurecimiento_verificar(login_usuario_actual()!==null,'Rehash invalida el nuevo inicio de sesión.');
    login_establecer(login_buscar_usuario('prueba_psicologa'));
    $h=historia_guardar($bd,['cod_estudiante'=>1,'fecha_apertura'=>date('Y-m-d'),'motivo_consulta'=>'Contenido ficticio original','estado'=>'Activa','formulario_completo'=>'1'],102);
    $bd->query("INSERT INTO grupos_opciones_historia(codigo,nombre) VALUES('prueba_historica','Grupo ficticio anterior')");$grupo=(int)$bd->insert_id;
    $bd->query("INSERT INTO opciones_historia(cod_grupo,descripcion) VALUES($grupo,'Opción ficticia anterior')");$opcion=(int)$bd->insert_id;
    $bd->query("INSERT INTO historia_opciones(cod_historia,cod_opcion) VALUES($h,$opcion)");
    $bd->query("INSERT INTO historia_familiares(cod_historia,nombre,relacion) VALUES($h,'Familiar ficticio','Tutor')");$familiar=(int)$bd->insert_id;
    historia_guardar($bd,['cod_estudiante'=>1,'motivo_consulta'=>'Contenido ficticio rectificado','formulario_completo'=>'1'],112,$h);
    $version=$bd->query('SELECT * FROM historia_versiones')->fetch_assoc();
    prueba_endurecimiento_verificar(json_decode($version['contenido_anterior'],true)['motivo_consulta']==='Contenido ficticio original'&&(int)$version['cod_editor']===112,'Versión previa o atribución perdida.');
    $relaciones=json_decode($version['contenido_anterior'],true)['_relaciones_originales'];
    prueba_endurecimiento_verificar((int)$relaciones['opciones'][0]['cod_opcion']===$opcion&&$relaciones['opciones'][0]['grupo_codigo']==='prueba_historica','Versión pierde opciones de grupos anteriores.');
    prueba_endurecimiento_verificar((int)$relaciones['familiares'][0]['cod_familiar']===$familiar,'Versión pierde identidad de familiares anteriores.');
    $bd->query("ALTER TABLE auditoria ADD CONSTRAINT fallo_sintetico CHECK (accion<>'Actualizar' OR cod_usuario<>102)");
    $antes=huella($bd);
    prueba_endurecimiento_rechaza(fn()=>historia_guardar($bd,['cod_estudiante'=>1,'motivo_consulta'=>'No debe persistir','formulario_completo'=>'1'],102,$h),'Edición no propaga fallo de auditoría.');
    prueba_endurecimiento_verificar(huella($bd)===$antes,'Rollback no incluye versiones.');
    $bd->query('ALTER TABLE auditoria DROP CONSTRAINT fallo_sintetico');
    seguridad_evento($bd,112,'historias_clinicas','Consultar',$h);
    $registro=$bd->query('SELECT * FROM auditoria ORDER BY cod_auditoria DESC LIMIT 1')->fetch_assoc();
    $bd->query("UPDATE usuarios SET usuario='nombre_cambiado' WHERE cod_usuario=112");
    prueba_endurecimiento_verificar($bd->query('SELECT actor_usuario FROM auditoria ORDER BY cod_auditoria DESC LIMIT 1')->fetch_row()[0]==='prueba_psicologa_dos','Cambio de cuenta altera la identidad histórica.');
    foreach(['auditoria','historia_versiones'] as $tabla) {
        prueba_endurecimiento_rechaza(fn()=>$bd->query("DELETE FROM $tabla LIMIT 1"),'Bitácora/versiones permiten borrar.');
        $campo=$tabla==='auditoria'?'detalle':'contenido_anterior';
        prueba_endurecimiento_rechaza(fn()=>$bd->query("UPDATE $tabla SET $campo=$campo LIMIT 1"),'Bitácora/versiones permiten sobrescribir.');
    }
    prueba_endurecimiento_verificar($bd->query("SELECT COUNT(*) FROM auditoria WHERE detalle LIKE '%Contenido ficticio%'")->fetch_row()[0]==0,'Contenido clínico en bitácora.');
    $consulta=proceso([PHP_BINARY,$raiz.'/herramientas/consultar_auditoria.php','--desde='.date('Y-m-d'),'--limite=1000']);
    $eventos=json_decode($consulta,true,512,JSON_THROW_ON_ERROR);
    prueba_endurecimiento_verificar(count($eventos)>0&&!array_key_exists('detalle',$eventos[0]),'CLI de auditoría expone detalle o no obtiene eventos.');
    proceso([PHP_BINARY,'-r','putenv("APP_ENV=production"); putenv("DB_USER=root"); require '.var_export($raiz.'/config/login.php',true).';'],false);
    $http=proceso([PHP_BINARY,'-r','putenv("APP_ENV=production"); putenv("DB_USER=operador_ficticio"); putenv("DB_PASS=ficticia"); $_SERVER["HTTP_X_FORWARDED_PROTO"]="https"; require '.var_export($raiz.'/includes/autenticacion.php',true).'; login_iniciar_sesion(); echo "INSEGURO";']);
    prueba_endurecimiento_verificar(str_contains($http,'HTTPS')&&!str_contains($http,'INSEGURO'),'Cabecera no confiable evita el requisito HTTPS.');
    seguridad_evento($bd,106,'prueba','Consultar',1);$bd->query('DELETE FROM usuarios WHERE cod_usuario=106');
    $historico=$bd->query('SELECT cod_usuario,actor_codigo,actor_usuario FROM auditoria ORDER BY cod_auditoria DESC LIMIT 1')->fetch_assoc();
    prueba_endurecimiento_verificar($historico['cod_usuario']===null&&(int)$historico['actor_codigo']===106&&$historico['actor_usuario']==='docente_disponible_dos','Baja física pierde atribución histórica.');
    $hoy=date('Y-m-d');$bd->query("INSERT INTO derivaciones(cod_derivacion,fecha,cod_estudiante,cod_docente,motivo) VALUES(1,'$hoy',1,201,'Derivación ficticia')");
    file_put_contents($evidencia,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    $ruta='derivaciones/'.basename($evidencia);$stmt=$bd->prepare("INSERT INTO derivacion_evidencias(cod_derivacion,nombre_original,ruta,tipo_mime) VALUES(1,'ficticio.png',?,'image/png')");$stmt->bind_param('s',$ruta);$stmt->execute();
    // Datos representativos ficticios: 1000 historias y 10000 sesiones adicionales.
    $bd->begin_transaction();
    for($i=0;$i<1000;$i++) {
        $bd->query("INSERT INTO historias_clinicas(cod_estudiante,cod_psicologa,fecha_apertura,motivo_consulta) VALUES(2,102,'$hoy','Ficticio')");$id=(int)$bd->insert_id;
        $valores=array_fill(0,10,"($id,102,'$hoy','Sesión ficticia')");
        $bd->query('INSERT INTO historia_anexos(cod_historia,cod_psicologa,fecha,descripcion) VALUES'.implode(',',$valores));
    }$bd->commit();
    $tiempos=[];$i=0;
    do {
        $inicio=microtime(true);
        $datos=estadisticas_datos($bd,['desde'=>date('Y-01-01'),'hasta'=>$hoy]);
        $tiempos[]=microtime(true)-$inicio;
        ++$i;
    } while($i<10);
    sort($tiempos);prueba_endurecimiento_verificar($datos['historias']===(int)$bd->query("SELECT COUNT(*) n FROM historias_clinicas WHERE fecha_apertura BETWEEN '".date('Y-01-01')."' AND '$hoy'")->fetch_assoc()['n'],'Rendimiento con conteo inconsistente de historias.');
    prueba_endurecimiento_verificar($tiempos[9]<2,'Meta local propuesta de 2 s excedida.');
    $clave=$temporal.'/claves/respaldo.key';
    proceso([PHP_BINARY,'-d','extension=sodium',$raiz.'/herramientas/clave_respaldo.php','--destino='.$clave]);
    prueba_endurecimiento_rechaza(fn()=>respaldo_fuera_web($raiz.'/database'),'Permite respaldos bajo htdocs.');
    prueba_endurecimiento_rechaza(fn()=>respaldo_clave($clave,$temporal.'/claves'),'Clave junto a copia permitida.');
    $clase=fopen('php://memory','w+');fwrite($clase,str_repeat('ficticio',20000));rewind($clase);
    $claveTest=file_get_contents($clave);respaldo_cifrar($clase,$temporal.'/marcos.enc',$claveTest);fclose($clase);
    $nulo=fopen(PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','wb');
    prueba_endurecimiento_rechaza(fn()=>respaldo_descifrar($temporal.'/marcos.enc',$nulo,random_bytes(32)),'Cifrado acepta clave equivocada.');
    $marcos=file_get_contents($temporal.'/marcos.enc');file_put_contents($temporal.'/truncada.enc',substr($marcos,0,-1));
    prueba_endurecimiento_rechaza(fn()=>respaldo_descifrar($temporal.'/truncada.enc',$nulo,$claveTest),'Cifrado acepta truncamiento.');
    file_put_contents($temporal.'/extra.enc',$marcos.'x');
    prueba_endurecimiento_rechaza(fn()=>respaldo_descifrar($temporal.'/extra.enc',$nulo,$claveTest),'Cifrado acepta datos después del terminador.');fclose($nulo);sodium_memzero($claveTest);
    $foto=huella($bd);$inicio=microtime(true);
    proceso([PHP_BINARY,'-d','extension=sodium',$raiz.'/herramientas/respaldar.php','--base='.$base,'--destino='.$temporal.'/copias','--clave='.$clave]);
    $copias=glob($temporal.'/copias/respaldo-*',GLOB_ONLYDIR);prueba_endurecimiento_verificar(count($copias)===1,'No hay copia única.');$copia=$copias[0];
    $args=[PHP_BINARY,'-d','extension=sodium',$raiz.'/herramientas/restaurar_prueba.php','--copia='.$copia,'--clave='.$clave,'--base='.$restaurada,'--archivos='.$temporal.'/archivos'];
    proceso($args);$duracion=microtime(true)-$inicio;
    $recuperada=new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],$restaurada,$config['puerto']);$recuperada->set_charset('utf8mb4');$recuperada->query("SET time_zone='-04:00'");
    prueba_endurecimiento_verificar(huella($recuperada)===$foto,'Restauración cambia filas o pierde relaciones.');
    prueba_endurecimiento_verificar(hash_file('sha256',$temporal.'/archivos/'.basename($evidencia))===hash_file('sha256',$evidencia),'Adjunto restaurado diferente.');
    prueba_endurecimiento_verificar(hash_file('sha256',$temporal.'/archivos/codigo/includes/autenticacion.php')===hash_file('sha256',$raiz.'/includes/autenticacion.php'),'Código restaurado diferente.');
    prueba_endurecimiento_verificar(hash_file('sha256',$temporal.'/archivos/codigo/database/psicologia_db.sql')===hash_file('sha256',$raiz.'/database/psicologia_db.sql'),'SQL de instalación restaurado diferente.');
    prueba_endurecimiento_verificar(hash_file('sha256',$temporal.'/archivos/codigo/database/migrations_normalizadas/016_auditoria_versiones.php')===hash_file('sha256',$raiz.'/database/migrations_normalizadas/016_auditoria_versiones.php'),'Migración de seguridad restaurada diferente.');
    prueba_endurecimiento_verificar(estadisticas_datos($recuperada,['desde'=>date('Y-01-01'),'hasta'=>$hoy])['historias']===$datos['historias'],'Consulta funcional después de restaurar falló.');
    prueba_endurecimiento_rechaza(fn()=>$recuperada->query('DELETE FROM auditoria LIMIT 1'),'Restauración pierde protección de bitácora.');
    mkdir($temporal.'/repetida',0700);$args[count($args)-1]='--archivos='.$temporal.'/repetida';proceso($args,false);
    prueba_endurecimiento_verificar(huella($recuperada)===$foto,'Restauración repetida sobrescribe destino.');
    $f=fopen($copia.'/base.enc','r+b');fseek($f,-1,SEEK_END);fwrite($f,"\0");fclose($f);
    $args[count($args)-2]='--base='.$fallida;proceso($args,false);
    prueba_endurecimiento_verificar($admin->query("SHOW DATABASES LIKE '$fallida'")->num_rows===0,'Copia alterada crea destino.');
    echo 'OK: '.$total.' comprobaciones de endurecimiento y recuperación. p95 local (1000 historias / 10000 sesiones): '.round($tiempos[9]*1000).' ms. Copia y recuperación: '.round($duracion,2)." s.\n";
}catch(Throwable $error){$fallo=true;fwrite(STDERR,'FALLO: '.$error->getMessage()."\n");}
finally {
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    // __DIR__ en Windows usa separadores nativos; calcular la misma ruta real.
    $limite=sys_get_temp_dir().'/sis_psicologia_login_'.substr(hash('sha256',realpath($raiz.'/includes').'|'.$base),0,16);
    if(is_dir($limite)&&preg_match('/^psicologia_test_seguridad_[a-f0-9]{12}$/D',$base)) {
        foreach(glob($limite.'/*.json') as $archivo)unlink($archivo);rmdir($limite);
    }
    if($admin instanceof mysqli)foreach([$base,$restaurada,$fallida] as $nombre) {
        if(!preg_match('/^psicologia_(test_seguridad|restore)_[a-f0-9]{12}$/D',$nombre))throw new RuntimeException('Destino de limpieza no permitido.');
        $admin->query("DROP DATABASE IF EXISTS `$nombre`");
    }
    if(is_file($evidencia)&&preg_match('#/derivaciones/evidencia_[a-f0-9]{32}\.png$#',$evidencia))unlink($evidencia);
    $real=realpath($temporal);$tmp=realpath(sys_get_temp_dir());
    if($real&&$tmp&&str_starts_with($real,$tmp.DIRECTORY_SEPARATOR)&&str_starts_with(basename($real),'psicologia-recuperacion-')) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($real);
    }
}exit($fallo?1:0);
