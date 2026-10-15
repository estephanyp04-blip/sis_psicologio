<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

const DERIVACION_IMAGEN_MAX_BYTES = 5 * 1024 * 1024;

function derivacion_imagen_descripcion($valor): string
{
    if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || mb_strlen($valor) > 1000) {
        throw new InvalidArgumentException('La descripción de la imagen admite hasta 1000 caracteres UTF-8.');
    }
    return trim($valor);
}

function derivacion_imagen_validar(?array $archivo, string $descripcion): ?array
{
    if ($archivo === null || !isset($archivo['error'])) return null;
    $error = (int)$archivo['error'];
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'La imagen supera el tamaño permitido de 5 MB.'
            : 'No se pudo recibir la imagen. Intente seleccionarla nuevamente.');
    }

    $temporal = $archivo['tmp_name'] ?? '';
    if (!is_string($temporal) || !is_uploaded_file($temporal)) {
        throw new InvalidArgumentException('El archivo enviado no es una imagen válida.');
    }
    $tamano = filesize($temporal);
    if ($tamano === false || $tamano <= 0 || $tamano > DERIVACION_IMAGEN_MAX_BYTES) {
        throw new InvalidArgumentException('La imagen debe tener un tamaño máximo de 5 MB.');
    }

    $mimesPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporal);
    $datosImagen = @getimagesize($temporal);
    if (!isset($mimesPermitidos[$mime]) || !$datosImagen || ($datosImagen['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Use una imagen JPG, PNG o WebP válida.');
    }
    if (trim($descripcion) === '') {
        throw new InvalidArgumentException('Describa qué desea destacar en la imagen.');
    }

    $nombreOriginal = basename(str_replace('\\', '/', (string)($archivo['name'] ?? 'imagen')));
    $nombreOriginal = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombreOriginal) ?? 'imagen';
    $nombreOriginal = mb_substr(trim($nombreOriginal), 0, 255);
    if ($nombreOriginal === '') $nombreOriginal = 'imagen.' . $mimesPermitidos[$mime];

    return [
        'temporal' => $temporal,
        'nombre_original' => $nombreOriginal,
        'tipo_mime' => $mime,
        'extension' => $mimesPermitidos[$mime],
        'descripcion' => $descripcion,
    ];
}

function derivacion_imagen_guardar(mysqli $bd, int $idDerivacion, array $imagen, ?int $idEvidencia = null): array
{
    $directorioReal = realpath(__DIR__);
    if ($directorioReal === false) throw new RuntimeException('No se pudo acceder al almacenamiento de imágenes.');

    $nombreSeguro = 'evidencia_' . bin2hex(random_bytes(16)) . '.' . $imagen['extension'];
    $destino = $directorioReal . DIRECTORY_SEPARATOR . $nombreSeguro;
    if (!move_uploaded_file($imagen['temporal'], $destino)) {
        throw new RuntimeException('No se pudo guardar la imagen. Inténtelo nuevamente.');
    }
    $rutaRelativa = 'derivaciones/' . $nombreSeguro;

    try {
        if ($idEvidencia !== null) {
            $stmt = $bd->prepare('UPDATE derivacion_evidencias
                SET nombre_original=?,ruta=?,tipo_mime=?,descripcion=? WHERE cod_evidencia=? AND cod_derivacion=?');
            $stmt->bind_param('ssssii', $imagen['nombre_original'], $rutaRelativa, $imagen['tipo_mime'], $imagen['descripcion'], $idEvidencia, $idDerivacion);
        } else {
            $stmt = $bd->prepare('INSERT INTO derivacion_evidencias
                (cod_derivacion,nombre_original,ruta,tipo_mime,descripcion) VALUES (?,?,?,?,?)');
            $stmt->bind_param('issss', $idDerivacion, $imagen['nombre_original'], $rutaRelativa, $imagen['tipo_mime'], $imagen['descripcion']);
        }
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $error) {
        @unlink($destino);
        throw $error;
    }

    return ['ruta' => $rutaRelativa, 'local' => $destino];
}

function derivacion_imagen_local(string $rutaRelativa): ?string
{
    if (!preg_match('#^derivaciones/evidencia_[a-f0-9]{32}\.(?:jpg|png|webp)$#', str_replace('\\', '/', $rutaRelativa))) return null;
    $directorio = realpath(__DIR__);
    $archivo = realpath(dirname(__DIR__) . '/' . $rutaRelativa);
    if ($directorio === false || $archivo === false || dirname($archivo) !== $directorio || !is_file($archivo)) return null;
    return $archivo;
}

function derivacion_imagen_eliminar(string $rutaRelativa): void
{
    $archivo = derivacion_imagen_local($rutaRelativa);
    if ($archivo !== null) @unlink($archivo);
}
