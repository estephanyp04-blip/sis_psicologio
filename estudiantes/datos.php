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
    return $conexion->query("SELECT s.cod_seccion, c.nombre AS curso_nombre, p.nombre AS paralelo, s.turno
        FROM secciones s
        INNER JOIN cursos c ON c.cod_curso = s.cod_curso AND c.estado = 'Activo'
        INNER JOIN paralelos p ON p.cod_paralelo = s.cod_paralelo AND p.estado = 'Activo'
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
        $stmt = $conexion->prepare('SELECT estado FROM estudiantes WHERE cod_estudiante=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $estudiante = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$estudiante) throw new InvalidArgumentException('El estudiante no existe.');

        $stmt = $conexion->prepare("SELECT cod_historial_academico,estado FROM historial_academico
            WHERE cod_estudiante=? AND estado IN ('Activo','Retirado')
            ORDER BY cod_historial_academico DESC LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $inscripcion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $cambio = $estudiante['estado'] !== $estado || ($inscripcion && $inscripcion['estado'] !== $estado);
        $stmt = $conexion->prepare('UPDATE estudiantes SET estado=? WHERE cod_estudiante=?');
        $stmt->bind_param('si', $estado, $id);
        $stmt->execute();
        $stmt->close();
        if ($inscripcion) {
            $stmt = $conexion->prepare('UPDATE historial_academico SET estado=? WHERE cod_historial_academico=?');
            $stmt->bind_param('si', $estado, $inscripcion['cod_historial_academico']);
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
    foreach (['codigo' => 20, 'ci' => 20, 'nombres' => 80, 'apellidos' => 80, 'paralelo' => 10,
        'estado' => 10, 'turno' => 20, 'genero' => 1, 'padre' => 150, 'madre' => 150,
        'tutor' => 150, 'telefono' => 25, 'direccion' => 255] as $campo => $maximo) {
        $valor = $entrada[$campo] ?? null;
        if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || mb_strlen(trim($valor)) > $maximo) {
            throw new InvalidArgumentException("Revise el campo $campo (máximo $maximo caracteres).");
        }
        $datos[$campo] = trim($valor);
    }
    $fechaNacimiento = $entrada['fecha_nacimiento'] ?? null;
    if (!is_string($fechaNacimiento) || ($fechaNacimiento !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento)
        || !checkdate((int)substr($fechaNacimiento, 5, 2), (int)substr($fechaNacimiento, 8, 2), (int)substr($fechaNacimiento, 0, 4))
        || $fechaNacimiento > date('Y-m-d')))) {
        throw new InvalidArgumentException('La fecha de nacimiento no es válida.');
    }
    $curso = filter_var($entrada['curso'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$curso || $datos['codigo'] === '' || $datos['nombres'] === '' || $datos['apellidos'] === '' || $datos['paralelo'] === ''
        || !in_array($datos['estado'], ['Activo', 'Retirado'], true)
        || !in_array($datos['turno'], ['Mañana', 'Tarde'], true)
        || !in_array($datos['genero'], ['M', 'F'], true)) {
        throw new InvalidArgumentException('Complete código, nombres, apellidos, curso, paralelo, turno, género y estado correctamente.');
    }
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('SELECT cod_estudiante FROM estudiantes WHERE cod_estudiante=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$existe) throw new InvalidArgumentException('El estudiante no existe.');
        $stmt = $conexion->prepare("SELECT i.cod_historial_academico,s.cod_seccion,s.turno,s.gestion,c.orden,p.nombre AS paralelo
            FROM historial_academico i INNER JOIN secciones s ON s.cod_seccion=i.cod_seccion
            INNER JOIN cursos c ON c.cod_curso=s.cod_curso INNER JOIN paralelos p ON p.cod_paralelo=s.cod_paralelo
            WHERE i.cod_estudiante=? AND i.estado IN ('Activo','Retirado')
            ORDER BY i.cod_historial_academico DESC LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $id); $stmt->execute();
        $inscripcion = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$inscripcion) throw new InvalidArgumentException('El estudiante no tiene una inscripción académica que actualizar.');
        $idSeccion = (int)$inscripcion['cod_seccion'];
        if ($curso !== (int)$inscripcion['orden'] || $datos['paralelo'] !== $inscripcion['paralelo'] || $datos['turno'] !== $inscripcion['turno']) {
            $stmt = $conexion->prepare("SELECT s.cod_seccion FROM secciones s
                INNER JOIN cursos c ON c.cod_curso=s.cod_curso INNER JOIN paralelos p ON p.cod_paralelo=s.cod_paralelo
                WHERE c.orden=? AND p.nombre=? AND s.turno=? AND s.gestion=?
                  AND s.estado='Activo' AND c.estado='Activo' AND p.estado='Activo' LIMIT 2");
            $stmt->bind_param('issi', $curso, $datos['paralelo'], $datos['turno'], $inscripcion['gestion']);
            $stmt->execute(); $secciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            if (count($secciones) !== 1) throw new InvalidArgumentException('El curso, paralelo, gestion y turno no identifican una seccion activa unica.');
            $idSeccion = (int)$secciones[0]['cod_seccion'];
        }
        $ci = $datos['ci'] !== '' ? $datos['ci'] : null;
        $fecha = $fechaNacimiento !== '' ? $fechaNacimiento : null;
        $sexo = $datos['genero'];
        $stmt = $conexion->prepare('UPDATE estudiantes SET codigo=?,ci=?,nombres=?,apellidos=?,fecha_nacimiento=?,sexo=?,direccion=?,telefono=?,estado=? WHERE cod_estudiante=?');
        $stmt->bind_param('sssssssssi', $datos['codigo'], $ci, $datos['nombres'], $datos['apellidos'], $fecha,
            $sexo, $datos['direccion'], $datos['telefono'], $datos['estado'], $id);
        $stmt->execute(); $stmt->close();
        $stmt = $conexion->prepare('UPDATE historial_academico SET cod_seccion=?,estado=? WHERE cod_historial_academico=?');
        $stmt->bind_param('isi', $idSeccion, $datos['estado'], $inscripcion['cod_historial_academico']);
        $stmt->execute(); $stmt->close();

        foreach (['padre' => 'Padre', 'madre' => 'Madre', 'tutor' => 'Tutor'] as $campo => $parentesco) {
            $stmt = $conexion->prepare('SELECT er.cod_responsable FROM estudiante_responsables er
                WHERE er.cod_estudiante=? AND er.parentesco=? ORDER BY er.es_principal DESC LIMIT 1 FOR UPDATE');
            $stmt->bind_param('is', $id, $parentesco); $stmt->execute();
            $responsable = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($datos[$campo] === '') {
                $stmt = $conexion->prepare('DELETE FROM estudiante_responsables WHERE cod_estudiante=? AND parentesco=?');
                $stmt->bind_param('is', $id, $parentesco); $stmt->execute(); $stmt->close();
                continue;
            }
            if ($responsable) {
                $idResponsable = (int)$responsable['cod_responsable'];
                $stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM estudiante_responsables WHERE cod_responsable=?');
                $stmt->bind_param('i', $idResponsable); $stmt->execute();
                $compartido = (int)$stmt->get_result()->fetch_assoc()['total'] > 1; $stmt->close();
                if (!$compartido) {
                    $stmt = $conexion->prepare('UPDATE responsables SET nombres=? WHERE cod_responsable=?');
                    $stmt->bind_param('si', $datos[$campo], $idResponsable); $stmt->execute(); $stmt->close();
                    continue;
                }
                $stmt = $conexion->prepare('DELETE FROM estudiante_responsables WHERE cod_estudiante=? AND parentesco=?');
                $stmt->bind_param('is', $id, $parentesco); $stmt->execute(); $stmt->close();
            }
            $stmt = $conexion->prepare('INSERT INTO responsables (nombres) VALUES (?)');
            $stmt->bind_param('s', $datos[$campo]); $stmt->execute();
            $idResponsable = (int)$conexion->insert_id; $stmt->close();
            $principal = $campo === 'tutor' ? 1 : 0;
            $stmt = $conexion->prepare('INSERT INTO estudiante_responsables (cod_estudiante,cod_responsable,parentesco,es_principal) VALUES (?,?,?,?)');
            $stmt->bind_param('iisi', $id, $idResponsable, $parentesco, $principal); $stmt->execute(); $stmt->close();
        }
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

        $stmt = $conexion->prepare('INSERT INTO historial_academico
            (cod_estudiante, cod_seccion, fecha_inicio, estado)
            VALUES (?, ?, CURRENT_DATE(), ?)');
        $stmt->bind_param('iis', $idEstudiante, $academico['cod_seccion'], $datos['estado']);
        $stmt->execute();
        $stmt->close();

        $stmtResponsable = $conexion->prepare('INSERT INTO responsables (nombres) VALUES (?)');
        $stmtVinculo = $conexion->prepare('INSERT INTO estudiante_responsables
            (cod_estudiante, cod_responsable, parentesco, es_principal) VALUES (?, ?, ?, ?)');
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
