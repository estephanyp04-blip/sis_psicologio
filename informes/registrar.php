<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Permisos
$rolActual = (int)($_SESSION['id_rol'] ?? 0);
$rolesPermitidos = [1, 2];
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

if ($rolActual > 0 && !in_array($rolActual, $rolesPermitidos, true)) {
    $_SESSION['mensaje'] = 'No tiene permiso para registrar informes.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: listar.php');
    exit;
}

// Usuario actual
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$nombrePsicologa = 'Psicóloga';

if ($idUsuario > 0) {
    $stmt = $conexion->prepare("
        SELECT nombre, apellido
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($usuario) {
        $nombrePsicologa = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
    }
} else {
    $stmt = $conexion->prepare("
        SELECT id_usuario, nombre, apellido
        FROM usuarios
        WHERE id_rol = 2
        AND estado = 'Activo'
        ORDER BY id_usuario ASC
        LIMIT 1
    ");
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($usuario) {
        $idUsuario = (int)$usuario['id_usuario'];
        $nombrePsicologa = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
    }
}

// Próximo número de ficha
$numeroFicha = 'INF-0001';

$resultadoFicha = $conexion->query("
    SELECT numero_ficha
    FROM informes
    ORDER BY id_informe DESC
    LIMIT 1
");

if ($resultadoFicha && $ultimaFicha = $resultadoFicha->fetch_assoc()) {
    if (preg_match('/(\d+)$/', $ultimaFicha['numero_ficha'], $coincidencia)) {
        $numeroFicha = 'INF-' . str_pad(
            ((int)$coincidencia[1]) + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}

// Consulta estudiantes
$sqlEstudiantes = "
    SELECT
        e.id_estudiante,
        e.nombres,
        e.apellidos,
        e.fecha_nacimiento,
        e.telefono,
        e.nombre_tutor,
        e.curso,
        e.paralelo,

        h.id_historia,
        h.impresion_diagnostica AS diagnostico,
        h.observaciones AS observaciones_historia,

        (
            SELECT s.recomendaciones
            FROM seguimientos s
            WHERE s.id_historia = h.id_historia
            ORDER BY s.fecha DESC, s.id_seguimiento DESC
            LIMIT 1
        ) AS recomendaciones,

        (
            SELECT d.id_derivacion
            FROM derivaciones d
            WHERE d.id_estudiante = e.id_estudiante
            ORDER BY d.fecha DESC, d.id_derivacion DESC
            LIMIT 1
        ) AS id_derivacion,

        (
            SELECT d.motivo
            FROM derivaciones d
            WHERE d.id_estudiante = e.id_estudiante
            ORDER BY d.fecha DESC, d.id_derivacion DESC
            LIMIT 1
        ) AS motivo_derivacion,

        (
            SELECT d.id_docente
            FROM derivaciones d
            WHERE d.id_estudiante = e.id_estudiante
            ORDER BY d.fecha DESC, d.id_derivacion DESC
            LIMIT 1
        ) AS id_docente,

        (
            SELECT CONCAT(doc.nombres, ' ', doc.apellidos)
            FROM derivaciones d
            INNER JOIN docentes doc
                ON doc.id_docente = d.id_docente
            WHERE d.id_estudiante = e.id_estudiante
            ORDER BY d.fecha DESC, d.id_derivacion DESC
            LIMIT 1
        ) AS docente_referente,

        (
            SELECT COUNT(*)
            FROM citas c
            WHERE c.id_estudiante = e.id_estudiante
            AND c.estado = 'Atendida'
        ) AS numero_atenciones

    FROM estudiantes e

    LEFT JOIN historias_clinicas h
        ON h.id_historia = (
            SELECT h2.id_historia
            FROM historias_clinicas h2
            WHERE h2.id_estudiante = e.id_estudiante
            ORDER BY h2.fecha_apertura DESC, h2.id_historia DESC
            LIMIT 1
        )

    WHERE e.estado = 'Activo'

    ORDER BY e.apellidos ASC, e.nombres ASC
";

$resultadoEstudiantes = $conexion->query($sqlEstudiantes);

if (!$resultadoEstudiantes) {
    die('Error al consultar estudiantes: ' . $conexion->error);
}

// HTML
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../index.php">Inicio</a>
            </li>
            <li class="breadcrumb-item">
                <a href="listar.php">Informes</a>
            </li>
            <li class="breadcrumb-item active">
                Registrar
            </li>
        </ol>
    </nav>

    <div class="formulario-derivacion">

        <div class="form-header">
            <div>
                <span class="text-primary fw-semibold">
                    <i class="bi bi-file-earmark-medical me-1"></i>
                    Departamento de Psicología
                </span>

                <h2 class="mt-2">
                    Nueva ficha psicológica
                </h2>

                <p>
                    Registro de informe psicológico según la ficha del Anexo 4.
                </p>
            </div>
        </div>

        <form action="guardar.php" method="POST" id="formInforme" autocomplete="off">

            <input
                type="hidden"
                name="id_usuario"
                value="<?= $idUsuario ?>"
            >

            <!-- Datos generales -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-person-vcard"></i>
                    Datos generales
                </h5>

                <hr>

                <div class="row g-4">

                    <div class="col-md-3">
                        <label class="form-label">
                            Ficha psicológica
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= e($numeroFicha) ?>"
                            readonly
                        >

                        <small class="text-muted">
                            Número generado automáticamente.
                        </small>
                    </div>

                    <div class="col-md-3">
                        <label for="fecha" class="form-label">
                            Fecha
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="date"
                            name="fecha"
                            id="fecha"
                            class="form-control"
                            value="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="id_estudiante" class="form-label">
                            Estudiante
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            name="id_estudiante"
                            id="id_estudiante"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Seleccione un estudiante...
                            </option>

                            <?php while ($estudiante = $resultadoEstudiantes->fetch_assoc()): ?>

                                <?php
                                $nombreCompleto = trim(
                                    $estudiante['apellidos'] . ' ' .
                                    $estudiante['nombres']
                                );
                                ?>

                                <option
                                    value="<?= (int)$estudiante['id_estudiante'] ?>"
                                    data-nombre="<?= e($nombreCompleto) ?>"
                                    data-fecha-nacimiento="<?= e($estudiante['fecha_nacimiento'] ?? '') ?>"
                                    data-telefono="<?= e($estudiante['telefono'] ?? '') ?>"
                                    data-tutor="<?= e($estudiante['nombre_tutor'] ?? '') ?>"
                                    data-curso="<?= e($estudiante['curso'] ?? '') ?>"
                                    data-paralelo="<?= e($estudiante['paralelo'] ?? '') ?>"
                                    data-historia="<?= (int)($estudiante['id_historia'] ?? 0) ?>"
                                    data-derivacion="<?= (int)($estudiante['id_derivacion'] ?? 0) ?>"
                                    data-atenciones="<?= (int)($estudiante['numero_atenciones'] ?? 0) ?>"
                                    data-docente="<?= e($estudiante['docente_referente'] ?? '') ?>"
                                    data-motivo-derivacion="<?= e($estudiante['motivo_derivacion'] ?? '') ?>"
                                    data-motivo-historia="<?= e($estudiante['motivo_derivacion'] ?? '') ?>"
                                    data-diagnostico="<?= e($estudiante['diagnostico'] ?? '') ?>"
                                    data-recomendaciones="<?= e($estudiante['recomendaciones'] ?? '') ?>"
                                    data-observaciones="<?= e($estudiante['observaciones_historia'] ?? '') ?>"
                                >
                                    <?= e($nombreCompleto) ?>
                                    — <?= e($estudiante['curso'] ?? '') ?>
                                    <?= e($estudiante['paralelo'] ?? '') ?>
                                </option>

                            <?php endwhile; ?>

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Curso
                        </label>

                        <input
                            type="text"
                            id="curso"
                            class="form-control"
                            readonly
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Paralelo
                        </label>

                        <input
                            type="text"
                            id="paralelo"
                            class="form-control"
                            readonly
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Colegio
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value='U.E. Cañada Pailita "B"'
                            readonly
                        >
                    </div>

                </div>

            </section>

            <!-- Información automática -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-magic"></i>
                    Información recuperada automáticamente
                </h5>

                <hr>

                <div class="row g-4">

                    <div class="col-md-4">
                        <label class="form-label">
                            N.º de atenciones
                        </label>

                        <input
                            type="number"
                            name="numero_atenciones"
                            id="numero_atenciones"
                            class="form-control"
                            value="0"
                            min="0"
                            readonly
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Referido por
                        </label>

                        <input
                            type="text"
                            name="referido_por"
                            id="referido_por"
                            class="form-control"
                            value=""
                            readonly
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Docente que refiere
                        </label>

                        <input
                            type="text"
                            id="docente_referente"
                            class="form-control"
                            value=""
                            readonly
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Historia clínica
                        </label>

                        <input
                            type="text"
                            id="historia_estado"
                            class="form-control"
                            value="Seleccione un estudiante"
                            readonly
                        >

                        <input
                            type="hidden"
                            name="id_historia"
                            id="id_historia"
                            value=""
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Psicólogo/a
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= e($nombrePsicologa) ?>"
                            readonly
                        >
                    </div>

                </div>

            </section>

            <!-- Atención -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-clipboard2-pulse"></i>
                    Datos de la atención
                </h5>

                <hr>

                <div class="mb-4">

                    <label class="form-label d-block">
                        Tipo de atención
                        <span class="text-danger">*</span>
                    </label>

                    <div class="row g-3">

                        <?php
                        $tiposAtencion = [
                            'Evaluación',
                            'Consejería',
                            'Orientación',
                            'Terapia',
                            'Acompañamiento pedagógico'
                        ];
                        ?>

                        <?php foreach ($tiposAtencion as $indice => $tipo): ?>

                            <div class="col-md-4 col-lg-3">

                                <div class="form-check border rounded-3 p-3 h-100">

                                    <input
                                        class="form-check-input ms-0 me-2"
                                        type="checkbox"
                                        name="tipo_atencion[]"
                                        value="<?= e($tipo) ?>"
                                        id="tipo_<?= $indice ?>"
                                    >

                                    <label
                                        class="form-check-label"
                                        for="tipo_<?= $indice ?>"
                                    >
                                        <?= e($tipo) ?>
                                    </label>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <div class="row g-4">

                    <div class="col-md-6">

                        <label for="motivo" class="form-label">
                            Motivo
                            <span class="text-danger">*</span>
                        </label>

                        <textarea
                            name="motivo"
                            id="motivo"
                            class="form-control"
                            rows="5"
                            maxlength="5000"
                            placeholder="El motivo se cargará automáticamente y puede ser complementado."
                            required
                        ></textarea>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Diagnóstico
                        </label>

                        <textarea
                            name="diagnostico"
                            id="diagnostico"
                            class="form-control"
                            rows="5"
                            maxlength="5000"
                            placeholder="Se cargará el diagnóstico disponible en la historia clínica."
                        ></textarea>

                    </div>

                </div>

            </section>

            <!-- Síntesis psicológica -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-person-lines-fill"></i>
                    Síntesis psicológica
                </h5>

                <hr>

                <div class="row g-4">

                    <div class="col-md-6">

                        <label for="aspecto_cognitivo" class="form-label">
                            Aspecto madurativo y/o cognitivo
                            <span class="text-danger">*</span>
                        </label>

                        <textarea
                            name="aspecto_cognitivo"
                            id="aspecto_cognitivo"
                            class="form-control"
                            rows="6"
                            maxlength="5000"
                            placeholder="Describa los aspectos cognitivos y/o madurativos observados."
                            required
                        ></textarea>

                    </div>

                    <div class="col-md-6">

                        <label for="aspectos_afectivos" class="form-label">
                            Aspectos afectivos
                            <span class="text-danger">*</span>
                        </label>

                        <textarea
                            name="aspectos_afectivos"
                            id="aspectos_afectivos"
                            class="form-control"
                            rows="6"
                            maxlength="5000"
                            placeholder="Describa los aspectos emocionales y afectivos observados."
                            required
                        ></textarea>

                    </div>

                </div>

            </section>

            <!-- Acuerdos -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-check2-square"></i>
                    Diagnóstico, acuerdos y compromisos
                </h5>

                <hr>

                <textarea
                    name="diagnostico_acuerdos"
                    id="acuerdos"
                    class="form-control"
                    rows="6"
                    maxlength="5000"
                    placeholder="Registre los acuerdos, compromisos y acciones establecidas durante la atención."
                ></textarea>

            </section>

            <!-- Recomendaciones -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-lightbulb"></i>
                    Recomendaciones y sugerencias
                </h5>

                <hr>

                <textarea
                    name="recomendaciones"
                    id="recomendaciones"
                    class="form-control"
                    rows="6"
                    maxlength="5000"
                    placeholder="Las recomendaciones del último seguimiento aparecerán aquí y podrán ser modificadas."
                ></textarea>

            </section>

            <!-- Recepción -->
            <section class="form-section">

                <h5 class="section-title">
                    <i class="bi bi-person-check"></i>
                    Recepción del informe
                </h5>

                <hr>

                <div class="row g-4">

                    <div class="col-md-8">

                        <label for="recibido_por" class="form-label">
                            Recibido por
                        </label>

                        <input
                            type="text"
                            name="recibido_por"
                            id="recibido_por"
                            class="form-control"
                            maxlength="150"
                            placeholder="Nombre de la persona que recibe el informe"
                        >

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">
                            Estado
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="Borrador"
                            readonly
                        >

                        <input
                            type="hidden"
                            name="estado"
                            value="Borrador"
                        >

                    </div>

                </div>

            </section>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mb-5">

                <a
                    href="listar.php"
                    class="btn btn-light border"
                >
                    <i class="bi bi-x-circle me-1"></i>
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="btnGuardar"
                >
                    <i class="bi bi-save me-1"></i>
                    Guardar informe
                </button>

            </div>

        </form>

    </div>

</div>

<script>
// JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const estudiante = document.getElementById('id_estudiante');
    const curso = document.getElementById('curso');
    const paralelo = document.getElementById('paralelo');
    const atenciones = document.getElementById('numero_atenciones');
    const referido = document.getElementById('referido_por');
    const docente = document.getElementById('docente_referente');
    const historia = document.getElementById('id_historia');
    const historiaEstado = document.getElementById('historia_estado');
    const motivo = document.getElementById('motivo');
    const diagnostico = document.getElementById('diagnostico_acuerdos');
    const recomendaciones = document.getElementById('recomendaciones');
    const form = document.getElementById('formInforme');
    const btnGuardar = document.getElementById('btnGuardar');

    function limpiarDatos() {
        curso.value = '';
        paralelo.value = '';
        atenciones.value = '0';
        referido.value = '';
        docente.value = '';
        historia.value = '';
        historiaEstado.value = 'Seleccione un estudiante';
        motivo.value = '';
        diagnostico.value = '';
        recomendaciones.value = '';
    }

    function cargarDatos() {
        const opcion = estudiante.options[estudiante.selectedIndex];

        if (!opcion || !opcion.value) {
            limpiarDatos();
            return;
        }

        curso.value = opcion.dataset.curso || '';
        paralelo.value = opcion.dataset.paralelo || '';
        atenciones.value = opcion.dataset.atenciones || '0';
        docente.value = opcion.dataset.docente || '';
        historia.value = opcion.dataset.historia || '';

        if (opcion.dataset.historia && opcion.dataset.historia !== '0') {
            historiaEstado.value = 'Historia clínica disponible';
        } else {
            historiaEstado.value = 'Sin historia clínica registrada';
        }

        if (opcion.dataset.derivacion && opcion.dataset.derivacion !== '0') {
            referido.value = 'Profesor';
        } else {
            referido.value = 'Voluntario';
        }

        motivo.value =
            opcion.dataset.motivoDerivacion ||
            opcion.dataset.motivoHistoria ||
            '';

        diagnostico.value =
            opcion.dataset.diagnostico || '';

        recomendaciones.value =
            opcion.dataset.recomendaciones || '';
    }

    estudiante.addEventListener('change', cargarDatos);

    form.addEventListener('submit', function(event) {
        const tipos = form.querySelectorAll(
            'input[name="tipo_atencion[]"]:checked'
        );

        if (tipos.length === 0) {
            event.preventDefault();
            alert('Seleccione al menos un tipo de atención.');
            return;
        }

        if (!estudiante.value) {
            event.preventDefault();
            alert('Seleccione un estudiante.');
            return;
        }

        btnGuardar.disabled = true;
        btnGuardar.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
    });
});
</script>

<?php include '../includes/footer.php'; ?>