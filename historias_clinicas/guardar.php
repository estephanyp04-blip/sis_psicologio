<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}

$modo = !isset($_SESSION['id_rol']);
$rol = (int)($_SESSION['id_rol'] ?? 0);

if (!$modo && !in_array($rol, [1, 2], true)) {
    header('Location: listar.php');
    exit;
}

function textoPost(string $campo): string
{
    return trim((string)($_POST[$campo] ?? ''));
}

function arregloPost(string $campo): array
{
    $valor = $_POST[$campo] ?? [];
    return is_array($valor) ? $valor : [];
}

$idEstudiante = (int)($_POST['id_estudiante'] ?? 0);
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

$fechaApertura = textoPost('fecha_apertura');
$lugarNacimiento = textoPost('lugar_nacimiento');
$celularEstudiante = textoPost('celular_estudiante');
$padreMadre = textoPost('padre_madre');
$derivadoPor = textoPost('derivado_por');
$fechaDerivacion = textoPost('fecha_derivacion');
$tutorCurso = textoPost('tutor_curso');
$talla = textoPost('talla');
$peso = textoPost('peso');
$valoracion = textoPost('valoracion');
$enfermedadesActuales = textoPost('enfermedades_actuales');

$motivoConsulta = textoPost('motivo_consulta');

$situacionEscolar = textoPost('situacion_escolar');
$cursosRepetidos = textoPost('cursos_repetidos');
$dificultadEscolar = textoPost('dificultad_escolar');
$materiaAgrada = textoPost('materia_agrada');
$materiaDesagrada = textoPost('materia_desagrada');
$relacionEscolar = textoPost('relacion_escolar');

$antecedentes = textoPost('antecedentes');
$valoracionFamiliar = textoPost('valoracion_familiar');
$contextoFamiliar = textoPost('contexto_familiar');

$impresionDiagnostica = textoPost('impresion_diagnostica');
$planIntervencion = textoPost('plan_intervencion');
$observaciones = textoPost('observaciones');
$estado = textoPost('estado');

$conductasRiesgo = arregloPost('conductas_riesgo');
$atencionDistraccion = arregloPost('atencion_distraccion');
$actividadMotora = arregloPost('actividad_motora');
$adaptacionNormas = arregloPost('adaptacion_normas');
$dificultadesSocioemocionales = arregloPost('dificultades_socioemocionales');
$estrategiasPrevias = arregloPost('estrategias_previas');
$familiares = arregloPost('familiares');

$errores = [];

if ($idEstudiante <= 0) {
    $errores[] = 'Seleccione un estudiante.';
}

$fecha = DateTime::createFromFormat('Y-m-d', $fechaApertura);

if (
    !$fecha ||
    $fecha->format('Y-m-d') !== $fechaApertura ||
    $fechaApertura > date('Y-m-d')
) {
    $errores[] = 'La fecha de apertura no es válida.';
}

if ($motivoConsulta === '') {
    $errores[] = 'El motivo de derivación es obligatorio.';
}

if (!in_array($estado, ['Activa', 'En seguimiento', 'Cerrada'], true)) {
    $errores[] = 'El estado seleccionado no es válido.';
}

if (
    $situacionEscolar !== '' &&
    !in_array($situacionEscolar, ['Muy buena', 'Buena', 'Regular', 'Deficiente'], true)
) {
    $errores[] = 'La situación escolar seleccionada no es válida.';
}

if (
    $valoracionFamiliar !== '' &&
    !in_array($valoracionFamiliar, ['Muy buena', 'Buena', 'Regular', 'Conflictiva'], true)
) {
    $errores[] = 'La valoración familiar seleccionada no es válida.';
}

$camposTexto = [
    $lugarNacimiento,
    $celularEstudiante,
    $padreMadre,
    $derivadoPor,
    $tutorCurso,
    $talla,
    $peso,
    $valoracion,
    $enfermedadesActuales,
    $motivoConsulta,
    $cursosRepetidos,
    $dificultadEscolar,
    $materiaAgrada,
    $materiaDesagrada,
    $relacionEscolar,
    $antecedentes,
    $contextoFamiliar,
    $impresionDiagnostica,
    $planIntervencion,
    $observaciones
];

foreach ($camposTexto as $texto) {
    if (mb_strlen($texto) > 5000) {
        $errores[] = 'Uno o más campos superan el límite permitido.';
        break;
    }
}

if (!$errores) {
    $stmt = $conexion->prepare("
        SELECT e.id_estudiante, h.id_historia
        FROM estudiantes e
        LEFT JOIN historias_clinicas h
            ON h.id_estudiante = e.id_estudiante
        WHERE e.id_estudiante = ?
          AND e.estado = 'Activo'
        LIMIT 1
    ");

    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        $errores[] = 'El estudiante no existe o no está activo.';
    } elseif (!empty($fila['id_historia'])) {
        $errores[] = 'El estudiante ya tiene una historia clínica.';
    }
}

if ($errores) {
    $_SESSION['datos_historia'] = $_POST;
    $_SESSION['mensaje'] = implode("\n", array_unique($errores));
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}

try {

    $conexion->begin_transaction();

    $sql = "
        INSERT INTO historias_clinicas (
            id_estudiante,
            id_usuario,
            fecha_apertura,
            lugar_nacimiento,
            celular_estudiante,
            padre_madre,
            derivado_por,
            fecha_derivacion,
            tutor_curso,
            talla,
            peso,
            valoracion,
            enfermedades_actuales,
            motivo_consulta,
            situacion_escolar,
            cursos_repetidos,
            dificultad_escolar,
            materia_agrada,
            materia_desagrada,
            relacion_escolar,
            antecedentes,
            valoracion_familiar,
            contexto_familiar,
            impresion_diagnostica,
            plan_intervencion,
            observaciones,
            estado
        )
        VALUES (
            ?,
            NULLIF(?, 0),
            ?,
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, ''),
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, ''),
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, ''),
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param(
        'iisssssssssssssssssssssssss',
        $idEstudiante,
        $idUsuario,
        $fechaApertura,
        $lugarNacimiento,
        $celularEstudiante,
        $padreMadre,
        $derivadoPor,
        $fechaDerivacion,
        $tutorCurso,
        $talla,
        $peso,
        $valoracion,
        $enfermedadesActuales,
        $motivoConsulta,
        $situacionEscolar,
        $cursosRepetidos,
        $dificultadEscolar,
        $materiaAgrada,
        $materiaDesagrada,
        $relacionEscolar,
        $antecedentes,
        $valoracionFamiliar,
        $contextoFamiliar,
        $impresionDiagnostica,
        $planIntervencion,
        $observaciones,
        $estado
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $idHistoria = $stmt->insert_id;
    $stmt->close();

    $gruposOpciones = [
        'conductas_riesgo' => $conductasRiesgo,
        'atencion_distraccion' => $atencionDistraccion,
        'actividad_motora' => $actividadMotora,
        'adaptacion_normas' => $adaptacionNormas,
        'dificultades_socioemocionales' => $dificultadesSocioemocionales,
        'estrategias_previas' => $estrategiasPrevias
    ];

    $stmtOpcion = $conexion->prepare("
        INSERT INTO historia_opciones (
            id_historia,
            grupo,
            valor
        )
        VALUES (?, ?, ?)
    ");

    foreach ($gruposOpciones as $grupo => $opciones) {

        foreach ($opciones as $valor) {

            $valor = trim((string)$valor);

            if ($valor === '') continue;

            if (mb_strlen($valor) > 255) {
                throw new Exception('Una opción supera los 255 caracteres.');
            }

            $stmtOpcion->bind_param(
                'iss',
                $idHistoria,
                $grupo,
                $valor
            );

            if (!$stmtOpcion->execute()) {
                throw new Exception($stmtOpcion->error);
            }
        }
    }

    $stmtOpcion->close();

    $stmtFamiliar = $conexion->prepare("
        INSERT INTO historia_familiares (
            id_historia,
            nombre,
            edad,
            relacion,
            profesion,
            ocupacion,
            observaciones
        )
        VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, ?)
    ");

    foreach ($familiares as $familiar) {

        if (!is_array($familiar)) continue;

        $nombre = trim((string)($familiar['nombre'] ?? ''));
        $edad = (int)($familiar['edad'] ?? 0);
        $relacion = trim((string)($familiar['relacion'] ?? ''));
        $profesion = trim((string)($familiar['profesion'] ?? ''));
        $ocupacion = trim((string)($familiar['ocupacion'] ?? ''));
        $observacionFamiliar = trim((string)($familiar['observaciones'] ?? ''));

        if (
            $nombre === '' &&
            $edad === 0 &&
            $relacion === '' &&
            $profesion === '' &&
            $ocupacion === '' &&
            $observacionFamiliar === ''
        ) {
            continue;
        }

        if ($nombre === '') {
            throw new Exception(
                'Cada integrante familiar registrado debe tener un nombre.'
            );
        }

        $stmtFamiliar->bind_param(
            'isissss',
            $idHistoria,
            $nombre,
            $edad,
            $relacion,
            $profesion,
            $ocupacion,
            $observacionFamiliar
        );

        if (!$stmtFamiliar->execute()) {
            throw new Exception($stmtFamiliar->error);
        }
    }

    $stmtFamiliar->close();

    $conexion->commit();

    unset($_SESSION['datos_historia']);

    $_SESSION['mensaje'] =
        'La historia clínica se creó correctamente.';

    $_SESSION['tipo_mensaje'] = 'success';

    header(
        'Location: ver.php?id=' . $idHistoria
    );

    exit;

} catch (Throwable $e) {

    $conexion->rollback();

    $_SESSION['datos_historia'] = $_POST;

    $_SESSION['mensaje'] =
        'No se pudo guardar la historia clínica: ' .
        $e->getMessage();

    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}