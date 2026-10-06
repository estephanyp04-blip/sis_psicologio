<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/ajax_derivaciones.php');
require_once __DIR__ . '/../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$idEstudiante = filter_var($_GET['id_estudiante'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$idEstudiante) login_error_json(400, 'Indique un estudiante válido.');

try {
    $conexion = login_bd();
    $stmt = $conexion->prepare('SELECT id_estudiante FROM estudiantes WHERE id_estudiante = ?');
    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$existe) login_error_json(404, 'El estudiante no existe.');

    $stmt = $conexion->prepare("SELECT d.id_derivacion AS id, d.fecha, d.materia, d.prioridad,
        d.motivo, d.observaciones, d.estado,
        TRIM(CONCAT(COALESCE(doc.nombres, ''), ' ', COALESCE(doc.apellidos, ''))) AS docente
        FROM derivaciones d LEFT JOIN docentes doc ON doc.id_docente = d.id_docente
        WHERE d.id_estudiante = ? ORDER BY d.fecha DESC, d.id_derivacion DESC");
    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();
    $derivaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($derivaciones as &$derivacion) $derivacion['id'] = (int)$derivacion['id'];
    unset($derivacion);
    echo json_encode(['derivaciones' => $derivaciones], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Consulta de derivaciones: ' . $error->getMessage());
    login_error_json(500, 'No se pudieron consultar las derivaciones.');
}
