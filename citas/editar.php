<?php
require_once '../config/conexion.php';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Verificar ID
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("Location: listar.php");
    exit();
}

$id = intval($_GET['id']);

// Obtener datos de la cita
$sql = "SELECT c.*, e.nombres, e.apellidos
        FROM citas c
        INNER JOIN estudiantes e
            ON c.id_estudiante = e.id_estudiante
        WHERE c.id_cita = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if($resultado->num_rows == 0){
    header("Location: listar.php");
    exit();
}

$cita = $resultado->fetch_assoc();
?>

<div class="main-content">

    <!-- Breadcrumb -->
    <nav class="breadcrumb mb-3">
        <span>Inicio</span> &gt;
        <a href="listar.php">Citas</a> &gt;
        <strong>Editar Cita</strong>
    </nav>

    <div class="card p-4">

        <h5 class="mb-4">
            <i class="bi bi-calendar-check"></i>
            Editar Cita
        </h5>

        <form action="procesar_editar.php"
              method="POST">

            <input type="hidden"
                   name="id_cita"
                   value="<?= $cita['id_cita'] ?>">

            <div class="row g-3">

                <!-- Estudiante (solo lectura) -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        Estudiante
                    </label>
                    <input type="text"
                           class="form-control"
                           value="<?= htmlspecialchars(
                               $cita['apellidos'] . ', ' . 
                               $cita['nombres']) ?>"
                           readonly>
                </div>

                <!-- Fecha -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        Fecha *
                    </label>
                    <input type="date"
                           name="fecha"
                           class="form-control"
                           value="<?= $cita['fecha'] ?>"
                           required>
                </div>

                <!-- Hora -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        Hora *
                    </label>
                    <select name="hora"
                            class="form-select"
                            required>
                        <?php
                        $inicio = strtotime('07:00');
                        $fin = strtotime('18:00');
                        $intervalo = 30 * 60;

                        for($hora = $inicio;
                            $hora <= $fin;
                            $hora += $intervalo){
                            $horaFormato = date('H:i', $hora);
                            $selected = ($horaFormato == 
                                substr($cita['hora'],0,5)) 
                                ? 'selected' : '';
                            echo "<option value='$horaFormato' 
                                  $selected>$horaFormato</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Estado -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        Estado
                    </label>
                    <select name="estado"
                            class="form-select">
                        <?php
                        $estados = ['Pendiente',
                                    'Atendida',
                                    'Cancelada',
                                    'Reprogramada'];
                        foreach($estados as $e){
                            $sel = ($e == $cita['estado'])
                                   ? 'selected' : '';
                            echo "<option value='$e' $sel>$e</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Observaciones -->
                <div class="col-md-9">
                    <label class="form-label fw-bold">
                        Observaciones
                    </label>
                    <textarea name="observaciones"
                              class="form-control"
                              rows="3">
<?= htmlspecialchars($cita['observaciones'] ?? '') ?>
                    </textarea>
                </div>

            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit"
                        class="btn btn-primary">
                    <i class="bi bi-check-circle"></i>
                    Actualizar Cita
                </button>
                <a href="listar.php"
                   class="btn btn-secondary">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>