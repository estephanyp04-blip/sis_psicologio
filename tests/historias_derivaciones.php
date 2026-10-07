<?php
/** Punto 3: persistencia clínica y derivaciones, exclusivamente con datos sintéticos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/historias_clinicas/datos.php';
require_once dirname(__DIR__) . '/derivaciones/datos.php';
require_once __DIR__ . '/datos_sinteticos.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/login.php';
$sufijo = bin2hex(random_bytes(6));
$base = 'psicologia_test_clinica_' . $sufijo;
$temporal = sys_get_temp_dir() . '/psicologia-clinica-' . $sufijo;
mkdir($temporal, 0700, true);
$admin = null;
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
    global $temporal;
    $p = proc_open($argumentos, [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/proceso.out', 'w'], 2 => ['file', $temporal . '/proceso.err', 'w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
    fclose($pipes[0]);
    $limite = microtime(true) + 45;
    do {
        $estado = proc_get_status($p);
        if (!$estado['running']) break;
        usleep(100000);
    } while (microtime(true) < $limite);
    if ($estado['running']) proc_terminate($p);
    proc_close($p);
    if ($estado['running'] || $estado['exitcode'] !== 0) throw new RuntimeException('Falló el proceso: ' . file_get_contents($temporal . '/proceso.err'));
    return file_get_contents($temporal . '/proceso.out');
}
function fila(mysqli $bd, string $sql): array { return $bd->query($sql)->fetch_assoc() ?? []; }
function foto(mysqli $bd): array
{
    $datos = [];
    foreach (['historias_clinicas', 'historia_opciones', 'historia_familiares', 'auditoria', 'derivaciones', 'derivacion_categorias'] as $tabla) {
        $datos[$tabla] = $bd->query("SELECT * FROM $tabla ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
    }
    return $datos;
}
function http(string $ruta, ?array $datos = null, string $usuario = 'prueba_admin'): array
{
    global $puerto, $secreto, $cookie;
    $headers = ['X-Prueba-Secreto: ' . $secreto, 'X-Prueba-Usuario: ' . $usuario, 'Connection: close'];
    if ($cookie) $headers[] = 'Cookie: ' . $cookie;
    $opciones = ['method' => $datos === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
    if ($datos !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $opciones['content'] = http_build_query($datos);
    }
    $opciones['header'] = implode("\r\n", $headers);
    $cuerpo = file_get_contents('http://127.0.0.1:' . $puerto . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    foreach ($http_response_header as $cabecera) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $cabecera, $m)) $cookie = $m[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
    verificar(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', (string)$cuerpo), "Error PHP en $ruta");
    return ['codigo' => (int)$m[1], 'cuerpo' => (string)$cuerpo, 'cabeceras' => implode("\n", $http_response_header)];
}
function formulario(string $html, string $id): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($doc);
    $pares = [];
    foreach ($xpath->query('//form[@id="' . $id . '"]//*[self::input or self::select or self::textarea][@name]') as $campo) {
        if ($campo->hasAttribute('disabled')) continue;
        $tipo = $campo->getAttribute('type');
        if (in_array($tipo, ['checkbox', 'radio'], true) && !$campo->hasAttribute('checked')) continue;
        $valor = $campo->getAttribute('value');
        if ($campo->tagName === 'textarea') $valor = $campo->textContent;
        if ($campo->tagName === 'select') {
            $opcion = $xpath->query('.//option[@selected]', $campo)->item(0) ?? $xpath->query('.//option', $campo)->item(0);
            $valor = $opcion ? $opcion->getAttribute('value') : '';
        }
        $pares[] = rawurlencode($campo->getAttribute('name')) . '=' . rawurlencode($valor);
    }
    parse_str(implode('&', $pares), $datos);
    verificar($datos !== [], "No se encontró el formulario $id");
    return $datos;
}

try {
    $admin = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $base, '--aplicar', '--instalar']);
    $bd = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], $base, $config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    prueba_autores($bd);
    prueba_estudiantes($bd, 8);
    $bd->query("INSERT INTO derivaciones (id_derivacion,fecha,id_estudiante,id_docente,id_materia,motivo,prioridad,observaciones) VALUES
        (1,'2026-01-01',1,201,5,'Motivo inicial','Alta','Referencia visible'),
        (2,'2026-01-02',1,201,2,'Motivo reciente','Media','Otra referencia'),
        (3,'2026-01-03',2,202,2,'Motivo ajeno','Media','Privado de otro estudiante'),
        (4,'2026-01-04',3,201,2,'Motivo navegador','Alta','Referencia navegador')");
    $entrada = ['id_estudiante' => 1, 'id_derivacion' => 1, 'fecha_apertura' => '2026-01-05',
        'fecha_derivacion' => '2026-01-01', 'motivo_consulta' => 'Motivo clínico <conservar>', 'estado' => 'Activa',
        'lugar_nacimiento' => 'Lugar clínico', 'celular_estudiante' => '222', 'padre_madre' => 'Tutor clínico',
        'tutor_curso' => 'Tutor <curso>', 'talla_cm' => '155.50', 'peso_kg' => '48.25', 'situacion_escolar' => 'Buena', 'valoracion_familiar' => 'Regular',
        'formulario_completo' => '1', 'familiares_presentes' => '1',
        'familiares' => [['nombre' => 'Familiar <uno>', 'edad' => '0', 'relacion' => 'Hermano', 'profesion' => 'Estudio', 'ocupacion' => 'Ocupación', 'observaciones' => 'Detalle familiar']]];
    foreach (HISTORIA_TEXTOS as $campo => $_) if (!isset($entrada[$campo])) $entrada[$campo] = 'Dato clínico ' . $campo;
    foreach (HISTORIA_OPCIONES as $grupo => $opciones) {
        $entrada[$grupo] = [$opciones[0]];
        $entrada['opciones_presentes'][$grupo] = '1';
    }
    $id = historia_guardar($bd, $entrada, 101);
    $guardada = fila($bd, "SELECT * FROM historias_clinicas WHERE id_historia=$id");
    foreach (HISTORIA_TEXTOS as $campo => $_) verificar($guardada[$campo] === $entrada[$campo], "El alta pierde $campo");
    verificar((int)$guardada['id_psicologa'] === 101 && (int)$guardada['id_derivacion_origen'] === 1, 'Alta sin autor o vínculo.');
    $hijos = historia_cargar_hijos($bd, $id);
    foreach (HISTORIA_OPCIONES as $grupo => $_) verificar($hijos[$grupo] === $entrada[$grupo], "Alta pierde $grupo");
    verificar($hijos['familiares'][0]['edad'] === 0 && $hijos['familiares'][0]['nombre'] === 'Familiar <uno>', 'Alta pierde familiar o edad cero.');
    verificar(fila($bd, 'SELECT accion FROM auditoria ORDER BY id_auditoria DESC LIMIT 1')['accion'] === 'Crear', 'No audita el alta.');

    historia_guardar($bd, ['id_estudiante' => 1, 'formulario_completo' => '1', 'id_usuario' => 999], 102, $id);
    $editada = fila($bd, "SELECT * FROM historias_clinicas WHERE id_historia=$id");
    foreach (HISTORIA_TEXTOS as $campo => $_) verificar($editada[$campo] === $guardada[$campo], "Edición parcial pierde $campo");
    verificar((int)$editada['id_psicologa'] === 101, 'Edición cambia autor.');
    verificar(historia_cargar_hijos($bd, $id) === $hijos, 'Edición parcial borra hijos omitidos.');
    $auditoria = fila($bd, 'SELECT * FROM auditoria ORDER BY id_auditoria DESC LIMIT 1');
    verificar((int)$auditoria['id_usuario'] === 102 && $auditoria['accion'] === 'Actualizar' && $auditoria['fecha'] !== null, 'No registra editora y fecha.');
    foreach (['1000', '-1', '1.555', '48 kg', ['4']] as $valor) rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['peso' => $valor]), 102, $id), 'Acepta peso inválido.');
    foreach ([['id_estudiante' => 2], ['id_derivacion' => 3], ['formulario_completo' => ''], ['fecha_apertura' => '2026-02-30'],
        ['motivo_consulta' => ''], ['estado' => 'Desconocido'], ['talla' => str_repeat('x', 31)], ['atencion_distraccion' => ['Opción inventada']],
        ['familiares' => [['nombre' => 'Familiar', 'edad' => 121]]]] as $cambio) {
        $antes = foto($bd);
        rechaza(fn() => historia_guardar($bd, array_replace($entrada, $cambio), 102, $id), 'Acepta historia inválida.');
        verificar(foto($bd) === $antes, 'El rechazo modifica datos.');
    }
    $antes = foto($bd);
    rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['observaciones' => 'Cambio que debe revertirse']), 9999, $id), 'Acepta auditor inexistente.');
    verificar(foto($bd) === $antes, 'Fallo de auditoría no revierte historia e hijos.');
    rechaza(fn() => historia_guardar($bd, $entrada, 101), 'Permite dos historias para un estudiante.');
    rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['id_estudiante' => 2, 'id_derivacion' => 1]), 101), 'Alta vincula derivación ajena.');
    $sinDerivacion = historia_guardar($bd, array_replace($entrada, ['id_estudiante' => 2, 'id_derivacion' => '']), 101);
    $sin = fila($bd, "SELECT * FROM historias_clinicas WHERE id_historia=$sinDerivacion");
    verificar($sin['id_derivacion_origen'] === null, 'No admite historia sin derivación.');
    $bd->query("INSERT INTO grupos_opciones_historia (id_grupo,codigo,nombre) VALUES (99,'grupo_legado','Grupo previo')");
    $bd->query("INSERT INTO opciones_historia (id_opcion,id_grupo,descripcion,estado) VALUES
        (98,1,'Opción histórica','Inactivo'),(99,99,'Conservar legado','Inactivo')");
    $bd->query("INSERT INTO historia_opciones (id_historia,id_opcion) VALUES ($id,98),($id,99)");
    $bd->query("UPDATE estudiantes SET estado='Retirado' WHERE id_estudiante=8");
    rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['id_estudiante' => 8, 'id_derivacion' => 0]), 101), 'Alta acepta estudiante retirado.');
    echo "Persistencia de campos, autoría, auditoría, validaciones y rollback: correctos.\n";

    $observaciones = "Categorías observadas: Conducta en Aula, Categoría histórica\n\nObservaciones adicionales: Texto previo <seguro>\n\nSolicitud de cita psicológica: Sí\n\nProfesional solicitado: Psicóloga de prueba";
    $stmt = $bd->prepare('UPDATE derivaciones SET observaciones=? WHERE id_derivacion=1');
    $stmt->bind_param('s', $observaciones); $stmt->execute(); $stmt->close();
    $derivacion = ['fecha' => '2026-01-01', 'materia' => 'Lenguaje', 'motivo' => 'Motivo editado', 'prioridad' => 'Alta'];
    derivacion_actualizar($bd, 1, $derivacion + ['observaciones' => ''], 3, 201);
    verificar(fila($bd, 'SELECT observaciones FROM derivaciones WHERE id_derivacion=1')['observaciones'] === $observaciones, 'Un POST incompleto borra observaciones.');
    $antes = foto($bd);
    rechaza(fn() => derivacion_actualizar($bd, 1, $derivacion, 3, 202), 'Docente modifica derivación ajena.');
    rechaza(fn() => derivacion_actualizar($bd, 1, $derivacion, 2, 0), 'Psicóloga edita derivación sin permiso.');
    rechaza(fn() => derivacion_actualizar($bd, 1, $derivacion + ['observaciones_presentes' => '1'], 1, 0), 'Acepta observaciones truncadas.');
    verificar(foto($bd) === $antes, 'Rechazo de derivación altera datos.');
    $bd->query("UPDATE derivaciones SET estado='Atendido' WHERE id_derivacion=3");
    rechaza(fn() => derivacion_actualizar($bd, 3, $derivacion, 1, 0), 'Edita derivación ya atendida.');

    mkdir($temporal . '/app', 0700); mkdir($temporal . '/sesiones', 0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if ($archivo->getExtension() !== 'php') continue;
        $relativa = substr($archivo->getPathname(), strlen($raiz) + 1);
        if (str_starts_with($relativa, 'tests' . DIRECTORY_SEPARATOR)) continue;
        $destino = $temporal . '/app/' . $relativa;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0700, true);
        copy($archivo->getPathname(), $destino);
    }
    $configPrueba = $config; $configPrueba['base_datos'] = $base; $configPrueba['base_url'] = '';
    file_put_contents($temporal . '/app/config/login.php', '<?php return ' . var_export($configPrueba, true) . ';');
    $secreto = bin2hex(random_bytes(32));
    file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export(['app' => $temporal . '/app', 'sesiones' => $temporal . '/sesiones', 'secreto' => $secreto], true) . ';');
    copy(__DIR__ . '/integracion_router.php', $temporal . '/router.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $puerto = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1); fclose($socket);
    $servidor = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-S', '127.0.0.1:' . $puerto, '-t', $temporal . '/app', $temporal . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/servidor.log', 'a'], 2 => ['file', $temporal . '/servidor.log', 'a']], $pipes, $temporal);
    fclose($pipes[0]);
    for ($i = 0; $i < 40; ++$i) { $s = @fsockopen('127.0.0.1', $puerto, $errno, $error, 0.1); if ($s) { fclose($s); break; } usleep(100000); }
    foreach (['' => 400, '?id_estudiante=0' => 400, '?id_estudiante[]=1' => 400, '?id_estudiante=9999' => 404, '?id_estudiante=1' => 200, '?id_estudiante=4' => 200] as $consulta => $codigo) {
        $r = http('historias_clinicas/ajax_derivaciones.php' . $consulta);
        verificar($r['codigo'] === $codigo && str_contains($r['cabeceras'], 'application/json'), 'Contrato JSON incorrecto.');
        $json = json_decode($r['cuerpo'], true, 512, JSON_THROW_ON_ERROR);
        if ($consulta === '?id_estudiante=1') verificar(array_column($json['derivaciones'], 'id') === [2, 1], 'JSON incluye derivaciones ajenas o desordenadas.');
        if ($consulta === '?id_estudiante=4') verificar($json['derivaciones'] === [], 'Sin derivaciones no devuelve lista vacía.');
    }
    $r = http('historias_clinicas/ajax_derivaciones.php?id_estudiante=1', []);
    verificar($r['codigo'] === 405 && isset(json_decode($r['cuerpo'], true)['error']), 'El endpoint acepta POST/no responde JSON.');
    $r = http('historias_clinicas/editar.php?id=' . $id);
    verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'Texto previo &lt;seguro&gt;'), 'Edición pierde referencia de derivación.');
    $post = formulario($r['cuerpo'], 'formHistoriaClinica');
    verificar($post['lugar_nacimiento'] === 'Lugar de ficha' && $post['celular_estudiante'] === '111' && $post['padre_madre'] === 'Tutor de ficha', 'No muestra los datos vigentes de la ficha.');
    verificar(in_array('Opción histórica', $post['atencion_distraccion'], true), 'No muestra opción histórica para conservarla.');
    verificar($post['derivado_por'] === 'Docente Uno' && $post['fecha_derivacion'] === '2026-01-01', 'Edición pierde docente y fecha de origen.');
    verificar($post['tutor_curso'] === 'Tutor <curso>' && $post['talla'] === '155.50' && $post['peso'] === '48.25', 'Formulario pierde tutor o medidas.');
    $r = http('historias_clinicas/actualizar.php', $post, 'prueba_psicologa');
    verificar(str_contains($r['cabeceras'], 'ver.php?id=' . $id), 'No guarda formulario renderizado.');
    $editada = fila($bd, "SELECT * FROM historias_clinicas WHERE id_historia=$id");
    foreach (HISTORIA_TEXTOS as $campo => $_) verificar($editada[$campo] === $guardada[$campo], "Ida y vuelta del formulario pierde $campo");
    verificar((int)$editada['id_psicologa'] === 101, 'Receptor HTTP cambia autor.');
    verificar((int)fila($bd, "SELECT COUNT(*) n FROM historia_opciones WHERE id_opcion=99")['n'] === 1, 'Borra grupos históricos no representados.');
    $r = http('historias_clinicas/ver.php?id=' . $id);
    verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'Tutor &lt;curso&gt;') && str_contains($r['cuerpo'], 'Docente Uno') && str_contains($r['cuerpo'], 'Dato clínico impresion_diagnostica') && str_contains($r['cuerpo'], 'Familiar &lt;uno&gt;'), 'Detalle no recupera campos clínicos escapados.');
    $antes = foto($bd);
    $invalido = array_replace($post, ['motivo_consulta' => 'Recuperar <edición>', 'talla' => '1000', 'peso' => '51.75', 'valoracion' => 'Recuperar valoración', 'tutor_curso' => 'Recuperar tutor', 'atencion_distraccion' => [], 'familiares' => []]);
    $r = http('historias_clinicas/actualizar.php', $invalido);
    verificar(str_contains($r['cabeceras'], 'editar.php?id=' . $id) && foto($bd) === $antes, 'Edición inválida no revierte/redirige.');
    $r = http('historias_clinicas/editar.php?id=' . $id);
    $recuperado = formulario($r['cuerpo'], 'formHistoriaClinica');
    verificar(str_contains($r['cuerpo'], 'Recuperar &lt;edición&gt;') && !isset($recuperado['atencion_distraccion']), 'No recupera texto o casillas desmarcadas.');
    verificar($recuperado['familiares'][0]['nombre'] === '', 'Error restaura familiares que se habían quitado.');
    verificar($recuperado['talla'] === '1000' && $recuperado['peso'] === '51.75' && $recuperado['valoracion'] === 'Recuperar valoración' && $recuperado['tutor_curso'] === 'Recuperar tutor', 'Error pierde talla, peso, valoración o tutor.');
    $recuperado['talla'] = '160.25';
    $r = http('historias_clinicas/actualizar.php', $recuperado);
    verificar(str_contains($r['cabeceras'], 'ver.php'), 'No permite corregir el error.');
    verificar(historia_cargar_hijos($bd, $id)['familiares'] === [] && historia_cargar_hijos($bd, $id)['atencion_distraccion'] === [], 'No guarda borrado explícito de hijos.');
    $r = http('historias_clinicas/registrar.php?id_estudiante=4');
    $alta = formulario($r['cuerpo'], 'formHistoriaClinica');
    $alta['id_derivacion'] = ''; $alta['motivo_consulta'] = 'Alta <recuperable>'; $alta['peso'] = '1000';
    $r = http('historias_clinicas/guardar.php', $alta);
    verificar(str_contains($r['cabeceras'], 'registrar.php'), 'Alta inválida no vuelve al formulario.');
    $r = http('historias_clinicas/registrar.php');
    verificar(str_contains($r['cuerpo'], 'Alta &lt;recuperable&gt;'), 'Alta pierde datos ante error.');
    $alta = formulario($r['cuerpo'], 'formHistoriaClinica'); $alta['peso'] = '45.50'; $alta['id_usuario'] = 999;
    $r = http('historias_clinicas/guardar.php', $alta);
    verificar(str_contains($r['cabeceras'], 'ver.php'), 'No guarda alta HTTP sin derivación.');
    verificar((int)fila($bd, 'SELECT id_psicologa FROM historias_clinicas WHERE id_estudiante=4')['id_psicologa'] === 101, 'Alta acepta autor de POST.');
    $bd->query("INSERT INTO categorias_derivacion (nombre,estado) VALUES ('Categoría nueva de catálogo','Activo'),('Categoría inactiva','Inactivo')");
    $r = http('derivaciones/editar.php?id=1');
    verificar(str_contains($r['cuerpo'], 'Categoría nueva de catálogo') && !str_contains($r['cuerpo'], 'Categoría inactiva') && !str_contains($r['cuerpo'], 'Social / Emocional'), 'Edición no usa el catálogo activo.');
    $postDerivacion = formulario($r['cuerpo'], 'formEditarDerivacion');
    $r = http('derivaciones/actualizar.php', $postDerivacion);
    verificar(str_contains($r['cabeceras'], 'listar.php') && fila($bd, 'SELECT observaciones FROM derivaciones WHERE id_derivacion=1')['observaciones'] === $observaciones, 'Guardar derivación sin JS altera observaciones.');
    $postDerivacion['observaciones_adicionales'] = 'Texto cambiado <nuevo>';
    $postDerivacion['categorias'] = ['Categoría nueva de catálogo'];
    $r = http('derivaciones/actualizar.php', $postDerivacion);
    $nuevas = fila($bd, 'SELECT observaciones FROM derivaciones WHERE id_derivacion=1')['observaciones'];
    verificar(str_contains($nuevas, 'Texto cambiado <nuevo>') && str_contains($nuevas, 'Categoría nueva de catálogo') && str_contains($nuevas, 'Profesional solicitado: Psicóloga de prueba') && str_contains($nuevas, 'Solicitud de cita psicológica: Sí'), 'Edición no conserva solicitud/profesional.');
    verificar(fila($bd, 'SELECT c.nombre FROM derivacion_categorias dc JOIN categorias_derivacion c ON c.id_categoria=dc.id_categoria WHERE dc.id_derivacion=1')['nombre'] === 'Categoría nueva de catálogo', 'Texto y categoría relacional no coinciden.');
    $bd->query("UPDATE categorias_derivacion SET estado='Inactivo' WHERE nombre='Categoría nueva de catálogo'");
    $bd->query("UPDATE materias SET estado='Inactivo' WHERE id_materia=5");
    $r = http('derivaciones/editar.php?id=1');
    $conservada = formulario($r['cuerpo'], 'formEditarDerivacion');
    verificar(in_array('Categoría nueva de catálogo', $conservada['categorias'], true) && $conservada['materia'] === 'Lenguaje', 'No permite conservar el catálogo histórico.');
    $r = http('derivaciones/actualizar.php', $conservada);
    verificar(str_contains($r['cabeceras'], 'listar.php'), 'No guarda categorías y materia históricas conservadas.');
    $antes = foto($bd);
    foreach (['Categoría inactiva', 'Social / Emocional', 'Inventada'] as $categoria) {
        rechaza(fn() => derivacion_actualizar($bd, 1, array_replace($postDerivacion, ['categorias' => [$categoria]]), 1, 0), 'Acepta categoría ausente/inactiva no vinculada.');
        verificar(foto($bd) === $antes, 'Categoría rechazada modifica la derivación.');
    }
    $postDerivacion['prioridad'] = 'Invalida'; $postDerivacion['observaciones_adicionales'] = 'Recuperar <derivación>';
    $r = http('derivaciones/actualizar.php', $postDerivacion);
    verificar(str_contains($r['cabeceras'], 'editar.php'), 'Derivación inválida no vuelve a edición.');
    $r = http('derivaciones/editar.php?id=1');
    verificar(str_contains($r['cuerpo'], 'Recuperar &lt;derivación&gt;'), 'No recupera/escapa derivación rechazada.');
    echo "JSON, formularios, receptores HTTP, conservación y recuperación de errores: correctos.\n";
    if (in_array('--navegador', $argv, true)) {
        $chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('No se encontró Chrome para --navegador.');
        file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export(['app' => $temporal . '/app', 'sesiones' => $temporal . '/sesiones', 'secreto' => $secreto, 'navegador' => true], true) . ';');
        file_put_contents($temporal . '/navegador.html', ejecutar([PHP_BINARY, __DIR__ . '/historias_navegador.php']));
        $html = ejecutar([$chrome, '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--user-data-dir=' . $temporal . '/chrome', '--dump-dom', '--virtual-time-budget=15000',
            'http://127.0.0.1:' . $puerto . '/__prueba_navegador?secreto=' . $secreto]);
        $doc = new DOMDocument(); @$doc->loadHTML($html);
        $resultado = json_decode($doc->getElementById('resultado')?->textContent ?? '', true);
        verificar(($resultado['ok'] ?? false) === true, 'Prueba navegador: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE));
        echo 'Navegador Chrome: ' . $resultado['total'] . " comprobaciones correctas.\n";
        verificar(fila($bd, 'SELECT motivo_consulta FROM historias_clinicas WHERE id_estudiante=5')['motivo_consulta'] === 'Alta sin derivación desde navegador', 'El alta de navegador no persistió.');
        verificar((int)fila($bd, 'SELECT id_psicologa FROM historias_clinicas WHERE id_estudiante=1')['id_psicologa'] === 101, 'La edición de navegador cambia autor.');
    }
    echo "OK: $total comprobaciones. Ninguna escritura en la base real.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    if (is_file($temporal . '/servidor.log')) fwrite(STDERR, implode('', array_slice(file($temporal . '/servidor.log'), -20)));
    $fallo = true;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    if (!preg_match('/^psicologia_test_clinica_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Base de limpieza no permitida.');
    if ($admin instanceof mysqli) $admin->query("DROP DATABASE IF EXISTS `$base`");
    $ruta = realpath($temporal);
    if ($ruta && dirname($ruta) === realpath(sys_get_temp_dir()) && basename($ruta) === 'psicologia-clinica-' . $sufijo) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
        }
        rmdir($ruta);
    }
}
exit(isset($fallo) ? 1 : 0);
