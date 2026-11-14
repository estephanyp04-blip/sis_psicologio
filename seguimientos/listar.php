<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/listar.php');
require_once __DIR__ . '/../config/conexion.php';

$idEstudiante = filter_var($_GET['estudiante'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($idEstudiante === false) { http_response_code(400); exit('Seleccione un estudiante válido.'); }
$estudiantes = $conexion->query("SELECT e.cod_estudiante,e.nombres,e.apellidos,COUNT(h.cod_historia) sesiones
    FROM estudiantes e INNER JOIN historias_clinicas h ON h.cod_estudiante=e.cod_estudiante
    GROUP BY e.cod_estudiante,e.nombres,e.apellidos ORDER BY e.apellidos,e.nombres")->fetch_all(MYSQLI_ASSOC);
$sesiones = [];
$nombre = '';
foreach ($estudiantes as $estudiante) {
    if ((int)$estudiante['cod_estudiante'] === $idEstudiante) $nombre = trim($estudiante['apellidos'] . ' ' . $estudiante['nombres']);
}
if ($idEstudiante && $nombre === '') { http_response_code(404); exit('El estudiante no tiene historias clínicas registradas.'); }
if ($idEstudiante) {
    $stmt = $conexion->prepare("SELECT h.cod_historia,h.fecha_apertura,h.cod_cita_origen,h.situacion_escolar,h.valoracion_familiar,
        h.impresion_diagnostica,h.plan_intervencion,h.estado,
        CONCAT_WS(' ',u.nombres,u.apellidos) profesional,
        COALESCE(indicadores.riesgo,0) riesgo,COALESCE(indicadores.socioemocionales,0) socioemocionales
        FROM historias_clinicas h LEFT JOIN usuarios u ON u.cod_usuario=h.cod_psicologa
        LEFT JOIN (
            SELECT ho.cod_historia,
                SUM(g.codigo='conductas_riesgo') riesgo,
                SUM(g.codigo='dificultades_socioemocionales') socioemocionales
            FROM historia_opciones ho INNER JOIN opciones_historia o ON o.cod_opcion=ho.cod_opcion
            INNER JOIN grupos_opciones_historia g ON g.cod_grupo=o.cod_grupo GROUP BY ho.cod_historia
        ) indicadores ON indicadores.cod_historia=h.cod_historia
        WHERE h.cod_estudiante=? ORDER BY h.fecha_apertura,h.cod_historia");
    $stmt->bind_param('i', $idEstudiante);
    $stmt->execute();
    $sesiones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$tituloPagina = 'Seguimiento del avance';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid">
    <h1>Seguimiento del avance</h1>
    <p class="text-muted">Cada sesión corresponde a una historia clínica registrada durante una atención. Compare las valoraciones del estudiante a lo largo de sus sesiones.</p>
    <form method="get" class="card card-body mb-4">
        <label for="estudiante" class="form-label">Estudiante con historia clínica</label>
        <div class="d-flex flex-wrap gap-2">
            <select id="estudiante" name="estudiante" class="form-select" style="max-width:650px" required>
                <option value="">Seleccione un estudiante...</option>
                <?php foreach ($estudiantes as $estudiante): ?>
                <option value="<?= (int)$estudiante['cod_estudiante'] ?>" <?= $idEstudiante === (int)$estudiante['cod_estudiante'] ? 'selected' : '' ?>><?= login_html(trim($estudiante['apellidos'] . ' ' . $estudiante['nombres'])) ?> · <?= (int)$estudiante['sesiones'] ?> sesiones</option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Ver avance</button>
        </div>
    </form>
    <?php if ($sesiones): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h4"><?= login_html($nombre) ?></h2><p class="mb-0"><strong><?= count($sesiones) ?> sesiones</strong> · Primera atención: <?= login_html(date('d/m/Y', strtotime($sesiones[0]['fecha_apertura']))) ?> · Última atención: <?= login_html(date('d/m/Y', strtotime($sesiones[count($sesiones)-1]['fecha_apertura']))) ?></p></div>
        <?php if (login_puede('historias_clinicas/registrar.php')): ?><a class="btn btn-primary" href="../historias_clinicas/registrar.php?cod_estudiante=<?= $idEstudiante ?>">Nueva sesión: historia clínica</a><?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table table-bordered table-hover align-middle">
        <caption>Valoraciones e indicadores registrados por sesión. Las cantidades de indicadores no representan un porcentaje de recuperación.</caption>
        <thead><tr><th>Sesión / fecha</th><th>Cita</th><th>Situación escolar</th><th>Valoración familiar</th><th>Indicadores de riesgo</th><th>Dificultades socioemocionales</th><th>Impresión diagnóstica / plan</th><th>Profesional</th><th>Historia clínica</th></tr></thead>
        <tbody><?php foreach ($sesiones as $indice => $sesion): ?>
            <tr data-historia="<?= (int)$sesion['cod_historia'] ?>">
                <th scope="row">Sesión <?= $indice + 1 ?><small class="d-block"><?= login_html(date('d/m/Y', strtotime($sesion['fecha_apertura']))) ?></small></th>
                <td><?= $sesion['cod_cita_origen'] ? '#' . (int)$sesion['cod_cita_origen'] : 'Sin cita vinculada' ?></td>
                <td><?= login_html($sesion['situacion_escolar'] ?: 'Sin valoración') ?></td>
                <td><?= login_html($sesion['valoracion_familiar'] ?: 'Sin valoración') ?></td>
                <td><?= (int)$sesion['riesgo'] ?> seleccionados</td>
                <td><?= (int)$sesion['socioemocionales'] ?> seleccionadas</td>
                <td><strong>Impresión:</strong> <?= nl2br(login_html($sesion['impresion_diagnostica'] ?: 'Sin registro')) ?><br><strong>Plan:</strong> <?= nl2br(login_html($sesion['plan_intervencion'] ?: 'Sin registro')) ?></td>
                <td><?= login_html($sesion['profesional']) ?></td>
                <td><a href="../historias_clinicas/ver.php?id=<?= (int)$sesion['cod_historia'] ?>">Ver historia #<?= (int)$sesion['cod_historia'] ?></a></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php elseif (!$estudiantes): ?>
    <p class="alert alert-info">Todavía no hay historias clínicas registradas.</p>
    <?php else: ?>
    <p class="alert alert-info">Seleccione un estudiante para consultar su avance entre sesiones.</p>
    <?php endif; ?>
</div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
