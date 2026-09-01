<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function volverConMensaje(string $mensaje, string $tipo = 'danger'): void
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = $tipo;
    header('Location: listar.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['archivo'])) {
    volverConMensaje('Seleccione un archivo CSV para importar.');
}

$archivo = $_FILES['archivo'];
if ($archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] > 5 * 1024 * 1024) {
    volverConMensaje('El archivo no es válido o supera el límite de 5 MB.');
}

$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    volverConMensaje('Solo se permiten archivos con formato CSV.');
}

$handle = fopen($archivo['tmp_name'], 'rb');
if ($handle === false) {
    volverConMensaje('No se pudo leer el archivo importado.');
}

$encabezados = fgetcsv($handle, 0, ';');
if ($encabezados === false) {
    fclose($handle);
    volverConMensaje('El archivo CSV está vacío.');
}

$encabezados[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $encabezados[0]);
$encabezados = array_map(static fn($valor): string => strtolower(trim((string) $valor)), $encabezados);

$columnasObligatorias = [
    'codigo', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento',
    'genero', 'curso', 'paralelo', 'turno', 'estado',
];
$faltantes = array_diff($columnasObligatorias, $encabezados);
if ($faltantes !== []) {
    fclose($handle);
    volverConMensaje('Faltan columnas obligatorias: ' . implode(', ', $faltantes) . '.');
}

$indices = array_flip($encabezados);
$permitidos = ['Masculino', 'Femenino'];
$turnos = ['Mañana', 'Tarde'];
$estados = ['Activo', 'En seguimiento', 'Baja', 'Retirado'];
$insertar = $conexion->prepare(
    'INSERT INTO estudiantes
    (codigo, ci, nombres, apellidos, fecha_nacimiento, genero, curso, paralelo, turno, estado, padre, madre, tutor, telefono, direccion)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$buscar = $conexion->prepare('SELECT id_estudiante FROM estudiantes WHERE codigo = ? OR ci = ? LIMIT 1');

$importados = 0;
$errores = [];
$filaNumero = 1;

while (($valores = fgetcsv($handle, 0, ';')) !== false) {
    $filaNumero++;
    if (count(array_filter($valores, static fn($valor): bool => trim((string) $valor) !== '')) === 0) {
        continue;
    }

    $dato = static function (string $columna) use ($indices, $valores): string {
        $indice = $indices[$columna] ?? null;
        return $indice === null ? '' : trim((string) ($valores[$indice] ?? ''));
    };

    $codigo = $dato('codigo');
    $ci = $dato('ci');
    $nombres = $dato('nombres');
    $apellidos = $dato('apellidos');
    $fecha = $dato('fecha_nacimiento');
    $genero = $dato('genero');
    $curso = $dato('curso');
    $paralelo = $dato('paralelo');
    $turno = $dato('turno');
    $estado = $dato('estado');

    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
    if (
        $codigo === '' || $ci === '' || $nombres === '' || $apellidos === '' ||
        !$fechaValida || $fechaValida->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d') ||
        !in_array($genero, $permitidos, true) || !in_array($curso, ['1', '2', '3', '4', '5', '6'], true) ||
        !in_array($paralelo, ['A', 'B', 'C'], true) || !in_array($turno, $turnos, true) ||
        !in_array($estado, $estados, true)
    ) {
        $errores[] = 'Fila ' . $filaNumero . ': contiene datos obligatorios inválidos.';
        continue;
    }

    $buscar->bind_param('ss', $codigo, $ci);
    $buscar->execute();
    if ($buscar->get_result()->num_rows > 0) {
        $errores[] = 'Fila ' . $filaNumero . ': el código o CI ya está registrado.';
        continue;
    }

    $padre = $dato('padre');
    $madre = $dato('madre');
    $tutor = $dato('tutor');
    $telefono = $dato('telefono');
    $direccion = $dato('direccion');
    $insertar->bind_param(
        'sssssssssssssss',
        $codigo, $ci, $nombres, $apellidos, $fecha, $genero, $curso, $paralelo,
        $turno, $estado, $padre, $madre, $tutor, $telefono, $direccion
    );

    if ($insertar->execute()) {
        $importados++;
    } else {
        $errores[] = 'Fila ' . $filaNumero . ': no se pudo guardar.';
    }
}

fclose($handle);
$buscar->close();
$insertar->close();

$mensaje = 'Se importaron ' . $importados . ' estudiante(s).';
if ($errores !== []) {
    $mensaje .= ' ' . implode(' ', array_slice($errores, 0, 5));
    if (count($errores) > 5) {
        $mensaje .= ' Hay más errores en otras filas.';
    }
}

volverConMensaje($mensaje, $importados > 0 ? 'success' : 'warning');
