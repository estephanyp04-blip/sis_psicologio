<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/includes/estudiantes_csv.php';
require_once dirname(__DIR__) . '/includes/informes_datos.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/login.php';
$admin = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
$admin->set_charset('utf8mb4');
$sufijo = bin2hex(random_bytes(6));
$bases = [];
$temporal = sys_get_temp_dir() . '/psicologia-integracion-' . $sufijo;
mkdir($temporal, 0700, true);
$servidor = null;
$total = 0;
$cookie = '';

function verificar(bool $condicion, string $mensaje): void
{
    global $total;
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
}
function rechaza(callable $accion, string $mensaje): void
{
    try { $accion(); } catch (InvalidArgumentException | mysqli_sql_exception $error) { verificar(true, $mensaje); return; }
    verificar(false, $mensaje);
}
function ejecutar(array $argumentos): string
{
    $p = proc_open($argumentos, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('No se pudo iniciar PHP.');
    fclose($pipes[0]);
    $salida = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($p) !== 0) throw new RuntimeException($salida . $error);
    return $salida;
}
function conectar(string $base): mysqli
{
    global $config;
    $bd = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], $base, $config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $bd;
}
function consultar(mysqli $bd, string $sql): array { return $bd->query($sql)->fetch_assoc() ?? []; }
function estructura(mysqli $bd): array
{
    $resultado = [];
    foreach ($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM) as [$tabla]) {
        $crear = $bd->query("SHOW CREATE TABLE `$tabla`")->fetch_row()[1];
        $resultado[$tabla] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $crear);
    }
    ksort($resultado);
    return $resultado;
}
function importar(mysqli $bd, string $csv): array
{
    $h = fopen('php://temp', 'w+'); fwrite($h, $csv); rewind($h);
    try { return estudiantes_importar_csv($bd, $h); } finally { fclose($h); }
}
function http_prueba(string $ruta, ?array $datos = null, ?string $csv = null, string $usuario = 'prueba_admin'): array
{
    global $puerto, $secreto, $cookie;
    $cabeceras = ['X-Prueba-Secreto: ' . $secreto, 'X-Prueba-Usuario: ' . $usuario, 'Connection: close'];
    if ($cookie) $cabeceras[] = 'Cookie: ' . $cookie;
    $opciones = ['method' => $datos === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
    if ($datos !== null) {
        if ($csv === null) {
            $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
            $opciones['content'] = http_build_query($datos);
        } else {
            $limite = 'PruebaCSV' . bin2hex(random_bytes(8));
            $cabeceras[] = 'Content-Type: multipart/form-data; boundary=' . $limite;
            $cuerpo = '';
            foreach ($datos as $nombre => $valor) $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$nombre\"\r\n\r\n$valor\r\n";
            $opciones['content'] = $cuerpo . "--$limite\r\nContent-Disposition: form-data; name=\"archivo\"; filename=\"prueba.csv\"\r\nContent-Type: text/csv\r\n\r\n$csv\r\n--$limite--\r\n";
        }
    }
    $opciones['header'] = implode("\r\n", $cabeceras);
    $cuerpo = file_get_contents('http://127.0.0.1:' . $puerto . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    foreach ($http_response_header as $cabecera) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $cabecera, $m)) $cookie = $m[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
    verificar(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', (string)$cuerpo), "Error PHP al abrir $ruta");
    return ['codigo' => (int)$m[1], 'cuerpo' => (string)$cuerpo, 'cabeceras' => implode("\n", $http_response_header)];
}

try {
    $nueva = 'psicologia_test_nueva_' . $sufijo;
    $actualizacion = 'psicologia_test_actualizar_' . $sufijo;
    $bases[] = $nueva;
    $salida = ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $nueva, '--aplicar', '--instalar']);
    verificar(str_contains($salida, 'verificado'), 'Falló la instalación vacía.');
    $bd = conectar($nueva);
    $antes = estructura($bd);
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $nueva, '--aplicar']);
    verificar($antes === estructura($bd), 'Reaplicar la migración altera el esquema.');
    verificar((int)consultar($bd, 'SELECT COUNT(*) n FROM roles')['n'] === 4, 'Faltan roles al instalar.');
    verificar((int)consultar($bd, 'SELECT COUNT(*) n FROM paralelos')['n'] === 24, 'Faltan catálogos al instalar.');

    // Clona solo DDL de la base configurada. Nunca copia pacientes, usuarios ni contraseñas.
    $origen = conectar($config['base_datos']);
    $bases[] = $actualizacion;
    $admin->query("CREATE DATABASE `$actualizacion` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $copia = conectar($actualizacion);
    $copia->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (estructura($origen) as $tabla => $crear) $copia->query($crear);
    $copia->query('SET FOREIGN_KEY_CHECKS=1');
    foreach (['roles', 'cursos', 'paralelos'] as $tabla) {
        $copia->query("INSERT INTO `$tabla` SELECT * FROM `$nueva`.`$tabla`");
    }
    // Mantiene reproducible la prueba después de actualizar la base local.
    foreach (['historias_clinicas'=>'id_cita','informes'=>'id_seguimiento'] as $tabla=>$campo) {
        if ($copia->query("SHOW COLUMNS FROM `$tabla` LIKE '$campo'")->num_rows) {
            $copia->query("ALTER TABLE `$tabla` DROP FOREIGN KEY `fk_{$tabla}_{$campo}_origen`, DROP COLUMN `$campo`");
        }
    }
    if ($copia->query("SHOW COLUMNS FROM citas LIKE 'turno_reservado'")->num_rows) {
        $copia->query('ALTER TABLE citas ADD UNIQUE KEY uk_cita_profesional_fecha_hora (id_usuario,fecha,hora)');
        $copia->query('ALTER TABLE citas DROP INDEX uk_citas_horario_vigente, DROP INDEX idx_citas_usuario, DROP COLUMN turno_reservado');
    }
    foreach (['informes_secuencia', 'esquema_migraciones'] as $tabla) $copia->query("DROP TABLE IF EXISTS `$tabla`");
    foreach (['seguimientos' => 'recomendaciones', 'historias_clinicas' => 'evolucion_caso'] as $tabla => $campo) {
        if ($copia->query("SHOW COLUMNS FROM `$tabla` LIKE '$campo'")->num_rows) $copia->query("ALTER TABLE `$tabla` DROP COLUMN `$campo`");
    }
    $copia->query("INSERT INTO usuarios (id_usuario,nombre,apellido,usuario,password,id_rol) VALUES (101,'Autor','Prueba','prueba_admin','hash-prueba',1)");
    $copia->query("INSERT INTO estudiantes (id_estudiante,codigo,ci,nombres,apellidos,id_curso,id_paralelo,curso,paralelo) VALUES (1,'LEGADO','LEGADO','Legado','Prueba',1,1,'1','A')");
    $copia->query("INSERT INTO historias_clinicas (id_historia,id_estudiante,id_usuario,fecha_apertura,motivo_consulta,observaciones) VALUES (1,1,101,'2026-01-01','Motivo previo','Conservar observaciones')");
    $copia->query("INSERT INTO seguimientos (id_historia,id_usuario,fecha,descripcion,acuerdos,proxima_sesion) VALUES (1,101,'2026-01-02','Descripción previa','Acuerdo previo','2026-02-01')");
    $copia->query("INSERT INTO informes (id_usuario,titulo,numero_ficha,id_estudiante,elaborado_por,id_historia) VALUES (101,'Título anterior','INF-0042',1,101,1)");
    $filasAntes = [];
    foreach (['usuarios','estudiantes','historias_clinicas','seguimientos','informes'] as $tabla) $filasAntes[$tabla] = $copia->query("SELECT * FROM `$tabla`")->fetch_all(MYSQLI_ASSOC);
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $actualizacion, '--aplicar']);
    verificar(estructura($bd) === estructura($copia), 'Instalación y actualización no producen el mismo esquema.');
    foreach ($filasAntes as $tabla => $filas) {
        $despues = $copia->query("SELECT * FROM `$tabla`")->fetch_all(MYSQLI_ASSOC);
        foreach ($filas as $i => $fila) verificar($fila === array_intersect_key($despues[$i], $fila), "La migración modificó datos previos: $tabla");
    }
    verificar((int)consultar($copia, 'SELECT ultimo FROM informes_secuencia WHERE id=1')['ultimo'] === 42, 'La secuencia no respeta las fichas previas.');
    $planificar = require $raiz . '/database/migrations/001_alinear_esquema.php';
    verificar($planificar($copia) === [], 'La migración no es idempotente.');
    echo "Instalación, actualización, conservación de datos e idempotencia: correctas.\n";

    $bd->query("INSERT INTO usuarios (id_usuario,nombre,apellido,usuario,password,id_rol) VALUES (101,'Admin','Prueba','prueba_admin','hash-prueba',1),(102,'Psicóloga','Prueba','prueba_psicologa','hash-prueba',2)");
    // IDs diferentes del grado visible: detecta el antiguo fallback numérico.
    $bd->query('UPDATE cursos SET id_curso=71 WHERE id_curso=1');
    $bd->query("UPDATE paralelos SET estado='Inactivo' WHERE id_curso=2 AND nombre='D'");
    $datos = estudiante_datos(['codigo'=>'UNO','ci'=>'CIUNO','nombres'=>'María','apellidos'=>'Prueba','fecha_nacimiento'=>'2010-02-28',
        'genero'=>'Femenino','curso'=>'1','paralelo'=>'D','turno'=>'Mañana','estado'=>'Activo']);
    $academico = estudiante_validar($datos, estudiante_catalogo($bd));
    verificar((int)$academico['id_curso'] === 71 && (int)$academico['id_paralelo'] === 4, 'No resuelve IDs reales del catálogo.');
    estudiante_insertar($bd, $datos, $academico);
    $idEstudiante = $bd->insert_id;
    $guardado = consultar($bd, "SELECT * FROM estudiantes WHERE id_estudiante=$idEstudiante");
    verificar($guardado['curso'] === '1' && (int)$guardado['id_curso'] === 71 && $guardado['sexo'] === 'F', 'No persiste datos coherentes del estudiante.');
    foreach ([['curso'=>'99'], ['curso'=>'2','paralelo'=>'D'], ['estado'=>'Baja'], ['estado'=>'En seguimiento'], ['fecha_nacimiento'=>'2010-02-30'], ['codigo'=>str_repeat('x',21)]] as $cambio) {
        rechaza(fn() => estudiante_validar(array_replace($datos,$cambio), estudiante_catalogo($bd)), 'Aceptó datos inválidos del estudiante.');
    }
    rechaza(fn() => estudiante_insertar($bd,$datos,$academico), 'Aceptó CI/código duplicado.');
    $encabezado = 'codigo;ci;nombres;apellidos;fecha_nacimiento;genero;curso;paralelo;turno;estado';
    $filaCSV = 'DOS;CIDOS;Ana;Prueba;2011-01-01;Femenino;1;A;Tarde;Activo';
    $resultado = importar($bd, "\xEF\xBB\xBF$encabezado\n$filaCSV\n$filaCSV\nTRES;CITRES;Ana;Prueba;2011-01-01;Femenino;2;D;Tarde;Activo\nCUATRO;CICUATRO;Ana;Prueba;2011-01-01;Femenino;3;D;Tarde;Retirado\nmal;columnas\n");
    verificar($resultado['importados'] === 2 && $resultado['rechazados'] === 3, 'El resumen parcial es incorrecto.');
    verificar(str_contains(implode(' ', $resultado['errores']), 'Fila 3'), 'El error de duplicado no indica su fila.');
    rechaza(fn() => importar($bd, "$encabezado;codigo\n"), 'Aceptó encabezados duplicados.');
    rechaza(fn() => importar($bd, 'codigo;ci'), 'Aceptó encabezados incompletos.');
    verificar((int)consultar($bd,"SELECT COUNT(*) n FROM estudiantes e JOIN paralelos p ON e.id_paralelo=p.id_paralelo WHERE p.id_curso<>e.id_curso")['n'] === 0, 'Se importó un paralelo ajeno al curso.');
    echo "Alta manual, catálogo con IDs no consecutivos e importación parcial: correctos.\n";

    $informe = informe_datos(['id_estudiante'=>$idEstudiante,'titulo'=>'Título de prueba','fecha'=>'2026-01-15','tipo_atencion'=>['Evaluación'],
        'motivo'=>'Motivo','aspecto_cognitivo'=>'Cognitivo','aspectos_afectivos'=>'Afectivo','estado'=>'Borrador']);
    verificar(informe_crear($bd,$informe,101) === 'INF-0001', 'Primera ficha incorrecta.');
    $idInforme = (int)consultar($bd,'SELECT MAX(id_informe) id FROM informes')['id'];
    $filaInforme = consultar($bd,"SELECT * FROM informes WHERE id_informe=$idInforme");
    verificar((int)$filaInforme['id_usuario']===101 && (int)$filaInforme['elaborado_por']===101 && $filaInforme['tipo']==='Individual' && $filaInforme['titulo']==='Título de prueba', 'No conserva el modelo individual y su autor.');
    verificar($filaInforme['id_historia']===null && $filaInforme['id_derivacion']===null, 'No admite informe sin historia.');
    informe_actualizar($bd,$idInforme,array_replace($informe,['estado'=>'Finalizado','titulo'=>'Título editado']));
    informe_actualizar($bd,$idInforme,array_replace($informe,['estado'=>'Finalizado','titulo'=>'Título editado']));
    verificar(consultar($bd,"SELECT estado FROM informes WHERE id_informe=$idInforme")['estado']==='Finalizado', 'No guarda el estado canónico.');
    foreach ([['estado'=>'Emitido'],['estado'=>'Anulado'],['titulo'=>''],['titulo'=>str_repeat('x',181)],['referido_por'=>str_repeat('x',151)],['recibido_por'=>str_repeat('x',151)],['tipo_atencion'=>['Desconocida']],['numero_atenciones'=>-1],['id_historia'=>987]] as $cambio) {
        rechaza(fn() => informe_crear($bd,array_replace($informe,$cambio),101), 'Aceptó informe inválido.');
    }
    rechaza(fn()=>informe_actualizar($bd,9999,$informe),'La edición inexistente comunicó éxito.');
    rechaza(fn()=>informe_actualizar($bd,$idInforme,array_replace($informe,['id_estudiante'=>9999])),'Aceptó cambio de estudiante.');
    $secuenciaAntes=consultar($bd,'SELECT ultimo FROM informes_secuencia WHERE id=1');
    rechaza(fn()=>informe_crear($bd,$informe,9999),'Aceptó autor inexistente.');
    verificar($secuenciaAntes===consultar($bd,'SELECT ultimo FROM informes_secuencia WHERE id=1'),'No revirtió la numeración tras fallo SQL.');
    $bd->query("INSERT INTO historias_clinicas (id_estudiante,id_usuario,fecha_apertura,motivo_consulta) VALUES ($idEstudiante,101,'2026-01-01','Motivo historia')");
    $idHistoria = $bd->insert_id;
    $bd->query("INSERT INTO seguimientos (id_historia,id_usuario,fecha,descripcion,acuerdos,recomendaciones,proxima_sesion) VALUES ($idHistoria,101,'2026-01-02','Evolución','Acuerdos separados','Recomendación verificable','2026-02-01')");
    $informe['id_historia']=$idHistoria;
    informe_crear($bd,$informe,102);
    verificar((int)consultar($bd,'SELECT id_historia FROM informes ORDER BY id_informe DESC LIMIT 1')['id_historia']===$idHistoria,'No vincula la historia correcta.');

    // Cuatro conexiones independientes compiten por la misma numeración.
    $trabajadores=[];
    $codigo = 'require ' . var_export($raiz.'/includes/informes_datos.php',true) . '; mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT); $c=' . var_export($config,true) . '; $b=new mysqli($c["host"],$c["usuario_bd"],$c["clave_bd"],' . var_export($nueva,true) . ',$c["puerto"]); $b->set_charset("utf8mb4"); echo informe_crear($b,' . var_export($informe,true) . ',101);';
    for($i=0;$i<4;$i++) {
        $p=proc_open([PHP_BINARY,'-r',$codigo],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $trabajadores[]=[$p,$pipes];
    }
    $fichas=[];
    foreach($trabajadores as [$p,$pipes]) {
        $fichas[]=stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
        verificar(proc_close($p)===0,'Falló informe concurrente: '.$error);
    }
    verificar(count(array_unique($fichas))===4,'La numeración concurrente produjo duplicados.');
    echo "Informes: creación, edición, rollback y numeración concurrente correctos.\n";

    mkdir($temporal.'/app',0700); mkdir($temporal.'/sesiones',0700);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz,FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if($archivo->getExtension()!=='php') continue;
        $relativa=substr($archivo->getPathname(),strlen($raiz)+1);
        if(str_starts_with($relativa,'tests'.DIRECTORY_SEPARATOR)) continue;
        $destino=$temporal.'/app/'.$relativa;
        if(!is_dir(dirname($destino))) mkdir(dirname($destino),0700,true);
        copy($archivo->getPathname(),$destino);
    }
    $configPrueba=$config; $configPrueba['base_datos']=$nueva;
    file_put_contents($temporal.'/app/config/login.php','<?php return '.var_export($configPrueba,true).';');
    $secreto=bin2hex(random_bytes(32));
    file_put_contents($temporal.'/fixture.php','<?php return '.var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','secreto'=>$secreto],true).';');
    copy(__DIR__.'/integracion_router.php',$temporal.'/router.php');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    $puerto=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1); fclose($socket);
    $servidor=proc_open([PHP_BINARY,'-d','display_errors=1','-S','127.0.0.1:'.$puerto,'-t',$temporal.'/app',$temporal.'/router.php'],
        [0=>['pipe','r'],1=>['file',$temporal.'/servidor.log','a'],2=>['file',$temporal.'/servidor.log','a']],$pipes,$temporal);
    fclose($pipes[0]);
    for($i=0;$i<40;$i++) { $s=@fsockopen('127.0.0.1',$puerto,$errno,$error,0.1); if($s){fclose($s);break;} usleep(100000); }
    foreach(['informes/registrar.php','informes/listar.php','informes/ver.php?id='.$idInforme,'informes/editar.php?id='.$idInforme,'estudiantes/registrar.php','estudiantes/listar.php'] as $ruta) {
        $r=http_prueba($ruta); verificar($r['codigo']===200,"Formulario no disponible: $ruta");
        if($ruta==='informes/registrar.php') verificar(str_contains($r['cuerpo'],'Recomendación verificable'),'No consulta recomendaciones de seguimientos.');
    }
    $token=['csrf'=>str_repeat('c',64)];
    $r=http_prueba('informes/guardar.php',array_replace($informe,['titulo'=>'','motivo'=>'Recuperar <contenido>'])+$token);
    verificar($r['codigo']===302 && str_contains($r['cabeceras'],'registrar.php'),'Alta inválida no vuelve al formulario.');
    $r=http_prueba('informes/registrar.php');
    verificar(str_contains($r['cuerpo'],'Recuperar &lt;contenido&gt;'),'No recupera/escapa el texto del informe.');
    $r=http_prueba('informes/guardar.php',$informe+$token+['id_usuario'=>9999]);
    verificar($r['codigo']===302 && str_contains($r['cabeceras'],'listar.php'),'Falló el receptor de alta de informes.');
    verificar((int)consultar($bd,'SELECT id_usuario FROM informes ORDER BY id_informe DESC LIMIT 1')['id_usuario']===101,'Aceptó autor desde POST.');
    $r=http_prueba('informes/actualizar.php',array_replace($informe,['id_informe'=>$idInforme,'titulo'=>'Edición HTTP','estado'=>'Finalizado'])+$token,null,'prueba_psicologa');
    verificar($r['codigo']===302 && str_contains($r['cabeceras'],'ver.php'),'Falló receptor de edición.');
    verificar((int)consultar($bd,"SELECT id_usuario FROM informes WHERE id_informe=$idInforme")['id_usuario']===101,'La edición cambia el autor.');
    $r=http_prueba('informes/actualizar.php',array_replace($informe,['id_informe'=>$idInforme,'recibido_por'=>str_repeat('x',151),'motivo'=>'Recuperar edición'])+$token);
    verificar(str_contains($r['cabeceras'],'editar.php'),'Edición inválida no redirige.');
    $r=http_prueba('informes/editar.php?id='.$idInforme);
    verificar(str_contains($r['cuerpo'],'Recuperar edición'),'No recupera los datos de edición.');
    $r=http_prueba('estudiantes/guardar.php',array_replace($datos,['estado'=>'Baja','nombres'=>'Recuperar <alumno>'])+$token);
    verificar(str_contains($r['cabeceras'],'registrar.php'),'Alta de estudiante inválida no redirige.');
    $r=http_prueba('estudiantes/registrar.php');
    verificar(str_contains($r['cuerpo'],'Recuperar &lt;alumno&gt;'),'No recupera/escapa los datos del estudiante.');
    $r=http_prueba('estudiantes/guardar.php',array_replace($datos,['codigo'=>'MANUALHTTP','ci'=>'MANUALHTTP'])+$token);
    verificar(str_contains($r['cabeceras'],'listar.php'),'Falló alta manual HTTP.');
    $csvHTTP=$encabezado."\n".str_replace(['DOS','CIDOS'],['HTTP','CIHTTP'],$filaCSV)."\nmal;columnas";
    $r=http_prueba('estudiantes/importar.php',$token,$csvHTTP);
    verificar($r['codigo']===302,'Falló carga multipart de CSV.');
    $r=http_prueba('estudiantes/listar.php');
    verificar(str_contains($r['cuerpo'],'1 estudiante(s); 1 fila(s) rechazadas'),'No presenta resumen de importación parcial.');
    verificar((int)consultar($bd,"SELECT COUNT(*) n FROM estudiantes WHERE codigo='HTTP'")['n']===1,'El archivo subido no persistió.');
    echo "Receptores HTTP, carga CSV, formularios y recuperación de errores: correctos.\n";
    echo "OK: $total comprobaciones. Ninguna escritura de prueba en la base real.\n";
} catch(Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");
    if(is_file($temporal.'/servidor.log')) fwrite(STDERR,file_get_contents($temporal.'/servidor.log'));
    $fallo=true;
} finally {
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    foreach($bases as $base) {
        if(!preg_match('/^psicologia_test_(nueva|actualizar)_[a-f0-9]{12}$/',$base)) throw new RuntimeException('Nombre de limpieza no permitido.');
        $admin->query("DROP DATABASE IF EXISTS `$base`");
    }
    $rutaReal=realpath($temporal);
    $raizTemporal=realpath(sys_get_temp_dir());
    if($rutaReal && str_starts_with($rutaReal,$raizTemporal.DIRECTORY_SEPARATOR) && basename($rutaReal)==='psicologia-integracion-'.$sufijo) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rutaReal,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            $archivo->isDir()?rmdir($archivo->getPathname()):unlink($archivo->getPathname());
        }
        rmdir($rutaReal);
    }
}
exit(isset($fallo)?1:0);
