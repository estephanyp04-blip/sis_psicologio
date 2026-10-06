<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/mis_derivaciones.php');

require_once '../config/conexion.php';

//  consultar las derivaciones registradas por el docente actual.
if (!isset($_SESSION['id_usuario']) || ($_SESSION['id_rol'] ?? 0) != 3) {
    header('Location: ../index.php');
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

$id_docente = (int)($_SESSION['id_docente'] ?? 0);

// Paso 14: obtener las derivaciones del docente para mostrarlas con su estado.
$sql = "SELECT d.*, e.nombres AS estudiante_nombres, e.apellidos AS estudiante_apellidos, e.ci AS estudiante_ci,
               e.curso, e.paralelo
        FROM derivaciones d
        LEFT JOIN estudiantes e ON e.id_estudiante = d.id_estudiante
        WHERE d.id_docente = ?
        ORDER BY d.fecha_registro DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_docente);
$stmt->execute();
$resultado = $stmt->get_result();
$derivaciones = [];

while ($fila = $resultado->fetch_assoc()) {
    $derivaciones[] = $fila;
}

$stmt->close();
?>

<div class="main-content">
    <nav class="breadcrumb mb-3">
        Inicio &gt; Derivaciones &gt; <strong>Mis derivaciones</strong>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Mis derivaciones</h2>
            <p class="text-muted">Consulte el estado de las derivaciones registradas por usted.</p>
        </div>
        <a href="registrar.php" class="btn btn-primary">Nueva derivación</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Materia</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($derivaciones)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Aún no registró derivaciones.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($derivaciones as $derivacion) { ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($derivacion['estudiante_apellidos'] . ', ' . $derivacion['estudiante_nombres']); ?></strong><br>
                                <small class="text-muted">CI: <?= htmlspecialchars($derivacion['estudiante_ci']); ?></small>
                            </td>
                            <td><?= htmlspecialchars($derivacion['materia'] ?? 'No registrada'); ?></td>
                            <td>
                                <?php $prioridad = $derivacion['prioridad'] ?? 'Media'; ?>
                                <span class="badge bg-<?= $prioridad === 'Alta' ? 'danger' : ($prioridad === 'Baja' ? 'success' : 'warning text-dark') ?>"><?= htmlspecialchars($prioridad); ?></span>
                            </td>
                            <td>
                                <?php
                                $badge = 'secondary';
                                if ($derivacion['estado'] === 'Pendiente') {
                                    $badge = 'warning';
                                } elseif (in_array($derivacion['estado'], ['Atendido', 'Finalizado'], true)) {
                                    $badge = 'success';
                                } elseif ($derivacion['estado'] === 'En atención') {
                                    $badge = 'primary';
                                } elseif ($derivacion['estado'] === 'Rechazado') {
                                    $badge = 'danger';
                                }
                                ?>
                                <span class="badge bg-<?= $badge; ?>"><?= htmlspecialchars($derivacion['estado']); ?></span>
                            </td>
                            <td><?= !empty($derivacion['fecha']) ? date('d/m/Y', strtotime($derivacion['fecha'])) : '-' ?></td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="ver.php?id=<?= (int) $derivacion['id_derivacion'] ?>" class="btn btn-sm btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                                    <?php if ($derivacion['estado'] === 'Pendiente'): ?>
                                        <a href="editar.php?id=<?= (int) $derivacion['id_derivacion'] ?>" class="btn btn-sm btn-outline-warning" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                        <form action="eliminar.php" method="POST" onsubmit="return confirm('¿Eliminar esta derivación pendiente?');">
                                            <?= login_campo_csrf() ?>
                                            <input type="hidden" name="id_derivacion" value="<?= (int) $derivacion['id_derivacion'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
