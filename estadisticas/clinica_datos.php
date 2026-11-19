<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../historias_clinicas/datos.php';

/** Fechas de atención inclusivas. Una edición documental no es otra atención. */
function clinica_filtros(array $entrada): array
{
    [$desde, $hasta] = flujo_filtro_fechas($entrada);
    $hasta = $hasta ?: date('Y-m-d');
    foreach ([$desde, $hasta] as $fecha) {
        if ($fecha !== '' && ($fecha < '1900-01-01' || $fecha > date('Y-m-d'))) {
            throw new InvalidArgumentException('Use fechas válidas hasta el día de hoy.');
        }
    }
    $f = ['desde' => $desde, 'hasta' => $hasta];
    foreach (['curso', 'paralelo', 'estudiante'] as $campo) $f[$campo] = flujo_id($entrada[$campo] ?? 0, true);
    $f['estado'] = $entrada['estado'] ?? '';
    if (!in_array($f['estado'], ['', 'Activa', 'En seguimiento', 'Cerrada'], true)) throw new InvalidArgumentException('Estado de historia no válido.');
    return $f;
}

function clinica_consultar(mysqli $bd, string $sql, array $valores = []): array
{
    $s = $bd->prepare($sql);
    if ($valores) $s->bind_param(str_repeat('s', count($valores)), ...$valores);
    $s->execute(); $filas = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    return $filas;
}

/** Una sola inscripción actual por estudiante; no duplica historias al cambiar de curso. */
function clinica_relacion_academica(): string
{
    return "LEFT JOIN historial_academico ia ON ia.cod_historial_academico=(
        SELECT i.cod_historial_academico FROM historial_academico i JOIN secciones s ON s.cod_seccion=i.cod_seccion
        WHERE i.cod_estudiante=e.cod_estudiante ORDER BY s.gestion DESC,i.fecha_inicio DESC,i.cod_historial_academico DESC LIMIT 1)
        LEFT JOIN secciones se ON se.cod_seccion=ia.cod_seccion
        LEFT JOIN cursos c ON c.cod_curso=se.cod_curso LEFT JOIN paralelos p ON p.cod_paralelo=se.cod_paralelo";
}

function clinica_catalogos(mysqli $bd): array
{
    return [
        'cursos' => clinica_consultar($bd, 'SELECT cod_curso,nombre FROM cursos ORDER BY orden,nombre'),
        'paralelos' => clinica_consultar($bd, 'SELECT cod_paralelo,nombre FROM paralelos ORDER BY nombre'),
        'grupos' => clinica_consultar($bd, 'SELECT codigo,nombre FROM grupos_opciones_historia ORDER BY cod_grupo'),
    ];
}

function clinica_estudiantes(mysqli $bd, array $f): array
{
    return clinica_consultar($bd, 'SELECT e.cod_estudiante,e.nombres,e.apellidos,c.nombre curso,p.nombre paralelo
        FROM estudiantes e ' . clinica_relacion_academica() . '
        WHERE EXISTS (SELECT 1 FROM historias_clinicas h WHERE h.cod_estudiante=e.cod_estudiante)
        AND (?=0 OR se.cod_curso=?) AND (?=0 OR se.cod_paralelo=?) ORDER BY e.apellidos,e.nombres,e.cod_estudiante',
        [$f['curso'], $f['curso'], $f['paralelo'], $f['paralelo']]);
}

/** Solo estados realmente conservados; nunca se reconstruye un pasado inexistente. */
function clinica_historias(mysqli $bd, array $f, bool $versiones = false): array
{
    if ($versiones && !$f['estudiante']) throw new InvalidArgumentException('Seleccione un estudiante para consultar versiones.');
    foreach (['curso'=>['cursos','cod_curso'], 'paralelo'=>['paralelos','cod_paralelo']] as $campo=>[$tabla,$id]) {
        if ($f[$campo] && !clinica_consultar($bd,"SELECT `$id` FROM `$tabla` WHERE `$id`=?",[$f[$campo]])) {
            throw new InvalidArgumentException('El curso o paralelo seleccionado no existe.');
        }
    }
    $where = 'WHERE (?=0 OR h.cod_estudiante=?) AND (?=0 OR se.cod_curso=?) AND (?=0 OR se.cod_paralelo=?)';
    $valores = [$f['estudiante'], $f['estudiante'], $f['curso'], $f['curso'], $f['paralelo'], $f['paralelo']];
    // En consulta general aplicar el período en SQL; individual necesita las versiones de fechas corregidas.
    if (!$versiones) {
        $where .= " AND (?='' OR h.fecha_apertura>=?) AND h.fecha_apertura<=? AND (?='' OR h.estado=?)";
        array_push($valores, $f['desde'], $f['desde'], $f['hasta'], $f['estado'], $f['estado']);
    }
    $join = 'FROM historias_clinicas h JOIN estudiantes e ON e.cod_estudiante=h.cod_estudiante ' . clinica_relacion_academica();
    $bd->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $filas = clinica_consultar($bd, "SELECT h.*,c.nombre curso,p.nombre paralelo,
            CONCAT_WS(' ',u.nombres,u.apellidos) profesional $join LEFT JOIN usuarios u ON u.cod_usuario=h.cod_psicologa
            $where ORDER BY h.fecha_apertura,h.cod_historia", $valores);
        $historias = [];
        foreach ($filas as $h) {
            $h['opciones'] = []; $h['familiares'] = []; $h['versiones'] = []; $h['categorias_derivacion'] = [];
            $historias[(int)$h['cod_historia']] = $h;
        }
        if ($historias) {
            $ids = array_keys($historias);
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            foreach (clinica_consultar($bd, "SELECT h.cod_historia,cat.nombre FROM historias_clinicas h
                JOIN derivacion_categorias dc ON dc.cod_derivacion=h.cod_derivacion_origen
                JOIN categorias_derivacion cat ON cat.cod_categoria=dc.cod_categoria
                WHERE h.cod_historia IN ($marcas) ORDER BY cat.cod_categoria", $ids) as $cat) {
                $historias[$cat['cod_historia']]['categorias_derivacion'][] = $cat['nombre'];
            }
            foreach (clinica_consultar($bd, "SELECT ho.cod_historia,g.codigo,o.descripcion FROM historia_opciones ho
                JOIN opciones_historia o ON o.cod_opcion=ho.cod_opcion JOIN grupos_opciones_historia g ON g.cod_grupo=o.cod_grupo
                WHERE ho.cod_historia IN ($marcas) ORDER BY g.cod_grupo,o.cod_opcion", $ids) as $o) {
                $historias[$o['cod_historia']]['opciones'][$o['codigo']][] = $o['descripcion'];
            }
            foreach (clinica_consultar($bd, "SELECT cod_historia,nombre,edad,relacion,profesion,ocupacion,observaciones
                FROM historia_familiares WHERE cod_historia IN ($marcas) ORDER BY cod_familiar", $ids) as $familiar) {
                $id = $familiar['cod_historia']; unset($familiar['cod_historia']);
                $historias[$id]['familiares'][] = $familiar;
            }
            if ($versiones) {
                foreach (clinica_consultar($bd, "SELECT cod_version,cod_historia,fecha,contenido_anterior FROM historia_versiones
                    WHERE cod_historia IN ($marcas) ORDER BY cod_version", $ids) as $v) {
                    $contenido = json_decode($v['contenido_anterior'], true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($contenido) || (int)($contenido['cod_historia'] ?? 0) !== (int)$v['cod_historia']
                        || (int)($contenido['cod_estudiante'] ?? 0) !== (int)$f['estudiante']) {
                        throw new RuntimeException('Una versión no corresponde a su historia o estudiante.');
                    }
                    $contenido['opciones'] = [];
                    foreach ($contenido['_relaciones_originales']['opciones'] ?? [] as $o) {
                        $contenido['opciones'][$o['grupo_codigo']][] = $o['descripcion'];
                    }
                    foreach (array_keys(HISTORIA_OPCIONES) as $grupo) {
                        if (!isset($contenido['opciones'][$grupo]) && isset($contenido[$grupo])) $contenido['opciones'][$grupo] = $contenido[$grupo];
                    }
                    $historias[$v['cod_historia']]['versiones'][] = ['id' => (int)$v['cod_version'], 'conservada_el' => $v['fecha'], 'datos' => $contenido];
                }
            }
        }
        $bd->commit();
        return array_values($historias);
    } catch (Throwable $e) { $bd->rollback(); throw $e; }
}

function clinica_registros(array $historias, array $f): array
{
    $registros = [];
    foreach ($historias as $h) {
        $versiones = $h['versiones']; unset($h['versiones']);
        $candidatos = [];
        foreach ($versiones as $v) {
            $candidatos[] = ['clave' => 'h' . $h['cod_historia'] . 'v' . $v['id'], 'tipo' => 'Versión conservada',
                'conservada_el' => $v['conservada_el'], 'orden' => $v['id'], 'datos' => $v['datos']];
        }
        $candidatos[] = ['clave' => 'h' . $h['cod_historia'] . 'actual', 'tipo' => 'Historia actual',
            'conservada_el' => null, 'orden' => PHP_INT_MAX, 'datos' => $h];
        foreach ($candidatos as $r) {
            $fecha = $r['datos']['fecha_apertura'] ?? '';
            if ($fecha === '' || ($f['desde'] !== '' && $fecha < $f['desde']) || $fecha > $f['hasta']
                || ($f['estado'] !== '' && ($r['datos']['estado'] ?? '') !== $f['estado'])) continue;
            $registros[$r['clave']] = $r;
        }
    }
    uasort($registros, static fn($a, $b) => [$a['datos']['fecha_apertura'], (int)$a['datos']['cod_historia'], $a['orden']]
        <=> [$b['datos']['fecha_apertura'], (int)$b['datos']['cod_historia'], $b['orden']]);
    return $registros;
}

function clinica_campos(): array
{
    return [
        'fecha_apertura'=>'Fecha de atención', 'motivo_consulta'=>'Motivo de consulta / derivación', 'antecedentes'=>'Antecedentes',
        'situacion_escolar'=>'Situación escolar', 'dificultad_escolar'=>'Dificultades escolares', 'cursos_repetidos'=>'Cursos repetidos',
        'materia_agrada'=>'Materia que le agrada', 'materia_desagrada'=>'Materia que le desagrada', 'relacion_escolar'=>'Relaciones escolares',
        'tutor_curso'=>'Tutor del curso', 'valoracion_familiar'=>'Valoración familiar', 'contexto_familiar'=>'Contexto familiar',
        'familiares'=>'Familiares registrados', 'talla_cm'=>'Talla (cm)', 'peso_kg'=>'Peso (kg)', 'valoracion_fisica'=>'Valoración física',
        'enfermedades_actuales'=>'Enfermedades actuales', 'evaluacion_inicial'=>'Evaluación inicial',
        'impresion_diagnostica'=>'Impresión diagnóstica profesional', 'plan_intervencion'=>'Plan de intervención registrado',
        'observaciones'=>'Observaciones profesionales', 'estado'=>'Estado de la historia', 'motivo_cierre'=>'Motivo de cierre',
    ];
}

function clinica_valor(mixed $valor): string
{
    if (is_array($valor)) {
        $partes = [];
        foreach ($valor as $v) $partes[] = is_array($v) ? implode(' · ', array_filter(array_map('strval', $v), static fn($s) => $s !== '')) : (string)$v;
        return implode("\n", $partes);
    }
    return trim((string)$valor);
}

/** Se comparan registros, sin convertir ordinales o textos en puntuaciones clínicas. */
function clinica_comparar(array $a, array $b, array $grupos): array
{
    $filas = [];
    foreach (clinica_campos() as $campo => $nombre) {
        $antes = clinica_valor($a[$campo] ?? null); $despues = clinica_valor($b[$campo] ?? null);
        $estado = $antes === $despues ? ($antes === '' ? 'Sin datos registrados' : 'Permanece registrado')
            : ($antes === '' ? 'Nuevo registro' : ($despues === '' ? 'Sin dato en el segundo registro' : 'Registro modificado'));
        $filas[] = compact('campo', 'nombre', 'antes', 'despues', 'estado');
    }
    $nombres = array_column($grupos, 'nombre', 'codigo');
    foreach (array_unique(array_merge(array_keys($nombres), array_keys($a['opciones'] ?? []), array_keys($b['opciones'] ?? []))) as $grupo) {
        $antes = array_values(array_unique($a['opciones'][$grupo] ?? []));
        $despues = array_values(array_unique($b['opciones'][$grupo] ?? []));
        sort($antes); sort($despues);
        $nuevas = array_values(array_diff($despues, $antes)); $sinMarca = array_values(array_diff($antes, $despues));
        $delta = count($despues) - count($antes);
        $estado = (!$antes && !$despues) ? 'Sin selecciones registradas; ausencia no confirmada'
            : ($antes === $despues ? 'Permanecen las marcas registradas' : 'Cambian las marcas registradas');
        if ($delta) $estado .= ' · ' . ($delta > 0 ? 'Aumenta' : 'Disminuye') . ' la cantidad de marcas (' . ($delta > 0 ? '+' : '') . $delta . ')';
        $filas[] = ['campo'=>$grupo, 'nombre'=>$nombres[$grupo] ?? $grupo, 'antes'=>implode("\n", $antes),
            'despues'=>implode("\n", $despues), 'estado'=>$estado, 'nuevas'=>$nuevas, 'sin_marca'=>$sinMarca];
    }
    return $filas;
}

function clinica_generales(mysqli $bd, array $f): array
{
    $historias = clinica_historias($bd, $f);
    $personas = []; $riesgo = []; $distribuciones = []; $meses = []; $cursos = []; $motivos = []; $conSeleccion = [];
    $estados = ['Activa'=>0, 'En seguimiento'=>0, 'Cerrada'=>0];
    $valoraciones = ['situacion_escolar'=>[], 'valoracion_familiar'=>[]];
    foreach ($historias as $h) {
        $id = (int)$h['cod_estudiante']; $personas[$id] = true;
        ++$estados[$h['estado']];
        if ($h['opciones']['conductas_riesgo'] ?? []) $riesgo[$id] = true;
        $mes = substr($h['fecha_apertura'], 0, 7); $meses[$mes] = ($meses[$mes] ?? 0) + 1;
        $curso = trim(($h['curso'] ?? 'Sin curso registrado') . ' ' . ($h['paralelo'] ?? ''));
        $cursos[$curso] = ($cursos[$curso] ?? 0) + 1;
        foreach (array_unique($h['categorias_derivacion']) as $nombre) $motivos[$nombre] = ($motivos[$nombre] ?? 0) + 1;
        foreach ($h['opciones'] as $grupo => $opciones) {
            if ($opciones) $conSeleccion[$grupo] = ($conSeleccion[$grupo] ?? 0) + 1;
            foreach (array_unique($opciones) as $opcion) $distribuciones[$grupo][$opcion] = ($distribuciones[$grupo][$opcion] ?? 0) + 1;
        }
        foreach ($valoraciones as $campo => $_) {
            $valor = trim((string)($h[$campo] ?? '')) ?: 'Sin valoración registrada';
            $valoraciones[$campo][$valor] = ($valoraciones[$campo][$valor] ?? 0) + 1;
        }
    }
    ksort($meses);
    $sinSeleccion = [];
    foreach ($conSeleccion as $grupo=>$total) $sinSeleccion[$grupo] = count($historias) - $total;
    return ['historias'=>count($historias), 'estudiantes'=>count($personas), 'estudiantes_riesgo'=>count($riesgo),
        'distribuciones'=>$distribuciones, 'meses'=>$meses, 'cursos'=>$cursos, 'valoraciones'=>$valoraciones,
        'motivos_derivacion'=>$motivos, 'sin_seleccion'=>$sinSeleccion, 'estados'=>$estados];
}

/** Datos administrativos para el informe mensual; no son medidas de evolución clínica. */
function clinica_atencion_estados(mysqli $bd, array $f): array
{
    $resultado = ['derivaciones'=>['Pendiente'=>0, 'En seguimiento'=>0, 'Atendido'=>0],
        'citas'=>['Pendiente'=>0, 'Reprogramada'=>0, 'Atendida'=>0, 'Cancelada'=>0]];
    foreach (array_keys($resultado) as $tabla) {
        $filas = clinica_consultar($bd, "SELECT x.estado,COUNT(*) total FROM `$tabla` x
            JOIN estudiantes e ON e.cod_estudiante=x.cod_estudiante " . clinica_relacion_academica() . "
            WHERE (?='' OR x.fecha>=?) AND x.fecha<=? AND (?=0 OR se.cod_curso=?) AND (?=0 OR se.cod_paralelo=?) GROUP BY x.estado",
            [$f['desde'],$f['desde'],$f['hasta'],$f['curso'],$f['curso'],$f['paralelo'],$f['paralelo']]);
        foreach ($filas as $fila) $resultado[$tabla][$fila['estado']] = (int)$fila['total'];
    }
    return $resultado;
}
