<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/actualizar.php');


require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id_estudiante', FILTER_VALIDATE_INT);
$ci = trim($_POST['ci'] ?? '');
$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$curso = filter_input(INPUT_POST, 'curso', FILTER_VALIDATE_INT);
$paralelo = trim($_POST['paralelo'] ?? '');
$estado = trim($_POST['estado'] ?? '');

if (!$id || !$curso || !in_array($estado, ['Activo', 'Retirado'], true)
    || $nombres === '' || $apellidos === '' || !in_array($paralelo, ['A', 'B', 'C', 'D'], true)) {
    $_SESSION['mensaje'] = 'Debe completar los datos del estudiante correctamente.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: editar.php?id=' . max(0, (int)$id));
    exit;
}

try {
    $conexion->begin_transaction();
    $stmt = $conexion->prepare("SELECT i.id_inscripcion,s.turno FROM inscripciones i
        INNER JOIN secciones s ON s.id_seccion=i.id_seccion
        WHERE i.id_estudiante=? AND i.estado IN ('Activo','Retirado')
        ORDER BY i.id_inscripcion DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $inscripcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$inscripcion) throw new InvalidArgumentException('El estudiante no tiene una inscripción académica que actualizar.');

    $stmt = $conexion->prepare("SELECT s.id_seccion FROM secciones s
        INNER JOIN cursos c ON c.id_curso=s.id_curso
        INNER JOIN paralelos p ON p.id_paralelo=s.id_paralelo
        INNER JOIN instituciones i ON i.id_institucion=s.id_institucion
        WHERE c.orden=? AND p.nombre=? AND s.turno=? AND s.gestion=YEAR(CURRENT_DATE())
          AND s.estado='Activo' AND c.estado='Activo' AND p.estado='Activo' AND i.estado='Activo'
        LIMIT 2");
    $stmt->bind_param('iss', $curso, $paralelo, $inscripcion['turno']);
    $stmt->execute();
    $secciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (count($secciones) !== 1) throw new InvalidArgumentException('La nueva combinación de curso, paralelo y turno no es única o no está activa.');

    $ci = $ci !== '' ? $ci : null;
    $stmt = $conexion->prepare('UPDATE estudiantes SET ci=?,nombres=?,apellidos=?,estado=? WHERE id_estudiante=?');
    $stmt->bind_param('ssssi', $ci, $nombres, $apellidos, $estado, $id);
    $stmt->execute();
    $stmt->close();

    $idSeccion = (int)$secciones[0]['id_seccion'];
    $stmt = $conexion->prepare('UPDATE inscripciones SET id_seccion=?,estado=? WHERE id_inscripcion=?');
    $stmt->bind_param('isi', $idSeccion, $estado, $inscripcion['id_inscripcion']);
    $stmt->execute();
    $stmt->close();
    $conexion->commit();
    $_SESSION['mensaje'] = 'El estudiante se actualizó correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
} catch (Throwable $error) {
    $conexion->rollback();
    error_log('Error al actualizar estudiante: ' . $error->getMessage());
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException
        ? $error->getMessage()
        : ($error instanceof mysqli_sql_exception && $error->getCode() === 1062
            ? 'El CI ya está registrado.' : 'No se pudo actualizar el estudiante.');
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: editar.php?id=' . (int)$id);
}
exit;
?>
