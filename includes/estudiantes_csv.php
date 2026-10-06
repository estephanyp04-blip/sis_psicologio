<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/estudiantes_datos.php';

/** Importación parcial: cada INSERT válido se confirma, cada fila rechazada se informa. */
function estudiantes_importar_csv(mysqli $conexion, $handle): array
{
    $encabezados = fgetcsv($handle, 0, ';');
    if ($encabezados === false) throw new InvalidArgumentException('El archivo CSV está vacío.');
    $encabezados[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$encabezados[0]);
    $encabezados = array_map(static fn($v) => strtolower(trim((string)$v)), $encabezados);
    if (count(array_unique($encabezados)) !== count($encabezados) || in_array('', $encabezados, true)) {
        throw new InvalidArgumentException('El CSV contiene encabezados repetidos o vacíos.');
    }
    $faltantes = array_diff(array_slice(ESTUDIANTE_CAMPOS, 0, 10), $encabezados);
    if ($faltantes) throw new InvalidArgumentException('Faltan columnas obligatorias: ' . implode(', ', $faltantes) . '.');
    $catalogo = estudiante_catalogo($conexion);
    $resultado = ['importados' => 0, 'rechazados' => 0, 'errores' => []];
    $numero = 1;
    while (($valores = fgetcsv($handle, 0, ';')) !== false) {
        ++$numero;
        if (count(array_filter($valores, static fn($v) => trim((string)$v) !== '')) === 0) continue;
        try {
            if (count($valores) !== count($encabezados)) {
                throw new InvalidArgumentException('El número de columnas no coincide con el encabezado.');
            }
            $datos = estudiante_datos(array_combine($encabezados, $valores));
            $academico = estudiante_validar($datos, $catalogo);
            estudiante_insertar($conexion, $datos, $academico);
            ++$resultado['importados'];
        } catch (InvalidArgumentException | mysqli_sql_exception $error) {
            ++$resultado['rechazados'];
            if (count($resultado['errores']) < 5) {
                $mensaje = $error instanceof InvalidArgumentException ? $error->getMessage()
                    : ($error->getCode() === 1062 ? 'El código o CI ya está registrado.' : 'No se pudo guardar la fila.');
                $resultado['errores'][] = "Fila $numero: $mensaje";
            }
            if ($error instanceof mysqli_sql_exception && in_array($error->getCode(), [2006, 2013], true)) {
                $resultado['errores'][] = 'Se interrumpió la conexión; quedan filas sin procesar.';
                break;
            }
        }
    }
    return $resultado;
}