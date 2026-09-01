<?php
require_once '../config/conexion.php';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

/* Obtener estudiantes activos */
$sqlEstudiantes = "
    SELECT id_estudiante, nombres, apellidos, curso, paralelo
    FROM estudiantes
    WHERE estado = 'Activo'
    ORDER BY apellidos ASC, nombres ASC
";

$estudiantes = $conexion->query($sqlEstudiantes);

/* Obtener una psicóloga activa */
$sqlPsi = "
    SELECT id_usuario
    FROM usuarios
    WHERE id_rol = 2
      AND estado = 'Activo'
    ORDER BY nombre ASC
    LIMIT 1
";

$resPsi = $conexion->query($sqlPsi);

$psicologa = $resPsi && $resPsi->num_rows > 0
    ? $resPsi->fetch_assoc()
    : null;
?>

<div class="main-content">

    <nav class="breadcrumb mb-3">
        <span>Inicio</span> &gt;
        <a href="listar.php">Citas</a> &gt;
        <strong>Nueva Cita</strong>
    </nav>

    <div class="card p-4">

        <h5 class="mb-4">
            <i class="bi bi-calendar-plus"></i>
            Registrar Nueva Cita
        </h5>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>

                <?php if ($_GET['error'] == 1): ?>
                    Ya existe una cita programada en ese horario.
                <?php elseif ($_GET['error'] == 2): ?>
                    Complete los campos obligatorios correctamente.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['exito'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                La cita fue registrada correctamente.
            </div>
        <?php endif; ?>

            <form action="procesar_registrar.php" method="POST">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Estudiante *
                        </label>

                        <select
                            name="id_estudiante"
                            class="form-select"
                            required>

                            <option value="">
                                Seleccione un estudiante
                            </option>

                            <?php while ($est = $estudiantes->fetch_assoc()): ?>
                                <option value="<?= $est['id_estudiante']; ?>">
                                    <?= htmlspecialchars(
                                        $est['apellidos'] . ', ' .
                                        $est['nombres'] . ' — ' .
                                        $est['curso'] . '° ' .
                                        $est['paralelo']
                                    ); ?>
                                </option>
                            <?php endwhile; ?>

                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            Fecha *
                        </label>

                        <input
                            type="date"
                            name="fecha"
                            class="form-control"
                            min="<?= date('Y-m-d'); ?>"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            Hora *
                        </label>

                        <select
                            name="hora"
                            class="form-select"
                            required>

                            <option value="">
                                Seleccione hora
                            </option>

                            <?php
                            $inicio = strtotime('07:00');
                            $fin = strtotime('18:00');

                            for (
                                $hora = $inicio;
                                $hora <= $fin;
                                $hora += 30 * 60
                            ) {
                                $horaFormato = date('H:i', $hora);
                            ?>
                                <option value="<?= $horaFormato; ?>">
                                    <?= $horaFormato; ?>
                                </option>
                            <?php } ?>

                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            Estado *
                        </label>

                        <select
                            name="estado"
                            class="form-select"
                            required>

                            <option value="Pendiente">Pendiente</option>
                            <option value="Atendida">Atendida</option>
                            <option value="Reprogramada">Reprogramada</option>

                        </select>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label fw-bold">
                            Observaciones
                        </label>

                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            placeholder="Observaciones adicionales..."></textarea>
                    </div>

                    <input
                        type="hidden"
                        name="id_usuario"
                        value="<?= (int) ($psicologa['id_usuario'] ?? 0); ?>">

                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i>
                        Guardar cita
                    </button>

                    <a href="listar.php" class="btn btn-secondary">
                        Cancelar
                    </a>
                </div>

            </form>

    </div>
</div>

<?php include '../includes/footer.php'; ?>