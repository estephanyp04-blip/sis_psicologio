<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/actualizar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/derivaciones_datos.php';

$id = filter_var($_POST['id_derivacion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { header('Location: listar.php'); exit; }
try {
    derivacion_actualizar($conexion, $id, $_POST, (int)$_SESSION['id_rol'], (int)($_SESSION['id_docente'] ?? 0));
    unset($_SESSION['edicion_derivacion']);
    $_SESSION['mensaje'] = 'La derivación fue actualizada correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
} catch (Throwable $error) {
    $datos = [];
    foreach (['fecha', 'materia', 'motivo', 'prioridad', 'observaciones_adicionales'] as $campo) {
        if (isset($_POST[$campo]) && is_string($_POST[$campo])) $datos[$campo] = $_POST[$campo];
    }
    if (isset($_POST['categorias']) && is_array($_POST['categorias'])) $datos['categorias'] = array_values(array_filter($_POST['categorias'], 'is_string'));
    elseif (isset($_POST['observaciones_presentes'])) $datos['categorias'] = [];
    $_SESSION['edicion_derivacion'] = ['id' => $id, 'datos' => $datos];
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo actualizar la derivación.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: editar.php?id=' . $id);
}
exit;