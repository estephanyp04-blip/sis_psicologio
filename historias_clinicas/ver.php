<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/ver.php');

/**
 * historias_clinicas/ver.php
 * Visualiza una historia clínica completa.
 */

require_once '../config/conexion.php';
require_once __DIR__ . '/../includes/historias_datos.php';

/* =========================================================
   HELPERS GENERALES
   ========================================================= */
function escapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mostrarDato($valor, string $vacio = 'No registrado'): string
{
    $valor = trim((string)$valor);
    return $valor !== ''
        ? escapar($valor)
        : '<span class="sin-dato">' . escapar($vacio) . '</span>';
}

function fechaMostrar(?string $fecha, string $defecto = 'No registrada'): string
{
    if (empty($fecha)) {
        return $defecto;
    }
    $ts = strtotime($fecha);
    return $ts !== false ? date('d/m/Y', $ts) : $defecto;
}

function calcularEdad(?string $fechaNacimiento): string
{
    if (empty($fechaNacimiento)) {
        return 'No registrada';
    }
    try {
        $nac = new DateTime($fechaNacimiento);
        return $nac->diff(new DateTime())->y . ' años';
    } catch (Throwable $e) {
        return 'No registrada';
    }
}

/**
 * Carga los seguimientos de una historia clínica adaptándose a la
 * estructura real de la tabla (los nombres de columnas pueden variar).
 */
function cargarSeguimientos(mysqli $conexion, int $idHistoria): array
{
    $resultado = [];

    /* 1. Verificar que la tabla exista */
    $rsTabla = $conexion->query("SHOW TABLES LIKE 'seguimientos'");
    if (!$rsTabla || $rsTabla->num_rows === 0) {
        return $resultado;
    }

    /* 2. Listar columnas reales */
    $columnasReales = [];
    $rsCols = $conexion->query("SHOW COLUMNS FROM seguimientos");
    if ($rsCols) {
        while ($c = $rsCols->fetch_assoc()) {
            $columnasReales[] = $c['Field'];
        }
    }

    if (empty($columnasReales)) {
        return $resultado;
    }

    /* 3. Mapear conceptos a columnas reales */
    $campos = [];

    $opcionesFecha = ['fecha', 'fecha_seguimiento', 'fecha_registro', 'fecha_creacion'];
    foreach ($opcionesFecha as $cand) {
        if (in_array($cand, $columnasReales, true)) {
            $campos['fecha'] = $cand;
            break;
        }
    }

    $opcionesEvolucion = ['evolucion', 'observaciones', 'descripcion', 'notas', 'detalle', 'comentario', 'texto'];
    foreach ($opcionesEvolucion as $cand) {
        if (in_array($cand, $columnasReales, true)) {
            $campos['evolucion'] = $cand;
            break;
        }
    }

    $opcionesRecomendaciones = ['recomendaciones', 'recomendacion', 'sugerencias'];
    foreach ($opcionesRecomendaciones as $cand) {
        if (in_array($cand, $columnasReales, true)) {
            $campos['recomendaciones'] = $cand;
            break;
        }
    }

    $opcionesProxima = ['proxima_sesion', 'proxima_cita', 'siguiente_cita', 'proxima', 'fecha_proxima'];
    foreach ($opcionesProxima as $cand) {
        if (in_array($cand, $columnasReales, true)) {
            $campos['proxima_cita'] = $cand;
            break;
        }
    }

    if (empty($campos)) {
        return $resultado;
    }

    /* 4. Armar SELECT dinámico */
    $select = [];
    foreach ($campos as $alias => $columnaReal) {
        $select[] = ($columnaReal === $alias)
            ? $columnaReal
            : "{$columnaReal} AS {$alias}";
    }

    /* 5. ORDER BY dinámico */
    $orderBy = [];
    if (isset($campos['fecha'])) {
        $orderBy[] = $campos['fecha'] . ' ASC';
    }
    if (in_array('id_seguimiento', $columnasReales, true)) {
        $orderBy[] = 'id_seguimiento ASC';
    }
    if (empty($orderBy)) {
        $orderBy[] = '1';
    }

    $sql = "SELECT " . implode(', ', $select) . "
            FROM seguimientos
            WHERE id_historia = ?
            ORDER BY " . implode(', ', $orderBy);

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        error_log('Error al preparar seguimientos: ' . $conexion->error);
        return $resultado;
    }

    $stmt->bind_param('i', $idHistoria);

    if ($stmt->execute()) {
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log('Error al ejecutar seguimientos: ' . $stmt->error);
    }

    $stmt->close();

    return $resultado;
}

/* =========================================================
   PERMISOS
   ========================================================= */
$rolActual       = (int)($_SESSION['id_rol'] ?? 0);
$rolesPermitidos = [1, 2];

if (!in_array($rolActual, $rolesPermitidos, true)) {
    $_SESSION['mensaje']      = 'No tiene permiso para consultar historias clínicas.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: ../index.php');
    exit;
}

$puedeEditar = in_array($rolActual, [1, 2], true);

/* =========================================================
   VALIDACIÓN DE ID
   ========================================================= */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: listar.php');
    exit;
}

/* =========================================================
   CONSULTAS
   ========================================================= */
$h            = null;
$opciones     = [];
$familiares   = [];
$seguimientos = [];

try {
    /* ---------- 1. Historia principal ---------- */
    $stmt = $conexion->prepare("
        SELECT
            h.*,
            e.codigo,
            e.ci,
            e.nombres,
            e.apellidos,
            e.fecha_nacimiento,
            e.genero,
            e.curso,
            e.paralelo,
            e.turno,
            e.tutor,
            e.telefono,
            u.nombre   AS profesional_nombre,
            u.apellido AS profesional_apellido
        FROM historias_clinicas h
        INNER JOIN estudiantes e
            ON e.id_estudiante = h.id_estudiante
        LEFT JOIN usuarios u
            ON u.id_usuario = h.id_usuario
        WHERE h.id_historia = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Error al preparar historia: ' . $conexion->error);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $h = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$h) {
        header('Location: listar.php');
        exit;
    }

    /* ---------- 2. Opciones marcadas ---------- */
    $opciones = [
        'conductas_riesgo'              => [],
        'atencion_distraccion'          => [],
        'actividad_motora'              => [],
        'adaptacion_normas'             => [],
        'dificultades_socioemocionales' => [],
        'estrategias_previas'           => [],
    ];

    $stmt = $conexion->prepare("
        SELECT grupo, valor
        FROM historia_opciones
        WHERE id_historia = ?
        ORDER BY id_opcion ASC
    ");

    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rsOpciones = $stmt->get_result();

        while ($o = $rsOpciones->fetch_assoc()) {
            if (isset($opciones[$o['grupo']])) {
                $opciones[$o['grupo']][] = $o['valor'];
            }
        }
        $stmt->close();
    }

    /* ---------- 3. Familiares ---------- */
    $stmt = $conexion->prepare("
        SELECT nombre, edad, relacion, profesion, ocupacion, observaciones
        FROM historia_familiares
        WHERE id_historia = ?
        ORDER BY id_familiar ASC
    ");

    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $familiares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    /* ---------- 4. Seguimientos (función dinámica) ---------- */
    $seguimientos = cargarSeguimientos($conexion, (int)$id);

} catch (Throwable $e) {
    error_log('Error en ver.php: ' . $e->getMessage());

    $_SESSION['mensaje']      = 'No se pudo cargar la historia clínica: ' . $e->getMessage();
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

/* =========================================================
   DATOS DERIVADOS
   ========================================================= */
$edad = calcularEdad($h['fecha_nacimiento'] ?? null);

$profesional = trim(
    ($h['profesional_nombre'] ?? '') . ' ' .
    ($h['profesional_apellido'] ?? '')
);

$nombreCompleto = trim(
    ($h['nombres'] ?? '') . ' ' . ($h['apellidos'] ?? '')
);
if ($nombreCompleto === '') {
    $nombreCompleto = 'Estudiante sin nombre';
}

$inicialAvatar = mb_strtoupper(mb_substr($nombreCompleto, 0, 1, 'UTF-8'), 'UTF-8');

$mensaje     = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';

if (!in_array($tipoMensaje, ['success', 'danger', 'warning', 'info'], true)) {
    $tipoMensaje = 'info';
}

unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$tituloPagina = 'Historia clínica';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content historia-detalle">
<div class="container-fluid">
<?php require_once __DIR__ . '/../includes/trazabilidad_vista.php'; flujo_panel($conexion,'historia',$h); ?>
<div class="d-flex gap-2 my-3 d-print-none">
    <a class="btn btn-primary" href="../seguimientos/registrar.php?id_historia=<?= (int)$h['id_historia'] ?>">Registrar seguimiento</a>
    <a class="btn btn-outline-primary" href="../informes/registrar.php?id_estudiante=<?= (int)$h['id_estudiante'] ?>">Crear informe</a>
</div>

    <!-- BREADCRUMB -->
    <nav aria-label="breadcrumb" class="d-print-none">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
                <a href="listar.php">Historias clínicas</a>
            </li>
            <li class="breadcrumb-item active">
                Expediente #<?= (int)$id ?>
            </li>
        </ol>
    </nav>

    <!-- MENSAJES -->
    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?= escapar($tipoMensaje) ?> d-print-none">
            <?= nl2br(escapar($mensaje)) ?>
        </div>
    <?php endif; ?>

    <!-- CABECERA -->
    <header class="expediente-head">
        <div class="expediente-identidad">
            <span class="expediente-avatar">
                <?= escapar($inicialAvatar) ?>
            </span>

            <div>
                <small>HISTORIA CLÍNICA PSICOLÓGICA #<?= (int)$id ?></small>
                <h1><?= escapar($nombreCompleto) ?></h1>
                <p>
                    <?= escapar(($h['curso'] ?? '') . '° ' . ($h['paralelo'] ?? '')) ?>
                    <?= !empty($h['turno']) ? ' · ' . escapar($h['turno']) : '' ?>
                </p>
            </div>
        </div>

        <div class="expediente-acciones d-print-none">
            <button type="button"
                    onclick="window.print()"
                    class="btn btn-outline-secondary">
                <i class="bi bi-printer me-2"></i>Imprimir
            </button>

            <?php if ($puedeEditar): ?>
                <a href="editar.php?id=<?= (int)$id ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Editar
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- META -->
    <section class="expediente-meta">
        <div>
            <span>Código</span>
            <strong><?= mostrarDato($h['codigo'] ?? '') ?></strong>
        </div>

        <div>
            <span>Fecha de apertura</span>
            <strong><?= escapar(fechaMostrar($h['fecha_apertura'] ?? null)) ?></strong>
        </div>

        <div>
            <span>Estado</span>
            <strong><?= escapar($h['estado'] ?? 'Sin estado') ?></strong>
        </div>

        <div>
            <span>Profesional</span>
            <strong><?= $profesional !== '' ? escapar($profesional) : 'Sin asignar' ?></strong>
        </div>
    </section>

    <!-- =====================================================
         1. DATOS GENERALES
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>1</span>
            <div>
                <h2>Datos generales</h2>
                <p>Información del estudiante</p>
            </div>
        </div>

        <div class="datos-clinicos-grid">
            <div><span>Nombre completo</span><strong><?= escapar($nombreCompleto) ?></strong></div>
            <div><span>Edad</span><strong><?= escapar($edad) ?></strong></div>
            <div><span>Curso</span><strong><?= escapar(($h['curso'] ?? '') . '° ' . ($h['paralelo'] ?? '')) ?></strong></div>

            <div>
                <span>Fecha de nacimiento</span>
                <strong><?= escapar(fechaMostrar($h['fecha_nacimiento'] ?? null)) ?></strong>
            </div>

            <div><span>Lugar de nacimiento</span><strong><?= mostrarDato($h['lugar_nacimiento'] ?? '') ?></strong></div>
            <div><span>Celular</span><strong><?= mostrarDato($h['celular_estudiante'] ?? ($h['telefono'] ?? '')) ?></strong></div>
            <div><span>Padre / Madre</span><strong><?= mostrarDato($h['padre_madre'] ?? '') ?></strong></div>
            <div><span>Derivado por</span><strong><?= mostrarDato($h['derivado_por'] ?? '') ?></strong></div>

            <div>
                <span>Fecha de derivación</span>
                <strong><?= escapar(fechaMostrar($h['fecha_derivacion'] ?? null)) ?></strong>
            </div>

            <div><span>Tutor de curso</span><strong><?= mostrarDato($h['tutor_curso'] ?? ($h['tutor'] ?? '')) ?></strong></div>
            <div><span>Talla</span><strong><?= mostrarDato($h['talla'] ?? '') ?></strong></div>
            <div><span>Peso</span><strong><?= mostrarDato($h['peso'] ?? '') ?></strong></div>
            <div><span>Valoración</span><strong><?= mostrarDato($h['valoracion'] ?? '') ?></strong></div>
        </div>

        <div class="texto-clinico mt-3">
            <strong>Enfermedades actuales</strong>
            <p>
                <?= !empty($h['enfermedades_actuales'])
                    ? nl2br(escapar($h['enfermedades_actuales']))
                    : 'Sin información registrada.' ?>
            </p>
        </div>
    </section>


    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>2</span>
            <div><h2>Motivo de derivación</h2></div>
        </div>

        <div class="texto-clinico">
            <?= !empty($h['motivo_consulta'])
                ? nl2br(escapar($h['motivo_consulta']))
                : '<span class="sin-dato">Sin información registrada.</span>' ?>
        </div>
    </section>

  
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>3</span>
            <div><h2>Situación escolar</h2></div>
        </div>

        <div class="datos-clinicos-grid">
            <div><span>Valoración</span><strong><?= mostrarDato($h['situacion_escolar'] ?? '') ?></strong></div>
            <div><span>Curso(s) repetido(s)</span><strong><?= mostrarDato($h['cursos_repetidos'] ?? '') ?></strong></div>
            <div><span>Dificultad escolar</span><strong><?= mostrarDato($h['dificultad_escolar'] ?? '') ?></strong></div>
            <div><span>Materia que más le agrada</span><strong><?= mostrarDato($h['materia_agrada'] ?? '') ?></strong></div>
            <div><span>Materia que menos le agrada</span><strong><?= mostrarDato($h['materia_desagrada'] ?? '') ?></strong></div>
        </div>

        <div class="texto-clinico mt-3">
            <strong>Relación con compañeros(as) y profesores(as)</strong>
            <p>
                <?= !empty($h['relacion_escolar'])
                    ? nl2br(escapar($h['relacion_escolar']))
                    : 'Sin información registrada.' ?>
            </p>
        </div>
    </section>

    <!-- =====================================================
         4. CONDUCTAS DE RIESGO
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>4</span>
            <div><h2>Conductas de riesgo</h2></div>
        </div>

        <div class="opciones-registradas">
            <?php if (!empty($opciones['conductas_riesgo'])): ?>
                <?php foreach ($opciones['conductas_riesgo'] as $valor): ?>
                    <span><i class="bi bi-check2"></i><?= escapar($valor) ?></span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="sin-dato">Sin conductas de riesgo registradas.</span>
            <?php endif; ?>
        </div>
    </section>

    <!-- =====================================================
         5. CONDUCTAS PROBLEMA
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>5</span>
            <div><h2>Conducta(s) problema(s)</h2></div>
        </div>

        <?php
        $grupos = [
            'atencion_distraccion'          => 'A. Atención / distracción',
            'actividad_motora'              => 'B. Actividad motora en exceso',
            'adaptacion_normas'             => 'C. Adaptación a normas',
            'dificultades_socioemocionales' => 'Dificultades socioemocionales',
            'estrategias_previas'           => 'Estrategias de intervención antes de la derivación',
        ];
        ?>

        <?php foreach ($grupos as $grupo => $titulo): ?>
            <div class="subseccion-clinica ver">
                <h3><?= escapar($titulo) ?></h3>

                <div class="opciones-registradas">
                    <?php if (!empty($opciones[$grupo])): ?>
                        <?php foreach ($opciones[$grupo] as $valor): ?>
                            <span><i class="bi bi-check2"></i><?= escapar($valor) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="sin-dato">Sin opciones marcadas.</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- =====================================================
         6. CONTEXTO FAMILIAR
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>6</span>
            <div><h2>Contexto familiar</h2></div>
        </div>

        <div class="texto-clinico">
            <strong>Antecedentes familiares</strong>
            <p>
                <?= !empty($h['antecedentes'])
                    ? nl2br(escapar($h['antecedentes']))
                    : 'Sin información registrada.' ?>
            </p>
        </div>

        <div class="table-responsive mt-4">
            <table class="table familiares-tabla">
                <thead>
                    <tr>
                        <th>Nombre y apellido</th>
                        <th>Edad</th>
                        <th>Relación</th>
                        <th>Estudio / Profesión</th>
                        <th>Ocupación</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($familiares)): ?>
                    <?php foreach ($familiares as $f): ?>
                        <tr>
                            <td><?= escapar($f['nombre'] ?? '—') ?></td>
                            <td><?= ($f['edad'] !== null && $f['edad'] !== '') ? (int)$f['edad'] : '—' ?></td>
                            <td><?= escapar(($f['relacion'] ?? '') ?: '—') ?></td>
                            <td><?= escapar(($f['profesion'] ?? '') ?: '—') ?></td>
                            <td><?= escapar(($f['ocupacion'] ?? '') ?: '—') ?></td>
                            <td><?= escapar(($f['observaciones'] ?? '') ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No se registraron integrantes familiares.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="datos-clinicos-grid mt-3">
            <div>
                <span>¿Cómo califica a su familia?</span>
                <strong><?= mostrarDato($h['valoracion_familiar'] ?? '') ?></strong>
            </div>
        </div>

        <div class="texto-clinico mt-3">
            <strong>Descripción del contexto familiar</strong>
            <p>
                <?= !empty($h['contexto_familiar'])
                    ? nl2br(escapar($h['contexto_familiar']))
                    : 'Sin información registrada.' ?>
            </p>
        </div>
    </section>

    <!-- =====================================================
         7. DIAGNÓSTICO
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>7</span>
            <div>
                <h2>Resultados del diagnóstico psicológico</h2>
                <p>Intelectual, emocional, organicidad y personalidad</p>
            </div>
        </div>

        <div class="texto-clinico">
            <?= !empty($h['impresion_diagnostica'])
                ? nl2br(escapar($h['impresion_diagnostica']))
                : '<span class="sin-dato">Sin información registrada.</span>' ?>
        </div>
        <div class="texto-clinico mt-3">
            <strong>Evaluación inicial</strong>
            <p><?= nl2br(mostrarDato($h['evaluacion_inicial'] ?? '')) ?></p>
        </div>
    </section>

    <!-- =====================================================
         8. ACUERDOS
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>8</span>
            <div><h2>Acuerdos con estudiante y/o Padre-Madre</h2></div>
        </div>

        <div class="texto-clinico">
            <?= !empty($h['plan_intervencion'])
                ? nl2br(escapar($h['plan_intervencion']))
                : '<span class="sin-dato">Sin información registrada.</span>' ?>
        </div>
    </section>

    <!-- =====================================================
         9. EVOLUCIÓN
         ===================================================== -->
    <section class="expediente-seccion">
        <div class="expediente-seccion-titulo">
            <span>9</span>
            <div>
                <h2>Evolución del caso</h2>
                <p>Registro cronológico del seguimiento</p>
            </div>
        </div>

        <div class="texto-clinico mb-3">
            <strong>Evaluación del progreso</strong>
            <p><?= mostrarDato(HISTORIA_EVOLUCIONES[(int)($h['evolucion_caso'] ?? 0)] ?? '', 'Sin evaluar') ?></p>
        </div>
        <?php if (!empty($h['observaciones'])): ?>
            <div class="evolucion-item">
                <div class="evolucion-fecha">
                    <?= escapar(fechaMostrar($h['fecha_apertura'] ?? null, 'Inicio')) ?>
                </div>
                <div>
                    <strong>Registro inicial</strong>
                    <p><?= nl2br(escapar($h['observaciones'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($seguimientos as $s): ?>
            <div class="evolucion-item">
                <div class="evolucion-fecha">
                    <?= escapar(fechaMostrar($s['fecha'] ?? null, 'Sin fecha')) ?>
                </div>

                <div>
                    <strong>Evolución</strong>
                    <p>
                        <?= nl2br(escapar(
                            !empty($s['evolucion'])
                                ? $s['evolucion']
                                : 'Sin detalle registrado.'
                        )) ?>
                    </p>

                    <?php if (!empty($s['recomendaciones'])): ?>
                        <small>
                            <b>Recomendaciones:</b>
                            <?= escapar($s['recomendaciones']) ?>
                        </small>
                    <?php endif; ?>

                    <?php if (!empty($s['proxima_cita'])): ?>
                        <small>
                            <b>Próxima cita:</b>
                            <?= escapar(fechaMostrar($s['proxima_cita'])) ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($h['observaciones']) && empty($seguimientos)): ?>
            <div class="sin-dato">
                Todavía no existen registros de evolución.
            </div>
        <?php endif; ?>
    </section>

    <!-- AVISO CONFIDENCIAL -->
    <div class="confidencial-box mt-4">
        <i class="bi bi-lock-fill"></i>
        <div>
            <strong>Documento confidencial</strong>
            <p>Uso exclusivo del personal autorizado del área de psicología.</p>
        </div>
    </div>

    <!-- BOTONES -->
    <div class="d-flex justify-content-between mt-4 d-print-none">
        <a href="listar.php" class="btn btn-light border">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>

        <?php if ($puedeEditar): ?>
            <a href="editar.php?id=<?= (int)$id ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-2"></i>Editar historia
            </a>
        <?php endif; ?>
    </div>

</div>
</main>

<?php include '../includes/footer.php'; ?>
