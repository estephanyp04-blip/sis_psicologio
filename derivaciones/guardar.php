<?php
require_once '../config/conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}
// Lectura y recuperación de los datos del formulario.
function textoDerivacion(string $campo): string
{
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}
function enteroDerivacion($valor): int
{
    if (!is_int($valor) && !is_string($valor)) {
        return 0;
    }
    return (int) (filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0);
}
function errorDerivacion(string $mensaje): void
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}
function escaparDerivacion($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$idUsuario = enteroDerivacion($_SESSION['id_usuario'] ?? 0);
$idEstudiante = enteroDerivacion($_POST['id_estudiante'] ?? 0);
$idDocenteElegido = enteroDerivacion($_POST['id_docente'] ?? 0);
$fecha = textoDerivacion('fecha');
$materia = textoDerivacion('materia');
$motivo = textoDerivacion('motivo');
$observaciones = textoDerivacion('observaciones');
$prioridad = textoDerivacion('prioridad');
$categorias = isset($_POST['categorias']) && is_array($_POST['categorias']) ? $_POST['categorias'] : [];
$solicitarCita = isset($_POST['solicitar_cita']) ? 1 : 0;
$idProfesional = enteroDerivacion($_POST['id_profesional'] ?? 0);
$categoriasPermitidas = ['Rendimiento Académico', 'Conducta en Aula', 'Social / Emocional', 'Dinámica Familiar', 'Acoso escolar / Acoso', 'Otro'];
$categoriasLimpias = [];
foreach ($categorias as $categoria) {
    if (is_string($categoria) && in_array(trim($categoria), $categoriasPermitidas, true)) {
        $categoriasLimpias[] = trim($categoria);
    }
}
$categoriasLimpias = array_values(array_unique($categoriasLimpias));
$datos = [
    'id_estudiante' => $idEstudiante, 'id_docente' => $idDocenteElegido,
    'fecha' => $fecha, 'materia' => $materia, 'motivo' => $motivo,
    'observaciones' => $observaciones, 'prioridad' => $prioridad,
    'categorias' => $categoriasLimpias, 'solicitar_cita' => $solicitarCita,
    'id_profesional' => $idProfesional
];
$_SESSION['datos_derivacion'] = $datos;
// El usuario debe estar autenticado para identificar al responsable.
if ($idUsuario <= 0) {
    errorDerivacion('Inicie sesión con una cuenta de Docente o Administrador antes de registrar la derivación.');
}
try {
    $stmtUsuario = $conexion->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ? AND estado = 'Activo' LIMIT 1");
    $stmtUsuario->bind_param('i', $idUsuario);
    $stmtUsuario->execute();
    $usuarioActual = $stmtUsuario->get_result()->fetch_assoc();
    $stmtUsuario->close();
    $idRol = (int) ($usuarioActual['id_rol'] ?? 0);
    if (!in_array($idRol, [1, 3], true)) {
        errorDerivacion('Su cuenta no tiene permiso para registrar derivaciones.');
    }
    // Validaciones del reporte.
    $errores = [];
    if ($idEstudiante <= 0) {
        $errores[] = 'Debe seleccionar un estudiante.';
    }
    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        $errores[] = 'La fecha del reporte no es válida.';
    } elseif ($fecha > date('Y-m-d')) {
        $errores[] = 'La fecha del reporte no puede ser futura.';
    }
    $materiasPermitidas = ['Matemática', 'Lenguaje y Comunicación', 'Ciencias Naturales', 'Ciencias Sociales', 'Biología', 'Física', 'Química', 'Inglés', 'Educación Física', 'Artes Plásticas', 'Música', 'Tecnología', 'Valores', 'Otra'];
    if (!in_array($materia, $materiasPermitidas, true)) {
        $errores[] = 'Debe seleccionar una materia válida.';
    }
    if (!$categoriasLimpias) {
        $errores[] = 'Debe seleccionar al menos una categoría observada válida.';
    }
    if ($motivo === '') {
        $errores[] = 'Debe escribir la descripción detallada de la situación.';
    }
    if (!in_array($prioridad, ['Alta', 'Media', 'Baja'], true)) {
        $errores[] = 'La prioridad seleccionada no es válida.';
    }
    if (mb_strlen($materia) > 100) {
        $errores[] = 'La materia no puede superar los 100 caracteres.';
    }
    if (mb_strlen($motivo) > 2000) {
        $errores[] = 'La descripción no puede superar los 2000 caracteres.';
    }
    if (mb_strlen($observaciones) > 3000) {
        $errores[] = 'Las observaciones no pueden superar los 3000 caracteres.';
    }
    if ($errores) {
        errorDerivacion(implode("\n", $errores));
    }
    $stmtEstudiante = $conexion->prepare("SELECT id_estudiante FROM estudiantes WHERE id_estudiante = ? AND estado = 'Activo' LIMIT 1");
    $stmtEstudiante->bind_param('i', $idEstudiante);
    $stmtEstudiante->execute();
    $existeEstudiante = $stmtEstudiante->get_result()->num_rows > 0;
    $stmtEstudiante->close();
    if (!$existeEstudiante) {
        errorDerivacion('El estudiante seleccionado no existe o no está activo.');
    }
    // Un docente solo puede registrar derivaciones a su propio nombre.
    if ($idRol === 3) {
        $stmtDocente = $conexion->prepare('SELECT id_docente FROM docentes WHERE id_usuario = ? LIMIT 2');
        $stmtDocente->bind_param('i', $idUsuario);
        $stmtDocente->execute();
        $resultadoDocente = $stmtDocente->get_result();
        if ($resultadoDocente->num_rows !== 1) {
            $stmtDocente->close();
            unset($_SESSION['id_docente']);
            errorDerivacion('Su cuenta no tiene un docente asociado de forma única. Solicite al administrador revisar la asignación de su cuenta.');
        }
        $idDocente = (int) $resultadoDocente->fetch_assoc()['id_docente'];
        $stmtDocente->close();
        $_SESSION['id_docente'] = $idDocente;
    } else {
        // El administrador elige al docente; no se asigna uno por defecto.
        $idDocente = $idDocenteElegido;
        if ($idDocente <= 0) {
            $resultadoDocentes = $conexion->query('SELECT id_docente, nombres, apellidos FROM docentes ORDER BY apellidos, nombres');
            $docentes = $resultadoDocentes->fetch_all(MYSQLI_ASSOC);
            $resultadoDocentes->free();
            if (!$docentes) {
                errorDerivacion('Primero registre un docente para poder asignarle esta derivación.');
            }
            // Los campos validados se envían de nuevo al confirmar al responsable.
            include '../includes/header.php';
            include '../includes/sidebar.php';
            include '../includes/navbar.php';
            ?>
            <div class="main-content">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h4">Docente responsable</h1>
                        <p>Seleccione al docente que realizó la derivación. Los datos del reporte se conservarán al continuar.</p>
                        <form action="guardar.php" method="POST">
                            <?php foreach ($datos as $campo => $valor): ?>
                                <?php if ($campo === 'id_docente' || ($campo === 'solicitar_cita' && !$valor)) { continue; } ?>
                                <?php if (is_array($valor)): ?>
                                    <?php foreach ($valor as $elemento): ?>
                                        <input type="hidden" name="<?= escaparDerivacion($campo) ?>[]" value="<?= escaparDerivacion($elemento) ?>">
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <input type="hidden" name="<?= escaparDerivacion($campo) ?>" value="<?= escaparDerivacion($valor) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <label class="form-label" for="id_docente">Docente <span class="text-danger">*</span></label>
                            <select class="form-select mb-3" id="id_docente" name="id_docente" required>
                                <option value="">Seleccione un docente...</option>
                                <?php foreach ($docentes as $docente): ?>
                                    <option value="<?= (int) $docente['id_docente'] ?>"><?= escaparDerivacion($docente['apellidos'] . ' ' . $docente['nombres']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Guardar derivación</button>
                            <a class="btn btn-outline-secondary" href="registrar.php">Volver al formulario</a>
                        </form>
                    </div>
                </div>
            </div>
            </body>
            </html>
            <?php
            exit;
        }
        $stmtDocente = $conexion->prepare('SELECT id_docente FROM docentes WHERE id_docente = ? LIMIT 1');
        $stmtDocente->bind_param('i', $idDocente);
        $stmtDocente->execute();
        $existeDocente = $stmtDocente->get_result()->num_rows === 1;
        $stmtDocente->close();
        if (!$existeDocente) {
            errorDerivacion('El docente seleccionado no existe. Seleccione un docente válido.');
        }
    }
    // Profesional solicitado y observaciones, como en el formulario original.
    $nombreProfesional = '';
    if ($solicitarCita === 1 && $idProfesional > 0) {
        $stmtProfesional = $conexion->prepare("SELECT nombre, apellido FROM usuarios WHERE id_usuario = ? AND id_rol = 2 AND estado = 'Activo' LIMIT 1");
        $stmtProfesional->bind_param('i', $idProfesional);
        $stmtProfesional->execute();
        $profesional = $stmtProfesional->get_result()->fetch_assoc();
        $stmtProfesional->close();
        if (!$profesional) {
            errorDerivacion('El profesional seleccionado no existe, no está activo o no tiene el rol de Psicóloga.');
        }
        $nombreProfesional = trim($profesional['nombre'] . ' ' . ($profesional['apellido'] ?? ''));
    }
    $partesObservaciones = ['Categorías observadas: ' . implode(', ', $categoriasLimpias)];
    if ($observaciones !== '') {
        $partesObservaciones[] = 'Observaciones adicionales: ' . $observaciones;
    }
    $partesObservaciones[] = 'Solicitud de cita psicológica: ' . ($solicitarCita === 1 ? 'Sí' : 'No');
    if ($nombreProfesional !== '') {
        $partesObservaciones[] = 'Profesional solicitado: ' . $nombreProfesional;
    }
    $observacionesFinales = implode(PHP_EOL . PHP_EOL, $partesObservaciones);
    $estado = 'Pendiente';
    // Guardar únicamente con un docente existente y validado.
    if ($idDocente <= 0) {
        errorDerivacion('No se pudo identificar al docente responsable. Solicite al administrador revisar su registro.');
    }
    // Conservar el vínculo con la psicóloga elegida para la solicitud.
    $idProfesionalGuardar = $solicitarCita === 1 && $idProfesional > 0 ? $idProfesional : null;
    $stmt = $conexion->prepare('INSERT INTO derivaciones (fecha, id_estudiante, id_docente, materia, motivo, observaciones, prioridad, estado, id_profesional, solicitar_cita) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('siisssssii', $fecha, $idEstudiante, $idDocente, $materia, $motivo, $observacionesFinales, $prioridad, $estado, $idProfesionalGuardar, $solicitarCita);
    $stmt->execute();
    $stmt->close();
    unset($_SESSION['datos_derivacion']);
    $_SESSION['mensaje'] = 'La derivación se registró correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
    exit;
} catch (mysqli_sql_exception $error) {
    error_log('Error en derivaciones/guardar.php: ' . $error->getMessage());
    errorDerivacion('No se pudo guardar la derivación. Los datos del formulario se conservaron. Inténtelo de nuevo o comuníquese con el administrador.');
}
