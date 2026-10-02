<?php
require_once __DIR__ . '/includes/autenticacion.php';
login_iniciar_sesion();
$error = '';
$nombreUsuario = '';
try {
    if (login_usuario_actual()) login_ir(login_config()['destinos'][$_SESSION['id_rol']]);
    if (($_SESSION['autenticado'] ?? false) === true) { $_SESSION = []; session_regenerate_id(true); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombreUsuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
        $clave = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!login_validar_token($_POST['csrf'] ?? null)) {
            $error = 'El formulario venció. Vuelve a intentarlo.';
        } elseif ($nombreUsuario === '' || strlen($nombreUsuario) > 100 || $clave === '' || strlen($clave) > 72 || strpos($clave, "\0") !== false) {
            $error = 'Introduce un usuario y una contraseña válidos.';
        } else {
            [$u, $error] = login_comprobar($nombreUsuario, $clave);
            if ($u) { login_establecer($u); login_ir(login_config()['destinos'][(int) $u['id_rol']]); }
        }
        unset($clave);
    }
} catch (Throwable $e) {
    error_log('Login: ' . $e->getMessage());
    http_response_code(503);
    $error = 'No fue posible iniciar sesión. Revisa la conexión y la configuración de usuarios con el administrador.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Sistema Psicológico</title>
<link href="/proyecto_vercionII/asset/css/login.css?v=2" rel="stylesheet">
    <link href="<?= login_html(login_url('assets/css/login.css')) ?>" rel="stylesheet">
</head>
<body class="login-page">
<main class="login-card">

    <section class="login-identidad" aria-labelledby="institucion">
        <span class="login-simbolo" aria-hidden="true">Ψ</span>
        <p class="login-etiqueta">DEPARTAMENTO DE PSICOLOGÍA</p>
        <h1 id="institucion">U.E. Cañada<br>Pailita «B»</h1>
        <p>Registro de citas y seguimiento de historias clínicas psicológicas de estudiantes.</p>
        <div class="login-nota">Un espacio para acompañar el bienestar estudiantil.</div>
    </section>
    <section class="login-formulario" aria-labelledby="titulo-login">
        <p class="login-etiqueta">ACCESO AL SISTEMA</p>
        <h2 id="titulo-login">Iniciar sesión</h2>
        <p class="login-ayuda">Ingresa con tu usuario y contraseña.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger login-error" role="alert"><?= login_html($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['salida'])): ?>
            <p class="login-aviso" role="status">Has cerrado la sesión.</p>
        <?php endif; ?>
        <form action="<?= login_html(login_url('login.php')) ?>" method="post">
            <input type="hidden" name="csrf" value="<?= login_html(login_token()) ?>">
            <div class="mb-4">
                <label class="form-label" for="usuario">Usuario</label>
                <input class="form-control" id="usuario" name="usuario" type="text" maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= login_html($nombreUsuario) ?>" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <div class="login-password">
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" id="ver-password" aria-controls="password" aria-pressed="false" hidden>Mostrar</button>
                </div>
            </div>
            <button class="btn login-boton w-100" type="submit">Ingresar</button>
        </form>
        <p class="login-soporte">Si no recuerdas tu contraseña, solicita ayuda al administrador.</p>
    </section>
</main>
<script src="<?= login_html(login_url('assets/js/login.js')) ?>" defer></script>
</body>
</html>
