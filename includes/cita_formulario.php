<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/citas_datos.php';
$id = isset($editarCita) ? (int)filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) : 0;
$cita = $id ? flujo_fila($conexion, 'SELECT c.*,CONCAT(u.nombre,\' \',u.apellido) profesional FROM citas c LEFT JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.id_cita=?', [$id]) : null;
if (isset($editarCita) && !$cita) { header('Location: listar.php'); exit; }
$datos = $cita ?? ['id_estudiante' => 0,'id_derivacion' => 0,'fecha' => date('Y-m-d'),'hora' => '', 'estado' => 'Pendiente','observaciones' => ''];
if (!$cita) {
    $derivacion = (int)filter_var($_GET['id_derivacion'] ?? 0, FILTER_VALIDATE_INT);
    if ($derivacion) {
        $origen = flujo_fila($conexion, 'SELECT id_estudiante,id_derivacion FROM derivaciones WHERE id_derivacion=?', [$derivacion]);
        if (!$origen) { http_response_code(404); exit('Derivación no encontrada.'); }
        $datos = array_replace($datos, $origen);
    }
}
$recuperados = $_SESSION['datos_cita'] ?? [];
if ((int)($recuperados['id_cita'] ?? 0) === $id) $datos = array_replace($datos, $recuperados);
$mensaje = $_SESSION['mensaje_cita'] ?? '';
unset($_SESSION['datos_cita'], $_SESSION['mensaje_cita']);
$estudiantes = $conexion->query("SELECT id_estudiante,CONCAT(apellidos,', ',nombres) nombre FROM estudiantes ORDER BY apellidos,nombres")->fetch_all(MYSQLI_ASSOC);
$derivaciones = $conexion->query("SELECT d.id_derivacion,d.id_estudiante,d.estado,CONCAT(e.apellidos,', ',e.nombres) nombre FROM derivaciones d JOIN estudiantes e ON e.id_estudiante=d.id_estudiante ORDER BY d.fecha DESC,d.id_derivacion DESC")->fetch_all(MYSQLI_ASSOC);
$tituloPagina = $id ? 'Editar cita' : 'Nueva cita';
include __DIR__ . '/header.php'; include __DIR__ . '/sidebar.php'; include __DIR__ . '/navbar.php';
?>
<main class="main-content"><div class="container-fluid">
<h1><?= login_html($tituloPagina) ?></h1>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= login_html($mensaje) ?></div><?php endif; ?>
<?php if (isset($_GET['exito'])): ?><div class="alert alert-success">Cita guardada correctamente.</div><?php endif; ?>
<div class="card p-4">
<p>Responsable: <?= login_html($cita ? ($cita['profesional'] ?? 'Sin responsable registrado') : ($_SESSION['nombre'] ?? 'Usuario de la sesión')) ?>.</p>
<form id="form-cita" method="POST" action="<?= $id ? 'procesar_editar.php' : 'procesar_registrar.php' ?>">
<?= login_campo_csrf() ?>
<?php if ($id): ?><input type="hidden" name="id_cita" value="<?= $id ?>"><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><label for="estudiante" class="form-label">Estudiante</label>
<select id="estudiante" name="id_estudiante" class="form-select" required <?= $id ? 'disabled' : '' ?>>
<option value="">Seleccione</option>
<?php foreach ($estudiantes as $e): ?><option value="<?= (int)$e['id_estudiante'] ?>" <?= (int)$datos['id_estudiante']===(int)$e['id_estudiante'] ? 'selected' : '' ?>><?= login_html($e['nombre']) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-6"><label for="derivacion" class="form-label">Derivación de origen</label>
<select id="derivacion" name="id_derivacion" class="form-select" <?= $id ? 'disabled' : '' ?>>
<option value="0">Sin derivación</option>
<?php foreach ($derivaciones as $d): ?><option value="<?= (int)$d['id_derivacion'] ?>" data-estudiante="<?= (int)$d['id_estudiante'] ?>" <?= (int)$datos['id_derivacion']===(int)$d['id_derivacion'] ? 'selected' : '' ?>>#<?= (int)$d['id_derivacion'] ?> · <?= login_html($d['nombre'] . ' · ' . $d['estado']) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-4"><label for="fecha" class="form-label">Fecha</label><input id="fecha" type="date" name="fecha" class="form-control" value="<?= login_html($datos['fecha']) ?>" required></div>
<div class="col-md-4"><label for="hora" class="form-label">Hora</label><select id="hora" name="hora" class="form-select" required><option value="">Seleccione</option>
<?php foreach (cita_horas() as $hora): ?><option value="<?= $hora ?>" <?= $hora===substr($datos['hora'],0,5) ? 'selected' : '' ?>><?= $hora ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label for="estado" class="form-label">Estado</label><select id="estado" name="estado" class="form-select">
<?php foreach ($id ? ['Pendiente','Reprogramada','Atendida','Cancelada'] : ['Pendiente'] as $estado): ?><option value="<?= $estado ?>" <?= $estado===$datos['estado'] ? 'selected' : '' ?>><?= $estado ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label for="observaciones" class="form-label">Observaciones</label><textarea id="observaciones" name="observaciones" class="form-control" rows="4" maxlength="5000"><?= login_html($datos['observaciones'] ?? '') ?></textarea></div>
</div><div class="mt-3"><button class="btn btn-primary">Guardar cita</button> <a class="btn btn-light" href="listar.php">Volver</a></div>
</form></div>
<?php if ($cita):
    require_once __DIR__ . '/trazabilidad_vista.php';
    flujo_panel($conexion, 'cita', $cita);
    $historia = flujo_fila($conexion, 'SELECT id_historia FROM historias_clinicas WHERE id_estudiante=?', [$cita['id_estudiante']]);
    if ($cita['estado'] !== 'Cancelada'):
?>
<a class="btn btn-primary mt-3" href="<?= $historia ? '../seguimientos/registrar.php?id_historia=' . (int)$historia['id_historia'] . '&amp;id_cita=' . $id : '../historias_clinicas/registrar.php?id_cita=' . $id ?>"><?= $historia ? 'Registrar seguimiento de esta cita' : 'Abrir historia desde esta cita' ?></a>
<?php endif; endif; ?>
</div></main>
<script>
const origenCita = document.getElementById('derivacion');
origenCita.addEventListener('change', () => { const estudiante = origenCita.selectedOptions[0].dataset.estudiante; if (estudiante) document.getElementById('estudiante').value = estudiante; });
document.getElementById('estudiante').addEventListener('change', () => { const estudiante = origenCita.selectedOptions[0].dataset.estudiante; if (estudiante && estudiante !== document.getElementById('estudiante').value) origenCita.value = '0'; });
</script>
<?php include __DIR__ . '/footer.php'; ?>
