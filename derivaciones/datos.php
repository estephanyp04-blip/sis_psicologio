<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/trazabilidad_datos.php';

function derivacion_catalogo_categorias(mysqli $bd): array
{
    return array_column($bd->query('SELECT id_categoria,nombre,estado FROM categorias_derivacion ORDER BY nombre')->fetch_all(MYSQLI_ASSOC), null, 'nombre');
}

function derivacion_categorias_registradas(mysqli $bd, int $id, string $observaciones): array
{
    $stmt = $bd->prepare('SELECT c.nombre FROM derivacion_categorias dc
        INNER JOIN categorias_derivacion c ON c.id_categoria=dc.id_categoria WHERE dc.id_derivacion=? ORDER BY c.nombre');
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
        $stmt = $bd->prepare('SELECT d.observaciones,d.estado,d.id_materia,m.nombre AS materia
            FROM derivaciones d LEFT JOIN materias m ON m.id_materia=d.id_materia
            WHERE d.id_derivacion = ? AND (? <> 3 OR d.id_docente = ?) FOR UPDATE');
        $stmt->bind_param('iii', $id, $rol, $docente); $stmt->execute();
        $actual = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$actual) throw new InvalidArgumentException('La derivación no existe o no tiene permiso para editarla.');
        if ($actual['estado'] !== 'Pendiente') throw new InvalidArgumentException('Solo se pueden editar derivaciones pendientes.');
        $catalogo = derivacion_catalogo_categorias($bd);
        $activas = array_keys(array_filter($catalogo, static fn($c) => $c['estado'] === 'Activo'));
        $registradas = derivacion_categorias_registradas($bd, $id, $actual['observaciones'] ?? '');
        $observaciones = derivacion_observaciones($entrada, $actual['observaciones'], $activas, $registradas);
        $idMateria = flujo_fila($bd, "SELECT id_materia FROM materias WHERE nombre=? AND (estado='Activo' OR id_materia=?) LIMIT 1", [$entrada['materia'], $actual['id_materia']]);
        if (!$idMateria) throw new InvalidArgumentException('La materia seleccionada no está activa en el catálogo.');
        $idMateria = (int)$idMateria['id_materia'];
        $stmt = $bd->prepare('UPDATE derivaciones SET fecha=?, id_materia=?, motivo=?, observaciones=?, prioridad=? WHERE id_derivacion=? AND (? <> 3 OR id_docente=?) AND estado=\'Pendiente\'');
        $stmt->bind_param('sisssiii', $entrada['fecha'], $idMateria, $entrada['motivo'], $observaciones, $entrada['prioridad'], $id, $rol, $docente);
        $stmt->execute(); $stmt->close();
        if (($entrada['observaciones_presentes'] ?? '') === '1') {
            $stmt = $bd->prepare('DELETE FROM derivacion_categorias WHERE id_derivacion=?');
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
            $vinculo = $bd->prepare('INSERT INTO derivacion_categorias (id_derivacion,id_categoria) VALUES (?,?)');
            foreach (array_unique($entrada['categorias'] ?? []) as $categoria) {
                // Una categoría histórica sin catálogo permanece en observaciones.
                if (!isset($catalogo[$categoria])) continue;
                $idCategoria = (int)$catalogo[$categoria]['id_categoria'];
                $vinculo->bind_param('ii', $id, $idCategoria); $vinculo->execute();
            }
            $vinculo->close();
        }
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }
}
