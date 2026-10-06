<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('informes/listar.php');

require_once '../config/conexion.php';

$rolActual = (int) ($_SESSION['id_rol'] ?? 0);
$rolesPermitidos = [1, 2, 4];

if (!in_array($rolActual, $rolesPermitidos, true)) {
    header('Location: ../index.php');
    exit;
}

$puedeGestionar = in_array($rolActual, [1, 2], true);
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$sql = "SELECT
            i.id_informe,
            i.numero_ficha,
            i.fecha,
            i.numero_atenciones,
            i.tipo_atencion,
            i.referido_por,
            i.estado,
            e.id_estudiante,
            e.nombres,
            e.apellidos,
            e.curso,
            e.paralelo,
            u.nombre AS nombre_psicologo,
            u.apellido AS apellido_psicologo
        FROM informes i
        INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
        INNER JOIN usuarios u ON i.id_usuario = u.id_usuario
        ORDER BY i.fecha DESC, i.id_informe DESC";

$resultado = $conexion->query($sql);

if (!$resultado) {
    die('Error al consultar los informes: ' . htmlspecialchars($conexion->error));
}

$sqlEstadisticas = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN estado = 'Borrador' THEN 1 ELSE 0 END) AS borradores,
    SUM(CASE WHEN estado = 'Finalizado' THEN 1 ELSE 0 END) AS finalizados
    FROM informes";

$resultadoEstadisticas = $conexion->query($sqlEstadisticas);

$estadisticas = [
    'total' => 0,
    'borradores' => 0,
    'finalizados' => 0
];

if ($resultadoEstadisticas) {
    $datosEstadisticas = $resultadoEstadisticas->fetch_assoc();

    if ($datosEstadisticas) {
        $estadisticas = array_merge($estadisticas, $datosEstadisticas);
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <nav class="breadcrumb mb-4">
        <span class="breadcrumb-item">
            <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
        </span>
        <span class="breadcrumb-item active">Informes</span>
    </nav>

    <div class="historias-hero">
        <div>
            <span>Departamento de Psicología</span>
            <h1>Informes psicológicos</h1>
            <p>Gestión de fichas psicológicas de los estudiantes.</p>
        </div>

        <?php if ($puedeGestionar): ?>
            <a href="registrar.php" class="btn btn-light">
                <i class="bi bi-plus-circle me-2"></i>
                Nuevo informe
            </a>
        <?php endif; ?>
    </div>

    <div class="historias-resumen">

        <article>
            <div class="resumen-icono total">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div>
                <strong><?= (int)$estadisticas['total']; ?></strong>
                <span>Total de informes</span>
            </div>
        </article>

        <article>
            <div class="resumen-icono pendiente">
                <i class="bi bi-file-earmark"></i>
            </div>
            <div>
                <strong><?= (int)$estadisticas['borradores']; ?></strong>
                <span>Borradores</span>
            </div>
        </article>

        <article>
            <div class="resumen-icono atendida">
                <i class="bi bi-check-circle"></i>
            </div>
            <div>
                <strong><?= (int)$estadisticas['finalizados']; ?></strong>
                <span>Finalizados</span>
            </div>
        </article>

    </div>

    <div class="derivaciones-filtros">

        <div class="filtro-busqueda">
            <i class="bi bi-search"></i>
            <input type="text" id="buscarInforme" placeholder="Buscar por estudiante o ficha..." autocomplete="off">
        </div>

        <select id="filtroCurso" class="form-select">
            <option value="">Todos los cursos</option>
            <option value="1">1ro</option>
            <option value="2">2do</option>
            <option value="3">3ro</option>
            <option value="4">4to</option>
            <option value="5">5to</option>
            <option value="6">6to</option>
        </select>

        <select id="filtroEstado" class="form-select">
            <option value="">Todos los estados</option>
            <option value="Borrador">Borrador</option>
            <option value="Finalizado">Finalizado</option>
        </select>

    </div>

    <div class="derivaciones-tabla-card">

        <div class="tabla-titulo">
            <div>
                <h2>Fichas psicológicas</h2>
                <p>Informes registrados en el sistema</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 derivaciones-tabla informes-tabla" id="tablaInformes">

                <thead>
                    <tr>
                        <th>Ficha</th>
                        <th>Estudiante</th>
                        <th>Curso</th>
                        <th>Fecha</th>
                        <th>Atenciones</th>
                        <th>Tipo de atención</th>
                        <th>Psicólogo/a</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($resultado->num_rows > 0): ?>

                    <?php while ($informe = $resultado->fetch_assoc()): ?>

                        <?php
                        $nombreEstudiante = trim($informe['apellidos'] . ' ' . $informe['nombres']);
                        $nombrePsicologo = trim($informe['nombre_psicologo'] . ' ' . $informe['apellido_psicologo']);
                        $cursoCompleto = trim($informe['curso'] . ' ' . $informe['paralelo']);
                        $tipoAtencion = $informe['tipo_atencion'] ?: 'No especificado';

                        $estadoClase = 'estado-neutro';

                        if ($informe['estado'] === 'Borrador') {
                            $estadoClase = 'estado-pendiente';
                        } elseif ($informe['estado'] === 'Finalizado') {
                            $estadoClase = 'estado-atendido';
                        }
                        ?>

                        <tr
                            data-estudiante="<?= htmlspecialchars(strtolower($nombreEstudiante)); ?>"
                            data-ficha="<?= htmlspecialchars(strtolower($informe['numero_ficha'])); ?>"
                            data-curso="<?= htmlspecialchars($informe['curso']); ?>"
                            data-estado="<?= htmlspecialchars($informe['estado']); ?>">

                            <td data-label="Ficha">
                                <strong><?= htmlspecialchars($informe['numero_ficha']); ?></strong>
                            </td>

                            <td data-label="Estudiante">
                                <div class="estudiante-celda">
                                    <div class="avatar-estudiante">
                                        <?= htmlspecialchars(strtoupper(substr($informe['nombres'], 0, 1))); ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($nombreEstudiante); ?></strong>
                                        <small>ID: <?= (int)$informe['id_estudiante']; ?></small>
                                    </div>
                                </div>
                            </td>

                            <td data-label="Curso">
                                <span class="curso-chip">
                                    <?= htmlspecialchars($cursoCompleto ?: '—'); ?>
                                </span>
                            </td>

                            <td data-label="Fecha">
                                <div class="fecha-celda">
                                    <i class="bi bi-calendar3"></i>
                                    <?= !empty($informe['fecha']) ? date('d/m/Y', strtotime($informe['fecha'])) : '—'; ?>
                                </div>
                            </td>

                            <td data-label="Atenciones">
                                <strong><?= (int)$informe['numero_atenciones']; ?></strong>
                            </td>

                            <td data-label="Tipo de atención">
                                <span class="text-muted">
                                    <?= htmlspecialchars($tipoAtencion); ?>
                                </span>
                            </td>

                            <td data-label="Psicólogo/a">
                                <?= htmlspecialchars($nombrePsicologo); ?>
                            </td>

                            <td data-label="Estado">
                                <span class="estado-chip <?= $estadoClase; ?>">
                                    <i></i>
                                    <?= htmlspecialchars($informe['estado']); ?>
                                </span>
                            </td>

                            <td data-label="Acciones" class="text-end">
                                <div class="acciones-tabla">

                                    <a
                                        href="ver.php?id=<?= (int)$informe['id_informe']; ?>"
                                        class="btn-accion"
                                        title="Ver informe">
                                        <i class="bi bi-eye"></i>
                                        <span>Ver</span>
                                    </a>

                                    <?php if ($puedeGestionar): ?>
                                        <a
                                            href="editar.php?id=<?= (int)$informe['id_informe']; ?>"
                                            class="btn-accion editar"
                                            title="Editar informe">
                                            <i class="bi bi-pencil"></i>
                                            <span>Editar</span>
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        href="imprimir.php?id=<?= (int)$informe['id_informe']; ?>"
                                        class="btn-accion"
                                        title="Imprimir informe"
                                        target="_blank">
                                        <i class="bi bi-printer"></i>
                                        <span>Imprimir</span>
                                    </a>

                                </div>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="estado-vacio">
                            <i class="bi bi-file-earmark-text"></i>
                            <strong>No hay informes registrados</strong>
                            <span>Los informes psicológicos aparecerán aquí cuando sean registrados.</span>
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscar = document.getElementById('buscarInforme');
    const filtroCurso = document.getElementById('filtroCurso');
    const filtroEstado = document.getElementById('filtroEstado');
    const filas = document.querySelectorAll('#tablaInformes tbody tr[data-estudiante]');

    function aplicarFiltros() {
        const texto = buscar.value.toLowerCase().trim();
        const curso = filtroCurso.value.toLowerCase().trim();
        const estado = filtroEstado.value.toLowerCase().trim();

        filas.forEach(function(fila) {
            const estudiante = fila.dataset.estudiante || '';
            const ficha = fila.dataset.ficha || '';
            const filaCurso = fila.dataset.curso || '';
            const filaEstado = (fila.dataset.estado || '').toLowerCase();

            const coincideBusqueda =
                !texto ||
                estudiante.includes(texto) ||
                ficha.includes(texto);

            const coincideCurso =
                !curso || filaCurso === curso;

            const coincideEstado =
                !estado || filaEstado === estado;

            fila.style.display =
                coincideBusqueda && coincideCurso && coincideEstado
                    ? ''
                    : 'none';
        });
    }

    buscar.addEventListener('input', aplicarFiltros);
    filtroCurso.addEventListener('change', aplicarFiltros);
    filtroEstado.addEventListener('change', aplicarFiltros);
});
</script>

<?php include '../includes/footer.php'; ?>
