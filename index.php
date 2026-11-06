<?php
require_once __DIR__ . '/includes/autenticacion.php';
requerir_acceso('index.php');
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/estadisticas/datos.php';
require_once __DIR__ . '/citas/datos.php';

$tituloPagina = 'Panel principal';
$panel = null;
$calendario = null;
$errorCalendario = '';
if(login_puede('citas/listar.php')) {
    try { $calendario=cita_calendario_datos($conexion,$_GET); }
    catch(InvalidArgumentException $error) { http_response_code(422);$errorCalendario=$error->getMessage(); }
    catch(Throwable $error) { http_response_code(503);$errorCalendario='No se pudo consultar el calendario. Intente nuevamente.';error_log('Calendario del panel: '.$error->getMessage()); }
}
$urlAgenda = static fn(array $filtros=[]): string => login_url('index.php'.($filtros?'?'.http_build_query($filtros):'')).'#agenda-calendario';
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
                                    <tr data-estudiante="<?= (int)$fila['cod_estudiante'] ?>">
                                        <th scope="row"><div class="panel-persona"><span class="panel-avatar" aria-hidden="true"><?= login_html(mb_strtoupper(mb_substr($fila['nombres'],0,1).mb_substr($fila['apellidos'],0,1))) ?></span>
                                            <span><?= login_html($nombre) ?><small>Código: <?= login_html($fila['codigo']) ?></small></span></div></th>
                                        <td><?= login_html(trim(($fila['curso'] ?? 'Sin curso registrado').' '.($fila['paralelo'] ?? ''))) ?></td>
                                        <td><span class="panel-prioridad panel-prioridad-<?= strtolower($fila['prioridad']) ?>"><?= login_html($fila['prioridad']) ?></span></td>
                                        <td><?= $fila['ultima_sesion'] ? $fechaVisible($fila['ultima_sesion']) : 'Sin sesiones' ?></td>
                                        <td><a class="panel-abrir" aria-label="Ver derivación de <?= login_html($nombre) ?>" href="<?= login_html(login_url('derivaciones/ver.php?id='.(int)$fila['cod_derivacion'])) ?>">Ver <span aria-hidden="true">›</span></a>
                                            <?php if ($fila['cod_historia']): ?><a class="panel-historia" href="<?= login_html(login_url('historias_clinicas/ver.php?id='.(int)$fila['cod_historia'])) ?>">Historia</a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?></tbody>
                            </table>
                        </div>
                        <p class="panel-pie">Mostrando <?= count($panel['prioritarios']) ?> de <?= $r['por_atender'] ?> estudiantes con derivaciones abiertas.</p>
                    <?php endif; ?>
                </section>

                <?php if(login_puede('citas/listar.php')): ?>
                <section class="panel-bloque panel-calendario" id="agenda-calendario" aria-labelledby="titulo-agenda">
                    <header class="panel-bloque-cabecera"><h2 id="titulo-agenda">Agenda de atención</h2><a href="<?= login_html(login_url('citas/listar.php')) ?>">Todas las citas ↗</a></header>
                    <?php if($errorCalendario): ?><p class="alert alert-danger m-3" role="alert"><?= login_html($errorCalendario) ?> <a href="<?= login_html($urlAgenda()) ?>">Volver a hoy</a></p>
                    <?php elseif($calendario): $diaSeleccionado=$calendario['seleccion'];$citasDia=$calendario['por_dia'][$diaSeleccionado]??[]; ?>
                    <?php if(!empty($_SESSION['mensaje_cita_estado'])): ?><p class="alert alert-info m-3" role="status"><?= login_html($_SESSION['mensaje_cita_estado']) ?></p><?php unset($_SESSION['mensaje_cita_estado']);endif; ?>
                    <details class="panel-calendario-mes" <?= isset($_GET['mes']) ? 'open' : '' ?>>
                        <summary>Consultar calendario y otras fechas</summary>
                        <nav class="calendario-navegacion" aria-label="Cambiar mes de la agenda">
                            <a class="btn btn-outline-secondary" aria-label="Mes anterior" href="<?= login_html($urlAgenda(['mes'=>$calendario['anterior']])) ?>">‹</a>
                            <h3><?= login_html($calendario['titulo']) ?></h3>
                            <a class="btn btn-outline-secondary" aria-label="Mes siguiente" href="<?= login_html($urlAgenda(['mes'=>$calendario['siguiente']])) ?>">›</a>
                        </nav>
                        <form method="get" action="<?= login_html(login_url('index.php').'#agenda-calendario') ?>" class="calendario-selector" id="filtro-calendario">
                            <label class="visually-hidden" for="mes">Consultar mes</label><input type="month" id="mes" name="mes" class="form-control" value="<?= login_html($calendario['mes']) ?>" required>
                            <button class="btn btn-primary">Ver mes</button><a class="btn btn-outline-primary" href="<?= login_html($urlAgenda()) ?>">Hoy</a>
                        </form>
                        <div class="calendario-cuadricula" aria-label="<?= login_html($calendario['titulo']) ?>">
                            <?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $nombreDia): ?><strong class="calendario-semana"><?= $nombreDia ?></strong><?php endforeach; ?>
                            <?php for($vacio=1;$vacio<(int)$calendario['inicio']->format('N');$vacio++): ?><span aria-hidden="true"></span><?php endfor; ?>
                            <?php for($numeroDia=1;$numeroDia<=(int)$calendario['inicio']->format('t');$numeroDia++):
                                $fechaDia=$calendario['mes'].'-'.str_pad((string)$numeroDia,2,'0',STR_PAD_LEFT);$cantidadDia=count($calendario['por_dia'][$fechaDia]??[]); ?>
                                <a class="calendario-dia <?= $fechaDia===$diaSeleccionado?'calendario-seleccionado':'' ?> <?= $fechaDia===date('Y-m-d')?'calendario-hoy':'' ?>" href="<?= login_html($urlAgenda(['mes'=>$calendario['mes'],'fecha'=>$fechaDia])) ?>" data-fecha="<?= $fechaDia ?>" <?= $fechaDia===$diaSeleccionado?'aria-current="date"':'' ?> aria-label="<?= login_html($fechaVisible($fechaDia).', '.$cantidadDia.' citas') ?>"><strong><?= $numeroDia ?></strong><small><?= $cantidadDia ?> <span>citas</span></small></a>
                            <?php endfor; ?>
                        </div>
                        <p class="calendario-ayuda">Selecciona un día para ver sus horarios. El conteo incluye las citas canceladas, identificadas en el listado.</p>
                    </details>
                    <div class="calendario-agenda-cabecera"><h3 id="agenda-fecha"><?= $diaSeleccionado===date('Y-m-d')?'Agenda de hoy':'Agenda del día' ?> · <?= $fechaVisible($diaSeleccionado) ?></h3><span><?= count($citasDia) ?> citas</span>
                        <?php if(login_puede('citas/registrar.php') && $diaSeleccionado>=date('Y-m-d')): ?><a class="btn btn-sm btn-primary" href="<?= login_html(login_url('citas/registrar.php?fecha='.$diaSeleccionado)) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva cita</a><?php endif; ?>
                    </div>
                    <?php if(!$citasDia): ?><p class="panel-vacio" role="status">No hay citas programadas para esta fecha.</p><?php else: ?>
                    <ol class="panel-agenda" aria-labelledby="agenda-fecha">
                        <?php foreach($citasDia as $cita): ?><li data-cita="<?= (int)$cita['cod_cita'] ?>">
                            <time datetime="<?= login_html($diaSeleccionado.'T'.$cita['hora']) ?>"><?= substr($cita['hora'],0,5) ?></time>
                            <div class="panel-cita panel-cita-<?= strtolower($cita['estado']) ?>">
                                <?php if(login_puede('citas/editar.php')): ?><a href="<?= login_html(login_url('citas/editar.php?id='.(int)$cita['cod_cita'])) ?>"><?= login_html($cita['nombres'].' '.$cita['apellidos']) ?></a><?php else: ?><strong><?= login_html($cita['nombres'].' '.$cita['apellidos']) ?></strong><?php endif; ?>
                                <span>Estado: <?= login_html($cita['estado']) ?></span><small>Profesional: <?= login_html($cita['profesional']) ?></small>
                                <?php if(login_puede('citas/cancelar.php') && in_array($cita['estado'],['Pendiente','Reprogramada'],true)): ?><details class="calendario-acciones"><summary>Cambiar estado o reprogramar</summary>
                                    <form action="<?= login_html(login_url('citas/cancelar.php')) ?>" method="post" class="row g-2 mt-1">
                                        <?= login_campo_csrf() ?><input type="hidden" name="cod_cita" value="<?= (int)$cita['cod_cita'] ?>"><input type="hidden" name="volver" value="panel"><input type="hidden" name="agenda_mes" value="<?= login_html($calendario['mes']) ?>"><input type="hidden" name="agenda_fecha" value="<?= $diaSeleccionado ?>">
                                        <div class="col-12"><label for="estado-<?= (int)$cita['cod_cita'] ?>" class="form-label">Estado</label><select id="estado-<?= (int)$cita['cod_cita'] ?>" name="estado" class="form-select"><?php foreach(array_unique([$cita['estado'],'Reprogramada','Atendida','Cancelada']) as $estadoCita): ?><option><?= $estadoCita ?></option><?php endforeach; ?></select></div>
                                        <div class="col-12 col-sm-6"><label for="fecha-<?= (int)$cita['cod_cita'] ?>" class="form-label">Fecha</label><input id="fecha-<?= (int)$cita['cod_cita'] ?>" name="fecha" type="date" class="form-control" value="<?= $cita['fecha'] ?>" required></div>
                                        <div class="col-12 col-sm-6"><label for="hora-<?= (int)$cita['cod_cita'] ?>" class="form-label">Hora</label><select id="hora-<?= (int)$cita['cod_cita'] ?>" name="hora" class="form-select"><?php foreach(cita_horas() as $horaCita): ?><option <?= substr($cita['hora'],0,5)===$horaCita?'selected':'' ?>><?= $horaCita ?></option><?php endforeach; ?></select></div>
                                        <div class="col-12"><button class="btn btn-primary btn-sm">Guardar estado/horario</button></div>
                                    </form></details><?php endif; ?>
                            </div>
                        </li><?php endforeach; ?>
                    </ol><?php endif; ?>
                    <?php endif; ?>
                </section><?php endif; ?>
            </div>
            <p class="panel-definiciones">Cada seguimiento guardado cuenta como una sesión. Las citas pendientes incluyen las reprogramadas. Los datos se actualizan al abrir o recargar el panel.</p>
        <?php endif; ?>

    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
