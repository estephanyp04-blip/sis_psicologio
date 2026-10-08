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
    if ($f['curso'] && !estadisticas_consultar($bd, 'SELECT id_curso FROM cursos WHERE id_curso=?', [$f['curso']])) {
        throw new InvalidArgumentException('El curso seleccionado no existe.');
    }
    // Una inscripción por estudiante: evita multiplicar sesiones al cambiar de curso.
    // El curso y el estado son los actuales; las fechas delimitan las sesiones.
    $uniones = "FROM historias_clinicas h
        INNER JOIN estudiantes e ON e.id_estudiante=h.id_estudiante
        LEFT JOIN inscripciones i ON i.id_inscripcion=(
            SELECT i2.id_inscripcion FROM inscripciones i2
            INNER JOIN secciones se2 ON se2.id_seccion=i2.id_seccion
            WHERE i2.id_estudiante=e.id_estudiante
            ORDER BY se2.gestion DESC,i2.fecha_inscripcion DESC,i2.id_inscripcion DESC LIMIT 1)
        LEFT JOIN secciones se ON se.id_seccion=i.id_seccion
        LEFT JOIN cursos c ON c.id_curso=se.id_curso
        LEFT JOIN paralelos p ON p.id_paralelo=se.id_paralelo";
    $condicion = "h.fecha_apertura<=? AND (?=0 OR c.id_curso=?) AND (?='' OR h.estado=?)";
    $valores = [$f['hasta'], $f['curso'], $f['curso'], $f['estado'], $f['estado']];

    $bd->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $historias = estadisticas_consultar($bd, "SELECT h.id_historia,h.id_estudiante,h.fecha_apertura,h.estado,
            e.nombres,e.apellidos,e.estado AS estado_estudiante,c.nombre AS curso,p.nombre AS paralelo,
            COALESCE(s.total,0) AS total_sesiones,COALESCE(s.periodo,0) AS sesiones_periodo,
            ultimo.id_seguimiento,ultimo.fecha AS ultima_sesion,ultimo.proxima_sesion
            $uniones
            LEFT JOIN (SELECT id_historia,COUNT(*) AS total,SUM(fecha BETWEEN ? AND ?) AS periodo
                FROM seguimientos WHERE fecha<=? GROUP BY id_historia) s ON s.id_historia=h.id_historia
            LEFT JOIN seguimientos ultimo ON ultimo.id_seguimiento=(
                SELECT s2.id_seguimiento FROM seguimientos s2 WHERE s2.id_historia=h.id_historia AND s2.fecha<=?
                ORDER BY s2.fecha DESC,s2.id_seguimiento DESC LIMIT 1)
            WHERE $condicion ORDER BY e.apellidos,e.nombres,h.id_historia",
            array_merge([$f['desde'], $f['hasta'], $f['hasta'], $f['hasta']], $valores));
        $mensual = estadisticas_consultar($bd, "SELECT DATE_FORMAT(s.fecha,'%Y-%m') AS mes,COUNT(*) AS total
            $uniones INNER JOIN seguimientos s ON s.id_historia=h.id_historia
            WHERE $condicion AND s.fecha BETWEEN ? AND ? GROUP BY mes ORDER BY mes",
            array_merge($valores, [$f['desde'], $f['hasta']]));
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }

    $resumen = ['historias' => count($historias), 'sesiones' => 0, 'con_sesiones' => 0, 'sin_sesiones' => 0];
    $estados = ['Activa' => 0, 'En seguimiento' => 0, 'Cerrada' => 0];
    foreach ($historias as &$historia) {
        $historia['sesiones_periodo'] = (int)$historia['sesiones_periodo'];
        $historia['total_sesiones'] = (int)$historia['total_sesiones'];
        $resumen['sesiones'] += $historia['sesiones_periodo'];
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
    return ['resumen' => $resumen, 'estados' => $estados, 'meses' => $meses, 'historias' => $historias];
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
            (SELECT COUNT(*) FROM historias_clinicas h JOIN estudiantes e ON e.id_estudiante=h.id_estudiante
                WHERE e.estado='Activo' AND h.estado='En seguimiento' AND h.fecha_apertura<=?) AS en_seguimiento,
            (SELECT COUNT(*) FROM estudiantes e WHERE e.estado='Activo' AND EXISTS (
                SELECT 1 FROM derivaciones d WHERE d.id_estudiante=e.id_estudiante AND d.prioridad='Alta'
                AND d.estado IN ('Pendiente','En seguimiento') AND d.fecha<=?)) AS prioridad_alta,
            (SELECT COUNT(*) FROM citas WHERE estado IN ('Pendiente','Reprogramada') AND fecha BETWEEN ? AND ?) AS citas_pendientes,
            (SELECT COUNT(*) FROM seguimientos WHERE fecha<=?) AS sesiones,
            (SELECT COUNT(*) FROM seguimientos WHERE fecha BETWEEN ? AND ?) AS sesiones_recientes,
            (SELECT COUNT(*) FROM estudiantes e WHERE e.estado='Activo' AND EXISTS (
                SELECT 1 FROM derivaciones d WHERE d.id_estudiante=e.id_estudiante
                AND d.estado IN ('Pendiente','En seguimiento') AND d.fecha<=?)) AS por_atender",
            [$hoy,$hoy,$hoy,$finAgenda,$hoy,$inicioSesiones,$hoy,$hoy])[0];
        // Una fila por estudiante, con su derivación abierta de mayor prioridad.
        $prioritarios = estadisticas_consultar($bd, "SELECT e.id_estudiante,e.nombres,e.apellidos,e.codigo,
            c.nombre AS curso,p.nombre AS paralelo,d.id_derivacion,d.prioridad,h.id_historia,
            (SELECT MAX(s.fecha) FROM seguimientos s WHERE s.id_historia=h.id_historia AND s.fecha<=?) AS ultima_sesion
            FROM estudiantes e
            INNER JOIN derivaciones d ON d.id_derivacion=(
                SELECT d2.id_derivacion FROM derivaciones d2 WHERE d2.id_estudiante=e.id_estudiante
                AND d2.estado IN ('Pendiente','En seguimiento') AND d2.fecha<=?
                ORDER BY FIELD(d2.prioridad,'Alta','Media','Baja'),d2.fecha,d2.id_derivacion LIMIT 1)
            LEFT JOIN historias_clinicas h ON h.id_estudiante=e.id_estudiante AND h.fecha_apertura<=?
            LEFT JOIN inscripciones i ON i.id_inscripcion=(
                SELECT i2.id_inscripcion FROM inscripciones i2 JOIN secciones se2 ON se2.id_seccion=i2.id_seccion
                WHERE i2.id_estudiante=e.id_estudiante
                ORDER BY se2.gestion DESC,i2.fecha_inscripcion DESC,i2.id_inscripcion DESC LIMIT 1)
            LEFT JOIN secciones se ON se.id_seccion=i.id_seccion
            LEFT JOIN cursos c ON c.id_curso=se.id_curso
            LEFT JOIN paralelos p ON p.id_paralelo=se.id_paralelo
            WHERE e.estado='Activo'
            ORDER BY FIELD(d.prioridad,'Alta','Media','Baja'),ultima_sesion,d.fecha,e.id_estudiante LIMIT 5",
            [$hoy,$hoy,$hoy]);
        $agenda = estadisticas_consultar($bd, "SELECT c.id_cita,c.hora,c.estado,e.nombres,e.apellidos,
            CONCAT_WS(' ',p.nombres,p.apellidos) AS profesional
            FROM citas c JOIN estudiantes e ON e.id_estudiante=c.id_estudiante
            JOIN usuarios u ON u.id_usuario=c.id_psicologa JOIN personas p ON p.id_persona=u.id_persona
            WHERE c.fecha=? AND c.estado<>'Cancelada' ORDER BY c.hora,c.id_cita", [$hoy]);
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }
    return ['resumen'=>array_map('intval',$resumen),'prioritarios'=>$prioritarios,'agenda'=>$agenda,
        'hoy'=>$hoy,'fin_agenda'=>$finAgenda,'inicio_sesiones'=>$inicioSesiones];
}
