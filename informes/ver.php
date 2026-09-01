<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$modoDesarrollo = !isset($_SESSION['id_rol']);
$rolActual = (int)($_SESSION['id_rol'] ?? 0);
if (!$modoDesarrollo && !in_array($rolActual, [1, 2, 4], true)) {
    header('Location: ../index.php');
    exit;
}

$puedeEditar = $modoDesarrollo || in_array($rolActual, [1, 2], true);

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dato($valor, string $vacio = 'No registrado'): string
{
    $valor = trim((string)$valor);
    return $valor === ''
        ? '<span class="text-muted">' . e($vacio) . '</span>'
        : nl2br(e($valor));
}

function fechaInforme($valor): string
{
    if (!$valor) {
        return 'No registrada';
    }

    $fecha = strtotime((string)$valor);
    return $fecha ? date('d/m/Y', $fecha) : 'No registrada';
}

$idInforme = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idInforme) {
    $_SESSION['mensaje'] = 'Informe no válido.';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: listar.php');
    exit;
}

$stmt = $conexion->prepare("
    SELECT
        i.*,
        e.codigo,
        e.ci,
        e.nombres,
        e.apellidos,
        e.fecha_nacimiento,
        e.curso AS estudiante_curso,
        e.paralelo AS estudiante_paralelo,
        u.nombre AS psicologo_nombre,
        u.apellido AS psicologo_apellido
    FROM informes i
    INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
    LEFT JOIN usuarios u ON u.id_usuario = i.elaborado_por
    WHERE i.id_informe = ?
    LIMIT 1
");
$stmt->bind_param('i', $idInforme);
$stmt->execute();
$informe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$informe) {
    $_SESSION['mensaje'] = 'El informe solicitado no existe.';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: listar.php');
    exit;
}

$nombreEstudiante = trim($informe['nombres'] . ' ' . $informe['apellidos']);
$nombrePsicologo = trim(($informe['psicologo_nombre'] ?? '') . ' ' . ($informe['psicologo_apellido'] ?? ''));
$estadoClase = [
    'Borrador' => 'bg-warning-subtle text-warning-emphasis',
    'Emitido' => 'bg-success-subtle text-success-emphasis',
    'Anulado' => 'bg-danger-subtle text-danger-emphasis',
][$informe['estado']] ?? 'bg-secondary-subtle text-secondary-emphasis';

$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$tituloPagina = 'Informe ' . $informe['numero_ficha'];
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <nav aria-label="breadcrumb" class="d-print-none mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="listar.php">Informes</a></li>
                <li class="breadcrumb-item active"><?= e($informe['numero_ficha']) ?></li>
            </ol>
        </nav>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show d-print-none" role="alert">
                <?= nl2br(e($mensaje)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <header class="expediente-head">
            <div class="expediente-identidad">
                <span class="expediente-avatar"><?= e(mb_strtoupper(mb_substr($informe['nombres'], 0, 1))) ?></span>
                <div>
                    <small>INFORME PSICOLÓGICO</small>
                    <h1><?= e($nombreEstudiante) ?></h1>
                    <p><?= e($informe['estudiante_curso'] . ' ' . $informe['estudiante_paralelo']) ?></p>
                </div>
            </div>
            <div class="expediente-acciones d-print-none">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="bi bi-printer me-2"></i>Imprimir
                </button>
                <?php if ($puedeEditar): ?>
                    <a href="editar.php?id=<?= (int)$idInforme ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-2"></i>Editar
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <section class="expediente-meta">
            <div><span>Ficha</span><strong><?= e($informe['numero_ficha']) ?></strong></div>
            <div><span>Fecha</span><strong><?= e(fechaInforme($informe['fecha'])) ?></strong></div>
            <div><span>Estado</span><strong><span class="badge <?= e($estadoClase) ?>"><?= e($informe['estado']) ?></span></strong></div>
            <div><span>Psicólogo/a</span><strong><?= e($nombrePsicologo ?: 'Sin asignar') ?></strong></div>
        </section>

        <section class="expediente-seccion">
            <div class="expediente-seccion-titulo"><span>1</span><div><h2>Datos generales</h2><p>Información del estudiante y atención</p></div></div>
            <div class="datos-clinicos-grid">
                <div><span>Nombre completo</span><strong><?= e($nombreEstudiante) ?></strong></div>
                <div><span>Código</span><strong><?= dato($informe['codigo']) ?></strong></div>
                <div><span>C.I.</span><strong><?= dato($informe['ci']) ?></strong></div>
                <div><span>Curso</span><strong><?= e($informe['estudiante_curso'] . ' ' . $informe['estudiante_paralelo']) ?></strong></div>
                <div><span>Fecha del informe</span><strong><?= e(fechaInforme($informe['fecha'])) ?></strong></div>
                <div><span>N.º de atenciones</span><strong><?= (int)$informe['numero_atenciones'] ?></strong></div>
                <div><span>Referido por</span><strong><?= dato($informe['referido_por']) ?></strong></div>
                <div><span>Recibido por</span><strong><?= dato($informe['recibido_por']) ?></strong></div>
            </div>
        </section>

        <section class="expediente-seccion">
            <div class="expediente-seccion-titulo"><span>2</span><div><h2>Datos de la atención</h2></div></div>
            <div class="datos-clinicos-grid">
                <div><span>Tipo de atención</span><strong><?= dato($informe['tipo_atencion']) ?></strong></div>
            </div>
            <div class="texto-clinico mt-3"><strong>Motivo</strong><p><?= dato($informe['motivo']) ?></p></div>
            <div class="texto-clinico mt-3"><strong>Diagnóstico</strong><p><?= dato($informe['diagnostico']) ?></p></div>
        </section>

        <section class="expediente-seccion">
            <div class="expediente-seccion-titulo"><span>3</span><div><h2>Síntesis psicológica</h2></div></div>
            <div class="texto-clinico"><strong>Aspecto madurativo y/o cognitivo</strong><p><?= dato($informe['aspecto_cognitivo']) ?></p></div>
            <div class="texto-clinico mt-3"><strong>Aspectos afectivos</strong><p><?= dato($informe['aspectos_afectivos']) ?></p></div>
        </section>

        <section class="expediente-seccion">
            <div class="expediente-seccion-titulo"><span>4</span><div><h2>Acuerdos y recomendaciones</h2></div></div>
            <div class="texto-clinico"><strong>Diagnóstico, acuerdos y compromisos</strong><p><?= dato($informe['diagnostico_acuerdos']) ?></p></div>
            <div class="texto-clinico mt-3"><strong>Recomendaciones y sugerencias</strong><p><?= dato($informe['recomendaciones']) ?></p></div>
        </section>

        <div class="d-flex justify-content-end gap-2 mb-5 d-print-none">
            <a href="listar.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Volver a informes</a>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>