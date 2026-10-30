<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/guardar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/datos.php';
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
$idUsuario = enteroDerivacion($_SESSION['cod_usuario'] ?? 0);
$idEstudiante = enteroDerivacion($_POST['cod_estudiante'] ?? 0);
$idDocenteElegido = enteroDerivacion($_POST['cod_docente'] ?? 0);
$fecha = textoDerivacion('fecha');
$materia = textoDerivacion('materia');
$motivo = textoDerivacion('motivo');
$observaciones = textoDerivacion('observaciones');
$descripcionImagen = textoDerivacion('descripcion_imagen');
$prioridad = textoDerivacion('prioridad');
$categorias = isset($_POST['categorias']) && is_array($_POST['categorias']) ? $_POST['categorias'] : [];
$solicitarCita = isset($_POST['solicitar_cita']) ? 1 : 0;
$idProfesional = enteroDerivacion($_POST['cod_profesional'] ?? 0);
$categoriasDisponibles = $conexion->query("SELECT cod_categoria,nombre FROM categorias_derivacion WHERE estado='Activo'")->fetch_all(MYSQLI_ASSOC);
$categoriasPorNombre = [];
foreach ($categoriasDisponibles as $categoria) $categoriasPorNombre[$categoria['nombre']] = (int)$categoria['cod_categoria'];
$categoriasLimpias = [];
foreach ($categorias as $categoria) {
    if (is_string($categoria) && isset($categoriasPorNombre[trim($categoria)])) $categoriasLimpias[] = trim($categoria);
}
$categoriasLimpias = array_values(array_unique($categoriasLimpias));
$datos = [
    'cod_estudiante' => $idEstudiante, 'cod_docente' => $idDocenteElegido,
    'fecha' => $fecha, 'materia' => $materia, 'motivo' => $motivo,
    'observaciones' => $observaciones, 'prioridad' => $prioridad,
    'descripcion_imagen' => $descripcionImagen,
    'categorias' => $categoriasLimpias, 'solicitar_cita' => $solicitarCita,
    'cod_profesional' => $idProfesional
];
$_SESSION['datos_derivacion'] = $datos;
// El usuario debe estar autenticado para identificar al responsable.
if ($idUsuario <= 0) {
    errorDerivacion('Inicie sesión con una cuenta de Docente o Administrador antes de registrar la derivación.');
}
try {
    $stmtUsuario = $conexion->prepare("SELECT cod_usuario FROM usuarios WHERE cod_usuario = ? AND estado = 'Activo' LIMIT 1");
    $stmtUsuario->bind_param('i', $idUsuario);
    $stmtUsuario->execute();
    $usuarioActual = $stmtUsuario->get_result()->fetch_assoc();
    $stmtUsuario->close();
    if (!$usuarioActual) errorDerivacion('La cuenta ya no está activa.');
    $descripcionImagen = derivacion_imagen_descripcion($descripcionImagen);
    $subidaImagen = derivacion_imagen_validar($_FILES['imagen'] ?? null, $descripcionImagen);
    if ($subidaImagen === null && $descripcionImagen !== '') {
        throw new InvalidArgumentException('Adjunte una imagen para guardar su descripción.');
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
    $stmtMateria = $conexion->prepare("SELECT cod_materia FROM materias WHERE nombre=? AND estado='Activo' LIMIT 1");
    $stmtMateria->bind_param('s', $materia);
    $stmtMateria->execute();
    $idMateria = (int)($stmtMateria->get_result()->fetch_assoc()['cod_materia'] ?? 0);
    $stmtMateria->close();
    if (!$idMateria) $errores[] = 'Debe seleccionar una materia activa del catálogo.';
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
    $stmtEstudiante = $conexion->prepare("SELECT cod_estudiante FROM estudiantes WHERE cod_estudiante = ? AND estado = 'Activo' LIMIT 1");
    $stmtEstudiante->bind_param('i', $idEstudiante);
    $stmtEstudiante->execute();
    $existeEstudiante = $stmtEstudiante->get_result()->num_rows > 0;
    $stmtEstudiante->close();
    if (!$existeEstudiante) {
        errorDerivacion('El estudiante seleccionado no existe o no está activo.');
    }
    // Las cuentas vinculadas a un perfil docente solo pueden atribuirse sus propios reportes.
    $idDocenteSesion = (int) ($_SESSION['cod_docente'] ?? 0);
    if ($idDocenteSesion > 0) {
        $stmtDocente = $conexion->prepare("SELECT d.cod_docente FROM docentes d
            INNER JOIN usuarios u ON u.cod_usuario=d.cod_usuario
            WHERE u.cod_usuario=? AND d.cod_docente=? AND d.estado='Activo' LIMIT 1");
        $stmtDocente->bind_param('ii', $idUsuario, $idDocenteSesion);
        $stmtDocente->execute();
        $docenteVinculado = $stmtDocente->get_result()->fetch_assoc();
        $stmtDocente->close();
        if (!$docenteVinculado) errorDerivacion('El perfil docente vinculado a su cuenta ya no está activo.');
        $idDocente = $idDocenteSesion;
    } else {
        if ($subidaImagen !== null && $idDocenteElegido <= 0) {
            errorDerivacion('Seleccione el docente responsable antes de adjuntar la imagen.');
        }
        // El administrador elige al docente; no se asigna uno por defecto.
        $idDocente = $idDocenteElegido;
        if ($idDocente <= 0) {
            $resultadoDocentes = $conexion->query("SELECT d.cod_docente,u.nombres,u.apellidos
                FROM docentes d INNER JOIN usuarios u ON u.cod_usuario=d.cod_usuario
                WHERE d.estado='Activo' ORDER BY u.apellidos,u.nombres");
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
                            <?= login_campo_csrf() ?>
                            <?php foreach ($datos as $campo => $valor): ?>
                                <?php if ($campo === 'cod_docente' || ($campo === 'solicitar_cita' && !$valor)) { continue; } ?>
                                <?php if (is_array($valor)): ?>
                                    <?php foreach ($valor as $elemento): ?>
                                        <input type="hidden" name="<?= escaparDerivacion($campo) ?>[]" value="<?= escaparDerivacion($elemento) ?>">
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <input type="hidden" name="<?= escaparDerivacion($campo) ?>" value="<?= escaparDerivacion($valor) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <label class="form-label" for="cod_docente">Docente <span class="text-danger">*</span></label>
                            <select class="form-select mb-3" id="cod_docente" name="cod_docente" required>
                                <option value="">Seleccione un docente...</option>
                                <?php foreach ($docentes as $docente): ?>
                                    <option value="<?= (int) $docente['cod_docente'] ?>"><?= escaparDerivacion($docente['apellidos'] . ' ' . $docente['nombres']) ?></option>
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
        $stmtDocente = $conexion->prepare("SELECT cod_docente FROM docentes WHERE cod_docente = ? AND estado='Activo' LIMIT 1");
        $stmtDocente->bind_param('i', $idDocente);
        $stmtDocente->execute();
        $existeDocente = $stmtDocente->get_result()->num_rows === 1;
        $stmtDocente->close();
        if (!$existeDocente) {
            errorDerivacion('El docente seleccionado no existe. Seleccione un docente válido.');
        }
    }
    $stmtMateriaDocente = $conexion->prepare("SELECT 1
        FROM docente_materias dm
        INNER JOIN materias m ON m.cod_materia=dm.cod_materia
        WHERE dm.cod_docente=? AND dm.cod_materia=? AND m.estado='Activo'
        LIMIT 1");
    $stmtMateriaDocente->bind_param('ii', $idDocente, $idMateria);
    $stmtMateriaDocente->execute();
    $materiaAsignada = $stmtMateriaDocente->get_result()->num_rows === 1;
    $stmtMateriaDocente->close();
    if (!$materiaAsignada) {
        errorDerivacion('La materia seleccionada no está asignada al docente.');
    }
    // Profesional solicitado y observaciones, como en el formulario original.
    $nombreProfesional = '';
    if ($solicitarCita === 1 && $idProfesional > 0) {
        $stmtProfesional = $conexion->prepare("SELECT u.nombres AS nombre,u.apellidos AS apellido
            FROM usuarios u
            WHERE u.cod_usuario = ? AND u.cod_rol = 2 AND u.estado = 'Activo' LIMIT 1");
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
    $archivoGuardado = null;
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('INSERT INTO derivaciones
            (fecha,cod_estudiante,cod_docente,cod_materia,motivo,observaciones,prioridad,estado,cod_psicologa_solicitada,solicitar_cita)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('siiissssii', $fecha, $idEstudiante, $idDocente, $idMateria, $motivo, $observacionesFinales, $prioridad, $estado, $idProfesionalGuardar, $solicitarCita);
        $stmt->execute();
        $idDerivacion = (int)$conexion->insert_id;
        $stmt->close();
        $stmtCategoria = $conexion->prepare('INSERT INTO derivacion_categorias (cod_derivacion,cod_categoria) VALUES (?,?)');
        foreach ($categoriasLimpias as $categoria) {
            $idCategoria = $categoriasPorNombre[$categoria];
            $stmtCategoria->bind_param('ii', $idDerivacion, $idCategoria);
            $stmtCategoria->execute();
        }
        $stmtCategoria->close();
        if ($subidaImagen !== null) {
            $archivoGuardado = derivacion_imagen_guardar($conexion, $idDerivacion, $subidaImagen);
        }
        $conexion->commit();
    } catch (Throwable $error) {
        $conexion->rollback();
        if ($archivoGuardado !== null) @unlink($archivoGuardado['local']);
        throw $error;
    }
    unset($_SESSION['datos_derivacion']);
    $_SESSION['mensaje'] = 'La derivación se registró correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
    exit;
} catch (InvalidArgumentException $error) {
    errorDerivacion($error->getMessage());
} catch (Throwable $error) {
    error_log('Error en derivaciones/guardar.php: ' . $error->getMessage());
    errorDerivacion('No se pudo guardar la derivación. Los datos del formulario se conservaron. Inténtelo de nuevo o comuníquese con el administrador.');
}
