<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/listar.php');

require_once '../config/conexion.php';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Consultar todas las citas con datos del estudiante
$sql = "SELECT c.id_cita, c.fecha, c.hora,
               c.estado, c.observaciones,
               e.nombres, e.apellidos,
               e.curso, e.paralelo
        FROM citas c
        INNER JOIN estudiantes e 
            ON c.id_estudiante = e.id_estudiante
        ORDER BY c.fecha DESC, c.hora ASC";

$resultado = $conexion->query($sql);
$total_citas = $resultado->num_rows;
?>

<div class="main-content">
    <?php if (!empty($_SESSION['mensaje_cita'])): ?>
        <div class="alert alert-danger"><?= login_html($_SESSION['mensaje_cita']) ?></div>
        <?php unset($_SESSION['mensaje_cita']); ?>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav class="breadcrumb mb-3">
        <span>Inicio</span> &gt; 
        <strong>Citas</strong>
    </nav>
    

    <!-- Barra superior -->
    <div class="card p-4 mb-4">
        <div class="row g-3 align-items-center">

            <!-- Buscador -->
            <div class="col-md-4">
                <input type="text"
                       id="buscador"
                       class="form-control"
                       placeholder="Buscar por estudiante...">
            </div>

            <!-- Filtro estado -->
            <div class="col-md-2">
                <select class="form-select" 
                        id="filtroEstado">
                    <option value="">Todos los estados</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="Atendida">Atendida</option>
                    <option value="Cancelada">Cancelada</option>
                    <option value="Reprogramada">Reprogramada</option>
                </select>
            </div>

            <!-- Filtro fecha -->
            <div class="col-md-2">
                <input type="date"
                       id="filtroFecha"
                       class="form-control">
            </div>

        </div>

        <div class="mt-3">
            <a href="registrar.php" 
               class="btn btn-primary">
                + Nueva Cita
            </a>
        </div>
    </div>

    <!-- Mensaje éxito -->
    <?php if(isset($_GET['exito'])): ?>
    <div class="alert alert-success 
                alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i>
        <?php
        if($_GET['exito'] == 1)
            echo " Cita registrada correctamente";
        if($_GET['exito'] == 2)
            echo " Cita actualizada correctamente";
        if($_GET['exito'] == 3)
            echo " Cita cancelada correctamente";
        ?>
        <button type="button" 
                class="btn-close"
                data-bs-dismiss="alert">
        </button>
    </div>
    <?php endif; ?>

    <!-- Mensaje error -->
    <?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger 
                alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php
        if($_GET['error'] == 1)
            echo " Ya existe una cita en ese horario";
        if($_GET['error'] == 2)
            echo " No se pudo procesar la solicitud";
        ?>
        <button type="button" 
                class="btn-close"
                data-bs-dismiss="alert">
        </button>
    </div>
    <?php endif; ?>

    <!-- Tabla -->
    <div class="card">
        <table class="table align-middle"
               id="tablaCitas">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Estudiante</th>
                    <th>Curso</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                    <th class="text-center">
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($resultado->num_rows > 0):
                    $numero = 1;
                    while($fila = 
                    $resultado->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $numero++ ?></td>
                    <td>
                        <?= htmlspecialchars(
                            $fila['nombres'] . ' ' . 
                            $fila['apellidos']) ?>
                    </td>
                    <td>
                        <?= $fila['curso'] ?>° 
                        <?= $fila['paralelo'] ?>
                    </td>
                    <td>
                        <?= date('d/m/Y', 
                            strtotime($fila['fecha'])) ?>
                    </td>
                    <td>
                        <?= date('H:i', 
                            strtotime($fila['hora'])) ?>
                    </td>
                    <td>
                        <?php
                        switch($fila['estado']){
                            case 'Pendiente':
                                echo '<span class="badge bg-warning text-dark">Pendiente</span>';
                                break;
                            case 'Atendida':
                                echo '<span class="badge bg-success">Atendida</span>';
                                break;
                            case 'Cancelada':
                                echo '<span class="badge bg-danger">Cancelada</span>';
                                break;
                            case 'Reprogramada':
                                echo '<span class="badge bg-info">Reprogramada</span>';
                                break;
                        }
                        ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $fila['observaciones'] ?? '—') ?>
                    </td>
                    <td class="text-center">
                        <a href="editar.php?id=<?= $fila['id_cita'] ?>"
                           class="btn btn-sm btn-warning"
                           title="Editar">✏</a>
                        <form action="cancelar.php" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Está segura de cancelar esta cita?')">
                            <?= login_campo_csrf() ?>
                            <input type="hidden" name="id_cita" value="<?= (int) $fila['id_cita'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Cancelar">❌</button>
                        </form>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="8" 
                        class="text-center py-4 text-muted">
                        No hay citas registradas
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pie -->
    <div class="mt-3">
        <span>
            Mostrando 
            <strong><?= $total_citas ?></strong> 
            citas en total
        </span>
    </div>

</div>

<!-- JavaScript -->
<script>
// Buscador
document.getElementById('buscador')
.addEventListener('keyup', function(){
    filtrarTabla();
});

// Filtro estado
document.getElementById('filtroEstado')
.addEventListener('change', function(){
    filtrarTabla();
});

// Filtro fecha
document.getElementById('filtroFecha')
.addEventListener('change', function(){
    filtrarTabla();
});

function filtrarTabla(){
    let texto = document.getElementById('buscador')
        .value.toLowerCase();
    let estado = document.getElementById('filtroEstado')
        .value.toLowerCase();
    let fecha = document.getElementById('filtroFecha')
        .value;

    let filas = document.querySelectorAll(
        '#tablaCitas tbody tr');

    filas.forEach(function(fila){
        let contenido = fila.textContent.toLowerCase();
        let mostrar = true;

        if(texto && !contenido.includes(texto)){
            mostrar = false;
        }
        if(estado && !contenido.includes(estado)){
            mostrar = false;
        }

        if(fecha){
            let fechaFila = fila.cells[3].textContent.trim();
            let partesFecha = fechaFila.split('/');
            let fechaFormateada = partesFecha.length === 3
                ? `${partesFecha[2]}-${partesFecha[1]}-${partesFecha[0]}`
                : '';

            if(fechaFormateada !== fecha){
                mostrar = false;
            }
        }

        fila.style.display = mostrar ? '' : 'none';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
