<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/trazabilidad_datos.php';

const DERIVACION_IMAGEN_MAX_BYTES = 5 * 1024 * 1024;

function derivacion_imagen_descripcion(mixed $valor): string
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

function derivacion_catalogo_categorias(mysqli $bd): array
{
    return array_column($bd->query('SELECT cod_categoria,nombre,estado FROM categorias_derivacion ORDER BY nombre')->fetch_all(MYSQLI_ASSOC), null, 'nombre');
}

function derivacion_categorias_registradas(mysqli $bd, int $id, string $observaciones): array
{
    $stmt = $bd->prepare('SELECT c.nombre FROM derivacion_categorias dc
        INNER JOIN categorias_derivacion c ON c.cod_categoria=dc.cod_categoria WHERE dc.cod_derivacion=? ORDER BY c.nombre');
    $stmt->bind_param('i', $id); $stmt->execute();
    $nombres = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'nombre');
    $stmt->close();
    // También conserva categorías de registros anteriores al catálogo relacional.
    return array_values(array_unique(array_merge(derivacion_desglosar($observaciones)['categorias'], $nombres)));
}

function derivacion_desglosar(string $observaciones): array
{
    $datos = ['categorias' => [], 'adicionales' => [], 'metadatos' => []];
    $bloques = preg_split('/\R{2,}(?=(?:Categorías observadas|Observaciones adicionales|Solicitud de cita psicológica|Profesional solicitado):)/u', $observaciones);
    foreach ($bloques as $bloque) {
        $bloque = trim($bloque);
        if (str_starts_with($bloque, 'Categorías observadas:')) {
            $texto = trim(substr($bloque, strlen('Categorías observadas:')));
            $datos['categorias'] = array_merge($datos['categorias'], array_filter(array_map('trim', explode(',', $texto))));
        } elseif (str_starts_with($bloque, 'Solicitud de cita psicológica:') || str_starts_with($bloque, 'Profesional solicitado:')) {
            $datos['metadatos'][] = $bloque;
        } else {
            if (str_starts_with($bloque, 'Observaciones adicionales:')) $bloque = trim(substr($bloque, strlen('Observaciones adicionales:')));
            if ($bloque !== '') $datos['adicionales'][] = $bloque;
        }
    }
    $datos['categorias'] = array_values(array_unique($datos['categorias']));
    $datos['adicionales'] = implode("\n\n", $datos['adicionales']);
    return $datos;
}

function derivacion_observaciones(array $entrada, ?string $original, array $permitidas, array $registradas): ?string
{
    // Un POST antiguo/incompleto nunca sustituye las observaciones por un oculto vacío.
    if (($entrada['observaciones_presentes'] ?? '') !== '1') return $original;
    if (!array_key_exists('observaciones_adicionales', $entrada)) throw new InvalidArgumentException('El formulario de observaciones está incompleto.');
    $previo = derivacion_desglosar($original ?? '');
    $categorias = $entrada['categorias'] ?? [];
    if (!is_array($categorias)) throw new InvalidArgumentException('Las categorías no son válidas.');
    foreach ($categorias as $categoria) {
        if (!is_string($categoria) || !in_array($categoria, array_merge($permitidas, $registradas), true)) {
            throw new InvalidArgumentException('Seleccione categorías válidas.');
        }
    }
    $categorias = array_values(array_unique($categorias));
    if (!$categorias && $registradas) throw new InvalidArgumentException('Seleccione al menos una categoría.');
    $adicionales = $entrada['observaciones_adicionales'];
    if (!is_string($adicionales) || !mb_check_encoding($adicionales, 'UTF-8') || mb_strlen($adicionales) > 3000) {
        throw new InvalidArgumentException('Las observaciones adicionales admiten hasta 3000 caracteres UTF-8.');
    }
    $adicionales = trim($adicionales);
    if ($categorias === $previo['categorias'] && $adicionales === $previo['adicionales']) return $original;
    $partes = [];
    if ($categorias) $partes[] = 'Categorías observadas: ' . implode(', ', $categorias);
    if ($adicionales !== '') $partes[] = 'Observaciones adicionales: ' . $adicionales;
    // La solicitud y el profesional se conservan desde la base, nunca desde un campo oculto.
    return implode("\n\n", array_merge($partes, $previo['metadatos']));
}

function derivacion_actualizar(mysqli $bd, int $id, array $entrada, int $rol, int $docente, ?array $archivo = null, bool $puedeEditar = false): void
{
    if (!in_array($rol, [1, 3], true) && !$puedeEditar) {
        throw new InvalidArgumentException('No tiene permiso para editar derivaciones.');
    }
    $descripcionImagenPresente = array_key_exists('descripcion_imagen', $entrada);
    $descripcionImagen = derivacion_imagen_descripcion($entrada['descripcion_imagen'] ?? '');
    $subidaImagen = derivacion_imagen_validar($archivo, $descripcionImagen);
    foreach (['fecha' => 10, 'materia' => 150, 'motivo' => 2000, 'prioridad' => 10] as $campo => $maximo) {
        $valor = $entrada[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === '' || !mb_check_encoding($valor, 'UTF-8') || mb_strlen($valor) > $maximo) {
            throw new InvalidArgumentException("Revise el campo $campo (máximo $maximo caracteres).");
        }
        $entrada[$campo] = trim($valor);
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $entrada['fecha']);
    if (!$fecha || $fecha->format('Y-m-d') !== $entrada['fecha'] || $entrada['fecha'] > date('Y-m-d')) throw new InvalidArgumentException('La fecha no es válida.');
    if (!in_array($entrada['prioridad'], ['Alta', 'Media', 'Baja'], true)) throw new InvalidArgumentException('La prioridad no es válida.');
    $archivoNuevo = null;
    $rutaAnterior = null;
    $bd->begin_transaction();
    try {
        $stmt = $bd->prepare('SELECT d.observaciones,d.estado,d.cod_docente,d.cod_materia,m.nombre AS materia
            FROM derivaciones d LEFT JOIN materias m ON m.cod_materia=d.cod_materia
            WHERE d.cod_derivacion = ? AND (? <= 0 OR d.cod_docente = ?) FOR UPDATE');
        $stmt->bind_param('iii', $id, $docente, $docente); $stmt->execute();
        $actual = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$actual) throw new InvalidArgumentException('La derivación no existe o no tiene permiso para editarla.');
        if ($actual['estado'] !== 'Pendiente') throw new InvalidArgumentException('Solo se pueden editar derivaciones pendientes.');
        $stmt = $bd->prepare('SELECT cod_evidencia,ruta,descripcion FROM derivacion_evidencias
            WHERE cod_derivacion=? ORDER BY cod_evidencia DESC LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute();
        $evidencia = $stmt->get_result()->fetch_assoc() ?: null; $stmt->close();
        $descripcionImagenFinal = $descripcionImagenPresente
            ? $descripcionImagen : (string)($evidencia['descripcion'] ?? '');
        if (!$evidencia && $subidaImagen === null && $descripcionImagenFinal !== '') {
            throw new InvalidArgumentException('Adjunte una imagen para guardar su descripción.');
        }
        $catalogo = derivacion_catalogo_categorias($bd);
        $activas = array_keys(array_filter($catalogo, static fn($c) => $c['estado'] === 'Activo'));
        $registradas = derivacion_categorias_registradas($bd, $id, $actual['observaciones'] ?? '');
        $observaciones = derivacion_observaciones($entrada, $actual['observaciones'], $activas, $registradas);
        $idMateria = flujo_fila($bd, "SELECT cod_materia FROM materias WHERE nombre=? AND (estado='Activo' OR cod_materia=?) LIMIT 1", [$entrada['materia'], $actual['cod_materia']]);
        if (!$idMateria) throw new InvalidArgumentException('La materia seleccionada no está activa en el catálogo.');
        $idMateria = (int)$idMateria['cod_materia'];
        if ($idMateria !== (int)$actual['cod_materia']) {
            $asignacion = flujo_fila($bd, 'SELECT cod_docente FROM docente_materias WHERE cod_docente=? AND cod_materia=?', [(int)$actual['cod_docente'], $idMateria]);
            if (!$asignacion) throw new InvalidArgumentException('La materia seleccionada no está asignada al docente.');
        }
        $stmt = $bd->prepare('UPDATE derivaciones SET fecha=?, cod_materia=?, motivo=?, observaciones=?, prioridad=? WHERE cod_derivacion=? AND (? <= 0 OR cod_docente=?) AND estado=\'Pendiente\'');
        $stmt->bind_param('sisssiii', $entrada['fecha'], $idMateria, $entrada['motivo'], $observaciones, $entrada['prioridad'], $id, $docente, $docente);
        $stmt->execute(); $stmt->close();
        if (($entrada['observaciones_presentes'] ?? '') === '1') {
            $stmt = $bd->prepare('DELETE FROM derivacion_categorias WHERE cod_derivacion=?');
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
            $vinculo = $bd->prepare('INSERT INTO derivacion_categorias (cod_derivacion,cod_categoria) VALUES (?,?)');
            foreach (array_unique($entrada['categorias'] ?? []) as $categoria) {
                // Una categoría histórica sin catálogo permanece en observaciones.
                if (!isset($catalogo[$categoria])) continue;
                $idCategoria = (int)$catalogo[$categoria]['cod_categoria'];
                $vinculo->bind_param('ii', $id, $idCategoria); $vinculo->execute();
            }
            $vinculo->close();
        }
        if ($subidaImagen !== null) {
            $subidaImagen['descripcion'] = $descripcionImagen;
            if ($evidencia) $rutaAnterior = (string)$evidencia['ruta'];
            $archivoNuevo = derivacion_imagen_guardar(
                $bd,
                $id,
                $subidaImagen,
                $evidencia ? (int)$evidencia['cod_evidencia'] : null
            );
        } elseif ($evidencia && $descripcionImagenPresente) {
            $stmt = $bd->prepare('UPDATE derivacion_evidencias SET descripcion=? WHERE cod_evidencia=? AND cod_derivacion=?');
            $idEvidencia = (int)$evidencia['cod_evidencia'];
            $stmt->bind_param('sii', $descripcionImagen, $idEvidencia, $id);
            $stmt->execute(); $stmt->close();
        }
        $bd->commit();
        if ($rutaAnterior !== null) derivacion_imagen_eliminar($rutaAnterior);
    } catch (Throwable $error) {
        $bd->rollback();
        if ($archivoNuevo !== null) @unlink($archivoNuevo['local']);
        throw $error;
    }
}
