<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/importar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/csv.php';

$handle = false;
try {
    $archivo = $_FILES['archivo'] ?? [];
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($archivo['name'] ?? null) || !is_string($archivo['tmp_name'] ?? null)
        || ($archivo['size'] ?? 0) > 5 * 1024 * 1024 || !is_uploaded_file($archivo['tmp_name'])) {
        throw new InvalidArgumentException('Seleccione un archivo CSV válido de hasta 5 MB.');
    }
    if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'csv') {
        throw new InvalidArgumentException('Solo se permiten archivos con formato CSV.');
    }
    $handle = fopen($archivo['tmp_name'], 'rb');
    if ($handle === false) throw new InvalidArgumentException('No se pudo leer el archivo importado.');
    $resultado = estudiantes_importar_csv($conexion, $handle);
    $_SESSION['mensaje'] = "Se importaron {$resultado['importados']} estudiante(s); {$resultado['rechazados']} fila(s) rechazadas. "
        . implode(' ', $resultado['errores']);
    $_SESSION['tipo_mensaje'] = $resultado['rechazados'] > 0 || $resultado['importados'] === 0 ? 'warning' : 'success';
} catch (Throwable $error) {
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo iniciar la importación.';
    $_SESSION['tipo_mensaje'] = 'danger';
} finally {
    if (is_resource($handle)) fclose($handle);
}
header('Location: listar.php');
exit;
