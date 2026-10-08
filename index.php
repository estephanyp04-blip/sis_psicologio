<?php
require_once __DIR__ . '/includes/autenticacion.php';
requerir_acceso('index.php');
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/estadisticas/datos.php';

$tituloPagina = 'Panel principal';
$panel = null;
try {
    $panel = estadisticas_panel($conexion);
} catch (Throwable $error) {
    http_response_code(503);
    error_log('Panel principal: ' . $error->getMessage());
}
$fechaVisible = static fn(string $fecha): string => date('d/m/Y', strtotime($fecha));
$nombreUsuario = trim((string)($_SESSION['nombre'] ?? ''));
$accesos = [
    ['estudiantes/listar.php','Estudiantes','Registrar y consultar estudiantes.','blue','bi-people'],
    ['derivaciones/listar.php','Derivaciones','Consultar solicitudes de atención.','orange','bi-send'],
    ['citas/listar.php','Citas','Programar y consultar citas.','green','bi-calendar2-week'],
    ['historias_clinicas/listar.php','Historias clínicas','Revisar historias y seguimientos.','purple','bi-file-earmark-medical'],
    ['docentes/listar.php','Docentes','Administrar docentes y materias.','cyan','bi-person-badge'],
    ['informes/listar.php','Informes','Preparar y consultar informes.','red','bi-file-earmark-bar-graph'],
    ['estadisticas/index.php','Estadísticas','Explorar sesiones por estudiante.','indigo','bi-bar-chart-line'],
];
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/navbar.php';
?>
<main class="main-content dashboard-page">
    <div class="container-fluid">
        <header class="panel-cabecera">
            <div><p class="panel-etiqueta">ATENCIÓN PSICOLÓGICA ESTUDIANTIL</p><h1>Panel principal</h1>
                <p><?= login_html($nombreUsuario) ?> · Resumen de atención y próximas actividades.</p></div>
            <time datetime="<?= date('Y-m-d') ?>"><?= date('d/m/Y') ?></time>
        </header>

        <?php if ($panel === null): ?>
            <div class="alert alert-danger" role="alert">No se pudo cargar el resumen. Intente nuevamente.</div>
        <?php else: ?>
            <?php $r = $panel['resumen'];
            $tarjetas = [
                ['en_seguimiento','Estudiantes en seguimiento',$r['en_seguimiento'],'Estudiantes activos con historia en seguimiento','orange','historias_clinicas/listar.php',
                    '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M19 21v-3a6 6 0 0 0-2-4"/>'],
                ['prioridad_alta','Estudiantes con prioridad alta',$r['prioridad_alta'],'Derivaciones pendientes o en seguimiento','red','derivaciones/listar.php',
                    '<path d="m8 2 8 0 6 6v8l-6 6H8l-6-6V8Z M12 7v6M12 17h.01"/>'],
                ['citas_pendientes','Citas pendientes',$r['citas_pendientes'],'Próximos 7 días · '.$fechaVisible($panel['hoy']).' al '.$fechaVisible($panel['fin_agenda']),'orange','citas/listar.php',
                    '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6M17 2v6M3 11h18M12 14v3l2 1"/>'],
                ['sesiones','Sesiones realizadas',$r['sesiones'],$r['sesiones_recientes'].' en los últimos 30 días','green','estadisticas/index.php',
                    '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'],
            ]; ?>
            <section class="panel-resumen" aria-label="Resumen de atención">
                <?php foreach ($tarjetas as [$clave,$etiqueta,$valor,$detalle,$color,$ruta,$icono]): ?>
                    <a class="panel-tarjeta panel-<?= $color ?>" href="<?= login_html(login_url($ruta)) ?>">
                        <div class="panel-tarjeta-titulo"><h2><?= login_html($etiqueta) ?></h2>
                            <span class="panel-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icono ?></svg></span></div>
                        <strong id="panel-<?= $clave ?>" data-valor="<?= $valor ?>"><?= number_format($valor,0,',','.') ?></strong>
                        <p><?= login_html($detalle) ?></p>
                    </a>
                <?php endforeach; ?>
            </section>

            <div class="panel-contenido">
                <section class="panel-bloque" aria-labelledby="titulo-prioritarios">
                    <header class="panel-bloque-cabecera"><h2 id="titulo-prioritarios">Seguimiento prioritario</h2>
                        <a href="<?= login_html(login_url('derivaciones/listar.php')) ?>">Ver derivaciones <span aria-hidden="true">↗</span></a></header>
                    <p class="panel-contexto">Estudiantes activos con derivaciones abiertas. Primero la prioridad más alta y quienes llevan más tiempo sin sesión. La prioridad corresponde a la derivación.</p>
                    <?php if (!$panel['prioritarios']): ?>
                        <p class="panel-vacio" role="status">No hay estudiantes con derivaciones pendientes o en seguimiento.</p>
                    <?php else: ?>
                        <div class="panel-tabla-scroll" tabindex="0" role="region" aria-label="Estudiantes con atención prioritaria">
                            <table class="panel-tabla">
                                <thead><tr><th scope="col">Estudiante</th><th scope="col">Curso actual</th><th scope="col">Prioridad</th><th scope="col">Última sesión</th><th scope="col">Consultar</th></tr></thead>
                                <tbody><?php foreach ($panel['prioritarios'] as $fila):
                                    $nombre = trim($fila['nombres'].' '.$fila['apellidos']); ?>
                                    <tr data-estudiante="<?= (int)$fila['id_estudiante'] ?>">
                                        <th scope="row"><div class="panel-persona"><span class="panel-avatar" aria-hidden="true"><?= login_html(mb_strtoupper(mb_substr($fila['nombres'],0,1).mb_substr($fila['apellidos'],0,1))) ?></span>
                                            <span><?= login_html($nombre) ?><small>Código: <?= login_html($fila['codigo']) ?></small></span></div></th>
                                        <td><?= login_html(trim(($fila['curso'] ?? 'Sin curso registrado').' '.($fila['paralelo'] ?? ''))) ?></td>
                                        <td><span class="panel-prioridad panel-prioridad-<?= strtolower($fila['prioridad']) ?>"><?= login_html($fila['prioridad']) ?></span></td>
                                        <td><?= $fila['ultima_sesion'] ? $fechaVisible($fila['ultima_sesion']) : 'Sin sesiones' ?></td>
                                        <td><a class="panel-abrir" aria-label="Ver derivación de <?= login_html($nombre) ?>" href="<?= login_html(login_url('derivaciones/ver.php?id='.(int)$fila['id_derivacion'])) ?>">Ver <span aria-hidden="true">›</span></a>
                                            <?php if ($fila['id_historia']): ?><a class="panel-historia" href="<?= login_html(login_url('historias_clinicas/ver.php?id='.(int)$fila['id_historia'])) ?>">Historia</a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?></tbody>
                            </table>
                        </div>
                        <p class="panel-pie">Mostrando <?= count($panel['prioritarios']) ?> de <?= $r['por_atender'] ?> estudiantes con derivaciones abiertas.</p>
                    <?php endif; ?>
                </section>

                <section class="panel-bloque" aria-labelledby="titulo-agenda">
                    <header class="panel-bloque-cabecera"><h2 id="titulo-agenda">Agenda de hoy</h2>
                        <a href="<?= login_html(login_url('citas/listar.php')) ?>">Ver todas las citas <span aria-hidden="true">↗</span></a></header>
                    <p class="panel-contexto"><?= $fechaVisible($panel['hoy']) ?> · <?= count($panel['agenda']) ?> citas, sin canceladas.</p>
                    <?php if (!$panel['agenda']): ?>
                        <p class="panel-vacio" role="status">No hay citas programadas para hoy.</p>
                    <?php else: ?>
                        <ol class="panel-agenda">
                            <?php foreach ($panel['agenda'] as $cita): ?>
                                <li data-cita="<?= (int)$cita['id_cita'] ?>">
                                    <time datetime="<?= login_html($panel['hoy'].'T'.$cita['hora']) ?>"><?= substr($cita['hora'],0,5) ?></time>
                                    <div class="panel-cita panel-cita-<?= strtolower($cita['estado']) ?>">
                                        <a href="<?= login_html(login_url('citas/editar.php?id='.(int)$cita['id_cita'])) ?>"><?= login_html($cita['nombres'].' '.$cita['apellidos']) ?></a>
                                        <span><?= login_html($cita['estado']) ?></span><small>Profesional: <?= login_html($cita['profesional']) ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            </div>
            <p class="panel-definiciones">Cada seguimiento guardado cuenta como una sesión. Las citas pendientes incluyen las reprogramadas. Los datos se actualizan al abrir o recargar el panel.</p>
        <?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
