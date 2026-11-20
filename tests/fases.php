<?php
/** Regresión de las correcciones, siempre en una base sintética desechable. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz=dirname(__DIR__);
require_once $raiz.'/citas/datos.php';
require_once $raiz.'/docentes/datos.php';
require_once $raiz.'/historias_clinicas/datos.php';
require_once $raiz.'/informes/datos.php';
require_once __DIR__.'/datos_sinteticos.php';
$config=require $raiz.'/config/login.php';
$base='psicologia_test_fases_'.bin2hex(random_bytes(6));
$total=0; $bd=null; $admin=null; $fallo=false;
function prueba_fases_verificar(bool $valor,string $mensaje): void { global $total; $total++; if (!$valor) throw new RuntimeException($mensaje); }
function prueba_fases_rechaza(callable $accion,string $mensaje): void { try {$accion();} catch(InvalidArgumentException $e) {prueba_fases_verificar(true,$mensaje);return;} prueba_fases_verificar(false,$mensaje); }
try {
    $admin=new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],'',$config['puerto']);
    $admin->query("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $bd=new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],$base,$config['puerto']);$bd->set_charset('utf8mb4');
    $bd->multi_query(file_get_contents($raiz.'/database/psicologia_db.sql'));
    do {if($r=$bd->store_result())$r->free();} while($bd->more_results() && $bd->next_result());
    foreach(glob($raiz.'/database/migrations_normalizadas/*.php') as $archivo) { $plan=require $archivo; foreach($plan($bd) as $sql)$bd->query($sql); }
    $plan=require $raiz.'/database/migrations_normalizadas/013_continuidad_atencion.php';
    prueba_fases_verificar($plan($bd)===[],'La migración no es idempotente.');
    prueba_autores($bd);prueba_estudiantes($bd,3);
    $hoy=date('Y-m-d');$manana=date('Y-m-d',strtotime('+1 day'));
    $calendario=cita_calendario_datos($bd,['mes'=>date('Y-m'),'fecha'=>$hoy]);
    prueba_fases_verificar($calendario['seleccion']===$hoy && $calendario['por_dia']===[],'Calendario vacío inventa citas.');
    prueba_fases_rechaza(fn()=>cita_calendario_datos($bd,['mes'=>'2026-13']),'Calendario acepta mes inválido.');
    prueba_fases_rechaza(fn()=>cita_calendario_datos($bd,['mes'=>'2026-11','fecha'=>'2026-12-01']),'Calendario acepta día de otro mes.');
    $bd->query("INSERT INTO derivaciones(cod_derivacion,fecha,cod_estudiante,cod_docente,motivo) VALUES(1,'$hoy',1,201,'Ficticio'),(2,'$hoy',1,201,'Segundo episodio ficticio')");
    // Recrear la estructura previa con datos, para comprobar la actualización 013.
    $bd->query("INSERT INTO derivaciones(cod_derivacion,fecha,cod_estudiante,cod_docente,motivo) VALUES(3,'$hoy',3,202,'Episodio previo ficticio')");
    $bd->query("INSERT INTO historias_clinicas(cod_historia,cod_estudiante,cod_psicologa,cod_derivacion_origen,fecha_apertura,motivo_consulta) VALUES(900,3,102,3,'$hoy','Antecedente ficticio')");
    $bd->query("INSERT INTO historia_anexos(cod_anexo,cod_historia,cod_psicologa,fecha,descripcion) VALUES(900,900,102,'$hoy','Nota previa ficticia')");
    $bd->query('ALTER TABLE derivaciones DROP FOREIGN KEY fk_derivacion_autor,DROP cod_usuario_autor,DROP autor_nombres,DROP autor_apellidos');
    $bd->query('ALTER TABLE historias_clinicas DROP motivo_cierre');
    $bd->query('ALTER TABLE informe_resumen DROP total_sesiones,DROP filtros_json');
    $bd->query("DELETE FROM esquema_migraciones_normalizadas WHERE version='013_continuidad_atencion'");
    $previa=flujo_fila($bd,'SELECT * FROM historia_anexos WHERE cod_anexo=900');
    foreach($plan($bd) as $sql)$bd->query($sql);
    prueba_fases_verificar(array_intersect_key(flujo_fila($bd,'SELECT * FROM historia_anexos WHERE cod_anexo=900'),$previa)===$previa,'Migración alteró columnas previas.');
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT cod_usuario_autor FROM derivaciones WHERE cod_derivacion=1')['cod_usuario_autor']===103,'Migración no conserva autor previo.');
    prueba_fases_verificar($plan($bd)===[],'Migración sobre datos no es idempotente.');
    // Retirar únicamente la fixture previa, dentro de la base desechable.
    $bd->query('DELETE FROM historia_anexos WHERE cod_anexo=900');$bd->query('DELETE FROM historias_clinicas WHERE cod_historia=900');$bd->query('DELETE FROM derivaciones WHERE cod_derivacion=3');
    $id=cita_guardar($bd,['cod_estudiante'=>1,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'08:00'],101);
    $bd->query("UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_cambiar_estado=0 WHERE p.cod_rol=2 AND m.nombre='Citas'");
    cita_guardar($bd,['observaciones'=>'Dato ficticio'],102,$id);
    prueba_fases_rechaza(fn()=>cita_guardar($bd,['estado'=>'Cancelada'],102,$id),'Editar permite cancelar sin permiso.');
    prueba_fases_rechaza(fn()=>cita_guardar($bd,['hora'=>'08:30'],102,$id),'Editar permite reprogramar sin permiso.');
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM citas WHERE cod_cita=?',[$id])['estado']==='Pendiente','Cambio rechazado dejó escritura parcial.');
    $bd->query("UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_cambiar_estado=1 WHERE p.cod_rol=2 AND m.nombre='Citas'");
    cita_guardar($bd,['hora'=>'08:30'],102,$id);
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM citas WHERE cod_cita=?',[$id])['estado']==='Reprogramada','No identifica reprogramación.');
    cita_guardar($bd,['estado'=>'Atendida'],102,$id);
    prueba_fases_rechaza(fn()=>cita_guardar($bd,['estado'=>'Pendiente'],102,$id),'Reabre cita atendida.');
    prueba_fases_rechaza(fn()=>docente_cambiar_estado($bd,201,'Inactivo',102),'Psicóloga pudo dar de baja docente sin permiso.');
    docente_cambiar_estado($bd,201,'Inactivo',101);
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM usuarios WHERE cod_usuario=103')['estado']==='Activo','Baja de perfil cambió cuenta.');
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT COUNT(*) n FROM derivaciones WHERE cod_docente=201')['n']===2,'Baja eliminó historia.');
    docente_cambiar_estado($bd,201,'Activo',101);
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM docentes WHERE cod_docente=201')['estado']==='Activo','No reactiva.');
    $bd->begin_transaction();docente_conservar_autoria($bd,201);$bd->query('UPDATE docentes SET cod_usuario=105 WHERE cod_docente=201');$bd->commit();
    $d=flujo_fila($bd,'SELECT cod_usuario_autor,autor_nombres FROM derivaciones WHERE cod_derivacion=1');
    prueba_fases_verificar((int)$d['cod_usuario_autor']===103 && $d['autor_nombres']==='Docente','Reasignación perdió autoría.');
    prueba_fases_verificar(!flujo_fila($bd,'SELECT cod_derivacion FROM derivaciones d WHERE d.cod_derivacion=1 AND d.cod_docente=201 AND (d.cod_usuario_autor IS NULL OR d.cod_usuario_autor=(SELECT cod_usuario FROM docentes WHERE cod_docente=d.cod_docente))'),'Nueva cuenta accede a derivaciones históricas ajenas.');
    echo "Fase 2: permisos, transiciones, autoría y perfil docente correctos.\n";
    $historia=['cod_estudiante'=>1,'cod_derivacion'=>1,'fecha_apertura'=>$hoy,'motivo_consulta'=>'Motivo ficticio','estado'=>'Activa','formulario_completo'=>'1'];
    $historiaId=historia_guardar($bd,$historia,102);
    $sesion=['cod_historia'=>$historiaId,'fecha'=>$hoy,'descripcion'=>'Sesión ficticia','proxima_sesion'=>$manana];
    // La atención se documenta en la historia; citas y episodios cambian explícitamente.
    $c1=cita_guardar($bd,['cod_estudiante'=>1,'cod_derivacion'=>2,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'09:00'],102);
    cita_guardar($bd,['estado'=>'Atendida'],102,$c1);
    $proxima=cita_guardar($bd,['cod_estudiante'=>1,'cod_derivacion'=>2,'cod_psicologa'=>102,'fecha'=>$manana,'hora'=>'09:00'],102);
    // Vincular la derivación 2 a una historia propia de su estudiante sin registro independiente.
    flujo_ejecutar($bd,'UPDATE historias_clinicas SET cod_derivacion_origen=2 WHERE cod_historia=?',[$historiaId]);
    derivacion_cambiar_estado($bd,2,'En seguimiento',102);
    derivacion_cambiar_estado($bd,2,'Atendido',102);
    derivacion_cambiar_estado($bd,2,'En seguimiento',102);
    flujo_ejecutar($bd,'UPDATE historias_clinicas SET cod_derivacion_origen=1 WHERE cod_historia=?',[$historiaId]);
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT COUNT(*) n FROM historia_anexos')['n']===0,'El flujo nuevo creó un seguimiento.');
    prueba_fases_rechaza(fn()=>historia_guardar($bd,array_replace($historia,['estado'=>'Cerrada']),102,$historiaId),'Cierra sin motivo.');
    prueba_fases_rechaza(fn()=>historia_guardar($bd,array_replace($historia,['estado'=>'Cerrada','motivo_cierre'=>'Decisión ficticia']),102,$historiaId),'Cierra sin confirmar pendientes.');
    historia_guardar($bd,array_replace($historia,['estado'=>'Cerrada','motivo_cierre'=>'Decisión ficticia','confirmar_cierre'=>'1']),102,$historiaId);
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM citas WHERE cod_cita=?',[$proxima])['estado']==='Pendiente','Cierre cancela registros silenciosamente.');
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM derivaciones WHERE cod_derivacion=2')['estado']==='En seguimiento','Cierre cambia episodio abierto.');
    historia_guardar($bd,array_replace($historia,['estado'=>'Activa']),102,$historiaId);
    echo "Fase 3: episodios, reservas, rollback y cierre con motivo correctos.\n";
    require_once $raiz.'/estadisticas/datos.php';
    $antecedentes=informe_antecedentes($bd,1,$hoy);
    prueba_fases_verificar($antecedentes['numero_atenciones']===1,'Cuenta citas en lugar de seguimientos.');
    $ayer=date('Y-m-d',strtotime('-1 day'));
    prueba_fases_verificar(informe_antecedentes($bd,1,$ayer)['cod_historia']===0 && informe_total_atenciones($bd,1,$ayer)===0,'Informe retrospectivo incluye datos posteriores.');
    $mensual=informe_mensual_crear($bd,['mes'=>date('Y-m')],102);
    $guardado=flujo_fila($bd,'SELECT * FROM informe_resumen WHERE cod_informe=?',[$mensual]);
    $snapshot=json_decode($guardado['filtros_json'],true);
    $estadisticas=estadisticas_datos($bd,$snapshot['filtros']);
    prueba_fases_verificar((int)$guardado['total_sesiones']===$estadisticas['historias'],'Informe mensual y estadísticas discrepan.');
    prueba_fases_verificar((int)$guardado['total_sesiones']===1,'Informe mensual duplica citas y sesiones.');
    $vacio=informe_mensual_crear($bd,['mes'=>date('Y-m',strtotime('first day of previous month'))],102);
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT total_sesiones FROM informe_resumen WHERE cod_informe=?',[$vacio])['total_sesiones']===0,'Período vacío inventa sesiones.');
    prueba_fases_rechaza(fn()=>informe_mensual_crear($bd,['mes'=>'2099-01'],102),'Informe mensual admite futuro.');
    prueba_fases_rechaza(fn()=>informe_mensual_crear($bd,['mes'=>date('Y-m')],104),'Director genera informe mensual.');
    $ficha=informe_crear($bd,informe_datos(['cod_estudiante'=>1,'cod_historia'=>$historiaId,'fecha'=>$hoy,'titulo'=>'Informe ficticio','motivo'=>'Ficticio','aspecto_cognitivo'=>'Ficticio','aspectos_afectivos'=>'Ficticio','tipo_atencion'=>['Evaluación'],'estado'=>'Borrador','numero_atenciones'=>999]),102);
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT d.numero_atenciones FROM informe_individual d JOIN informes i ON i.cod_informe=d.cod_informe WHERE i.numero_ficha=?',[$ficha])['numero_atenciones']===1,'Acepta manipulación del conteo individual.');
    $inicioMes=date('Y-m-01');$ultimoPrevio=date('Y-m-d',strtotime($inicioMes.' -1 day'));
    $hLimites=historia_guardar($bd,['cod_estudiante'=>3,'fecha_apertura'=>$ultimoPrevio,'motivo_consulta'=>'Límites ficticios','estado'=>'En seguimiento','formulario_completo'=>'1'],102);
    flujo_ejecutar($bd,"INSERT INTO historias_clinicas(cod_estudiante,cod_psicologa,fecha_apertura,motivo_consulta) VALUES(2,102,?,'Atención futura sintética')",[$manana]);
    $mensualLimites=informe_mensual_crear($bd,['mes'=>date('Y-m')],102);
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT total_sesiones FROM informe_resumen WHERE cod_informe=?',[$mensualLimites])['total_sesiones']===1,'No respeta primer día/corte del mes actual.');
    $mensualPrevio=informe_mensual_crear($bd,['mes'=>substr($ultimoPrevio,0,7)],102);
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT total_sesiones FROM informe_resumen WHERE cod_informe=?',[$mensualPrevio])['total_sesiones']===1,'No incluye último día del mes previo.');
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT total_sesiones FROM informe_resumen WHERE cod_informe=?',[$mensual])['total_sesiones']===1,'Informe guardado cambia con datos posteriores.');
    $cursoOtro=(int)flujo_fila($bd,'SELECT cod_curso FROM cursos WHERE orden=2')['cod_curso'];
    $mensualFiltro=informe_mensual_crear($bd,['mes'=>date('Y-m'),'curso'=>$cursoOtro],102);
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT total_sesiones FROM informe_resumen WHERE cod_informe=?',[$mensualFiltro])['total_sesiones']===0,'Mensual ignora curso.');
    flujo_ejecutar($bd,'UPDATE historias_clinicas SET impresion_diagnostica=?,fecha_actualizacion=? WHERE cod_historia=?',['Texto posterior ficticio',$manana,$historiaId]);
    flujo_ejecutar($bd,'UPDATE derivaciones SET motivo=?,fecha_actualizacion=? WHERE cod_derivacion=1',['Motivo posterior ficticio',$manana]);
    $corteModificado=informe_antecedentes($bd,1,$hoy);
    prueba_fases_verificar($corteModificado['diagnostico']==='' && $corteModificado['motivo']==='','Precarga textos modificados después del corte.');
    echo "Fase 4: conteo común, corte retrospectivo e informe mensual correctos.\n";
    // Migrar una base existente conserva las historias y permite varias por estudiante.
    $antesMigracion = $bd->query('SELECT * FROM historias_clinicas ORDER BY cod_historia')->fetch_all(MYSQLI_ASSOC);
    $bd->query('ALTER TABLE historias_clinicas ADD UNIQUE KEY uk_historia_estudiante(cod_estudiante)');
    $bd->query('ALTER TABLE historias_clinicas DROP INDEX idx_historias_estudiante');
    $bd->query("DELETE FROM esquema_migraciones_normalizadas WHERE version='014_historias_por_estudiante'");
    $migracionHistorias = require $raiz.'/database/migrations_normalizadas/014_historias_por_estudiante.php';
    foreach ($migracionHistorias($bd) as $sql) $bd->query($sql);
    prueba_fases_verificar($migracionHistorias($bd) === [], 'La migración 014 no es idempotente.');
    prueba_fases_verificar($antesMigracion === $bd->query('SELECT * FROM historias_clinicas ORDER BY cod_historia')->fetch_all(MYSQLI_ASSOC), 'La migración modifica historias existentes.');
    flujo_ejecutar($bd, 'INSERT INTO derivaciones(cod_derivacion,fecha,cod_estudiante,cod_docente,cod_materia,motivo,prioridad) VALUES(99,?,1,201,5,?,?)', [$hoy,'Otra atención','Media']);
    prueba_fases_rechaza(fn()=>derivacion_cambiar_estado($bd,99,'En seguimiento',102),'Usa una historia ajena a la derivación para iniciar su atención.');
    $segundaEntrada = array_replace($historia,['cod_derivacion'=>99,'motivo_consulta'=>'Segunda historia','impresion_diagnostica'=>'Diagnóstico de segunda historia']);
    $segundaHistoria = historia_guardar($bd,$segundaEntrada,102);
    $terceraHistoria = historia_guardar($bd,array_replace($historia,['cod_derivacion'=>0,'motivo_consulta'=>'Historia sin derivación']),102);
    prueba_fases_verificar(count(informe_historias($bd,1,$hoy)) === 3, 'No admite tres historias del mismo estudiante.');
    prueba_fases_verificar(flujo_fila($bd,'SELECT motivo_consulta FROM historias_clinicas WHERE cod_historia=?',[$historiaId])['motivo_consulta']==='Motivo ficticio','Otra historia sobrescribe la primera.');
    $nuevaCita = cita_guardar($bd,['cod_estudiante'=>1,'cod_derivacion'=>99,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'17:00','estado'=>'Pendiente'],102);
    $citaPrueba = flujo_fila($bd,'SELECT * FROM citas WHERE cod_cita=?',[$nuevaCita]);
    prueba_fases_verificar(array_map('intval',array_column(flujo_historias_cita($bd,$citaPrueba),'cod_historia')) === [$segundaHistoria], 'La cita ofrece historias de otra derivación.');
    flujo_ejecutar($bd,'INSERT INTO historia_anexos(cod_historia,cod_psicologa,cod_derivacion,cod_cita,fecha,descripcion,recomendaciones) VALUES(?,?,?,?,?,?,?)',[$segundaHistoria,102,99,$nuevaCita,$hoy,'Archivo sintético','Recomendaciones de segunda historia']);
    $nuevoSeguimiento=(int)$bd->insert_id;
    prueba_fases_verificar((int)flujo_fila($bd,'SELECT cod_historia FROM historia_anexos WHERE cod_anexo=?',[$nuevoSeguimiento])['cod_historia']===$segundaHistoria,'Guarda seguimiento en otra historia.');
    $sinSeleccion = informe_antecedentes($bd,1,$hoy);
    prueba_fases_verificar($sinSeleccion['requiere_historia'] && $sinSeleccion['cod_historia']===0 && $sinSeleccion['diagnostico']==='', 'Precarga una historia arbitraria.');
    $seleccion = informe_antecedentes($bd,1,$hoy,$segundaHistoria);
    prueba_fases_verificar($seleccion['cod_historia']===$segundaHistoria && $seleccion['diagnostico']==='Diagnóstico de segunda historia','Mezcla antecedentes de varias historias.');
    prueba_fases_rechaza(fn()=>informe_antecedentes($bd,2,$hoy,$segundaHistoria),'Permite consultar la historia de otro estudiante.');
    $datosMultiples = informe_datos(['cod_estudiante'=>1,'fecha'=>$hoy,'titulo'=>'Informe con varias historias','motivo'=>'Ficticio','aspecto_cognitivo'=>'Ficticio','aspectos_afectivos'=>'Ficticio','tipo_atencion'=>['Evaluación'],'estado'=>'Borrador']);
    prueba_fases_rechaza(fn()=>informe_crear($bd,$datosMultiples,102),'Guarda un informe sin elegir entre varias historias.');
    foreach ([$historiaId,$segundaHistoria,$terceraHistoria] as $historiaElegida) {
        $fichaHistoria = informe_crear($bd,array_replace($datosMultiples,['cod_historia'=>$historiaElegida]),102);
        prueba_fases_verificar((int)flujo_fila($bd,'SELECT d.cod_historia FROM informe_individual d JOIN informes i ON i.cod_informe=d.cod_informe WHERE i.numero_ficha=?',[$fichaHistoria])['cod_historia']===$historiaElegida,'El informe se asocia a la historia más reciente en vez de la seleccionada.');
    }
    flujo_ejecutar($bd,"UPDATE historias_clinicas SET estado='En seguimiento' WHERE cod_historia=?",[$historiaId]);
    $panelMultiple = estadisticas_panel($bd);
    prueba_fases_verificar($panelMultiple['resumen']['en_seguimiento']===2,'Cuenta dos veces al estudiante con varias historias en seguimiento.');
    $idsPrioritarios=array_column($panelMultiple['prioritarios'],'cod_estudiante');
    prueba_fases_verificar(count($idsPrioritarios)===count(array_unique($idsPrioritarios)),'Duplica estudiantes en el panel.');
    historia_guardar($bd,array_replace($segundaEntrada,['estado'=>'Cerrada','motivo_cierre'=>'Cierre individual','confirmar_cierre'=>'1']),102,$segundaHistoria);
    prueba_fases_verificar(flujo_fila($bd,'SELECT estado FROM historias_clinicas WHERE cod_historia=?',[$historiaId])['estado']==='En seguimiento','Cerrar una historia cierra otra del estudiante.');
    echo "Historias múltiples: migración, citas, seguimientos, informes y panel correctos.\n";
    echo "OK: $total comprobaciones nuevas; base exclusivamente sintética.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");$fallo=true;}
finally {if($bd instanceof mysqli)$bd->close();if($admin instanceof mysqli){$admin->query("DROP DATABASE IF EXISTS `$base`");$admin->close();}}
exit($fallo?1:0);
