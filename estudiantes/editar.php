<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/editar.php');
require_once __DIR__ . '/../config/conexion.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { header('Location: listar.php'); exit; }
$stmt = $conexion->prepare("SELECT e.*,c.orden AS curso,p.nombre AS paralelo,s.id_institucion,s.gestion,s.turno
    FROM estudiantes e LEFT JOIN inscripciones i ON i.id_inscripcion=(
        SELECT i2.id_inscripcion FROM inscripciones i2 WHERE i2.id_estudiante=e.id_estudiante
        AND i2.estado IN ('Activo','Retirado') ORDER BY i2.id_inscripcion DESC LIMIT 1)
    LEFT JOIN secciones s ON s.id_seccion=i.id_seccion LEFT JOIN cursos c ON c.id_curso=s.id_curso
    LEFT JOIN paralelos p ON p.id_paralelo=s.id_paralelo WHERE e.id_estudiante=?");
$stmt->bind_param('i', $id); $stmt->execute();
$fila = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$fila) { http_response_code(404); exit('Estudiante no encontrado.'); }
$cursos = $conexion->query('SELECT orden,nombre FROM cursos ORDER BY orden')->fetch_all(MYSQLI_ASSOC);
$paralelos = $conexion->query('SELECT nombre FROM paralelos ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$recuperacion = $_SESSION['edicion_estudiante'] ?? [];
unset($_SESSION['edicion_estudiante']);
if (($recuperacion['id'] ?? 0) === $id) $fila = array_replace($fila, $recuperacion['datos']);
$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
$tituloPagina = 'Editar estudiante';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>
<main class="main-content">
    <div class="container-fluid py-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h1 class="h3 mb-1">Editar estudiante</h1><p class="text-muted mb-0">Actualice los datos del estudiante.</p></div>
            <a href="listar.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= login_html($mensaje) ?></div>
        <?php endif; ?>
        <div class="card shadow-sm border-0"><div class="card-body p-4">
            <form id="formEditarEstudiante" action="actualizar.php" method="POST">
                <?= login_campo_csrf() ?>
                <input type="hidden" name="id_estudiante" value="<?= $id ?>">
                <div class="row g-3">
                    <?php foreach (['ci' => 'CI', 'nombres' => 'Nombres', 'apellidos' => 'Apellidos'] as $campo => $etiqueta): ?>
                        <div class="col-md-4">
                            <label for="<?= $campo ?>" class="form-label"><?= $etiqueta ?></label>
                            <input type="text" id="<?= $campo ?>" name="<?= $campo ?>" class="form-control"
                                maxlength="<?= $campo === 'ci' ? 20 : 80 ?>" <?= $campo === 'ci' ? '' : 'required' ?>
                                value="<?= login_html($fila[$campo] ?? '') ?>">
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-4">
                        <label for="curso" class="form-label">Curso</label>
                        <select id="curso" name="curso" class="form-select" required>
                            <option value="">Seleccione un curso</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?= (int)$curso['orden'] ?>" <?= (int)$fila['curso'] === (int)$curso['orden'] ? 'selected' : '' ?>><?= login_html($curso['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="paralelo" class="form-label">Paralelo</label>
                        <select id="paralelo" name="paralelo" class="form-select" required>
                            <option value="">Seleccione un paralelo</option>
                            <?php foreach ($paralelos as $paralelo): ?>
                                <option value="<?= login_html($paralelo['nombre']) ?>" <?= $fila['paralelo'] === $paralelo['nombre'] ? 'selected' : '' ?>><?= login_html($paralelo['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select id="estado" name="estado" class="form-select" required>
                            <?php foreach (['Activo', 'Retirado'] as $estado): ?>
                                <option value="<?= $estado ?>" <?= $fila['estado'] === $estado ? 'selected' : '' ?>><?= $estado ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Guardar cambios</button>
            </form>
        </div></div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
