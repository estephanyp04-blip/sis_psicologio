<?php
require_once '../config/conexion.php';

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

        <!-- Información Personal -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5>Información Personal</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" name="codigo" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="ci">CI</label>
                        <input type="text" id="ci" name="ci" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="nombres">Nombres</label>
                        <input type="text" id="nombres" name="nombres" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="genero">Género</label>
                        <select id="genero" name="genero" class="form-select" required>
                            <option value="" selected disabled>Seleccione...</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Femenino">Femenino</option>
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
                            <option value="" selected disabled>Seleccione un curso</option>
                            <option value="1">1°</option>
                            <option value="2">2°</option>
                            <option value="3">3°</option>
                            <option value="4">4°</option>
                            <option value="5">5°</option>
                            <option value="6">6°</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="paralelo">Paralelo</label>
                        <select id="paralelo" class="form-select" name="paralelo" required>
                            <option value="" selected disabled>Seleccione un paralelo</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="turno">Turno</label>
                        <select id="turno" name="turno" class="form-select" required>
                            <option value="" selected disabled>Seleccione un turno</option>
                            <option value="Mañana">Mañana</option>
                            <option value="Tarde">Tarde</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-select" required>
                            <option value="" selected disabled>Seleccione un estado</option>
                            <option value="Activo">Activo</option>
                            <option value="En seguimiento">En seguimiento</option>
                            <option value="Baja">Baja</option>
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
                        <input type="text" id="padre" name="padre" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="madre">Nombre de la Madre</label>
                        <input type="text" id="madre" name="madre" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tutor">Tutor</label>
                        <input type="text" id="tutor" name="tutor" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control" minlength="7" maxlength="15">
                    </div>

                    <div class="col-12 mb-3">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion" rows="3" class="form-control"></textarea>
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
