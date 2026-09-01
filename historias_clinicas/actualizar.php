<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
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

$idHistoria = (int)($_POST['id_historia'] ?? 0);
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

if ($idHistoria <= 0) {
    $errores[] = 'La historia clínica no es válida.';
}

if ($idEstudiante <= 0) {
    $errores[] = 'El estudiante no es válido.';
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
    !in_array(
        $situacionEscolar,
        ['Muy buena', 'Buena', 'Regular', 'Deficiente'],
        true
    )
) {
    $errores[] = 'La situación escolar seleccionada no es válida.';
}

if (
    $valoracionFamiliar !== '' &&
    !in_array(
        $valoracionFamiliar,
        ['Muy buena', 'Buena', 'Regular', 'Conflictiva'],
        true
    )
) {
    $errores[] = 'La valoración familiar no es válida.';
}

/* Verificar que la historia exista */
if (!$errores) {
    $stmt = $conexion->prepare("
        SELECT id_historia, id_estudiante
        FROM historias_clinicas
        WHERE id_historia = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $idHistoria);
    $stmt->execute();
    $historiaActual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$historiaActual) {
        $errores[] = 'La historia clínica no existe.';
    } elseif ((int)$historiaActual['id_estudiante'] !== $idEstudiante) {
        $errores[] = 'El estudiante de la historia clínica no coincide.';
    }
}

if ($errores) {
    $_SESSION['datos_historia'] = $_POST;
    $_SESSION['mensaje'] = implode("\n", array_unique($errores));
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: editar.php?id=' . $idHistoria);
    exit;
}

try {

    $conexion->begin_transaction();

    /*  ACTUALIZAR HISTORIA PRINCIPAL*/

    $stmt = $conexion->prepare("
        UPDATE historias_clinicas SET
            id_usuario = NULLIF(?, 0),
            fecha_apertura = ?,
            lugar_nacimiento = ?,
            celular_estudiante = ?,
            padre_madre = ?,
            derivado_por = ?,
            fecha_derivacion = NULLIF(?, ''),
            tutor_curso = ?,
            talla = ?,
            peso = ?,
            valoracion = ?,
            enfermedades_actuales = ?,
            motivo_consulta = ?,
            situacion_escolar = NULLIF(?, ''),
            cursos_repetidos = ?,
            dificultad_escolar = ?,
            materia_agrada = ?,
            materia_desagrada = ?,
            relacion_escolar = ?,
            antecedentes = ?,
            valoracion_familiar = NULLIF(?, ''),
            contexto_familiar = ?,
            impresion_diagnostica = ?,
            plan_intervencion = ?,
            observaciones = ?,
            estado = ?
        WHERE id_historia = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($conexion->error);
    }

    $stmt->bind_param(
        'isssssssssssssssssssssssssi',
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
        $estado,
        $idHistoria
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    /*SINCRONIZAR OPCIONES */

    $stmt = $conexion->prepare("
        DELETE FROM historia_opciones
        WHERE id_historia = ?
    ");

    $stmt->bind_param('i', $idHistoria);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

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

    if (!$stmtOpcion) {
        throw new Exception($conexion->error);
    }

    foreach ($gruposOpciones as $grupo => $opciones) {

        foreach (array_unique($opciones) as $valor) {

            $valor = trim((string)$valor);

            if ($valor === '') continue;

            if (mb_strlen($valor) > 255) {
                throw new Exception(
                    'Una de las opciones seleccionadas supera el límite permitido.'
                );
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

    /* SINCRONIZAR FAMILIARES */

    $stmt = $conexion->prepare("
        DELETE FROM historia_familiares
        WHERE id_historia = ?
    ");

    $stmt->bind_param('i', $idHistoria);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

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
        VALUES (
            ?,
            ?,
            NULLIF(?, 0),
            ?,
            ?,
            ?,
            ?
        )
    ");

    if (!$stmtFamiliar) {
        throw new Exception($conexion->error);
    }

    foreach ($familiares as $familiar) {

        if (!is_array($familiar)) continue;

        $nombre = trim((string)($familiar['nombre'] ?? ''));
        $edad = (int)($familiar['edad'] ?? 0);
        $relacion = trim((string)($familiar['relacion'] ?? ''));
        $profesion = trim((string)($familiar['profesion'] ?? ''));
        $ocupacion = trim((string)($familiar['ocupacion'] ?? ''));
        $observacionFamiliar = trim(
            (string)($familiar['observaciones'] ?? '')
        );

        /* Ignorar filas completamente vacías */
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
                'Cada familiar registrado debe tener nombre.'
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

    /*  FINALIZAR*/

    $conexion->commit();

    unset($_SESSION['datos_historia']);

    $_SESSION['mensaje'] =
        'La historia clínica se actualizó correctamente.';

    $_SESSION['tipo_mensaje'] = 'success';

    header('Location: ver.php?id=' . $idHistoria);
    exit;

} catch (Throwable $e) {

    $conexion->rollback();

    $_SESSION['datos_historia'] = $_POST;

    $_SESSION['mensaje'] =
        'No se pudo actualizar la historia clínica: ' .
        $e->getMessage();

    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: editar.php?id=' . $idHistoria);
    exit;
}