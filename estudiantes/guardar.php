<?php

require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}

$campos = [
    'codigo', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'genero',
    'curso', 'paralelo', 'turno', 'estado', 'padre', 'madre', 'tutor',
    'telefono', 'direccion',
];

$datos = [];
foreach ($campos as $campo) {
    $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
}

$generos = ['Masculino', 'Femenino'];
$turnos = ['Mañana', 'Tarde'];
$estados = ['Activo', 'En seguimiento', 'Baja'];

if (
    $datos['codigo'] === '' || $datos['ci'] === '' || $datos['nombres'] === '' ||
    $datos['apellidos'] === '' || $datos['fecha_nacimiento'] === '' ||
    !in_array($datos['genero'], $generos, true) || !in_array($datos['curso'], ['1', '2', '3', '4', '5', '6'], true) ||
    !in_array($datos['paralelo'], ['A', 'B', 'C'], true) || !in_array($datos['turno'], $turnos, true) ||
    !in_array($datos['estado'], $estados, true)
) {
    header('Location: registrar.php?error=datos');
    exit;
}

$fecha = DateTime::createFromFormat('Y-m-d', $datos['fecha_nacimiento']);
if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_nacimiento'] || $datos['fecha_nacimiento'] > date('Y-m-d')) {
    header('Location: registrar.php?error=fecha');
    exit;
}

$duplicado = $conexion->prepare('SELECT id_estudiante FROM estudiantes WHERE codigo = ? OR ci = ? LIMIT 1');
$duplicado->bind_param('ss', $datos['codigo'], $datos['ci']);
$duplicado->execute();
if ($duplicado->get_result()->num_rows > 0) {
    $duplicado->close();
    header('Location: registrar.php?error=duplicado');
    exit;
}
$duplicado->close();

$sql = 'INSERT INTO estudiantes (codigo, ci, nombres, apellidos, fecha_nacimiento, genero, curso, paralelo, turno, estado, padre, madre, tutor, telefono, direccion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = $conexion->prepare($sql);
$stmt->bind_param(
    'sssssssssssssss',
    $datos['codigo'], $datos['ci'], $datos['nombres'], $datos['apellidos'],
    $datos['fecha_nacimiento'], $datos['genero'], $datos['curso'], $datos['paralelo'],
    $datos['turno'], $datos['estado'], $datos['padre'], $datos['madre'],
    $datos['tutor'], $datos['telefono'], $datos['direccion']
);

if (!$stmt->execute()) {
    $stmt->close();
    header('Location: registrar.php?error=guardar');
    exit;
}

$stmt->close();

$_SESSION['mensaje'] = 'El estudiante se registró correctamente.';
$_SESSION['tipo_mensaje'] = 'success';

header('Location: listar.php');
exit;
