<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}

$rolActual = (int)($_SESSION['id_rol'] ?? 0);
if ($rolActual > 0 && !in_array($rolActual, [1, 2], true)) {
    $_SESSION['mensaje'] = 'No tiene permiso para registrar informes.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: listar.php');
    exit;
}

function textoPost(string $campo): string
{
    return trim((string)($_POST[$campo] ?? ''));
}

function enteroPost(string $campo): int
{
    $valor = filter_var($_POST[$campo] ?? 0, FILTER_VALIDATE_INT);
    return $valor === false ? 0 : (int)$valor;
}

function volverConError(string $mensaje): void
{
    $_SESSION['datos_informe'] = $_POST;
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}

$idEstudiante = enteroPost('id_estudiante');
$idHistoria = enteroPost('id_historia');
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$fecha = textoPost('fecha');
$tiposAtencionPost = $_POST['tipo_atencion'] ?? [];
$tiposAtencion = is_array($tiposAtencionPost) ? $tiposAtencionPost : [];
$tiposAtencion = array_values(array_filter(
    array_map(static fn($tipo): string => trim((string)$tipo), $tiposAtencion),
    static fn(string $tipo): bool => $tipo !== ''
));
$tipoAtencion = implode(', ', $tiposAtencion);
$motivo = textoPost('motivo');
$diagnostico = textoPost('diagnostico');
$aspectoCognitivo = textoPost('aspecto_cognitivo');
$aspectosAfectivos = textoPost('aspectos_afectivos');
$diagnosticoAcuerdos = textoPost('diagnostico_acuerdos');
$recomendaciones = textoPost('recomendaciones');
$recibidoPor = textoPost('recibido_por');
$numeroAtenciones = max(0, enteroPost('numero_atenciones'));
$referidoPor = textoPost('referido_por');
$estado = textoPost('estado');

$fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) {
    volverConError('La fecha del informe no es válida.');
}

if ($idEstudiante <= 0) {
    volverConError('Seleccione un estudiante.');
}

if ($tipoAtencion === '') {
    volverConError('Seleccione al menos un tipo de atención.');
}

if ($motivo === '' || $aspectoCognitivo === '' || $aspectosAfectivos === '') {
    volverConError('Complete los campos obligatorios del informe.');
}

if (!in_array($estado, ['Borrador', 'Emitido', 'Anulado'], true)) {
    volverConError('El estado seleccionado no es válido.');
}

foreach ([
    $motivo,
    $diagnostico,
    $aspectoCognitivo,
    $aspectosAfectivos,
    $diagnosticoAcuerdos,
    $recomendaciones,
    $recibidoPor,
] as $texto) {
    if (mb_strlen($texto) > 5000) {
        volverConError('Uno o más campos superan el límite permitido.');
    }
}

$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("
        SELECT id_estudiante
        FROM estudiantes
        WHERE id_estudiante = ? AND estado = 'Activo'
        LIMIT 1
    ");
    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();
    $estudianteExiste = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$estudianteExiste) {
        throw new RuntimeException('El estudiante no existe o no está activo.');
    }

    $stmt = $conexion->prepare("
        SELECT id_historia
        FROM historias_clinicas
        WHERE id_estudiante = ?
        ORDER BY id_historia DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();
    $historia = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $idHistoriaReal = $historia ? (int)$historia['id_historia'] : 0;
    if ($idHistoria > 0 && $idHistoria !== $idHistoriaReal) {
        throw new RuntimeException('La historia clínica no corresponde al estudiante.');
    }
    $idHistoria = $idHistoriaReal;

    $stmt = $conexion->prepare("
        SELECT id_derivacion
        FROM derivaciones
        WHERE id_estudiante = ?
        ORDER BY fecha DESC, id_derivacion DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();
    $derivacion = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $idDerivacion = $derivacion ? (int)$derivacion['id_derivacion'] : 0;

    $numeroFicha = 'INF-0001';
    $resultadoFicha = $conexion->query("
        SELECT numero_ficha
        FROM informes
        ORDER BY id_informe DESC
        LIMIT 1
        FOR UPDATE
    ");
    $ultimaFicha = $resultadoFicha->fetch_assoc();
    if ($ultimaFicha && preg_match('/(\d+)$/', (string)$ultimaFicha['numero_ficha'], $coincidencia)) {
        $numeroFicha = 'INF-' . str_pad(
            (int)$coincidencia[1] + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    $stmt = $conexion->prepare("
        INSERT INTO informes (
            numero_ficha,
            fecha,
            id_estudiante,
            elaborado_por,
            numero_atenciones,
            referido_por,
            id_historia,
            id_derivacion,
            tipo_atencion,
            motivo,
            diagnostico,
            aspecto_cognitivo,
            aspectos_afectivos,
            diagnostico_acuerdos,
            recomendaciones,
            recibido_por,
            estado
        ) VALUES (
            ?,
            ?,
            ?,
            NULLIF(?, 0),
            ?,
            NULLIF(?, ''),
            NULLIF(?, 0),
            NULLIF(?, 0),
            ?,
            ?,
            NULLIF(?, ''),
            ?,
            ?,
            NULLIF(?, ''),
            NULLIF(?, ''),
            NULLIF(?, ''),
            ?
        )
    ");
    $stmt->bind_param(
        'ssiiisiisssssssss',
        $numeroFicha,
        $fecha,
        $idEstudiante,
        $idUsuario,
        $numeroAtenciones,
        $referidoPor,
        $idHistoria,
        $idDerivacion,
        $tipoAtencion,
        $motivo,
        $diagnostico,
        $aspectoCognitivo,
        $aspectosAfectivos,
        $diagnosticoAcuerdos,
        $recomendaciones,
        $recibidoPor,
        $estado
    );
    $stmt->execute();
    $stmt->close();
    $conexion->commit();

    unset($_SESSION['datos_informe']);
    $_SESSION['mensaje'] = "Informe {$numeroFicha} guardado correctamente.";
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
    exit;
} catch (Throwable $error) {
    $conexion->rollback();
    $_SESSION['datos_informe'] = $_POST;
    $_SESSION['mensaje'] = 'No se pudo guardar el informe: ' . $error->getMessage();
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}
