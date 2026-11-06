
<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/datos.php';

/**
 * Detecta el separador del CSV.
 * Admite coma, punto y coma y tabulación.
 */
function estudiantes_detectar_separador($handle): string
{
    $posicion = ftell($handle);
    rewind($handle);

    $primeraLinea = fgets($handle);
    fseek($handle, $posicion === false ? 0 : $posicion);

    if ($primeraLinea === false) {
        throw new InvalidArgumentException('El archivo CSV está vacío.');
    }

    $primeraLinea = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        $primeraLinea
    );

    $separadores = [';', ',', "\t"];
    $mejor = ';';
    $mayorCantidad = 0;

    foreach ($separadores as $separador) {
        $columnas = str_getcsv($primeraLinea, $separador);
        $cantidad = count($columnas);

        if ($cantidad > $mayorCantidad) {
            $mayorCantidad = $cantidad;
            $mejor = $separador;
        }
    }

    return $mejor;
}

/**
 * Importación parcial de estudiantes.
 * Cada fila válida se inserta individualmente.
 */
function estudiantes_importar_csv(mysqli $conexion, $handle): array
{
    // 1. Detectar separador automáticamente
    $separador = estudiantes_detectar_separador($handle);

    // 2. Leer encabezados
    $encabezados = fgetcsv($handle, 0, $separador);

    if ($encabezados === false) {
        throw new InvalidArgumentException(
            'El archivo CSV está vacío.'
        );
    }

    // 3. Eliminar BOM UTF-8
    $encabezados[0] = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        (string) $encabezados[0]
    );

    // 4. Normalizar encabezados
    $encabezados = array_map(
        static function ($valor) {
            return strtolower(trim((string) $valor));
        },
        $encabezados
    );

    // 5. Verificar encabezados duplicados o vacíos
    if (
        count(array_unique($encabezados)) !== count($encabezados)
        || in_array('', $encabezados, true)
    ) {
        throw new InvalidArgumentException(
            'El CSV contiene encabezados repetidos o vacíos.'
        );
    }

    // 6. Validar columnas obligatorias
    $obligatorios = array_slice(ESTUDIANTE_CAMPOS, 0, 10);

    $faltantes = array_diff($obligatorios, $encabezados);

    if (!empty($faltantes)) {
        throw new InvalidArgumentException(
            'Faltan columnas obligatorias: '
            . implode(', ', $faltantes)
            . '. Columnas encontradas: '
            . implode(', ', $encabezados)
        );
    }

    // 7. Obtener catálogo académico
    $catalogo = estudiante_catalogo($conexion);

    $resultado = [
        'importados' => 0,
        'rechazados' => 0,
        'errores' => []
    ];

    $numero = 1;

    // 8. Recorrer filas del CSV
    while (
        ($valores = fgetcsv($handle, 0, $separador)) !== false
    ) {
        ++$numero;

        // Ignorar filas completamente vacías
        if (
            count(array_filter(
                $valores,
                static fn($v) => trim((string) $v) !== ''
            )) === 0
        ) {
            continue;
        }

        try {
            // Verificar cantidad de columnas
            if (count($valores) !== count($encabezados)) {
                throw new InvalidArgumentException(
                    'El número de columnas no coincide con el encabezado.'
                );
            }

            // Asociar encabezados con valores
            $fila = array_combine($encabezados, $valores);

            if ($fila === false) {
                throw new InvalidArgumentException(
                    'No se pudieron interpretar los datos.'
                );
            }

            // Utilizar funciones existentes
            $datos = estudiante_datos($fila);

            $academico = estudiante_validar(
                $datos,
                $catalogo
            );

            estudiante_insertar(
                $conexion,
                $datos,
                $academico
            );

            ++$resultado['importados'];

        } catch (InvalidArgumentException | mysqli_sql_exception $error) {
            ++$resultado['rechazados'];

            if (count($resultado['errores']) < 5) {
                if ($error instanceof InvalidArgumentException) {
                    $mensaje = $error->getMessage();
                } elseif ((int) $error->getCode() === 1062) {
                    $mensaje = 'El código o CI ya está registrado.';
                } else {
                    $mensaje = 'No se pudo guardar la fila.';
                }

                $resultado['errores'][] =
                    "Fila {$numero}: {$mensaje}";
            }

            if (
                $error instanceof mysqli_sql_exception
                && in_array((int) $error->getCode(), [2006, 2013], true)
            ) {
                $resultado['errores'][] =
                    'Se interrumpió la conexión; quedan filas sin procesar.';
                break;
            }
        }
    }

    return $resultado;
}
