<?php
require_once __DIR__ . '/includes/autenticacion.php';
login_iniciar_sesion();
$error = '';
$nombreUsuario = '';
try {
    if (login_usuario_actual()) login_ir(login_config()['destinos'][$_SESSION['cod_rol']]);
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
            if ($u) { login_establecer($u); login_ir(login_config()['destinos'][(int) $u['cod_rol']]); }
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
    <link rel="icon" type="image/png" sizes="512x512" href="<?= login_html(login_url('asset/img/favicon.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= login_html(login_url('asset/img/apple-touch-icon.png')) ?>">
    <meta name="theme-color" content="#0f172a">
    <link href="<?= login_html(login_url('asset/css/login.css?v=4')) ?>" rel="stylesheet">
</head>
<body class="login-page">
<main class="login-card" aria-labelledby="titulo-login">
    <header class="login-cabecera">
        <img class="login-simbolo" src="<?= login_html(login_url('asset/img/logo-sistema.svg')) ?>" alt="">
        <h1 id="titulo-login">Sistema Psicol&oacute;gico</h1>

    </header>
    <section class="login-formulario" aria-label="Acceso al sistema">
        <p class="login-ayuda">Ingresa con tu usuario y contraseña.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger login-error" role="alert"><?= login_html($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['salida'])): ?>
            <p class="login-aviso" role="status">Has cerrado la sesión.</p>
        <?php endif; ?>
        <form action="<?= login_html(login_url('login.php')) ?>" method="post">
            <input type="hidden" name="csrf" value="<?= login_html(login_token()) ?>">
            <div class="login-campo">
                <label class="visually-hidden" for="usuario">Usuario</label>
                <span class="login-icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.5-3.5 3.1-5.5 7-5.5s6.5 2 7 5.5"/></svg>
                </span>
                <input class="form-control" id="usuario" name="usuario" type="text" maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Usuario" value="<?= login_html($nombreUsuario) ?>" required autofocus>
            </div>
            <div class="login-campo">
                <label class="visually-hidden" for="password">Contraseña</label>
                <span class="login-icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2"/></svg>
                </span>
                <div class="login-password">
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" placeholder="Contraseña" required>
                    <button type="button" id="ver-password" aria-label="Mostrar contraseña" aria-controls="password" aria-pressed="false" hidden>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/><path class="login-ojo-cubierto" d="m4 4 16 16"/></svg>
                    </button>
                </div>
            </div>
            <button class="login-boton" type="submit">Iniciar sesión</button>
        </form>
        <p class="login-soporte"><span>¿Olvidaste tu contraseña?</span><br>Solicita ayuda al administrador.</p>
    </section>
</main>
<script src="<?= login_html(login_url('asset/js/login.js')) ?>" defer></script>
</body>
</html>
