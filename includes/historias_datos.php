<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/trazabilidad_datos.php';

const HISTORIA_TEXTOS = [
    'fecha_apertura' => 10, 'lugar_nacimiento' => 150, 'celular_estudiante' => 30,
    'padre_madre' => 150, 'derivado_por' => 150, 'fecha_derivacion' => 10,
    'tutor_curso' => 150, 'talla' => 30, 'peso' => 30, 'valoracion' => 150,
    'enfermedades_actuales' => 5000, 'motivo_consulta' => 5000, 'situacion_escolar' => 50,
    'cursos_repetidos' => 150, 'dificultad_escolar' => 5000, 'materia_agrada' => 150,
    'materia_desagrada' => 150, 'relacion_escolar' => 5000, 'antecedentes' => 5000,
    'valoracion_familiar' => 50, 'contexto_familiar' => 5000, 'evaluacion_inicial' => 5000,
    'impresion_diagnostica' => 5000, 'plan_intervencion' => 5000, 'observaciones' => 5000, 'estado' => 30,
];
const HISTORIA_OPCIONES = [
    'conductas_riesgo' => ['Delictiva', 'Pandillaje', 'Sospecha de consumo/droga', 'Problemática sexual'],
    'atencion_distraccion' => ['Se distrae mirando a cualquier lado', 'Se olvida sus carpetas o tareas',
        'Habla constantemente', 'Se equivoca por descuido', 'No termina de hacer tareas / se atrasa', 'Lenguaje inapropiado / conducta obscena'],
    'actividad_motora' => ['Se mueve constantemente en su asiento', 'Se para y se sienta constantemente'],
    'adaptacion_normas' => ['No obedece el reglamento del colegio', 'Le es difícil seguir indicaciones / protesta',
        'No cumple normas establecidas', 'Propicia el desorden', 'Miente', 'Agrede de manera verbal',
        'Agrede de manera física', 'Llama por apodos / bullying', 'Sus compañeros lo rechazan'],
    'dificultades_socioemocionales' => ['Falta de interés por aprender', 'Se le observa triste, deprimido, desanimado',
        'Cambia de humor frecuentemente', 'Prefiere estar solo(a)', 'No participa en las actividades',
        'Se calla y aguanta si lo molestan', 'Tartamudea / habla nerviosamente', 'Se muerde / come las uñas',
        'Duda al expresar su opinión', 'Se mantiene callado'],
    'estrategias_previas' => ['Conversación con él/ella', 'Llamadas de atención', 'Conversación con el tutor/a a cargo',
        'Premios y sanciones', 'Mayor supervisión', 'Ninguna'],
];
const HISTORIA_EVOLUCIONES = [1 => 'Empeoró', 2 => 'Sin mejoría', 3 => 'Estable', 4 => 'Mejoró', 5 => 'Mejoró significativamente'];

function historia_texto($valor, string $campo, int $maximo): string
{
    if (!is_string($valor)) throw new InvalidArgumentException("El campo $campo debe ser texto.");
    $valor = trim($valor);
    if (!mb_check_encoding($valor, 'UTF-8') || mb_strlen($valor) > $maximo) {
        throw new InvalidArgumentException("El campo $campo admite hasta $maximo caracteres UTF-8.");
    }
    return $valor;
}

function historia_fecha(string $fecha, bool $opcional = false): void
{
    if ($opcional && $fecha === '') return;
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if (!$objeto || $objeto->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) {
        throw new InvalidArgumentException('La fecha de apertura o derivación no es válida.');
    }
}

function historia_cargar_hijos(mysqli $bd, int $id): array
{
    $datos = array_fill_keys(array_keys(HISTORIA_OPCIONES), []);
    $stmt = $bd->prepare('SELECT grupo, valor FROM historia_opciones WHERE id_historia = ? ORDER BY id_opcion');
    $stmt->bind_param('i', $id); $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $opcion) {
        if (array_key_exists($opcion['grupo'], $datos)) $datos[$opcion['grupo']][] = $opcion['valor'];
    }
    $stmt->close();
    $stmt = $bd->prepare('SELECT nombre, edad, relacion, profesion, ocupacion, observaciones FROM historia_familiares WHERE id_historia = ? ORDER BY id_familiar');
    $stmt->bind_param('i', $id); $stmt->execute();
    $datos['familiares'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $datos;
}

function historia_validar(array $entrada, array $anterior = []): array
{
    $datos = [];
    foreach (HISTORIA_TEXTOS as $campo => $maximo) {
        // La omisión en una edición conserva el valor, incluidos NULL y cadenas vacías.
        $datos[$campo] = array_key_exists($campo, $entrada)
            ? historia_texto($entrada[$campo], $campo, $maximo) : ($anterior[$campo] ?? null);
        if (!$anterior && $datos[$campo] === null) $datos[$campo] = '';
    }
    historia_fecha((string)$datos['fecha_apertura']);
    historia_fecha((string)$datos['fecha_derivacion'], true);
    if ($datos['motivo_consulta'] === '' || $datos['motivo_consulta'] === null) throw new InvalidArgumentException('El motivo de consulta es obligatorio.');
    foreach (['estado' => ['Activa', 'En seguimiento', 'Cerrada'],
        'situacion_escolar' => ['', null, 'Muy buena', 'Buena', 'Regular', 'Deficiente'],
        'valoracion_familiar' => ['', null, 'Muy buena', 'Buena', 'Regular', 'Conflictiva']] as $campo => $permitidos) {
        if (!in_array($datos[$campo], $permitidos, true)) throw new InvalidArgumentException("El valor de $campo no es válido.");
    }
    $evolucion = array_key_exists('evolucion_caso', $entrada) ? $entrada['evolucion_caso'] : ($anterior['evolucion_caso'] ?? null);
    $datos['evolucion_caso'] = null;
    if ($evolucion !== null && $evolucion !== '') {
        $valor = filter_var($evolucion, FILTER_VALIDATE_INT);
        if ($valor === false || !isset(HISTORIA_EVOLUCIONES[$valor])) throw new InvalidArgumentException('La evolución debe ser un valor de 1 a 5 o quedar sin evaluar.');
        $datos['evolucion_caso'] = $valor;
    }
    $presentes = $entrada['opciones_presentes'] ?? [];
    if (!is_array($presentes)) throw new InvalidArgumentException('El formulario de opciones está incompleto.');
    foreach (HISTORIA_OPCIONES as $grupo => $permitidas) {
        $enviado = array_key_exists($grupo, $entrada) || isset($presentes[$grupo]);
        $opciones = $enviado ? ($entrada[$grupo] ?? []) : ($anterior[$grupo] ?? []);
        if (!is_array($opciones)) throw new InvalidArgumentException("Las opciones de $grupo no son válidas.");
        $permitidas = array_merge($permitidas, $anterior[$grupo] ?? []);
        $datos[$grupo] = [];
        foreach ($opciones as $opcion) {
            $opcion = historia_texto($opcion, $grupo, 255);
            if (!in_array($opcion, $permitidas, true)) throw new InvalidArgumentException("Hay una opción no válida en $grupo.");
            $datos[$grupo][] = $opcion;
        }
        $datos[$grupo] = array_values(array_unique($datos[$grupo]));
    }
    $familiares = $entrada['familiares'] ?? (isset($entrada['familiares_presentes']) ? [] : ($anterior['familiares'] ?? []));
    if (!is_array($familiares)) throw new InvalidArgumentException('La información familiar no es válida.');
    $datos['familiares'] = [];
    foreach ($familiares as $familiar) {
        if (!is_array($familiar)) throw new InvalidArgumentException('La información familiar no es válida.');
        $fila = [];
        foreach (['nombre' => 150, 'relacion' => 100, 'profesion' => 150, 'ocupacion' => 150, 'observaciones' => 5000] as $campo => $maximo) {
            $fila[$campo] = historia_texto($familiar[$campo] ?? '', 'familiar.' . $campo, $maximo);
        }
        $edad = $familiar['edad'] ?? '';
        $fila['edad'] = null;
        if ($edad !== '' && $edad !== null) {
            $fila['edad'] = filter_var($edad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
            if ($fila['edad'] === false) throw new InvalidArgumentException('La edad familiar debe estar entre 0 y 120 años.');
        }
        if (count(array_filter($fila, static fn($valor) => $valor !== '' && $valor !== null)) === 0) continue;
        if ($fila['nombre'] === '') throw new InvalidArgumentException('Cada familiar registrado debe tener nombre.');
        $datos['familiares'][] = $fila;
    }
    foreach (['fecha_derivacion', 'situacion_escolar', 'valoracion_familiar'] as $campo) {
        if ($datos[$campo] === '') $datos[$campo] = null;
    }
    return $datos;
}

function historia_guardar(mysqli $bd, array $entrada, int $autor, ?int $idHistoria = null): int
{
    $idEstudiante = filter_var($entrada['id_estudiante'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$idEstudiante || $autor <= 0) throw new InvalidArgumentException('El estudiante o usuario no es válido.');
    // Ubicado al final del formulario: rechaza POST truncados por max_input_vars.
    if (($entrada['formulario_completo'] ?? '') !== '1') throw new InvalidArgumentException('El formulario está incompleto. Recargue la página antes de guardar.');
    $bd->begin_transaction();
    try {
        $anterior = [];
        if ($idHistoria !== null) {
            $stmt = $bd->prepare('SELECT * FROM historias_clinicas WHERE id_historia = ? AND id_estudiante = ? FOR UPDATE');
            $stmt->bind_param('ii', $idHistoria, $idEstudiante); $stmt->execute();
            $anterior = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$anterior) throw new InvalidArgumentException('La historia no existe o no corresponde al estudiante.');
            $idEnviado = array_key_exists('id_derivacion', $entrada)
                ? filter_var($entrada['id_derivacion'] === '' ? 0 : $entrada['id_derivacion'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                : (int)($anterior['id_derivacion'] ?? 0);
            if ($idEnviado === false || $idEnviado !== (int)($anterior['id_derivacion'] ?? 0)) {
                throw new InvalidArgumentException('La derivación de origen de la historia no puede cambiarse en esta edición.');
            }
            $anterior = array_merge($anterior, historia_cargar_hijos($bd, $idHistoria));
        } else {
            $stmt = $bd->prepare("SELECT id_estudiante FROM estudiantes WHERE id_estudiante = ? AND estado = 'Activo' FOR UPDATE");
            $stmt->bind_param('i', $idEstudiante); $stmt->execute();
            $existe = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$existe) throw new InvalidArgumentException('El estudiante no existe o no está activo.');
        }
        $datos = historia_validar($entrada, $anterior);
        $idCita = flujo_id($entrada['id_cita'] ?? $anterior['id_cita'] ?? 0, true);
        if ($anterior && $idCita !== (int)($anterior['id_cita'] ?? 0)) throw new InvalidArgumentException('La cita de origen no puede cambiarse.');
        $columnas = array_keys(HISTORIA_TEXTOS);
        $columnas[] = 'evolucion_caso';
        $valores = array_map(static fn($campo) => $datos[$campo], $columnas);
        $tipos = str_repeat('s', count(HISTORIA_TEXTOS)) . 'i';
        if ($idHistoria === null) {
            $derivacionEnviada = $entrada['id_derivacion'] ?? 0;
            $idDerivacion = filter_var($derivacionEnviada === '' ? 0 : $derivacionEnviada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($idDerivacion === false) throw new InvalidArgumentException('La derivación no es válida.');
            if ($idDerivacion > 0) {
                $stmt = $bd->prepare('SELECT id_derivacion FROM derivaciones WHERE id_derivacion = ? AND id_estudiante = ?');
                $stmt->bind_param('ii', $idDerivacion, $idEstudiante); $stmt->execute();
                $existe = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$existe) throw new InvalidArgumentException('La derivación no corresponde al estudiante.');
            }
            if ($idCita) {
                $cita = flujo_fila($bd, 'SELECT * FROM citas WHERE id_cita=? FOR UPDATE', [$idCita]);
                if (!$cita || (int)$cita['id_estudiante'] !== $idEstudiante || $cita['estado'] === 'Cancelada'
                    || (int)($cita['id_derivacion'] ?? 0) !== $idDerivacion || $cita['fecha'] > $datos['fecha_apertura']) {
                    throw new InvalidArgumentException('La cita no coincide con el estudiante, la derivación o la fecha de apertura.');
                }
            }
            $columnas = array_merge(['id_estudiante', 'id_usuario', 'id_derivacion', 'id_cita'], $columnas);
            $valores = array_merge([$idEstudiante, $autor, $idDerivacion ?: null, $idCita ?: null], $valores);
            $tipos = 'iiii' . $tipos;
            $stmt = $bd->prepare('INSERT INTO historias_clinicas (' . implode(',', $columnas) . ') VALUES (' . implode(',', array_fill(0, count($columnas), '?')) . ')');
        } else {
            $asignaciones = array_map(static fn($campo) => "$campo = ?", $columnas);
            $stmt = $bd->prepare('UPDATE historias_clinicas SET ' . implode(',', $asignaciones) . ', fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_historia = ?');
            $valores[] = $idHistoria; $tipos .= 'i';
        }
        $stmt->bind_param($tipos, ...$valores); $stmt->execute();
        if ($idHistoria === null) $idHistoria = (int)$stmt->insert_id;
        $stmt->close();
        foreach (HISTORIA_OPCIONES as $grupo => $_) {
            // Solo reemplaza grupos enviados, conservando los omitidos y grupos históricos desconocidos.
            if ($anterior && !array_key_exists($grupo, $entrada) && !isset($entrada['opciones_presentes'][$grupo])) continue;
            $stmt = $bd->prepare('DELETE FROM historia_opciones WHERE id_historia = ? AND grupo = ?');
            $stmt->bind_param('is', $idHistoria, $grupo); $stmt->execute(); $stmt->close();
            foreach ($datos[$grupo] as $valor) {
                $stmt = $bd->prepare('INSERT INTO historia_opciones (id_historia,grupo,valor) VALUES (?,?,?)');
                $stmt->bind_param('iss', $idHistoria, $grupo, $valor); $stmt->execute(); $stmt->close();
            }
        }
        if (!$anterior || array_key_exists('familiares', $entrada) || isset($entrada['familiares_presentes'])) {
            $stmt = $bd->prepare('DELETE FROM historia_familiares WHERE id_historia = ?');
            $stmt->bind_param('i', $idHistoria); $stmt->execute(); $stmt->close();
            foreach ($datos['familiares'] as $fila) {
                $stmt = $bd->prepare('INSERT INTO historia_familiares (id_historia,nombre,edad,relacion,profesion,ocupacion,observaciones) VALUES (?,?,?,?,?,?,?)');
                $stmt->bind_param('isissss', $idHistoria, $fila['nombre'], $fila['edad'], $fila['relacion'], $fila['profesion'], $fila['ocupacion'], $fila['observaciones']);
                $stmt->execute(); $stmt->close();
            }
        }
        $accion = $anterior ? 'Actualizar' : 'Crear';
        $stmt = $bd->prepare("INSERT INTO auditoria (id_usuario,modulo,accion,registro_id,detalle) VALUES (?,'historias_clinicas',?,?,'Guardado del expediente clínico')");
        $stmt->bind_param('isi', $autor, $accion, $idHistoria); $stmt->execute(); $stmt->close();
        $bd->commit();
        return $idHistoria;
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }
}

/** Datos recuperables, sin token ni identificadores ajenos al formulario actual. */
function historia_recuperar_entrada(array $entrada): array
{
    $permitidos = array_merge(array_keys(HISTORIA_TEXTOS), array_keys(HISTORIA_OPCIONES),
        ['id_historia', 'id_estudiante', 'id_derivacion', 'id_cita', 'evolucion_caso', 'familiares', 'opciones_presentes', 'familiares_presentes']);
    $datos = array_intersect_key($entrada, array_flip($permitidos));
    foreach (array_merge(array_keys(HISTORIA_TEXTOS), ['id_historia', 'id_estudiante', 'id_derivacion', 'id_cita', 'evolucion_caso']) as $campo) {
        if (array_key_exists($campo, $datos) && !is_scalar($datos[$campo])) unset($datos[$campo]);
    }
    foreach (HISTORIA_OPCIONES as $grupo => $_) {
        if (isset($datos[$grupo])) $datos[$grupo] = is_array($datos[$grupo]) ? array_values(array_filter($datos[$grupo], 'is_string')) : [];
        if (isset($entrada['opciones_presentes'][$grupo]) && !isset($datos[$grupo])) $datos[$grupo] = [];
    }
    if (isset($entrada['familiares_presentes']) && !isset($datos['familiares'])) $datos['familiares'] = [];
    if (isset($datos['familiares'])) {
        $datos['familiares'] = is_array($datos['familiares']) ? array_values(array_filter($datos['familiares'], 'is_array')) : [];
        foreach ($datos['familiares'] as &$familiar) $familiar = array_filter($familiar, 'is_scalar');
        unset($familiar);
    }
    return $datos;
}
