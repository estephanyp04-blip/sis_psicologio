<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/autenticacion.php';
$baseUrl = login_config()['base_url'];

/** Avisos visibles para el trabajo diario; no se guardan ni modifican datos. */
function navbar_notificaciones(mysqli $bd, int $rol, int $usuario): array
{
    $avisos = [];
    if (in_array($rol, [1, 2], true) && login_puede('derivaciones/listar.php')) {
        $derivaciones = $bd->query("SELECT d.cod_derivacion,d.fecha,d.prioridad,e.nombres,e.apellidos
            FROM derivaciones d INNER JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante
            WHERE d.estado='Pendiente' ORDER BY FIELD(d.prioridad,'Alta','Media','Baja'),d.fecha,d.cod_derivacion LIMIT 5")
            ->fetch_all(MYSQLI_ASSOC);
        foreach ($derivaciones as $derivacion) {
            $avisos[] = [
                'tipo' => 'derivacion', 'fecha' => $derivacion['fecha'],
                'titulo' => 'Derivación pendiente' . ($derivacion['prioridad'] === 'Alta' ? ' · prioridad alta' : ''),
                'detalle' => trim($derivacion['apellidos'] . ' ' . $derivacion['nombres']),
                'url' => login_url('derivaciones/ver.php?id=' . (int)$derivacion['cod_derivacion']),
            ];
        }
    }
    if (login_puede('citas/listar.php')) {
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime('+7 days'));
        $sql = "SELECT c.cod_cita,c.fecha,c.hora,e.nombres,e.apellidos
            FROM citas c INNER JOIN estudiantes e ON e.cod_estudiante=c.cod_estudiante
            WHERE c.estado IN ('Pendiente','Reprogramada') AND c.fecha BETWEEN ? AND ?";
        if ($rol === 2) $sql .= ' AND c.cod_psicologa=?';
        $sql .= ' ORDER BY c.fecha,c.hora,c.cod_cita LIMIT 5';
        $stmt = $bd->prepare($sql);
        if ($rol === 2) $stmt->bind_param('ssi', $desde, $hasta, $usuario);
        else $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $cita) {
            $avisos[] = [
                'tipo' => 'cita', 'fecha' => $cita['fecha'] . ' ' . substr($cita['hora'], 0, 5),
                'titulo' => $cita['fecha'] === $desde ? 'Cita de hoy' : 'Próxima cita',
                'detalle' => trim($cita['apellidos'] . ' ' . $cita['nombres']) . ' · ' . substr($cita['hora'], 0, 5),
                'url' => login_url('citas/editar.php?id=' . (int)$cita['cod_cita']),
            ];
        }
        $stmt->close();
    }
    usort($avisos, static fn(array $a, array $b): int => strcmp($a['fecha'], $b['fecha']));
    return array_slice($avisos, 0, 8);
}

$notificaciones = [];
try {
    $notificaciones = navbar_notificaciones(login_bd(), (int)($_SESSION['cod_rol'] ?? 0), (int)($_SESSION['cod_usuario'] ?? 0));
} catch (Throwable $error) {
    error_log('Notificaciones: ' . $error->getMessage());
}
?>
<!-- Barra superior -->
<nav class="navbar navbar-top">
    <div class="navbar-top-content d-flex align-items-center justify-content-between w-100">
        <button class="btn btn-menu-toggle" type="button" data-sidebar-toggle aria-controls="sidebar-navigation" aria-expanded="false" aria-label="Abrir menú de navegación">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <span class="fw-bold text-primary navbar-system-title">
            <i class="bi bi-heart-pulse"></i>
            Sistema Psicológico Estudiantil
        </span>
        <div class="navbar-user-actions d-flex align-items-center gap-3">
            <div class="dropdown navbar-notificaciones">
                <button class="btn btn-light navbar-boton-campana" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= count($notificaciones) ? count($notificaciones) . ' notificaciones' : 'No hay notificaciones' ?>">
                    <i class="bi bi-bell-fill" aria-hidden="true"></i><?php if ($notificaciones): ?><span class="navbar-notificaciones-contador"><?= count($notificaciones) ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end navbar-notificaciones-menu">
                    <div class="navbar-notificaciones-titulo"><strong>Notificaciones</strong><span><?= count($notificaciones) ? count($notificaciones) . ' pendientes' : 'Al día' ?></span></div>
                    <?php foreach ($notificaciones as $notificacion): ?>
                    <a class="dropdown-item navbar-notificacion" href="<?= login_html($notificacion['url']) ?>">
                        <i class="bi <?= $notificacion['tipo'] === 'derivacion' ? 'bi-send-fill' : 'bi-calendar-event-fill' ?>" aria-hidden="true"></i>
                        <span><strong><?= login_html($notificacion['titulo']) ?></strong><small><?= login_html($notificacion['detalle']) ?></small></span>
                    </a>
                    <?php endforeach; ?>
                    <?php if (!$notificaciones): ?>
                    <div class="navbar-notificaciones-vacio"><i class="bi bi-check-circle" aria-hidden="true"></i> No hay derivaciones pendientes ni citas en los próximos 7 días.</div>
                    <?php endif; ?>
                    <?php if (login_puede('citas/listar.php') || login_puede('derivaciones/listar.php')): ?>
                    <div class="navbar-notificaciones-pie"><?php if (login_puede('citas/listar.php')): ?><a href="<?= login_html(login_url('citas/listar.php')) ?>">Ver agenda</a><?php endif; ?><?php if (login_puede('derivaciones/listar.php')): ?><a href="<?= login_html(login_url('derivaciones/listar.php')) ?>">Ver derivaciones</a><?php endif; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <span class="text-muted navbar-user-name">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <form action="<?= login_html(login_url('cerrar_sesion.php')) ?>" method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= login_html(login_token()) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary navbar-logout" aria-label="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</nav>
