<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/La_Paz');

$baseUrl = '/proyecto_vercionII';

function redirigir(string $ruta): void
{
    header('Location: ' . $ruta);
    exit;
}

function longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir($baseUrl . '/derivaciones/listar.php');
}

$modoDesarrollo = !isset($_SESSION['id_rol']);
$rolActual = isset($_SESSION['id_rol'])
    ? (int) $_SESSION['id_rol']
    : 0;

$puedeActualizar = $modoDesarrollo
    || in_array($rolActual, [1, 3], true);

if (!$puedeActualizar) {
    $_SESSION['mensaje'] =
        'No tiene permiso para actualizar derivaciones.';

    $_SESSION['tipo_mensaje'] = 'danger';

    redirigir($baseUrl . '/derivaciones/listar.php');
}

$idDerivacion = filter_input(
    INPUT_POST,
    'id_derivacion',
    FILTER_VALIDATE_INT
);

$fecha = trim($_POST['fecha'] ?? '');
$materia = trim($_POST['materia'] ?? '');
$motivo = trim($_POST['motivo'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');
$prioridad = trim($_POST['prioridad'] ?? '');

if (!$idDerivacion || $idDerivacion <= 0) {
    $_SESSION['mensaje'] =
        'El identificador de la derivación no es válido.';

    $_SESSION['tipo_mensaje'] = 'warning';

    redirigir($baseUrl . '/derivaciones/listar.php');
}

$errores = [];
$prioridadesPermitidas = ['Alta', 'Media', 'Baja'];

$fechaObjeto = DateTime::createFromFormat(
    'Y-m-d',
    $fecha
);

$fechaValida = $fechaObjeto
    && $fechaObjeto->format('Y-m-d') === $fecha;

if (!$fechaValida) {
    $errores[] = 'La fecha no es válida.';
} elseif ($fecha > date('Y-m-d')) {
    $errores[] = 'La fecha no puede ser futura.';
}

if ($materia === '') {
    $errores[] = 'La materia es obligatoria.';
} elseif (longitud($materia) > 100) {
    $errores[] =
        'La materia no puede superar los 100 caracteres.';
}

if ($motivo === '') {
    $errores[] =
        'La descripción de la situación es obligatoria.';
} elseif (longitud($motivo) > 2000) {
    $errores[] =
        'La descripción no puede superar los 2000 caracteres.';
}

if (longitud($observaciones) > 3000) {
    $errores[] =
        'Las observaciones no pueden superar los 3000 caracteres.';
}

if (!in_array($prioridad, $prioridadesPermitidas, true)) {
    $errores[] = 'La prioridad seleccionada no es válida.';
}

if ($errores !== []) {
    $_SESSION['mensaje'] = implode(' ', $errores);
    $_SESSION['tipo_mensaje'] = 'danger';

    redirigir(
        $baseUrl .
        '/derivaciones/editar.php?id=' .
        $idDerivacion
    );
}

/* Comprobar que la derivación exista */
$sqlBuscar = "SELECT id_docente, estado
              FROM derivaciones
              WHERE id_derivacion = ?
              LIMIT 1";

$stmtBuscar = $conexion->prepare($sqlBuscar);

if (!$stmtBuscar) {
    die(
        'Error al preparar la consulta: ' .
        htmlspecialchars($conexion->error)
    );
}

$stmtBuscar->bind_param('i', $idDerivacion);
$stmtBuscar->execute();

$resultado = $stmtBuscar->get_result();
$derivacion = $resultado->fetch_assoc();

$stmtBuscar->close();

if (!$derivacion) {
    $_SESSION['mensaje'] =
        'La derivación que intenta actualizar no existe.';

    $_SESSION['tipo_mensaje'] = 'warning';

    redirigir($baseUrl . '/derivaciones/listar.php');
}

if ($derivacion['estado'] !== 'Pendiente') {
    $_SESSION['mensaje'] =
        'Solamente se pueden modificar derivaciones pendientes.';

    $_SESSION['tipo_mensaje'] = 'warning';

    redirigir($baseUrl . '/derivaciones/listar.php');
}

/* Comprobar propietario cuando existe sesión de docente */
$idDocenteActual = isset($_SESSION['id_docente'])
    ? (int) $_SESSION['id_docente']
    : 0;

if (
    $rolActual === 3
    && $idDocenteActual > 0
    && (int) $derivacion['id_docente'] !== $idDocenteActual
) {
    $_SESSION['mensaje'] =
        'No puede modificar la derivación de otro docente.';

    $_SESSION['tipo_mensaje'] = 'danger';

    redirigir($baseUrl . '/derivaciones/listar.php');
}

/* Actualizar los datos */
$sqlActualizar = "UPDATE derivaciones
                  SET fecha = ?,
                      materia = ?,
                      motivo = ?,
                      observaciones = ?,
                      prioridad = ?
                  WHERE id_derivacion = ?
                    AND estado = 'Pendiente'";

$stmtActualizar = $conexion->prepare($sqlActualizar);

if (!$stmtActualizar) {
    die(
        'Error al preparar la actualización: ' .
        htmlspecialchars($conexion->error)
    );
}

$stmtActualizar->bind_param(
    'sssssi',
    $fecha,
    $materia,
    $motivo,
    $observaciones,
    $prioridad,
    $idDerivacion
);

if (!$stmtActualizar->execute()) {
    $_SESSION['mensaje'] =
        'No se pudieron guardar los cambios: ' .
        $stmtActualizar->error;

    $_SESSION['tipo_mensaje'] = 'danger';

    $stmtActualizar->close();

    redirigir(
        $baseUrl .
        '/derivaciones/editar.php?id=' .
        $idDerivacion
    );
}

$stmtActualizar->close();

$_SESSION['mensaje'] =
    'La derivación fue actualizada correctamente.';

$_SESSION['tipo_mensaje'] = 'success';

redirigir($baseUrl . '/derivaciones/listar.php');