<?php
/** Pruebas HTTP aisladas. Uso: C:\xampp\php\php.exe tests\seguridad.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
$raiz = dirname(__DIR__);
$permisos = require $raiz . '/config/permisos.php';
$soloAcceso = in_array('--solo-acceso', $argv, true);
$internosModulo = [
    'estudiantes/datos.php', 'estudiantes/csv.php', 'derivaciones/datos.php',
    'citas/datos.php', 'citas/formulario.php', 'historias_clinicas/datos.php',
    'historias_clinicas/formulario.php', 'informes/datos.php', 'estadisticas/datos.php',
];
$temporal = sys_get_temp_dir() . '/psicologia-seguridad-' . bin2hex(random_bytes(8));
mkdir($temporal, 0700, true);
mkdir($temporal . '/app', 0700);
mkdir($temporal . '/sesiones', 0700);
$servidor = null;
$total = 0;

function comprobar(bool $condicion, string $mensaje): void
{
    global $total;
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
}

function peticion(string $ruta, string $metodo = 'GET', array $caso = [], array $datos = []): array
{
    global $puerto, $secreto;
    $cabeceras = [
        'X-Prueba-Secreto: ' . $secreto,
        'X-Prueba-Caso: ' . base64_encode(json_encode($caso)),
        'Connection: close',
    ];
    $opciones = ['method' => $metodo, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10];
    if ($metodo === 'POST') {
        $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
        $opciones['content'] = http_build_query($datos);
    }
    $opciones['header'] = implode("\r\n", $cabeceras);
    $cuerpo = file_get_contents('http://127.0.0.1:' . $puerto . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $estado);
    return ['codigo' => (int) ($estado[1] ?? 0), 'cuerpo' => (string) $cuerpo, 'cabeceras' => $http_response_header ?? []];
}

function estadoNegocio(array $respuesta): array
{
    $partes = explode("\n__ESTADO_PRUEBA__", $respuesta['cuerpo']);
    return count($partes) === 2 ? (json_decode($partes[1], true) ?: []) : [];
}

try {
    // Copia de PHP fuera del DocumentRoot; la aplicación real no se modifica.
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    foreach ($archivos as $archivo) {
        if ($archivo->getExtension() !== 'php') continue;
        $relativa = substr($archivo->getPathname(), strlen($raiz) + 1);
        if (str_starts_with($relativa, 'tests' . DIRECTORY_SEPARATOR)) continue;
        $destino = $temporal . '/app/' . $relativa;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0700, true);
        copy($archivo->getPathname(), $destino);
    }
    // Detiene las pruebas de acceso antes del código de negocio. Para los casos
    // funcionales se usa la MISMA conexión con tablas TEMPORARY, nunca otra.
    file_put_contents($temporal . '/app/config/conexion.php', <<<'PHP'
<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
if (!empty($GLOBALS['prueba_negocio'])) {
    $conexion = login_bd();
} else {
    echo 'ACCESO_VALIDADO';
    exit;
}
PHP
    );
    $secreto = bin2hex(random_bytes(32));
    $config = ['app' => $temporal . '/app', 'sesiones' => $temporal . '/sesiones', 'secreto' => $secreto, 'internos_modulo' => $internosModulo];
    file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export($config, true) . ';');
    copy(__DIR__ . '/seguridad_router.php', $temporal . '/router.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('No se pudo reservar un puerto local: ' . $error);
    $puerto = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $servidor = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', '-S', '127.0.0.1:' . $puerto, '-t', $temporal . '/app', $temporal . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/servidor.log', 'a'], 2 => ['file', $temporal . '/servidor.log', 'a']],
        $pipes,
        $temporal
    );
    if (!is_resource($servidor)) throw new RuntimeException('No se pudo iniciar el servidor de pruebas.');
    fclose($pipes[0]);
    $listo = false;
    for ($intento = 0; $intento < 40; ++$intento) {
        $conexion = @fsockopen('127.0.0.1', $puerto, $errno, $error, 0.1);
        if ($conexion) { fclose($conexion); $listo = true; break; }
        usleep(100000);
    }
    comprobar($listo, 'El servidor de pruebas no inició.');
    $token = str_repeat('c', 64);

    foreach ($permisos as $ruta => $permiso) {
        $metodo = $permiso['metodos'][0];
        $r = peticion($ruta, $metodo);
        if ($ruta === 'historias_clinicas/ajax_derivaciones.php') {
            comprobar($r['codigo'] === 401, 'El endpoint JSON debe devolver 401 sin sesión.');
            comprobar(isset(json_decode($r['cuerpo'], true)['error']), 'El error de sesión no es JSON.');
        } else {
            comprobar($r['codigo'] === 303, "Sin sesión no redirige a login: $ruta ({$r['codigo']})");
            comprobar(str_contains(implode(' ', $r['cabeceras']), '/login.php'), "Redirección incorrecta: $ruta");
        }
        foreach ([1, 2, 3, 4] as $rol) {
            $r = peticion($ruta, $metodo, ['rol' => $rol], ['csrf' => $token]);
            $permitido = in_array($rol, $permiso['roles'], true);
            comprobar($r['codigo'] === ($permitido ? 200 : 403), "Permiso incorrecto en $ruta, rol $rol ({$r['codigo']})");
            comprobar(str_contains($r['cuerpo'], 'ACCESO_VALIDADO') === $permitido, "Se alcanzó negocio sin permiso: $ruta, rol $rol");
        }
        $rol = $permiso['roles'][0];
        if (in_array('POST', $permiso['metodos'], true)) {
            foreach ([[], ['csrf' => 'incorrecto'], ['csrf' => ['array']]] as $datos) {
                $r = peticion($ruta, 'POST', ['rol' => $rol], $datos);
                comprobar($r['codigo'] === 403 && !str_contains($r['cuerpo'], 'ACCESO_VALIDADO'), "CSRF no rechazado: $ruta");
            }
            $r = peticion($ruta, 'POST', ['rol' => $rol], ['csrf' => $token]);
            comprobar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'ACCESO_VALIDADO'), "CSRF válido rechazado: $ruta");
        }
        $r = peticion($ruta, in_array('GET', $permiso['metodos'], true) ? 'PUT' : 'GET', ['rol' => $rol]);
        comprobar($r['codigo'] === 405, "Método no permitido aceptado: $ruta");
    }
    echo 'Matriz de ' . count($permisos) . " rutas, cuatro roles, métodos y CSRF: correcta.\n";

    // Reglas de negocio fijas: no derivar estas expectativas de la política.
    foreach ([
        ['usuarios/guardar.php', 'POST', 2], ['usuarios/actualizar.php', 'POST', 3],
        ['docentes/listar.php', 'GET', 3], ['estudiantes/listar.php', 'GET', 3],
        ['historias_clinicas/ver.php', 'GET', 4], ['citas/cancelar.php', 'POST', 4],
        ['estadisticas/index.php', 'GET', 3], ['estadisticas/index.php', 'GET', 4],
        ['derivaciones/editar.php', 'GET', 2], ['informes/guardar.php', 'POST', 4],
    ] as [$ruta, $metodo, $rol]) {
        $r = peticion($ruta, $metodo, ['rol' => $rol], ['csrf' => $token]);
        comprobar($r['codigo'] === 403, "Se amplió un permiso restringido: $ruta, rol $rol");
    }

    foreach (['vencida', 'duracion', 'inactivo', 'rol_inactivo', 'clave_cambiada', 'rol_cambiado'] as $sesion) {
        $r = peticion('index.php', 'GET', ['rol' => 1, 'sesion' => $sesion]);
        comprobar($r['codigo'] === 303, "Sesión revocada aceptada: $sesion");
        $r = peticion('historias_clinicas/ajax_derivaciones.php?id_estudiante=1', 'GET', ['rol' => 1, 'sesion' => $sesion]);
        comprobar($r['codigo'] === 401 && isset(json_decode($r['cuerpo'], true)['error']), "El endpoint no rechaza sesión revocada con JSON: $sesion");
    }
    foreach (['sin_docente', 'docente_duplicado'] as $sesion) {
        $r = peticion('derivaciones/listar.php', 'GET', ['rol' => 3, 'sesion' => $sesion]);
        comprobar($r['codigo'] === 303, "Vínculo docente inválido aceptado: $sesion");
    }
    foreach (array_merge(glob($raiz . '/config/*.php'), glob($raiz . '/includes/*.php'), array_map(static fn($ruta) => $raiz . '/' . $ruta, $internosModulo)) as $archivo) {
        $ruta = str_replace('\\', '/', substr($archivo, strlen($raiz) + 1));
        foreach (['GET', 'POST'] as $metodo) {
            $r = peticion($ruta, $metodo);
            comprobar($r['codigo'] === 404, "Archivo interno accesible por $metodo: $ruta");
        }
    }
    echo "Sesiones vencidas/revocadas y archivos internos: correctos.\n";

    // El modo de acceso permite validar únicamente la política de las rutas.
    if (!$soloAcceso) {
        foreach (['ver.php', 'editar.php'] as $pagina) {
            $r = peticion('derivaciones/' . $pagina . '?id=2', 'GET', ['rol' => 3, 'negocio' => true]);
            // El sufijo de prueba incluye el estado completo, solo se inspecciona el HTML real.
            $html = explode("\n__ESTADO_PRUEBA__", $r['cuerpo'])[0];
            comprobar($r['codigo'] === 302 && !str_contains($html, 'AJENA_PRIVADA'), "Docente consulta derivación ajena en $pagina");
            $r = peticion('derivaciones/' . $pagina . '?id=1', 'GET', ['rol' => 3, 'negocio' => true]);
            comprobar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'PROPIA_PRIVADA'), "Docente no puede consultar su derivación en $pagina");
        }
        $datos = ['csrf' => $token, 'id_derivacion' => 2, 'fecha' => date('Y-m-d'), 'materia' => 'Lenguaje y Comunicación', 'motivo' => 'CAMBIO_PRUEBA', 'observaciones' => 'OBS_PRUEBA', 'prioridad' => 'Media'];
        $r = peticion('derivaciones/actualizar.php', 'POST', ['rol' => 3, 'negocio' => true], $datos);
        comprobar((estadoNegocio($r)['derivaciones'][1]['motivo'] ?? '') === 'AJENA_PRIVADA', 'Docente actualizó una derivación ajena.');
        $datos['id_derivacion'] = 1;
        $r = peticion('derivaciones/actualizar.php', 'POST', ['rol' => 3, 'negocio' => true], $datos);
        comprobar((estadoNegocio($r)['derivaciones'][0]['motivo'] ?? '') === 'CAMBIO_PRUEBA', 'No se pudo actualizar una derivación propia con CSRF válido.');
        $r = peticion('derivaciones/eliminar.php', 'POST', ['rol' => 3, 'negocio' => true], ['csrf' => $token, 'id_derivacion' => 2]);
        comprobar(count(estadoNegocio($r)['derivaciones'] ?? []) === 2, 'Docente eliminó una derivación ajena.');
        $r = peticion('derivaciones/eliminar.php', 'POST', ['rol' => 3, 'negocio' => true], ['csrf' => $token, 'id_derivacion' => 1]);
        comprobar(count(estadoNegocio($r)['derivaciones'] ?? []) === 1, 'No se pudo eliminar una derivación propia con CSRF válido.');
        $r = peticion('citas/cancelar.php?id=1', 'GET', ['rol' => 2, 'negocio' => true]);
        comprobar($r['codigo'] === 405 && (estadoNegocio($r)['cita'] ?? '') === 'Pendiente', 'GET canceló una cita.');
        $r = peticion('citas/cancelar.php', 'POST', ['rol' => 2, 'negocio' => true], ['id_cita' => 1]);
        comprobar($r['codigo'] === 403 && (estadoNegocio($r)['cita'] ?? '') === 'Pendiente', 'POST sin CSRF canceló una cita.');
        $r = peticion('citas/cancelar.php', 'POST', ['rol' => 2, 'negocio' => true], ['id_cita' => 1, 'csrf' => $token]);
        comprobar((estadoNegocio($r)['cita'] ?? '') === 'Cancelada', 'POST válido no canceló la cita de prueba.');
        echo "Propiedad de derivaciones y cancelación de citas: correctas.\n";

        $estadoEstudiante = static fn(array $r): array => array_intersect_key(estadoNegocio($r), array_flip(['estudiantes', 'inscripciones']));
        $baseEstudiante = $estadoEstudiante(peticion('estudiantes/listar.php', 'GET', ['rol' => 2, 'negocio' => true]));
        comprobar(count($baseEstudiante['inscripciones'] ?? []) === 4, 'No se prepararon las inscripciones de prueba.');
        foreach ([1, 2] as $rol) {
            foreach (['retirar' => 'Retirado', 'reactivar' => 'Activo'] as $accion => $estado) {
                $r = peticion('estudiantes/cambiar_estado.php', 'POST', ['rol' => $rol, 'negocio' => true, 'estudiante_retirado' => $accion === 'reactivar'],
                    ['csrf' => $token, 'id_estudiante' => 1, 'accion' => $accion]);
                $guardado = $estadoEstudiante($r);
                comprobar($r['codigo'] === 302, "No redirige después de $accion, rol $rol.");
                comprobar(($guardado['estudiantes'][0]['estado'] ?? '') === $estado, "No se pudo $accion al estudiante.");
                comprobar(($guardado['inscripciones'][2]['estado'] ?? '') === $estado, "No se pudo $accion la última inscripción.");
                foreach ([0, 1, 3] as $indice) {
                    comprobar(($guardado['inscripciones'][$indice] ?? null) === $baseEstudiante['inscripciones'][$indice], 'Se alteró una inscripción histórica o ajena.');
                }
                comprobar(($guardado['estudiantes'][1] ?? null) === $baseEstudiante['estudiantes'][1], 'Se modificó otro estudiante.');
            }
        }
        foreach ([
            ['GET', 2, []],
            ['POST', 2, ['id_estudiante' => 1, 'accion' => 'retirar']],
            ['POST', 3, ['csrf' => $token, 'id_estudiante' => 1, 'accion' => 'retirar']],
            ['POST', 4, ['csrf' => $token, 'id_estudiante' => 1, 'accion' => 'retirar']],
        ] as [$metodo, $rol, $datos]) {
            $r = peticion('estudiantes/cambiar_estado.php', $metodo, ['rol' => $rol, 'negocio' => true], $datos);
            comprobar($r['codigo'] === ($metodo === 'GET' ? 405 : 403) && $estadoEstudiante($r) === $baseEstudiante, 'Se cambió el estado sin método, CSRF o rol permitido.');
        }
        foreach ([['id_estudiante' => 0], ['id_estudiante' => 999], ['accion' => 'borrar'], ['accion' => ['retirar']]] as $invalido) {
            $r = peticion('estudiantes/cambiar_estado.php', 'POST', ['rol' => 2, 'negocio' => true],
                array_replace(['csrf' => $token, 'id_estudiante' => 1, 'accion' => 'retirar'], $invalido));
            comprobar($r['codigo'] === 302 && $estadoEstudiante($r) === $baseEstudiante, 'Una entrada inválida modificó el estado.');
        }
        $r = peticion('estudiantes/cambiar_estado.php', 'POST', ['rol' => 2, 'negocio' => true, 'fallo_inscripcion' => true],
            ['csrf' => $token, 'id_estudiante' => 1, 'accion' => 'retirar']);
        comprobar($r['codigo'] === 302 && $estadoEstudiante($r) === $baseEstudiante, 'El fallo de inscripción dejó al estudiante retirado parcialmente.');
        $r = peticion('estudiantes/cambiar_estado.php', 'POST', ['rol' => 2, 'negocio' => true],
            ['csrf' => $token, 'id_estudiante' => 1, 'accion' => 'reactivar']);
        comprobar($estadoEstudiante($r) === $baseEstudiante, 'Repetir un estado cambió registros.');
        $r = peticion('estudiantes/listar.php', 'POST', ['rol' => 2, 'negocio' => true],
            ['csrf' => $token, 'id_estudiante' => 1, 'accion' => 'retirar']);
        comprobar($r['codigo'] === 405 && $estadoEstudiante($r) === $baseEstudiante, 'El listado todavía acepta escrituras.');
        echo "Retiro/reactivación de estudiantes, inscripción, permisos y rollback: correctos.\n";

        foreach ([
            ['derivaciones/registrar.php', 3], ['derivaciones/editar.php?id=1', 3],
            ['derivaciones/mis_derivaciones.php', 3], ['derivaciones/listar.php', 3],
            ['estudiantes/listar.php', 2], ['citas/listar.php', 2],
        ] as [$ruta, $rol]) {
            $r = peticion($ruta, 'GET', ['rol' => $rol, 'negocio' => true]);
            comprobar($r['codigo'] === 200, "No renderiza el formulario protegido: $ruta");
            $html = explode("\n__ESTADO_PRUEBA__", $r['cuerpo'])[0];
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//form') as $formulario) {
                if (strtoupper($formulario->getAttribute('method')) !== 'POST') continue;
                $campos = $xpath->query('.//input[@name="csrf"]', $formulario);
                comprobar($campos->length === 1 && $campos->item(0)->getAttribute('value') === $token, "Token ausente o duplicado en formulario de $ruta");
            }
            if (str_starts_with($ruta, 'derivaciones/')) {
                $menu = $xpath->query('//aside//a');
                foreach ($menu as $enlace) {
                    comprobar(!preg_match('~/(usuarios|docentes|estudiantes|historias_clinicas|citas|informes)/~', $enlace->getAttribute('href')), 'El menú de docente expone módulos restringidos.');
                }
            }
            if ($ruta === 'derivaciones/registrar.php') {
                comprobar($xpath->query('//form[@id="formBuscarEstudiante"]//input[@name="csrf"]')->length === 0, 'El buscador GET incluye el token CSRF.');
                comprobar($xpath->query('//select[@name="curso" and @form="formBuscarEstudiante"]')->length === 1, 'El filtro no pertenece al formulario de búsqueda separado.');
            }
            if (in_array($ruta, ['derivaciones/listar.php', 'derivaciones/mis_derivaciones.php'], true)) {
                comprobar(!str_contains($html, 'OTRO_ESTUDIANTE'), 'El listado docente expone derivaciones ajenas.');
                comprobar(str_contains($html, 'Lenguaje y Comunicación') && str_contains($html, 'Prioridad: Media'), 'El listado unificado perdió materia o prioridad.');
                comprobar($xpath->query('//form[@action="eliminar.php"]//input[@name="id_derivacion" and @value="1"]')->length === 1, 'Falta la eliminación de la derivación propia.');
                comprobar($xpath->query('//form[@action="eliminar.php"]//input[@name="id_derivacion" and @value="2"]')->length === 0, 'Se ofrece eliminar una derivación ajena.');
            }
            if ($ruta === 'estudiantes/listar.php') {
                comprobar($xpath->query('//form[contains(@class,"formulario-retirar") and @action="cambiar_estado.php"]')->length === 2, 'El retiro no apunta al receptor centralizado.');
            }
        }
        $r = peticion('cerrar_sesion.php', 'POST', ['rol' => 3, 'negocio' => true], ['csrf' => $token]);
        comprobar($r['codigo'] === 303 && (estadoNegocio($r)['autenticado'] ?? true) === false, 'Cerrar sesión no destruyó la autenticación.');
        echo "Formularios renderizados, menú por rol y cierre de sesión: correctos.\n";
    }

    // Cada PHP de módulo debe tener política o figurar entre los internos probados por HTTP.
    foreach (['usuarios', 'docentes', 'estudiantes', 'derivaciones', 'citas', 'historias_clinicas', 'seguimientos', 'informes', 'estadisticas'] as $modulo) {
        foreach (glob($raiz . '/' . $modulo . '/*.php') as $archivo) {
            $ruta = $modulo . '/' . basename($archivo);
            if (in_array($ruta, $internosModulo, true)) continue;
            comprobar(isset($permisos[$ruta]), "Ruta sin política: $ruta");
            $fuente = file_get_contents($archivo);
            comprobar(str_starts_with($fuente, '<?php'), "Hay salida o BOM antes de la protección: $ruta");
        }
    }
    echo "OK: $total comprobaciones" . ($soloAcceso ? ' de acceso (sin pruebas de negocio)' : '') . ". Solo se usaron tablas TEMPORARY de conexión.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    if (is_file($temporal . '/servidor.log')) {
        $log = file($temporal . '/servidor.log');
        fwrite(STDERR, implode('', array_slice($log, -14)));
    }
    $fallo = true;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    // Borrar solo el directorio temporal aleatorio creado arriba, tras verificarlo.
    $baseTemporal = realpath(sys_get_temp_dir());
    $destinoTemporal = realpath($temporal);
    if ($baseTemporal && $destinoTemporal && dirname($destinoTemporal) === $baseTemporal
        && str_starts_with(basename($destinoTemporal), 'psicologia-seguridad-')) {
        $restos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destinoTemporal, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($restos as $resto) {
            if ($resto->isLink()) unlink($resto->getPathname());
            elseif ($resto->isDir()) rmdir($resto->getPathname());
            else unlink($resto->getPathname());
        }
        rmdir($destinoTemporal);
    }
}
exit(isset($fallo) ? 1 : 0);
