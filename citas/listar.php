<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/listar.php');

require_once __DIR__ . '/../config/conexion.php';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Consultar todas las citas con datos del estudiante
$sql = "SELECT c.cod_cita, c.fecha, c.hora,
               c.estado, c.observaciones,
               e.nombres, e.apellidos,
               e.curso, e.paralelo
        FROM citas c
        INNER JOIN vista_estudiantes e
            ON c.cod_estudiante = e.cod_estudiante
        ORDER BY c.fecha DESC, c.hora ASC";

$resultado = $conexion->query($sql);
$total_citas = $resultado->num_rows;
$rolUsuarioSesion = (int) ($_SESSION['cod_rol'] ?? 0);
$idUsuarioSesion = (int) ($_SESSION['cod_usuario'] ?? 0);
$solicitudesSql = "SELECT d.cod_derivacion,d.fecha_registro,d.prioridad,
        e.nombres,e.apellidos,ve.curso,ve.paralelo,
        COALESCE(d.autor_nombres,pd.nombres) AS docente_nombres,COALESCE(d.autor_apellidos,pd.apellidos) AS docente_apellidos,
        pu.nombres AS psicologa_nombres,pu.apellidos AS psicologa_apellidos
    FROM derivaciones d
    INNER JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante
    LEFT JOIN vista_estudiantes ve ON ve.cod_estudiante=e.cod_estudiante
    LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
    LEFT JOIN usuarios pd ON pd.cod_usuario=COALESCE(d.cod_usuario_autor,doc.cod_usuario)
    LEFT JOIN usuarios pu ON pu.cod_usuario=d.cod_psicologa_solicitada

    WHERE d.solicitar_cita=1
      AND NOT EXISTS (SELECT 1 FROM citas c WHERE c.cod_derivacion=d.cod_derivacion AND c.estado<>'Cancelada')";
if ($rolUsuarioSesion === 2) {
    $solicitudesSql .= " AND (d.cod_psicologa_solicitada IS NULL OR d.cod_psicologa_solicitada=?)";
}
$solicitudesSql .= " ORDER BY FIELD(d.prioridad,'Alta','Media','Baja'),d.fecha_registro ASC,d.cod_derivacion ASC";
$stmtSolicitudes = $conexion->prepare($solicitudesSql);
if ($rolUsuarioSesion === 2) {
    $stmtSolicitudes->bind_param('i', $idUsuarioSesion);
}
$stmtSolicitudes->execute();
$solicitudesPendientes = $stmtSolicitudes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSolicitudes->close();
?>

<main class="main-content">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="h3 mb-1">Citas</h1><p class="text-muted mb-0">Consulta la agenda y administra los horarios de atención.</p></div>
        <?php if (login_puede('citas/registrar.php')): ?><a class="btn btn-primary" href="registrar.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva cita</a><?php endif; ?>
    </header>
    <?php if(login_puede('index.php')): ?><a class="btn btn-outline-primary mb-3" href="<?= login_html(login_url('index.php').'#agenda-calendario') ?>">Calendario del panel principal</a><?php endif; ?>
    <?php if ($solicitudesPendientes): ?>
    <section class="card border-warning shadow-sm mb-4">
        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h5 mb-0"><i class="bi bi-calendar2-plus me-2"></i>Solicitudes de cita pendientes</h2>
            <span class="badge bg-warning text-dark"><?= count($solicitudesPendientes) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Estudiante</th><th>Curso</th><th>Docente</th><th>Psicóloga solicitada</th><th>Prioridad</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($solicitudesPendientes as $solicitud): ?>
                    <tr>
                        <td><?= login_html(trim($solicitud['apellidos'] . ' ' . $solicitud['nombres'])) ?></td>
                        <td><?= login_html(trim(($solicitud['curso'] ?? '') . ' ' . ($solicitud['paralelo'] ?? ''))) ?></td>
                        <td><?= login_html(trim(($solicitud['docente_apellidos'] ?? '') . ' ' . ($solicitud['docente_nombres'] ?? ''))) ?></td>
                        <td><?= login_html(trim(($solicitud['psicologa_nombres'] ?? '') . ' ' . ($solicitud['psicologa_apellidos'] ?? '')) ?: 'Sin asignar') ?></td>
                        <td><?= login_html($solicitud['prioridad']) ?></td>
                        <td><a class="btn btn-sm btn-primary" href="registrar.php?cod_derivacion=<?= (int)$solicitud['cod_derivacion'] ?>">Programar cita</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

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
                <label class="form-label" for="buscador">Buscar estudiante</label>
                <input type="text"
                       id="buscador"
                       class="form-control"
                       placeholder="Buscar por estudiante...">
            </div>

            <!-- Filtro estado -->
            <div class="col-md-2">
                <label class="form-label" for="filtroEstado">Estado</label>
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
                <label class="form-label" for="filtroFecha">Fecha de la cita</label>
                <input type="date"
                       id="filtroFecha"
                       class="form-control">
            </div>

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
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de citas">
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
                        <?php if (login_puede('citas/editar.php')): ?><a href="editar.php?id=<?= $fila['cod_cita'] ?>"
                           class="btn btn-sm btn-outline-secondary"
                           title="Editar cita"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a><?php endif; ?>
                        <?php if (login_puede('citas/cancelar.php')): ?>
                        <form action="cancelar.php" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Está segura de cancelar esta cita?')">
                            <?= login_campo_csrf() ?>
                            <input type="hidden" name="cod_cita" value="<?= (int) $fila['cod_cita'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar cita"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar</button>
                        </form>
                        <?php endif; ?>
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
    </div>

    <!-- Pie -->
    <div class="mt-3">
        <span>
            Mostrando
            <strong><?= $total_citas ?></strong>
            citas en total
        </span>
    </div>

</main>

<!-- JavaScript -->
<script>
// Buscador
document.getElementById('buscador')
.addEventListener('input', function(){
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
        if (fila.cells.length < 8) return;
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
