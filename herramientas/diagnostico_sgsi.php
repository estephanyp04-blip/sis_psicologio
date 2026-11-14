

<?php
// Diagnóstico de solo lectura. Nunca mostrar credenciales ni datos clínicos.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$argumentos = array_slice($argv, 1);
if ($argumentos !== [] && $argumentos !== ['--json']) {
    fwrite(STDERR, "Uso: php herramientas/diagnostico_sgsi.php [--json]\n");
    exit(64);
}
$json = $argumentos === ['--json'];
$controles = [];
$agregar = static function (string $id, string $estado, string $detalle) use (&$controles): void {
    $controles[] = compact('id', 'estado', 'detalle');
};

try {
    $config = require __DIR__ . '/../config/login.php';
    $agregar('BD_CUENTA', strtolower($config['usuario_bd']) === 'root' ? 'alerta' : 'pendiente',
        strtolower($config['usuario_bd']) === 'root'
            ? 'El proceso CLI usa root. Separar ejecución y administración antes del despliegue institucional.'
            : 'La cuenta no se llama root; revisar sus privilegios efectivos por separado.');
    $agregar('BD_CLAVE', $config['clave_bd'] === '' ? 'alerta' : 'observado',
        $config['clave_bd'] === '' ? 'La clave configurada está vacía.' : 'Hay clave configurada; no se evalúa su fortaleza.');
    $agregar('COOKIE_SECURE', $config['cookie_segura'] ? 'observado' : 'pendiente',
        $config['cookie_segura'] ? 'Secure forzado en configuración CLI; verificar respuesta real de Apache.'
            : 'Secure depende de detección HTTPS en la aplicación; comprobar despliegue real.');
    $agregar('SESION_LIMITES', $config['inactividad'] > 0 && $config['duracion_maxima'] >= $config['inactividad'] ? 'observado' : 'alerta',
        'Inactividad: ' . (int)$config['inactividad'] . ' s; duración máxima: ' . (int)$config['duracion_maxima'] . ' s. Validar contra política aprobada.');

    require_once __DIR__ . '/../config/conexion_login.php';
    try {
        $bd = login_bd();
        $bd->query('SELECT 1');
        $agregar('BD_CONEXION', 'observado', 'Conexión y consulta de solo lectura correctas.');
        try {
            $fila = $bd->query("SELECT COUNT(*) AS total FROM usuarios u LEFT JOIN roles r ON r.cod_rol=u.cod_rol WHERE u.estado='Activo' AND (r.cod_rol IS NULL OR r.estado<>'Activo')")->fetch_assoc();
            $agregar('CUENTAS_ROL', (int)$fila['total'] === 0 ? 'observado' : 'alerta',
                (int)$fila['total'] === 0 ? 'Sin cuentas activas con rol ausente o inactivo.' : 'Hay cuentas activas con rol ausente o inactivo; revisar con el responsable.');
            $fila = $bd->query("SELECT COUNT(*) AS total FROM usuarios u JOIN roles r ON r.cod_rol=u.cod_rol WHERE u.estado='Activo' AND r.estado='Activo' AND u.cod_rol=1")->fetch_assoc();
            $agregar('ADMIN_ACTIVO', (int)$fila['total'] > 0 ? 'observado' : 'alerta',
                (int)$fila['total'] > 0 ? 'Existe administración activa; necesidad y cantidad pendientes de revisión.' : 'No se encontró cuenta activa con rol administrador activo.');
            $bd->query('SELECT cod_usuario,modulo,accion,registro_id,fecha FROM auditoria LIMIT 0');
            $agregar('AUDITORIA_ESQUEMA', 'observado', 'Columnas de auditoría accesibles; no verifica cobertura, integridad ni inmutabilidad.');
        } catch (Throwable $error) {
            $agregar('BD_ESQUEMA', 'error', 'No se completaron las consultas de estructura/cuentas. Revisar esquema y permisos sin publicar detalles internos.');
        }
    } catch (Throwable $error) {
        $agregar('BD_CONEXION', 'error', 'No se pudo conectar o consultar. Comprobar servicio MySQL, host, puerto, base y credenciales en el entorno de este proceso.');
    }
} catch (Throwable $error) {
    $agregar('CONFIGURACION', 'error', 'No se pudo cargar o evaluar la configuración local.');
}

$agregar('GESTION', 'pendiente', 'Alcance, responsables, riesgos, copias, formación y auditorías requieren evidencia institucional. Este diagnóstico no acredita ISO.');
$estados = array_column($controles, 'estado');
$salida = in_array('error', $estados, true) ? 1 : (in_array('alerta', $estados, true) ? 2 : 0);
$resultado = ['fecha' => date(DATE_ATOM), 'entorno' => 'PHP CLI', 'solo_lectura' => true, 'codigo_salida' => $salida, 'controles' => $controles];
if ($json) {
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), PHP_EOL;
} else {
    echo "Diagnóstico SGSI — configuración del proceso PHP CLI\n";
    foreach ($controles as $control) echo '[' . $control['estado'] . '] ' . $control['id'] . ': ' . $control['detalle'] . PHP_EOL;
    echo 'Código de salida: ' . $salida . PHP_EOL;
}
exit($salida);
