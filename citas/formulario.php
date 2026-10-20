<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/datos.php';
$id = isset($editarCita) ? (int)filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) : 0;
$cita = $id ? flujo_fila($conexion, 'SELECT c.*,CONCAT(p.nombres,\' \',p.apellidos) profesional
    FROM citas c
    LEFT JOIN usuarios u ON u.cod_usuario=c.cod_psicologa
    LEFT JOIN personas p ON p.cod_persona=u.cod_persona
    WHERE c.cod_cita=?', [$id]) : null;
if (isset($editarCita) && !$cita) { header('Location: listar.php'); exit; }
$datos = $cita ?? ['cod_estudiante' => 0,'cod_derivacion' => 0,'cod_psicologa' => 0,'fecha' => date('Y-m-d'),'hora' => '', 'estado' => 'Pendiente','observaciones' => ''];
$idDerivacionOrigen = $cita ? (int) ($cita['cod_derivacion'] ?? 0) : (int) filter_var($_GET['cod_derivacion'] ?? 0, FILTER_VALIDATE_INT);
$origen = null;
if (!$cita) {
    if ($idDerivacionOrigen) {
        $origen = flujo_fila($conexion, 'SELECT d.cod_estudiante,d.cod_derivacion,d.solicitar_cita,d.cod_psicologa_solicitada,
            CONCAT(e.nombres,\' \',e.apellidos) AS estudiante,
            CONCAT(p.nombres,\' \',p.apellidos) AS psicologa_solicitada
            FROM derivaciones d
            INNER JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante
            LEFT JOIN usuarios u ON u.cod_usuario=d.cod_psicologa_solicitada
            LEFT JOIN personas p ON p.cod_persona=u.cod_persona
            WHERE d.cod_derivacion=?', [$idDerivacionOrigen]);
        if (!$origen) { http_response_code(404); exit('Derivación no encontrada.'); }
        $datos = array_replace($datos, $origen, [
            'cod_psicologa' => (int) ($origen['cod_psicologa_solicitada'] ?? 0),
            'observaciones' => (int) ($origen['solicitar_cita'] ?? 0) === 1
                ? 'Solicitud de cita recibida desde la derivacion #' . $idDerivacionOrigen
                : '',
        ]);
    }
}
$recuperados = $_SESSION['datos_cita'] ?? [];
if ((int)($recuperados['cod_cita'] ?? 0) === $id) $datos = array_replace($datos, $recuperados);
$mensaje = $_SESSION['mensaje_cita'] ?? '';
unset($_SESSION['datos_cita'], $_SESSION['mensaje_cita']);
$estudiantes = $conexion->query("SELECT cod_estudiante,CONCAT(apellidos,', ',nombres) nombre FROM estudiantes WHERE estado='Activo' ORDER BY apellidos,nombres")->fetch_all(MYSQLI_ASSOC);
$profesionales = $conexion->query("SELECT u.cod_usuario,CONCAT(p.nombres,' ',p.apellidos) nombre
    FROM usuarios u INNER JOIN personas p ON p.cod_persona=u.cod_persona
    WHERE u.cod_rol=2 AND u.estado='Activo' ORDER BY p.apellidos,p.nombres")->fetch_all(MYSQLI_ASSOC);
$idUsuarioSesion = (int) ($_SESSION['cod_usuario'] ?? 0);
$rolUsuarioSesion = (int) ($_SESSION['cod_rol'] ?? 0);
$idPsicologaSeleccionada = (int) ($datos['cod_psicologa'] ?? 0);
if (!$cita && $rolUsuarioSesion === 2) $idPsicologaSeleccionada = $idUsuarioSesion;
$derivaciones = $conexion->query("SELECT d.cod_derivacion,d.cod_estudiante,d.estado,CONCAT(e.apellidos,', ',e.nombres) nombre
    FROM derivaciones d JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante
    ORDER BY d.fecha DESC,d.cod_derivacion DESC")->fetch_all(MYSQLI_ASSOC);
$tituloPagina = $id ? 'Editar cita' : 'Nueva cita';
include __DIR__ . '/../includes/header.php'; include __DIR__ . '/../includes/sidebar.php'; include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid">
<h1><?= login_html($tituloPagina) ?></h1>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= login_html($mensaje) ?></div><?php endif; ?>
<?php if (isset($_GET['exito'])): ?><div class="alert alert-success">Cita guardada correctamente.</div><?php endif; ?>
<?php if ($origen && (int) ($origen['solicitar_cita'] ?? 0) === 1): ?>
<div class="alert alert-info"><strong>Solicitud recibida.</strong> La docente pidió una cita para <?= login_html($origen['estudiante']) ?>.
<?php if (!empty($origen['psicologa_solicitada'])): ?> Psicóloga solicitada: <?= login_html($origen['psicologa_solicitada']) ?>.<?php endif; ?></div>
<?php endif; ?>
<div class="card p-4">
<p>Responsable: <?= login_html($cita ? ($cita['profesional'] ?? 'Sin responsable registrado') : ($_SESSION['nombre'] ?? 'Usuario de la sesión')) ?>.</p>
<form id="form-cita" method="POST" action="<?= $id ? 'procesar_editar.php' : 'procesar_registrar.php' ?>">
<?= login_campo_csrf() ?>
<?php if ($id): ?><input type="hidden" name="cod_cita" value="<?= $id ?>"><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><label for="estudiante" class="form-label">Estudiante</label>
<select id="estudiante" name="cod_estudiante" class="form-select" required <?= $id ? 'disabled' : '' ?>>
<option value="">Seleccione</option>
<?php foreach ($estudiantes as $e): ?><option value="<?= (int)$e['cod_estudiante'] ?>" <?= (int)$datos['cod_estudiante']===(int)$e['cod_estudiante'] ? 'selected' : '' ?>><?= login_html($e['nombre']) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-6"><label for="derivacion" class="form-label">Derivación de origen</label>
<select id="derivacion" name="cod_derivacion" class="form-select" <?= $id ? 'disabled' : '' ?>>
<option value="0">Sin derivación</option>
<?php foreach ($derivaciones as $d): ?><option value="<?= (int)$d['cod_derivacion'] ?>" data-estudiante="<?= (int)$d['cod_estudiante'] ?>" <?= (int)$datos['cod_derivacion']===(int)$d['cod_derivacion'] ? 'selected' : '' ?>>#<?= (int)$d['cod_derivacion'] ?> · <?= login_html($d['nombre'] . ' · ' . $d['estado']) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-4"><label for="fecha" class="form-label">Fecha</label><input id="fecha" type="date" name="fecha" class="form-control" value="<?= login_html($datos['fecha']) ?>" required></div>
<div class="col-md-4"><label for="hora" class="form-label">Hora</label><select id="hora" name="hora" class="form-select" required><option value="">Seleccione</option>
<?php foreach (cita_horas() as $hora): ?><option value="<?= $hora ?>" <?= $hora===substr($datos['hora'],0,5) ? 'selected' : '' ?>><?= $hora ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label for="estado" class="form-label">Estado</label><select id="estado" name="estado" class="form-select">
<?php foreach ($id ? ['Pendiente','Reprogramada','Atendida','Cancelada'] : ['Pendiente'] as $estado): ?><option value="<?= $estado ?>" <?= $estado===$datos['estado'] ? 'selected' : '' ?>><?= $estado ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label for="observaciones" class="form-label">Observaciones</label><textarea id="observaciones" name="observaciones" class="form-control" rows="4" maxlength="5000"><?= login_html($datos['observaciones'] ?? '') ?></textarea></div>
<div class="col-md-6">
<?php if ($id): ?>
<label class="form-label">Psicóloga responsable</label><input class="form-control" value="<?= login_html($cita['profesional'] ?? 'Sin responsable') ?>" readonly>
<?php elseif ($rolUsuarioSesion === 2): ?>
<label class="form-label">Psicóloga responsable</label><input class="form-control" value="<?= login_html($_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? 'Psicóloga de la sesión') ?>" readonly>
<input type="hidden" name="cod_psicologa" value="<?= $idUsuarioSesion ?>">
<?php else: ?>
<label for="psicologa" class="form-label">Psicóloga responsable <span class="text-danger">*</span></label>
<select id="psicologa" name="cod_psicologa" class="form-select" required>
<option value="">Seleccione una psicóloga</option>
<?php foreach ($profesionales as $profesional): ?><option value="<?= (int)$profesional['cod_usuario'] ?>" <?= $idPsicologaSeleccionada === (int)$profesional['cod_usuario'] ? 'selected' : '' ?>><?= login_html($profesional['nombre']) ?></option><?php endforeach; ?>
</select>
<?php if (!$profesionales): ?><div class="form-text text-danger">No hay psicólogas activas para asignar la cita.</div><?php endif; ?>
<?php endif; ?>
</div>
</div><div class="mt-3"><button class="btn btn-primary">Guardar cita</button> <a class="btn btn-light" href="listar.php">Volver</a></div>
</form></div>
<?php if ($cita):
    require_once __DIR__ . '/../includes/trazabilidad_vista.php';
    flujo_panel($conexion, 'cita', $cita);
    $historia = flujo_fila($conexion, 'SELECT cod_historia FROM historias_clinicas WHERE cod_estudiante=?', [$cita['cod_estudiante']]);
    if ($cita['estado'] !== 'Cancelada'):
?>
<a class="btn btn-primary mt-3" href="<?= $historia ? '../seguimientos/registrar.php?cod_historia=' . (int)$historia['cod_historia'] . '&amp;cod_cita=' . $id : '../historias_clinicas/registrar.php?cod_cita=' . $id ?>"><?= $historia ? 'Registrar seguimiento de esta cita' : 'Abrir historia desde esta cita' ?></a>
<?php endif; endif; ?>
</div></main>
<script>
const origenCita = document.getElementById('derivacion');
origenCita.addEventListener('change', () => { const estudiante = origenCita.selectedOptions[0].dataset.estudiante; if (estudiante) document.getElementById('estudiante').value = estudiante; });
document.getElementById('estudiante').addEventListener('change', () => { const estudiante = origenCita.selectedOptions[0].dataset.estudiante; if (estudiante && estudiante !== document.getElementById('estudiante').value) origenCita.value = '0'; });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
