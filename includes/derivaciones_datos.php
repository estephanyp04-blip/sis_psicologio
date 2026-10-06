<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

const DERIVACION_CATEGORIAS = ['Rendimiento Académico', 'Conducta en Aula', 'Social / Emocional', 'Dinámica Familiar', 'Acoso escolar / Acoso', 'Otro'];

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

function derivacion_observaciones(array $entrada, ?string $original): ?string
{
    // Un POST antiguo/incompleto nunca sustituye las observaciones por un oculto vacío.
    if (($entrada['observaciones_presentes'] ?? '') !== '1') return $original;
    if (!array_key_exists('observaciones_adicionales', $entrada)) throw new InvalidArgumentException('El formulario de observaciones está incompleto.');
    $previo = derivacion_desglosar($original ?? '');
    $categorias = $entrada['categorias'] ?? [];
    if (!is_array($categorias)) throw new InvalidArgumentException('Las categorías no son válidas.');
    foreach ($categorias as $categoria) {
        if (!is_string($categoria) || !in_array($categoria, array_merge(DERIVACION_CATEGORIAS, $previo['categorias']), true)) {
            throw new InvalidArgumentException('Seleccione categorías válidas.');
        }
    }
    $categorias = array_values(array_unique($categorias));
    if (!$categorias && $previo['categorias']) throw new InvalidArgumentException('Seleccione al menos una categoría.');
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

function derivacion_actualizar(mysqli $bd, int $id, array $entrada, int $rol, int $docente): void
{
    if (!in_array($rol, [1, 3], true)) throw new InvalidArgumentException('No tiene permiso para editar derivaciones.');
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
    $bd->begin_transaction();
    try {
        $stmt = $bd->prepare('SELECT observaciones, estado FROM derivaciones WHERE id_derivacion = ? AND (? <> 3 OR id_docente = ?) FOR UPDATE');
        $stmt->bind_param('iii', $id, $rol, $docente); $stmt->execute();
        $actual = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$actual) throw new InvalidArgumentException('La derivación no existe o no tiene permiso para editarla.');
        if ($actual['estado'] !== 'Pendiente') throw new InvalidArgumentException('Solo se pueden editar derivaciones pendientes.');
        $observaciones = derivacion_observaciones($entrada, $actual['observaciones']);
        $stmt = $bd->prepare('UPDATE derivaciones SET fecha=?, materia=?, motivo=?, observaciones=?, prioridad=? WHERE id_derivacion=? AND (? <> 3 OR id_docente=?) AND estado=\'Pendiente\'');
        $stmt->bind_param('sssssiii', $entrada['fecha'], $entrada['materia'], $entrada['motivo'], $observaciones, $entrada['prioridad'], $id, $rol, $docente);
        $stmt->execute(); $stmt->close();
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }
}
