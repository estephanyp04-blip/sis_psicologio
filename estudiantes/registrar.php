<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/registrar.php');

require_once '../config/conexion.php';

$datosEstudiante = $_SESSION['datos_estudiante'] ?? [];
$mensajeRegistro = $_SESSION['mensaje'] ?? '';
unset($_SESSION['datos_estudiante'], $_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">

    <!-- Breadcrumb -->
    <nav class="breadcrumb mb-3">
        Inicio > Estudiantes > <strong>Registrar</strong>
    </nav>

    <!-- Encabezado -->
    <div class="mb-4">
        <h2>Registrar estudiante</h2>
        <p class="text-muted">Complete la información del estudiante.</p>
    </div>

    <?php
    $erroresRegistro = [
        'datos' => 'Complete todos los campos obligatorios con valores válidos.',
        'fecha' => 'La fecha de nacimiento no es válida.',
        'duplicado' => 'El código o CI ya está registrado.',
        'guardar' => 'No se pudo guardar el estudiante. Intente nuevamente.',
    ];
    $errorRegistro = $_GET['error'] ?? '';
    if (isset($erroresRegistro[$errorRegistro])):
    ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($erroresRegistro[$errorRegistro], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form action="guardar.php" method="POST">
        <?php if ($mensajeRegistro !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($mensajeRegistro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?= login_campo_csrf() ?>

        <!-- Información Personal -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5>Información Personal</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" name="codigo" class="form-control" required value="<?= htmlspecialchars((string)($datosEstudiante['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="ci">CI</label>
                        <input type="text" id="ci" name="ci" class="form-control" required value="<?= htmlspecialchars((string)($datosEstudiante['ci'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="nombres">Nombres</label>
                        <input type="text" id="nombres" name="nombres" class="form-control" required value="<?= htmlspecialchars((string)($datosEstudiante['nombres'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" required value="<?= htmlspecialchars((string)($datosEstudiante['apellidos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" required value="<?= htmlspecialchars((string)($datosEstudiante['fecha_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="genero">Género</label>
                        <select id="genero" name="genero" class="form-select" required>
                            <option value="" disabled <?= ($datosEstudiante['genero'] ?? '') === '' ? 'selected' : '' ?>>Seleccione...</option>
                            <option value="Masculino" <?= ($datosEstudiante['genero'] ?? '') === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="Femenino" <?= ($datosEstudiante['genero'] ?? '') === 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información Académica -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5>Información Académica</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="curso">Curso</label>
                        <select id="curso" class="form-select" name="curso" required>
                            <option value="" disabled <?= ($datosEstudiante['curso'] ?? '') === '' ? 'selected' : '' ?>>Seleccione un curso</option>
                            <option value="1" <?= ($datosEstudiante['curso'] ?? '') === '1' ? 'selected' : '' ?>>1°</option>
                            <option value="2" <?= ($datosEstudiante['curso'] ?? '') === '2' ? 'selected' : '' ?>>2°</option>
                            <option value="3" <?= ($datosEstudiante['curso'] ?? '') === '3' ? 'selected' : '' ?>>3°</option>
                            <option value="4" <?= ($datosEstudiante['curso'] ?? '') === '4' ? 'selected' : '' ?>>4°</option>
                            <option value="5" <?= ($datosEstudiante['curso'] ?? '') === '5' ? 'selected' : '' ?>>5°</option>
                            <option value="6" <?= ($datosEstudiante['curso'] ?? '') === '6' ? 'selected' : '' ?>>6°</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="paralelo">Paralelo</label>
                        <select id="paralelo" class="form-select" name="paralelo" required>
                            <option value="" disabled <?= ($datosEstudiante['paralelo'] ?? '') === '' ? 'selected' : '' ?>>Seleccione un paralelo</option>
                            <option value="A" <?= ($datosEstudiante['paralelo'] ?? '') === 'A' ? 'selected' : '' ?>>A</option>
                            <option value="B" <?= ($datosEstudiante['paralelo'] ?? '') === 'B' ? 'selected' : '' ?>>B</option>
                            <option value="C" <?= ($datosEstudiante['paralelo'] ?? '') === 'C' ? 'selected' : '' ?>>C</option>
                            <option value="D" <?= ($datosEstudiante['paralelo'] ?? '') === 'D' ? 'selected' : '' ?>>D</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="turno">Turno</label>
                        <select id="turno" name="turno" class="form-select" required>
                            <option value="" disabled <?= ($datosEstudiante['turno'] ?? '') === '' ? 'selected' : '' ?>>Seleccione un turno</option>
                            <option value="Mañana" <?= ($datosEstudiante['turno'] ?? '') === 'Mañana' ? 'selected' : '' ?>>Mañana</option>
                            <option value="Tarde" <?= ($datosEstudiante['turno'] ?? '') === 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-select" required>
                            <option value="" disabled <?= ($datosEstudiante['estado'] ?? '') === '' ? 'selected' : '' ?>>Seleccione un estado</option>
                            <option value="Activo" <?= ($datosEstudiante['estado'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Retirado" <?= ($datosEstudiante['estado'] ?? '') === 'Retirado' ? 'selected' : '' ?>>Retirado</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información Familiar -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5>Información Familiar</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="padre">Nombre del Padre</label>
                        <input type="text" id="padre" name="padre" class="form-control" value="<?= htmlspecialchars((string)($datosEstudiante['padre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="madre">Nombre de la Madre</label>
                        <input type="text" id="madre" name="madre" class="form-control" value="<?= htmlspecialchars((string)($datosEstudiante['madre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tutor">Tutor</label>
                        <input type="text" id="tutor" name="tutor" class="form-control" value="<?= htmlspecialchars((string)($datosEstudiante['tutor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control" minlength="7" maxlength="15" value="<?= htmlspecialchars((string)($datosEstudiante['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-12 mb-3">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion" rows="3" class="form-control"><?= htmlspecialchars((string)($datosEstudiante['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="listar.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar estudiante</button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
