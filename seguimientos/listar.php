<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/listar.php');
require_once __DIR__ . '/../config/conexion.php';

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$buscar = trim((string)($_GET['buscar'] ?? ''));
$errores = [];
foreach (['desde' => $desde, 'hasta' => $hasta] as $campo => $fecha) {
    if ($fecha === '') continue;
    $objeto = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
    if (!$objeto || $objeto->format('Y-m-d') !== $fecha) $errores[] = 'Seleccione fechas válidas.';
}
if ($desde !== '' && $hasta !== '' && $desde > $hasta) $errores[] = 'La fecha inicial debe ser anterior a la fecha final.';
if (mb_strlen($buscar) > 100) $errores[] = 'La búsqueda admite hasta 100 caracteres.';

$seguimientos = [];
if (!$errores) {
    $sql = "SELECT s.cod_seguimiento,s.fecha,s.descripcion,s.proxima_sesion,
            h.cod_historia,h.estado AS estado_historia,e.cod_estudiante,e.codigo,e.nombres,e.apellidos,
            CONCAT(u.nombres,' ',u.apellidos) AS profesional
        FROM seguimientos s
        INNER JOIN historias_clinicas h ON h.cod_historia=s.cod_historia
        INNER JOIN estudiantes e ON e.cod_estudiante=h.cod_estudiante
        INNER JOIN usuarios u ON u.cod_usuario=s.cod_psicologa
        WHERE (?='' OR s.fecha>=?) AND (?='' OR s.fecha<=?)
          AND (?='' OR CONCAT(e.apellidos,' ',e.nombres,' ',e.codigo) LIKE ?)
        ORDER BY s.fecha DESC,s.cod_seguimiento DESC LIMIT 200";
    $stmt = $conexion->prepare($sql);
    $patron = '%' . $buscar . '%';
    $stmt->bind_param('ssssss', $desde, $desde, $hasta, $hasta, $buscar, $patron);
    $stmt->execute();
    $seguimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$tituloPagina = 'Seguimientos psicológicos';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content">
    <div class="container-fluid">
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Seguimientos</li>
        </ol></nav>
        <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1>Seguimientos psicológicos</h1>
                <p class="text-muted mb-0">Consulte las atenciones registradas desde la historia clínica de cada estudiante.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= login_html(login_url('historias_clinicas/listar.php')) ?>">Abrir historias clínicas</a>
        </header>
        <?php foreach (array_unique($errores) as $error): ?>
            <div class="alert alert-danger" role="alert"><?= login_html($error) ?></div>
        <?php endforeach; ?>
        <form method="get" action="<?= login_html(login_url('seguimientos/listar.php')) ?>" class="card card-body mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4"><label class="form-label" for="buscar">Estudiante o código</label><input class="form-control" id="buscar" name="buscar" value="<?= login_html($buscar) ?>" maxlength="100"></div>
                <div class="col-md-4 col-lg-3"><label class="form-label" for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" value="<?= login_html($desde) ?>"></div>
                <div class="col-md-4 col-lg-3"><label class="form-label" for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="<?= login_html($hasta) ?>"></div>
                <div class="col-md-4 col-lg-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= login_html(login_url('seguimientos/listar.php')) ?>">Limpiar</a></div>
            </div>
        </form>
        <?php if (!$seguimientos): ?>
            <div class="alert alert-light border" role="status">No hay seguimientos que coincidan con estos filtros.</div>
        <?php else: ?>
            <p class="text-muted">Se muestran hasta 200 atenciones, de la más reciente a la más antigua.</p>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Fecha</th><th>Estudiante</th><th>Profesional</th><th>Historia</th><th>Próxima sesión prevista</th><th>Detalle</th></tr></thead>
                    <tbody><?php foreach ($seguimientos as $seguimiento): ?>
                        <tr>
                            <td><?= login_html(date('d/m/Y', strtotime($seguimiento['fecha']))) ?></td>
                            <th scope="row"><?= login_html(trim($seguimiento['apellidos'] . ' ' . $seguimiento['nombres'])) ?><small class="d-block text-muted">Código: <?= login_html($seguimiento['codigo']) ?></small></th>
                            <td><?= login_html($seguimiento['profesional']) ?></td>
                            <td><a href="<?= login_html(login_url('historias_clinicas/ver.php?id=' . (int)$seguimiento['cod_historia'])) ?>"><?= login_html($seguimiento['estado_historia']) ?></a></td>
                            <td><?= $seguimiento['proxima_sesion'] ? login_html(date('d/m/Y', strtotime($seguimiento['proxima_sesion']))) : 'Sin fecha prevista' ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?= login_html(login_url('seguimientos/ver.php?id=' . (int)$seguimiento['cod_seguimiento'])) ?>">Ver atención</a></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
