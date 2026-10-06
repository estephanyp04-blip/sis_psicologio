<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Incluir en el navbar, después de proteger la página con requerir_roles().
require_once __DIR__ . '/autenticacion.php';
?>
<form action="<?= login_html(login_url('cerrar_sesion.php')) ?>" method="post" class="d-inline">
    <input type="hidden" name="csrf" value="<?= login_html(login_token()) ?>">
    <button type="submit" class="btn btn-outline-danger btn-sm">Cerrar sesión</button>
</form>
