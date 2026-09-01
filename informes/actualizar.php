<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$rolActual = (int)($_SESSION['id_rol'] ?? 0);
if ($rolActual > 0 && !in_array($rolActual, [1, 2], true)) {
    header('Location: listar.php');
    exit;
}

function textoPost(string $campo): string
{
    return trim((string)($_POST[$campo] ?? ''));
}

function errorEditar(int $id, string $mensaje): void
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: editar.php?id=' . $id);
    exit;
}

$idInforme = filter_var($_POST['id_informe'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$idEstudiante = filter_var($_POST['id_estudiante'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$fecha = textoPost('fecha');
$tipos = $_POST['tipo_atencion'] ?? [];
$tipos = is_array($tipos) ? array_values(array_filter(array_map('trim', $tipos))) : [];
$tipoAtencion = implode(', ', $tipos);
$motivo = textoPost('motivo');
$diagnostico = textoPost('diagnostico');
$aspectoCognitivo = textoPost('aspecto_cognitivo');
$aspectosAfectivos = textoPost('aspectos_afectivos');
$diagnosticoAcuerdos = textoPost('diagnostico_acuerdos');
$recomendaciones = textoPost('recomendaciones');
$recibidoPor = textoPost('recibido_por');
$referidoPor = textoPost('referido_por');
$numeroAtenciones = max(0, (int)($_POST['numero_atenciones'] ?? 0));
$estado = textoPost('estado');

if ($idInforme <= 0) {
    header('Location: listar.php');
    exit;
}

$fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) {
    errorEditar($idInforme, 'La fecha del informe no es válida.');
}
if ($idEstudiante <= 0 || $tipoAtencion === '' || $motivo === '' || $aspectoCognitivo === '' || $aspectosAfectivos === '') {
    errorEditar($idInforme, 'Complete todos los campos obligatorios.');
}
if (!in_array($estado, ['Borrador', 'Emitido', 'Anulado'], true)) {
    errorEditar($idInforme, 'El estado seleccionado no es válido.');
}
foreach ([$motivo, $diagnostico, $aspectoCognitivo, $aspectosAfectivos, $diagnosticoAcuerdos, $recomendaciones, $recibidoPor] as $texto) {
    if (mb_strlen($texto) > 5000) {
        errorEditar($idInforme, 'Uno o más campos superan el límite permitido.');
    }
}

$stmt = $conexion->prepare("
    UPDATE informes SET
        fecha = ?, numero_atenciones = ?, referido_por = NULLIF(?, ''),
        tipo_atencion = ?, motivo = ?, diagnostico = NULLIF(?, ''),
        aspecto_cognitivo = ?, aspectos_afectivos = ?,
        diagnostico_acuerdos = NULLIF(?, ''), recomendaciones = NULLIF(?, ''),
        recibido_por = NULLIF(?, ''), estado = ?,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_informe = ? AND id_estudiante = ?
");
$stmt->bind_param(
    'sissssssssssii',
    $fecha,
    $numeroAtenciones,
    $referidoPor,
    $tipoAtencion,
    $motivo,
    $diagnostico,
    $aspectoCognitivo,
    $aspectosAfectivos,
    $diagnosticoAcuerdos,
    $recomendaciones,
    $recibidoPor,
    $estado,
    $idInforme,
    $idEstudiante
);
$stmt->execute();
$actualizado = $stmt->affected_rows >= 0;
$stmt->close();

if (!$actualizado) {
    errorEditar($idInforme, 'No se pudo actualizar el informe.');
}

$_SESSION['mensaje'] = 'Informe actualizado correctamente.';
$_SESSION['tipo_mensaje'] = 'success';
header('Location: ver.php?id=' . $idInforme);
exit;
