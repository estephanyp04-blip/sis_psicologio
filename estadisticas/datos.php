<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

function estadisticas_filtros(array $entrada, ?string $hoy = null): array
{
    $hoy ??= date('Y-m-d');
    $datos = [];
    foreach (['desde' => substr($hoy, 0, 4) . '-01-01', 'hasta' => $hoy] as $campo => $defecto) {
        $valor = $entrada[$campo] ?? $defecto;
        $fecha = is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
        if (!$fecha || $fecha->format('Y-m-d') !== $valor || $valor > $hoy || $valor < '1900-01-01') {
            throw new InvalidArgumentException('Seleccione fechas válidas, sin superar el día de hoy.');
        }
        $datos[$campo] = $valor;
    }
    if ($datos['desde'] > $datos['hasta'] || (new DateTimeImmutable($datos['desde']))->diff(new DateTimeImmutable($datos['hasta']))->days > 365) {
        throw new InvalidArgumentException('El período debe estar ordenado y abarcar como máximo 366 días.');
    }
    $datos['curso'] = filter_var($entrada['curso'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4294967295]]);
    $datos['estado'] = $entrada['estado'] ?? '';
    if ($datos['curso'] === false || !in_array($datos['estado'], ['', 'Activa', 'En seguimiento', 'Cerrada'], true)) {
        throw new InvalidArgumentException('Revise el curso y el estado de la historia.');
    }
    return $datos;
}

function estadisticas_consultar(mysqli $bd, string $sql, array $valores): array
{
    $stmt = $bd->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function estadisticas_datos(mysqli $bd, array $filtros): array
{
    $f = estadisticas_filtros($filtros);
    if ($f['curso'] && !estadisticas_consultar($bd, 'SELECT cod_curso FROM cursos WHERE cod_curso=?', [$f['curso']])) {
        throw new InvalidArgumentException('El curso seleccionado no existe.');
    }
    // Una inscripción por estudiante: evita multiplicar sesiones al cambiar de curso.
    // El curso y el estado son los actuales; las fechas delimitan las sesiones.
    $uniones = "FROM historias_clinicas h
        INNER JOIN estudiantes e ON e.cod_estudiante=h.cod_estudiante
        LEFT JOIN historial_academico i ON i.cod_historial_academico=(
            SELECT i2.cod_historial_academico FROM historial_academico i2
            INNER JOIN secciones se2 ON se2.cod_seccion=i2.cod_seccion
            WHERE i2.cod_estudiante=e.cod_estudiante
            ORDER BY se2.gestion DESC,i2.fecha_inicio DESC,i2.cod_historial_academico DESC LIMIT 1)
        LEFT JOIN secciones se ON se.cod_seccion=i.cod_seccion
        LEFT JOIN cursos c ON c.cod_curso=se.cod_curso
        LEFT JOIN paralelos p ON p.cod_paralelo=se.cod_paralelo";
    $condicion = "h.fecha_apertura<=? AND (?=0 OR c.cod_curso=?) AND (?='' OR h.estado=?)";
    $valores = [$f['hasta'], $f['curso'], $f['curso'], $f['estado'], $f['estado']];

    $bd->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $historias = estadisticas_consultar($bd, "SELECT h.cod_historia,h.cod_estudiante,h.fecha_apertura,h.estado,
            h.situacion_escolar,h.valoracion_familiar,
            e.nombres,e.apellidos,e.estado AS estado_estudiante,c.nombre AS curso,p.nombre AS paralelo,
            COALESCE(s.total,0) AS total_sesiones,COALESCE(s.periodo,0) AS sesiones_periodo,
            ultimo.cod_seguimiento,ultimo.fecha AS ultima_sesion,ultimo.proxima_sesion
            $uniones
            LEFT JOIN (SELECT cod_historia,COUNT(*) AS total,SUM(fecha BETWEEN ? AND ?) AS periodo
                FROM seguimientos WHERE fecha<=? GROUP BY cod_historia) s ON s.cod_historia=h.cod_historia
            LEFT JOIN seguimientos ultimo ON ultimo.cod_seguimiento=(
                SELECT s2.cod_seguimiento FROM seguimientos s2 WHERE s2.cod_historia=h.cod_historia AND s2.fecha<=?
                ORDER BY s2.fecha DESC,s2.cod_seguimiento DESC LIMIT 1)
            WHERE $condicion ORDER BY e.apellidos,e.nombres,h.cod_historia",
            array_merge([$f['desde'], $f['hasta'], $f['hasta'], $f['hasta']], $valores));
        $mensual = estadisticas_consultar($bd, "SELECT DATE_FORMAT(s.fecha,'%Y-%m') AS mes,COUNT(*) AS total
            $uniones INNER JOIN seguimientos s ON s.cod_historia=h.cod_historia
            WHERE $condicion AND s.fecha BETWEEN ? AND ? GROUP BY mes ORDER BY mes",
            array_merge($valores, [$f['desde'], $f['hasta']]));
        $relacionAcademicaActual = "INNER JOIN estudiantes e ON e.cod_estudiante=x.cod_estudiante
            LEFT JOIN historial_academico ia ON ia.cod_historial_academico=(
                SELECT ia2.cod_historial_academico FROM historial_academico ia2
                INNER JOIN secciones s2 ON s2.cod_seccion=ia2.cod_seccion
                WHERE ia2.cod_estudiante=e.cod_estudiante
                ORDER BY s2.gestion DESC,ia2.fecha_inicio DESC,ia2.cod_historial_academico DESC LIMIT 1)
            LEFT JOIN secciones sec ON sec.cod_seccion=ia.cod_seccion
            LEFT JOIN cursos curso_actual ON curso_actual.cod_curso=sec.cod_curso";
        $valoresAtencion = [$f['desde'], $f['hasta'], $f['curso'], $f['curso']];
        $derivacionesPorEstado = estadisticas_consultar($bd, "SELECT x.estado,COUNT(*) AS total
            FROM derivaciones x $relacionAcademicaActual
            WHERE x.fecha BETWEEN ? AND ? AND (?=0 OR curso_actual.cod_curso=?)
            GROUP BY x.estado", $valoresAtencion);
        $citasPorEstado = estadisticas_consultar($bd, "SELECT x.estado,COUNT(*) AS total
            FROM citas x $relacionAcademicaActual
            WHERE x.fecha BETWEEN ? AND ? AND (?=0 OR curso_actual.cod_curso=?)
            GROUP BY x.estado", $valoresAtencion);
        $catalogoOpciones = $bd->query("SELECT g.codigo AS grupo,g.nombre AS nombre_grupo,
                o.cod_opcion,o.descripcion
            FROM grupos_opciones_historia g
            INNER JOIN opciones_historia o ON o.cod_grupo=g.cod_grupo
            WHERE o.estado='Activo' ORDER BY g.cod_grupo,o.cod_opcion")->fetch_all(MYSQLI_ASSOC);
        $selecciones = estadisticas_consultar($bd, "SELECT h.cod_historia,h.cod_estudiante,
                g.codigo AS grupo,g.nombre AS nombre_grupo,o.cod_opcion,o.descripcion
            $uniones
            INNER JOIN historia_opciones ho ON ho.cod_historia=h.cod_historia
            INNER JOIN opciones_historia o ON o.cod_opcion=ho.cod_opcion
            INNER JOIN grupos_opciones_historia g ON g.cod_grupo=o.cod_grupo
            WHERE $condicion ORDER BY g.cod_grupo,o.cod_opcion,h.cod_historia",
            $valores);
        $motivos = estadisticas_consultar($bd, "SELECT cd.nombre AS motivo,
                COUNT(DISTINCT h.cod_historia) AS historias,
                COUNT(DISTINCT h.cod_estudiante) AS estudiantes
            $uniones
            INNER JOIN derivaciones d ON d.cod_derivacion=h.cod_derivacion_origen
            INNER JOIN derivacion_categorias dc ON dc.cod_derivacion=d.cod_derivacion
            INNER JOIN categorias_derivacion cd ON cd.cod_categoria=dc.cod_categoria
            WHERE $condicion
            GROUP BY cd.cod_categoria,cd.nombre
            ORDER BY historias DESC,cd.nombre LIMIT 8", $valores);
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }

    $resumen = ['historias' => count($historias), 'sesiones' => 0, 'acumuladas' => 0, 'con_sesiones' => 0, 'sin_sesiones' => 0];
    $estados = ['Activa' => 0, 'En seguimiento' => 0, 'Cerrada' => 0];
    foreach ($historias as &$historia) {
        $historia['sesiones_periodo'] = (int)$historia['sesiones_periodo'];
        $historia['total_sesiones'] = (int)$historia['total_sesiones'];
        $resumen['sesiones'] += $historia['sesiones_periodo'];
        $resumen['acumuladas'] += $historia['total_sesiones'];
        $resumen[$historia['sesiones_periodo'] > 0 ? 'con_sesiones' : 'sin_sesiones']++;
        $estados[$historia['estado']]++;
    }
    unset($historia);
    $porMes = array_column($mensual, 'total', 'mes');
    $meses = [];
    $mes = new DateTimeImmutable(substr($f['desde'], 0, 7) . '-01');
    while ($mes->format('Y-m') <= substr($f['hasta'], 0, 7)) {
        $clave = $mes->format('Y-m');
        $meses[$clave] = (int)($porMes[$clave] ?? 0);
        $mes = $mes->modify('+1 month');
    }

    $estadosDerivaciones = ['Pendiente' => 0, 'En seguimiento' => 0, 'Atendido' => 0];
    foreach ($derivacionesPorEstado as $fila) {
        if (array_key_exists($fila['estado'], $estadosDerivaciones)) $estadosDerivaciones[$fila['estado']] = (int)$fila['total'];
    }
    $estadosCitas = ['Pendiente' => 0, 'Reprogramada' => 0, 'Atendida' => 0, 'Cancelada' => 0];
    foreach ($citasPorEstado as $fila) {
        if (array_key_exists($fila['estado'], $estadosCitas)) $estadosCitas[$fila['estado']] = (int)$fila['total'];
    }

    $distribuciones = [];
    foreach ($catalogoOpciones as $opcion) {
        $grupo = $opcion['grupo'];
        $idOpcion = (int)$opcion['cod_opcion'];
        if (!isset($distribuciones[$grupo])) {
            $distribuciones[$grupo] = [
                'nombre' => $opcion['nombre_grupo'], 'historias' => 0, 'estudiantes' => 0,
                'opciones' => [], '_historias' => [], '_estudiantes' => [],
            ];
        }
        $distribuciones[$grupo]['opciones'][$idOpcion] = [
            'nombre' => $opcion['descripcion'], 'historias' => 0, 'estudiantes' => 0, '_estudiantes' => [],
        ];
    }

    $estudiantes = [];
    foreach ($historias as $historia) {
        $idEstudiante = (int)$historia['cod_estudiante'];
        if (!isset($estudiantes[$idEstudiante])) {
            $estudiantes[$idEstudiante] = [
                'cod_estudiante' => $idEstudiante,
                'nombres' => $historia['nombres'], 'apellidos' => $historia['apellidos'],
                'curso' => $historia['curso'], 'paralelo' => $historia['paralelo'],
                'historias' => 0, 'cod_historia' => (int)$historia['cod_historia'],
                'fecha_apertura' => $historia['fecha_apertura'],
                'situacion_escolar' => $historia['situacion_escolar'] ?? null,
                'valoracion_familiar' => $historia['valoracion_familiar'] ?? null,
                'opciones' => [],
            ];
        }
        ++$estudiantes[$idEstudiante]['historias'];
        if ($historia['fecha_apertura'] > $estudiantes[$idEstudiante]['fecha_apertura']
            || ($historia['fecha_apertura'] === $estudiantes[$idEstudiante]['fecha_apertura']
                && (int)$historia['cod_historia'] > $estudiantes[$idEstudiante]['cod_historia'])) {
            $estudiantes[$idEstudiante]['curso'] = $historia['curso'];
            $estudiantes[$idEstudiante]['paralelo'] = $historia['paralelo'];
            $estudiantes[$idEstudiante]['cod_historia'] = (int)$historia['cod_historia'];
            $estudiantes[$idEstudiante]['fecha_apertura'] = $historia['fecha_apertura'];
            $estudiantes[$idEstudiante]['situacion_escolar'] = $historia['situacion_escolar'] ?? null;
            $estudiantes[$idEstudiante]['valoracion_familiar'] = $historia['valoracion_familiar'] ?? null;
        }
    }

    $porGrupoHistoria = [];
    $porGrupoEstudiante = [];
    foreach ($selecciones as $seleccion) {
        $grupo = $seleccion['grupo'];
        $idOpcion = (int)$seleccion['cod_opcion'];
        $idHistoria = (int)$seleccion['cod_historia'];
        $idEstudiante = (int)$seleccion['cod_estudiante'];
        if (!isset($distribuciones[$grupo])) {
            $distribuciones[$grupo] = [
                'nombre' => $seleccion['nombre_grupo'], 'historias' => 0, 'estudiantes' => 0,
                'opciones' => [], '_historias' => [], '_estudiantes' => [],
            ];
        }
        if (!isset($distribuciones[$grupo]['opciones'][$idOpcion])) {
            $distribuciones[$grupo]['opciones'][$idOpcion] = [
                'nombre' => $seleccion['descripcion'], 'historias' => 0, 'estudiantes' => 0, '_estudiantes' => [],
            ];
        }
        ++$distribuciones[$grupo]['opciones'][$idOpcion]['historias'];
        $distribuciones[$grupo]['opciones'][$idOpcion]['_estudiantes'][$idEstudiante] = true;
        $distribuciones[$grupo]['_historias'][$idHistoria] = true;
        $distribuciones[$grupo]['_estudiantes'][$idEstudiante] = true;
        $porGrupoHistoria[$grupo][$idHistoria] = true;
        $porGrupoEstudiante[$grupo][$idEstudiante] = true;
        $estudiantes[$idEstudiante]['opciones'][$grupo][$seleccion['descripcion']] = true;
    }

    foreach ($distribuciones as &$grupo) {
        $grupo['historias'] = count($grupo['_historias']);
        $grupo['estudiantes'] = count($grupo['_estudiantes']);
        unset($grupo['_historias'], $grupo['_estudiantes']);
        foreach ($grupo['opciones'] as &$opcion) {
            $opcion['estudiantes'] = count($opcion['_estudiantes']);
            unset($opcion['_estudiantes']);
        }
        unset($opcion);
    }
    unset($grupo);

    foreach ($estudiantes as &$estudiante) {
        foreach ($estudiante['opciones'] as &$opcionesEstudiante) {
            $opcionesEstudiante = array_keys($opcionesEstudiante);
            sort($opcionesEstudiante, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($opcionesEstudiante);
        foreach (['conductas_riesgo', 'dificultades_socioemocionales'] as $grupo) {
            $estudiante[$grupo] = $estudiante['opciones'][$grupo] ?? [];
        }
        unset($estudiante['opciones']);
    }
    unset($estudiante);
    uasort($estudiantes, static fn(array $a, array $b): int =>
        strcasecmp(trim($a['apellidos'] . ' ' . $a['nombres']), trim($b['apellidos'] . ' ' . $b['nombres'])));

    $valoraciones = [
        'situacion_escolar' => ['Muy buena' => 0, 'Buena' => 0, 'Regular' => 0, 'Deficiente' => 0],
        'valoracion_familiar' => ['Muy buena' => 0, 'Buena' => 0, 'Regular' => 0, 'Conflictiva' => 0],
    ];
    foreach ($historias as $historia) {
        foreach (array_keys($valoraciones) as $campo) {
            $valor = $historia[$campo] ?? '';
            if ($valor !== '' && isset($valoraciones[$campo][$valor])) ++$valoraciones[$campo][$valor];
        }
    }

    return [
        'resumen' => $resumen, 'estados' => $estados, 'meses' => $meses, 'historias' => $historias,
        'atencion' => ['derivaciones' => $estadosDerivaciones, 'citas' => $estadosCitas],
        'distribuciones' => $distribuciones, 'motivos_derivacion' => $motivos,
        'estudiantes' => array_values($estudiantes), 'valoraciones' => $valoraciones,
        'resumen_clinico' => [
            'estudiantes' => count($estudiantes),
            'estudiantes_riesgo' => count($porGrupoEstudiante['conductas_riesgo'] ?? []),
            'estudiantes_socioemocionales' => count($porGrupoEstudiante['dificultades_socioemocionales'] ?? []),
            'historias_riesgo' => count($porGrupoHistoria['conductas_riesgo'] ?? []),
            'historias_socioemocionales' => count($porGrupoHistoria['dificultades_socioemocionales'] ?? []),
        ],
    ];
}

function estadisticas_panel(mysqli $bd, ?string $hoy = null): array
{
    $fecha = new DateTimeImmutable($hoy ?? date('Y-m-d'));
    $hoy = $fecha->format('Y-m-d');
    $finAgenda = $fecha->modify('+6 days')->format('Y-m-d');
    $inicioSesiones = $fecha->modify('-29 days')->format('Y-m-d');
    $bd->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $resumen = estadisticas_consultar($bd, "SELECT
            (SELECT COUNT(*) FROM historias_clinicas h JOIN estudiantes e ON e.cod_estudiante=h.cod_estudiante
                WHERE e.estado='Activo' AND h.estado='En seguimiento' AND h.fecha_apertura<=?) AS en_seguimiento,
            (SELECT COUNT(*) FROM estudiantes e WHERE e.estado='Activo' AND EXISTS (
                SELECT 1 FROM derivaciones d WHERE d.cod_estudiante=e.cod_estudiante AND d.prioridad='Alta'
                AND d.estado IN ('Pendiente','En seguimiento') AND d.fecha<=?)) AS prioridad_alta,
            (SELECT COUNT(*) FROM citas WHERE estado IN ('Pendiente','Reprogramada') AND fecha BETWEEN ? AND ?) AS citas_pendientes,
            (SELECT COUNT(*) FROM seguimientos WHERE fecha<=?) AS sesiones,
            (SELECT COUNT(*) FROM seguimientos WHERE fecha BETWEEN ? AND ?) AS sesiones_recientes,
            (SELECT COUNT(*) FROM estudiantes e WHERE e.estado='Activo' AND EXISTS (
                SELECT 1 FROM derivaciones d WHERE d.cod_estudiante=e.cod_estudiante
                AND d.estado IN ('Pendiente','En seguimiento') AND d.fecha<=?)) AS por_atender",
            [$hoy,$hoy,$hoy,$finAgenda,$hoy,$inicioSesiones,$hoy,$hoy])[0];
        // Una fila por estudiante, con su derivación abierta de mayor prioridad.
        $prioritarios = estadisticas_consultar($bd, "SELECT e.cod_estudiante,e.nombres,e.apellidos,e.codigo,
            c.nombre AS curso,p.nombre AS paralelo,d.cod_derivacion,d.prioridad,h.cod_historia,
            (SELECT MAX(s.fecha) FROM seguimientos s WHERE s.cod_historia=h.cod_historia AND s.fecha<=?) AS ultima_sesion
            FROM estudiantes e
            INNER JOIN derivaciones d ON d.cod_derivacion=(
                SELECT d2.cod_derivacion FROM derivaciones d2 WHERE d2.cod_estudiante=e.cod_estudiante
                AND d2.estado IN ('Pendiente','En seguimiento') AND d2.fecha<=?
                ORDER BY FIELD(d2.prioridad,'Alta','Media','Baja'),d2.fecha,d2.cod_derivacion LIMIT 1)
            LEFT JOIN historias_clinicas h ON h.cod_estudiante=e.cod_estudiante AND h.fecha_apertura<=?
            LEFT JOIN historial_academico i ON i.cod_historial_academico=(
                SELECT i2.cod_historial_academico FROM historial_academico i2 JOIN secciones se2 ON se2.cod_seccion=i2.cod_seccion
                WHERE i2.cod_estudiante=e.cod_estudiante
                ORDER BY se2.gestion DESC,i2.fecha_inicio DESC,i2.cod_historial_academico DESC LIMIT 1)
            LEFT JOIN secciones se ON se.cod_seccion=i.cod_seccion
            LEFT JOIN cursos c ON c.cod_curso=se.cod_curso
            LEFT JOIN paralelos p ON p.cod_paralelo=se.cod_paralelo
            WHERE e.estado='Activo'
            ORDER BY FIELD(d.prioridad,'Alta','Media','Baja'),ultima_sesion,d.fecha,e.cod_estudiante LIMIT 5",
            [$hoy,$hoy,$hoy]);
        $agenda = estadisticas_consultar($bd, "SELECT c.cod_cita,c.hora,c.estado,e.nombres,e.apellidos,
            CONCAT_WS(' ',u.nombres,u.apellidos) AS profesional
            FROM citas c JOIN estudiantes e ON e.cod_estudiante=c.cod_estudiante
            JOIN usuarios u ON u.cod_usuario=c.cod_psicologa
            WHERE c.fecha=? AND c.estado<>'Cancelada' ORDER BY c.hora,c.cod_cita", [$hoy]);
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }
    return ['resumen'=>array_map('intval',$resumen),'prioritarios'=>$prioritarios,'agenda'=>$agenda,
        'hoy'=>$hoy,'fin_agenda'=>$finAgenda,'inicio_sesiones'=>$inicioSesiones];
}
