<?php
require_once '../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$idEstudiante = isset($_POST['id_estudiante'])
    ? (int) $_POST['id_estudiante']
    : 0;

$fecha = isset($_POST['fecha'])
    ? trim($_POST['fecha'])
    : '';

$hora = isset($_POST['hora'])
    ? substr(trim($_POST['hora']), 0, 5)
    : '';

$estado = isset($_POST['estado'])
    ? trim($_POST['estado'])
    : '';

$observaciones = isset($_POST['observaciones'])
    ? trim($_POST['observaciones'])
    : '';

$estadosPermitidos = [
    'Pendiente',
    'Atendida',
    'Reprogramada'
];

if (
    $idEstudiante <= 0 ||
    $fecha === '' ||
    $hora === '' ||
    !in_array($estado, $estadosPermitidos, true)
) {
    header('Location: registrar.php?error=2');
    exit;
}

$fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);

if (
    !$fechaValida ||
    $fechaValida->format('Y-m-d') !== $fecha ||
    $fecha < date('Y-m-d')
) {
    header('Location: registrar.php?error=2');
    exit;
}

$horasPermitidas = [];

for (
    $horaDisponible = strtotime('07:00');
    $horaDisponible <= strtotime('18:00');
    $horaDisponible += 30 * 60
) {
    $horasPermitidas[] = date('H:i', $horaDisponible);
}

if (!in_array($hora, $horasPermitidas, true)) {
    header('Location: registrar.php?error=2');
    exit;
}

/* Verificar estudiante */
$sqlEstudiante = '
    SELECT id_estudiante
    FROM estudiantes
    WHERE id_estudiante = ?
      AND estado = "Activo"
    LIMIT 1
';

$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param('i', $idEstudiante);
$stmtEstudiante->execute();

if ($stmtEstudiante->get_result()->num_rows === 0) {
    $stmtEstudiante->close();

    header('Location: registrar.php?error=2');
    exit;
}

$stmtEstudiante->close();

/* Verificar horario ocupado */
$sqlVerificar = '
    SELECT id_cita
    FROM citas
    WHERE fecha = ?
      AND hora = ?
      AND estado != "Cancelada"
';

$stmtVerificar = $conexion->prepare($sqlVerificar);
$stmtVerificar->bind_param('ss', $fecha, $hora);
$stmtVerificar->execute();

if ($stmtVerificar->get_result()->num_rows > 0) {
    $stmtVerificar->close();

    header('Location: registrar.php?error=1');
    exit;
}

$stmtVerificar->close();

/* Guardar cita sin usuario asignado */
$sqlInsertar = '
    INSERT INTO citas (
        id_estudiante,
        id_usuario,
        fecha,
        hora,
        estado,
        observaciones
    ) VALUES (?, NULL, ?, ?, ?, ?)
';

$stmtInsertar = $conexion->prepare($sqlInsertar);

$stmtInsertar->bind_param(
    'issss',
    $idEstudiante,
    $fecha,
    $hora,
    $estado,
    $observaciones
);

$stmtInsertar->execute();
$stmtInsertar->close();

header('Location: listar.php?exito=1');
exit;