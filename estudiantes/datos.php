<?php
// Validación y persistencia compartidas por las operaciones de estudiantes.
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
    return $conexion->query("SELECT s.id_seccion, c.nombre AS curso_nombre, p.nombre AS paralelo, s.turno
        FROM secciones s
        INNER JOIN instituciones i ON i.id_institucion = s.id_institucion AND i.estado = 'Activo'
        INNER JOIN cursos c ON c.id_curso = s.id_curso AND c.estado = 'Activo'
        INNER JOIN paralelos p ON p.id_paralelo = s.id_paralelo AND p.estado = 'Activo'
        WHERE s.estado = 'Activo' AND s.gestion = YEAR(CURRENT_DATE())
        ORDER BY c.orden, p.nombre, s.turno")->fetch_all(MYSQLI_ASSOC);
}

function estudiante_resolver_academico(array $catalogo, string $curso, string $paralelo, string $turno = ''): array
{
    $coincidencias = [];
    foreach ($catalogo as $fila) {
        $nombre = $fila['curso_nombre'];
        $grado = null;
        if (preg_match('/^([1-6])(?:ro|do|to|°|º)?(?: de Secundaria)?$/ui', $nombre, $m)) $grado = $m[1];
        if (($nombre === $curso || ($grado !== null && $grado === $curso))
            && $fila['paralelo'] === $paralelo
            && ($turno === '' || $fila['turno'] === $turno)) {
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
    return estudiante_resolver_academico($catalogo, $datos['curso'], $datos['paralelo'], $datos['turno']);
}

function estudiante_cambiar_estado(mysqli $conexion, int $id, string $accion): bool
{
    if ($id <= 0) throw new InvalidArgumentException('El estudiante seleccionado no es válido.');
    if (!in_array($accion, ['retirar', 'reactivar'], true)) throw new InvalidArgumentException('La acción solicitada no es válida.');
    $estado = $accion === 'retirar' ? 'Retirado' : 'Activo';
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('SELECT estado FROM estudiantes WHERE id_estudiante=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $estudiante = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$estudiante) throw new InvalidArgumentException('El estudiante no existe.');

        $stmt = $conexion->prepare("SELECT id_inscripcion,estado FROM inscripciones
            WHERE id_estudiante=? AND estado IN ('Activo','Retirado')
            ORDER BY id_inscripcion DESC LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $inscripcion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $cambio = $estudiante['estado'] !== $estado || ($inscripcion && $inscripcion['estado'] !== $estado);
        $stmt = $conexion->prepare('UPDATE estudiantes SET estado=? WHERE id_estudiante=?');
        $stmt->bind_param('si', $estado, $id);
        $stmt->execute();
        $stmt->close();
        if ($inscripcion) {
            $stmt = $conexion->prepare('UPDATE inscripciones SET estado=? WHERE id_inscripcion=?');
            $stmt->bind_param('si', $estado, $inscripcion['id_inscripcion']);
            $stmt->execute();
            $stmt->close();
        }
        $conexion->commit();
        return $cambio;
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}

function estudiante_actualizar(mysqli $conexion, int $id, array $entrada): void
{
    if ($id <= 0) throw new InvalidArgumentException('El estudiante seleccionado no es válido.');
    $datos = [];
    foreach (['ci' => 20, 'nombres' => 80, 'apellidos' => 80, 'paralelo' => 10, 'estado' => 10] as $campo => $maximo) {
        $valor = $entrada[$campo] ?? null;
        if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || mb_strlen(trim($valor)) > $maximo) {
            throw new InvalidArgumentException("Revise el campo $campo (máximo $maximo caracteres).");
        }
        $datos[$campo] = trim($valor);
    }
    $curso = filter_var($entrada['curso'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$curso || $datos['nombres'] === '' || $datos['apellidos'] === '' || $datos['paralelo'] === ''
        || !in_array($datos['estado'], ['Activo', 'Retirado'], true)) {
        throw new InvalidArgumentException('Complete nombres, apellidos, curso, paralelo y estado correctamente.');
    }
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('SELECT id_estudiante FROM estudiantes WHERE id_estudiante=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$existe) throw new InvalidArgumentException('El estudiante no existe.');
        $stmt = $conexion->prepare("SELECT i.id_inscripcion,s.id_seccion,s.id_institucion,s.turno,s.gestion,c.orden,p.nombre AS paralelo
            FROM inscripciones i INNER JOIN secciones s ON s.id_seccion=i.id_seccion
            INNER JOIN cursos c ON c.id_curso=s.id_curso INNER JOIN paralelos p ON p.id_paralelo=s.id_paralelo
            WHERE i.id_estudiante=? AND i.estado IN ('Activo','Retirado')
            ORDER BY i.id_inscripcion DESC LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $id); $stmt->execute();
        $inscripcion = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$inscripcion) throw new InvalidArgumentException('El estudiante no tiene una inscripción académica que actualizar.');
        $idSeccion = (int)$inscripcion['id_seccion'];
        if ($curso !== (int)$inscripcion['orden'] || $datos['paralelo'] !== $inscripcion['paralelo']) {
            $stmt = $conexion->prepare("SELECT s.id_seccion FROM secciones s
                INNER JOIN cursos c ON c.id_curso=s.id_curso INNER JOIN paralelos p ON p.id_paralelo=s.id_paralelo
                INNER JOIN instituciones i ON i.id_institucion=s.id_institucion
                WHERE c.orden=? AND p.nombre=? AND s.turno=? AND s.gestion=? AND s.id_institucion=?
                  AND s.estado='Activo' AND c.estado='Activo' AND p.estado='Activo' AND i.estado='Activo' LIMIT 2");
            $stmt->bind_param('issii', $curso, $datos['paralelo'], $inscripcion['turno'], $inscripcion['gestion'], $inscripcion['id_institucion']);
            $stmt->execute(); $secciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            if (count($secciones) !== 1) throw new InvalidArgumentException('El curso y paralelo no están disponibles para la institución, gestión y turno del estudiante.');
            $idSeccion = (int)$secciones[0]['id_seccion'];
        }
        $ci = $datos['ci'] !== '' ? $datos['ci'] : null;
        $stmt = $conexion->prepare('UPDATE estudiantes SET ci=?,nombres=?,apellidos=?,estado=? WHERE id_estudiante=?');
        $stmt->bind_param('ssssi', $ci, $datos['nombres'], $datos['apellidos'], $datos['estado'], $id);
        $stmt->execute(); $stmt->close();
        $stmt = $conexion->prepare('UPDATE inscripciones SET id_seccion=?,estado=? WHERE id_inscripcion=?');
        $stmt->bind_param('isi', $idSeccion, $datos['estado'], $inscripcion['id_inscripcion']);
        $stmt->execute(); $stmt->close();
        $conexion->commit();
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}

function estudiante_insertar(mysqli $conexion, array $datos, array $academico): void
{
    $sexo = $datos['genero'] === 'Masculino' ? 'M' : 'F';
    $ci = $datos['ci'] !== '' ? $datos['ci'] : null;
    $fecha = $datos['fecha_nacimiento'] !== '' ? $datos['fecha_nacimiento'] : null;
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('INSERT INTO estudiantes
            (codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, direccion, telefono, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssssss', $datos['codigo'], $ci, $datos['nombres'], $datos['apellidos'],
            $fecha, $sexo, $datos['direccion'], $datos['telefono'], $datos['estado']);
        $stmt->execute();
        $idEstudiante = (int) $conexion->insert_id;
        $stmt->close();

        $stmt = $conexion->prepare('INSERT INTO inscripciones
            (id_estudiante, id_seccion, fecha_inscripcion, estado)
            VALUES (?, ?, CURRENT_DATE(), ?)');
        $stmt->bind_param('iis', $idEstudiante, $academico['id_seccion'], $datos['estado']);
        $stmt->execute();
        $stmt->close();

        $stmtResponsable = $conexion->prepare('INSERT INTO responsables (nombres) VALUES (?)');
        $stmtVinculo = $conexion->prepare('INSERT INTO estudiante_responsables
            (id_estudiante, id_responsable, parentesco, es_principal) VALUES (?, ?, ?, ?)');
        foreach (['padre' => 'Padre', 'madre' => 'Madre', 'tutor' => 'Tutor'] as $campo => $parentesco) {
            if ($datos[$campo] === '') continue;
            $stmtResponsable->bind_param('s', $datos[$campo]);
            $stmtResponsable->execute();
            $idResponsable = (int) $conexion->insert_id;
            $principal = $campo === 'tutor' ? 1 : 0;
            $stmtVinculo->bind_param('iisi', $idEstudiante, $idResponsable, $parentesco, $principal);
            $stmtVinculo->execute();
        }
        $stmtResponsable->close();
        $stmtVinculo->close();
        $conexion->commit();
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}
