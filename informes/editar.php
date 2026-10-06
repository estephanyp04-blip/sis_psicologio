<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('informes/editar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/../includes/informes_datos.php';

$rolActual = (int)($_SESSION['id_rol'] ?? 0);
if (!in_array($rolActual, [1, 2], true)) {
    $_SESSION['mensaje'] = 'No tiene permiso para editar informes.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: listar.php');
    exit;
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$idInforme = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idInforme) {
    header('Location: listar.php');
    exit;
}

$stmt = $conexion->prepare("
    SELECT i.*,d.*,i.fecha_inicio AS fecha,e.nombres,e.apellidos,e.curso,e.paralelo
    FROM informes i
    INNER JOIN informe_individual d ON d.id_informe=i.id_informe
    INNER JOIN vista_estudiantes e ON e.id_estudiante=d.id_estudiante
    WHERE i.id_informe = ?
    LIMIT 1
");
$stmt->bind_param('i', $idInforme);
$stmt->execute();
$informe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$informe) {
    $_SESSION['mensaje'] = 'El informe solicitado no existe.';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: listar.php');
    exit;
}

$recuperado = $_SESSION['edicion_informe'] ?? [];
unset($_SESSION['edicion_informe']);
$tipos = array_filter(array_map('trim', explode(',', (string)$informe['tipo_atencion'])));
if (($recuperado['id'] ?? 0) === $idInforme) {
    $datos = $recuperado['datos'];
    $tipos = $datos['tipo_atencion'];
    unset($datos['id_estudiante'], $datos['id_historia'], $datos['tipo_atencion']);
    $informe = array_replace($informe, $datos);
}
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$opcionesAtencion = INFORME_ATENCIONES;

$tituloPagina = 'Editar informe ' . $informe['numero_ficha'];
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></li>
                <li class="breadcrumb-item"><a href="listar.php">Informes</a></li>
                <li class="breadcrumb-item active">Editar <?= e($informe['numero_ficha']) ?></li>
            </ol>
        </nav>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show" role="alert">
                <?= nl2br(e($mensaje)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="formulario-derivacion">
            <div class="form-header">
                <div>
                    <span class="text-primary fw-semibold"><i class="bi bi-pencil-square me-1"></i>Departamento de Psicología</span>
                    <h2 class="mt-2">Editar ficha psicológica</h2>
                    <p>Actualice la información del informe <?= e($informe['numero_ficha']) ?>.</p>
                </div>
            </div>

            <form action="actualizar.php" method="POST" id="formInforme" autocomplete="off">
                <?= login_campo_csrf() ?>
                <input type="hidden" name="id_informe" value="<?= (int)$idInforme ?>">

                <section class="form-section">
                    <h5 class="section-title"><i class="bi bi-person-vcard"></i>Datos generales</h5>
                    <hr>
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo" id="titulo" class="form-control" maxlength="180" required value="<?= e($informe['titulo']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ficha psicológica</label>
                            <input type="text" class="form-control" value="<?= e($informe['numero_ficha']) ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" id="fecha" class="form-control" value="<?= e($informe['fecha']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estudiante</label>
                            <input type="text" class="form-control" value="<?= e(trim($informe['apellidos'] . ' ' . $informe['nombres'])) ?>" readonly>
                            <input type="hidden" name="id_estudiante" value="<?= (int)$informe['id_estudiante'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Curso</label>
                            <input type="text" class="form-control" value="<?= e($informe['curso'] . ' ' . $informe['paralelo']) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="numero_atenciones" class="form-label">N.º de atenciones</label>
                            <input type="number" name="numero_atenciones" id="numero_atenciones" class="form-control" value="<?= (int)$informe['numero_atenciones'] ?>" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="referido_por" class="form-label">Referido por</label>
                            <input type="text" name="referido_por" id="referido_por" class="form-control" value="<?= e($informe['referido_por']) ?>" maxlength="150">
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h5 class="section-title"><i class="bi bi-clipboard2-pulse"></i>Datos de la atención</h5>
                    <hr>
                    <label class="form-label d-block">Tipo de atención <span class="text-danger">*</span></label>
                    <div class="row g-3 mb-4">
                        <?php foreach ($opcionesAtencion as $indice => $opcion): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="form-check border rounded-3 p-3 h-100">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="tipo_atencion[]" value="<?= e($opcion) ?>" id="tipo_<?= $indice ?>" <?= in_array($opcion, $tipos, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tipo_<?= $indice ?>"><?= e($opcion) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                            <textarea name="motivo" id="motivo" class="form-control" rows="5" maxlength="5000" required><?= e($informe['motivo']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="diagnostico" class="form-label">Diagnóstico</label>
                            <textarea name="diagnostico" id="diagnostico" class="form-control" rows="5" maxlength="5000"><?= e($informe['diagnostico']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h5 class="section-title"><i class="bi bi-person-lines-fill"></i>Síntesis psicológica</h5>
                    <hr>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="aspecto_cognitivo" class="form-label">Aspecto madurativo y/o cognitivo <span class="text-danger">*</span></label>
                            <textarea name="aspecto_cognitivo" id="aspecto_cognitivo" class="form-control" rows="6" maxlength="5000" required><?= e($informe['aspecto_cognitivo']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="aspectos_afectivos" class="form-label">Aspectos afectivos <span class="text-danger">*</span></label>
                            <textarea name="aspectos_afectivos" id="aspectos_afectivos" class="form-control" rows="6" maxlength="5000" required><?= e($informe['aspectos_afectivos']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h5 class="section-title"><i class="bi bi-check2-square"></i>Acuerdos y recepción</h5>
                    <hr>
                    <label for="diagnostico_acuerdos" class="form-label">Diagnóstico, acuerdos y compromisos</label>
                    <textarea name="diagnostico_acuerdos" id="diagnostico_acuerdos" class="form-control mb-4" rows="5" maxlength="5000"><?= e($informe['diagnostico_acuerdos']) ?></textarea>
                    <label for="recomendaciones" class="form-label">Recomendaciones y sugerencias</label>
                    <textarea name="recomendaciones" id="recomendaciones" class="form-control mb-4" rows="5" maxlength="5000"><?= e($informe['recomendaciones']) ?></textarea>
                    <label for="recibido_por" class="form-label">Recibido por</label>
                    <input type="text" name="recibido_por" id="recibido_por" class="form-control mb-4" value="<?= e($informe['recibido_por']) ?>" maxlength="150">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select">
                        <?php foreach (INFORME_ESTADOS as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= $informe['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </section>

                <div class="d-flex justify-content-end gap-2 mb-5">
                    <a href="ver.php?id=<?= (int)$idInforme ?>" class="btn btn-light border">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
