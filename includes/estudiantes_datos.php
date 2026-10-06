<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

const ESTUDIANTE_CAMPOS = ['codigo', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'genero',
    'curso', 'paralelo', 'turno', 'estado', 'padre', 'madre', 'tutor', 'telefono', 'direccion'];

function estudiante_datos(array $entrada): array
{
    $datos = [];
    foreach (ESTUDIANTE_CAMPOS as $campo) {
        $datos[$campo] = is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';
    }
    return $datos;
}

// El número visible es el grado; nunca se interpreta como la clave primaria.
function estudiante_catalogo(mysqli $conexion): array
{
    return $conexion->query("SELECT c.id_curso, c.nombre AS curso_nombre, p.id_paralelo, p.nombre AS paralelo
        FROM cursos c INNER JOIN paralelos p ON p.id_curso = c.id_curso
        WHERE c.estado = 'Activo' AND p.estado = 'Activo' ORDER BY c.nombre, p.nombre")->fetch_all(MYSQLI_ASSOC);
}

function estudiante_resolver_academico(array $catalogo, string $curso, string $paralelo): array
{
    $coincidencias = [];
    foreach ($catalogo as $fila) {
        $nombre = $fila['curso_nombre'];
        $grado = null;
        if (preg_match('/^([1-6])(?:ro|do|to|°|º)?(?: de Secundaria)?$/ui', $nombre, $m)) $grado = $m[1];
        if (($nombre === $curso || ($grado !== null && $grado === $curso)) && $fila['paralelo'] === $paralelo) {
            $fila['curso'] = $grado ?? $nombre;
            $coincidencias[] = $fila;
        }
    }
    if (count($coincidencias) !== 1) {
        throw new InvalidArgumentException('El curso y paralelo deben identificar una única combinación activa del catálogo.');
    }
    if (mb_strlen($coincidencias[0]['curso']) > 10) {
        throw new InvalidArgumentException('El nombre de curso no es compatible con el campo académico.');
    }
    return $coincidencias[0];
}

function estudiante_validar(array $datos, array $catalogo): array
{
    foreach (['codigo', 'ci', 'nombres', 'apellidos'] as $campo) {
        if ($datos[$campo] === '') throw new InvalidArgumentException('Complete código, CI, nombres y apellidos.');
    }
    foreach (['codigo' => 20, 'ci' => 20, 'nombres' => 80, 'apellidos' => 80,
        'padre' => 150, 'madre' => 150, 'tutor' => 150, 'telefono' => 25, 'direccion' => 255] as $campo => $maximo) {
        if (!mb_check_encoding($datos[$campo], 'UTF-8') || mb_strlen($datos[$campo]) > $maximo) {
            throw new InvalidArgumentException("El campo $campo debe estar en UTF-8 y tener como máximo $maximo caracteres.");
        }
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha_nacimiento']);
    if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_nacimiento'] || $datos['fecha_nacimiento'] > date('Y-m-d')) {
        throw new InvalidArgumentException('La fecha de nacimiento no es válida.');
    }
    if (!in_array($datos['genero'], ['Masculino', 'Femenino'], true)
        || !in_array($datos['turno'], ['Mañana', 'Tarde'], true)
        || !in_array($datos['estado'], ['Activo', 'Retirado'], true)) {
        throw new InvalidArgumentException('Revise género, turno y estado (Activo o Retirado).');
    }
    return estudiante_resolver_academico($catalogo, $datos['curso'], $datos['paralelo']);
}

function estudiante_insertar(mysqli $conexion, array $datos, array $academico): void
{
    $sexo = $datos['genero'] === 'Masculino' ? 'M' : 'F';
    $stmt = $conexion->prepare('INSERT INTO estudiantes
        (codigo, ci, nombres, apellidos, fecha_nacimiento, genero, curso, id_curso, paralelo,
        id_paralelo, turno, estado, padre, madre, tutor, telefono, direccion, sexo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssssisissssssss', $datos['codigo'], $datos['ci'], $datos['nombres'],
        $datos['apellidos'], $datos['fecha_nacimiento'], $datos['genero'], $academico['curso'],
        $academico['id_curso'], $academico['paralelo'], $academico['id_paralelo'], $datos['turno'],
        $datos['estado'], $datos['padre'], $datos['madre'], $datos['tutor'], $datos['telefono'], $datos['direccion'], $sexo);
    try { $stmt->execute(); } finally { $stmt->close(); }
}