<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/historias_datos.php';

$historia = isset($historia) && is_array($historia)
    ? $historia
    : [];

$estudiantes = isset($estudiantes) && is_array($estudiantes)
    ? $estudiantes
    : [];

$datosIniciales = isset($datosIniciales) && is_array($datosIniciales)
    ? $datosIniciales
    : [];

$esEdicion = !empty($historia['id_historia']);
$recuperacion = $_SESSION['datos_historia'] ?? [];
$datosSesion = ($recuperacion['id'] ?? -1) === (int)($historia['id_historia'] ?? 0)
    ? ($recuperacion['datos'] ?? []) : [];
unset($_SESSION['datos_historia']);
if ($esEdicion) unset($datosSesion['id_historia'], $datosSesion['id_estudiante'], $datosSesion['id_derivacion'], $datosSesion['id_cita']);

function valorHistoria(string $campo, $defecto = '')
{
    global $datosSesion, $historia, $datosIniciales;

    if (array_key_exists($campo, $datosSesion)) {
        return $datosSesion[$campo];
    }
    if (array_key_exists($campo, $historia)) {
        return $historia[$campo];
    }
    if (array_key_exists($campo, $datosIniciales)) {
        return $datosIniciales[$campo];
    }
    return $defecto;
}

function seleccionado(string $campo, string $valor): string
{
    return valorHistoria($campo) === $valor ? 'selected' : '';
}

function marcado(string $campo, string $valor): string
{
    $actual = valorHistoria($campo, []);
    return is_array($actual) && in_array($valor, $actual, true)
        ? 'checked'
        : '';
}

function opcionesHistoria(string $grupo): array
{
    // Los valores históricos también se muestran para no perderlos al guardar.
    return array_values(array_unique(array_merge(HISTORIA_OPCIONES[$grupo], valorHistoria($grupo, []))));
}

$accionFormulario = $esEdicion ? 'actualizar.php' : 'guardar.php';

$vieneDerivacion = (int)valorHistoria('id_derivacion', 0) > 0;
?>

<form
    action="<?= escapar($accionFormulario) ?>"
    method="POST"
    id="formHistoriaClinica"
    autocomplete="off"
>
    <?= login_campo_csrf() ?>
    <input type="hidden" name="id_cita" value="<?= (int)valorHistoria('id_cita', 0) ?>">
    <?php if ((int)valorHistoria('id_cita', 0)): ?>
        <p class="alert alert-info">Cita de origen #<?= (int)valorHistoria('id_cita') ?>. Se conservarán su estudiante y derivación al guardar.</p>
    <?php endif; ?>
    <?php foreach (HISTORIA_OPCIONES as $grupo => $_): ?>
        <input type="hidden" name="opciones_presentes[<?= escapar($grupo) ?>]" value="1">
    <?php endforeach; ?>
    <input type="hidden" name="familiares_presentes" value="1">
    <input type="hidden" name="situacion_escolar" value="">
    <input type="hidden" name="valoracion_familiar" value="">
    <?php if ($esEdicion): ?>
        <input
            type="hidden"
            name="id_historia"
            value="<?= (int)valorHistoria('id_historia') ?>"
        >
    <?php endif; ?>

    <!-- ✅ SIEMPRE presente; el JS decide su valor -->
    <input
        type="hidden"
        name="id_derivacion"
        id="input_id_derivacion"
        value="<?= (int)valorHistoria('id_derivacion', 0) ?>"
    >

    <section class="historia-formulario-top">
        <div>
            <span class="historia-etiqueta">
                <i class="bi bi-heart-pulse"></i>
                Área Psicológica
            </span>
            <h2>Historia Clínica Psicológica</h2>
        </div>

        <div class="historia-estado-form">
            <label for="estado">Estado del expediente</label>
            <select name="estado" id="estado" class="form-select" required>
                <option value="Activa" <?= seleccionado('estado', 'Activa') ?>>Activa</option>
                <option value="En seguimiento" <?= seleccionado('estado', 'En seguimiento') ?>>En seguimiento</option>
                <option value="Cerrada" <?= seleccionado('estado', 'Cerrada') ?>>Cerrada</option>
            </select>
        </div>
    </section>

    <nav class="historia-navegacion">
        <a href="#seccion-datos">Datos</a>
        <a href="#seccion-motivo">Motivo</a>
        <a href="#seccion-escolar">Situación escolar</a>
        <a href="#seccion-conductas">Conductas problema</a>
        <a href="#seccion-familia">Familia</a>
        <a href="#seccion-diagnostico">Diagnóstico</a>
        <a href="#seccion-acuerdos">Acuerdos</a>
        <a href="#seccion-evolucion">Evolución</a>
    </nav>

    <!-- =====================================================
         1. DATOS GENERALES
         ===================================================== -->
    <section class="historia-bloque" id="seccion-datos">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">1</span>
            <span>
                <strong>Datos generales</strong>
                <small>Información básica del estudiante</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>

        <div class="historia-bloque-contenido">
            <div class="row g-4">

                <!-- ESTUDIANTE -->
                <div class="col-lg-8">
                    <label for="id_estudiante" class="form-label">
                        Estudiante <span class="text-danger">*</span>
                    </label>
                    <select
                        name="id_estudiante"
                        id="id_estudiante"
                        class="form-select"
                        required
                        <?= $esEdicion ? 'disabled' : '' ?>
                    >
                        <option value="">Seleccione estudiante...</option>
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <?php
                            $seleccionadoEstudiante =
                                (int)valorHistoria('id_estudiante') ===
                                (int)$estudiante['id_estudiante'];
                            ?>
                            <option
                                value="<?= (int)$estudiante['id_estudiante'] ?>"
                                data-nombre="<?= escapar(
                                    $estudiante['nombres'] . ' ' . $estudiante['apellidos']
                                ) ?>"
                                data-fecha-nacimiento="<?= escapar(
                                    $estudiante['fecha_nacimiento'] ?? ''
                                ) ?>"
                                data-lugar-nacimiento="<?= escapar(
                                    $estudiante['lugar_nacimiento'] ?? ''
                                ) ?>"
                                data-celular="<?= escapar(
                                    $estudiante['telefono'] ?? ''
                                ) ?>"
                                data-padre-madre="<?= escapar(
                                    $estudiante['nombre_tutor'] ?? ''
                                ) ?>"
                                data-curso="<?= escapar($estudiante['curso'] ?? '') ?>"
                                data-paralelo="<?= escapar($estudiante['paralelo'] ?? '') ?>"
                                <?= $seleccionadoEstudiante ? 'selected' : '' ?>
                            >
                                <?= escapar(
                                    $estudiante['apellidos'] . ' ' . $estudiante['nombres']
                                ) ?>
                                - <?= escapar(
                                    ($estudiante['curso'] ?? '') . ' ' .
                                    ($estudiante['paralelo'] ?? '')
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($esEdicion): ?>
                        <input
                            type="hidden"
                            name="id_estudiante"
                            value="<?= (int)valorHistoria('id_estudiante') ?>"
                        >
                    <?php endif; ?>
                </div>

                <!-- FECHA APERTURA -->
                <div class="col-lg-4">
                    <label for="fecha_apertura" class="form-label">
                        Fecha de apertura <span class="text-danger">*</span>
                    </label>
                    <input
                        type="date"
                        name="fecha_apertura"
                        id="fecha_apertura"
                        class="form-control"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= escapar(
                            valorHistoria('fecha_apertura', date('Y-m-d'))
                        ) ?>"
                        required
                    >
                </div>

                <!-- ✅ SELECTOR DE DERIVACIÓN -->
                <?php if (!$esEdicion): ?>
                <div class="col-12" id="bloqueDerivacion" style="display: none;">
                    <label for="selector_derivacion" class="form-label fw-semibold">
                        <i class="bi bi-send-fill me-1 text-primary"></i>
                        Derivación a vincular
                        <small class="text-muted fw-normal">
                            (opcional — elige la derivación que originó este caso)
                        </small>
                    </label>
                    <select id="selector_derivacion" class="form-select">
                        <option value="">— Sin derivación / sin vincular —</option>
                    </select>
                    <small class="text-muted d-block mt-1" id="infoDerivacion"></small>
                </div>
                <?php endif; ?>

                <!-- NOMBRE COMPLETO -->
                <div class="col-md-6">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" id="nombre_completo" class="form-control" readonly>
                </div>

                <!-- FECHA NACIMIENTO -->
                <div class="col-md-3">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="text" id="fecha_nacimiento_estudiante" class="form-control" readonly>
                </div>

                <!-- LUGAR NACIMIENTO -->
                <div class="col-md-3">
                    <label class="form-label">Lugar de nacimiento</label>
                    <input
                        type="text"
                        name="lugar_nacimiento"
                        id="lugar_nacimiento"
                        class="form-control"
                        value="<?= escapar(valorHistoria('lugar_nacimiento')) ?>"
                        readonly
                    >
                </div>

                <!-- CELULAR -->
                <div class="col-md-6">
                    <label class="form-label">Celular</label>
                    <input
                        type="text"
                        name="celular_estudiante"
                        id="celular_estudiante"
                        class="form-control"
                        value="<?= escapar(valorHistoria('celular_estudiante')) ?>"
                        readonly
                    >
                </div>

                <!-- TUTOR -->
                <div class="col-md-6">
                    <label class="form-label">Padre / Madre / Tutor</label>
                    <input
                        type="text"
                        name="padre_madre"
                        id="padre_madre"
                        class="form-control"
                        value="<?= escapar(valorHistoria('padre_madre')) ?>"
                        readonly
                    >
                </div>

                <!-- ✅ DERIVADO POR con id -->
                <div class="col-md-6">
                    <label class="form-label">Derivado por</label>
                    <input
                        type="text"
                        name="derivado_por"
                        id="derivado_por"
                        class="form-control"
                        value="<?= escapar(valorHistoria('derivado_por')) ?>"
                    >
                </div>

                <!-- ✅ FECHA DERIVACIÓN con id -->
                <div class="col-md-6">
                    <label class="form-label">Fecha de derivación</label>
                    <input
                        type="date"
                        name="fecha_derivacion"
                        id="fecha_derivacion"
                        class="form-control"
                        value="<?= escapar(valorHistoria('fecha_derivacion')) ?>"
                    >
                </div>

                <!-- ✅ MATERIA (siempre renderizada, oculta si vacía) -->
                <div class="col-md-6" id="bloqueMateriaDeriv"
                    <?= valorHistoria('materia_derivacion') === ''
                        ? 'style="display:none;"' : '' ?>>
                    <label class="form-label">Materia de derivación</label>
                    <input
                        type="text"
                        id="materia_derivacion"
                        class="form-control"
                        value="<?= escapar(valorHistoria('materia_derivacion')) ?>"
                        readonly
                    >
                </div>

                <!-- ✅ PRIORIDAD (siempre renderizada, oculta si vacía) -->
                <div class="col-md-6" id="bloquePrioridadDeriv"
                    <?= valorHistoria('prioridad_derivacion') === ''
                        ? 'style="display:none;"' : '' ?>>
                    <label class="form-label">Prioridad de derivación</label>
                    <input
                        type="text"
                        id="prioridad_derivacion"
                        class="form-control"
                        value="<?= escapar(valorHistoria('prioridad_derivacion')) ?>"
                        readonly
                    >
                </div>

                <!-- TUTOR DE CURSO -->
                <div class="col-md-6">
                    <label class="form-label">Tutor de curso</label>
                    <input
                        type="text"
                        name="tutor_curso"
                        class="form-control"
                        value="<?= escapar(valorHistoria('tutor_curso')) ?>"
                    >
                </div>

                <!-- TALLA -->
                <div class="col-md-3">
                    <label class="form-label">Talla</label>
                    <input
                        type="text"
                        name="talla"
                        class="form-control"
                        value="<?= escapar(valorHistoria('talla')) ?>"
                        placeholder="Ej.: 1.55 m"
                    >
                </div>

                <!-- PESO -->
                <div class="col-md-3">
                    <label class="form-label">Peso</label>
                    <input
                        type="text"
                        name="peso"
                        class="form-control"
                        value="<?= escapar(valorHistoria('peso')) ?>"
                        placeholder="Ej.: 48 kg"
                    >
                </div>

                <!-- VALORACIÓN -->
                <div class="col-md-6">
                    <label class="form-label">Valoración</label>
                    <input
                        type="text"
                        name="valoracion"
                        class="form-control"
                        value="<?= escapar(valorHistoria('valoracion')) ?>"
                    >
                </div>

                <!-- ENFERMEDADES -->
                <div class="col-12">
                    <label class="form-label">Enfermedades actuales</label>
                    <textarea
                        name="enfermedades_actuales"
                        class="form-control textarea-auto"
                        rows="2"
                        placeholder="Registre enfermedades actuales si corresponde..."
                    ><?= escapar(valorHistoria('enfermedades_actuales')) ?></textarea>
                </div>

            </div>
        </div>
    </section>

    <!-- =====================================================
         2. MOTIVO DE DERIVACIÓN
         ===================================================== -->
    <section class="historia-bloque" id="seccion-motivo">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">2</span>
            <span>
                <strong>Motivo de derivación</strong>
                <small>Razón por la que el estudiante fue derivado</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>

        <div class="historia-bloque-contenido">
            <textarea
                name="motivo_consulta"
                id="motivo_consulta"
                class="form-control textarea-auto"
                rows="3"
                maxlength="5000"
                required
                placeholder="Describa el motivo de derivación..."
            ><?= escapar(valorHistoria('motivo_consulta')) ?></textarea>

            <div class="mt-4" id="bloqueObservacionesDeriv"
                <?= valorHistoria('observaciones_derivacion') === ''
                    ? 'style="display:none;"' : '' ?>>
                <label class="form-label fw-semibold">
                    Información registrada por el docente
                </label>
                <textarea
                    class="form-control textarea-auto"
                    rows="4"
                    id="observaciones_derivacion"
                    readonly
                ><?= escapar(valorHistoria('observaciones_derivacion')) ?></textarea>
            </div>
        </div>
    </section>

    <!-- =====================================================
         3. SITUACIÓN ESCOLAR
         ===================================================== -->
    <section class="historia-bloque" id="seccion-escolar">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">3</span>
            <span>
                <strong>Situación escolar</strong>
                <small>Rendimiento y adaptación escolar</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>

        <div class="historia-bloque-contenido">
            <label class="form-label fw-semibold">
                Valoración de la situación escolar
            </label>
            <div class="opciones-clinicas">
                <?php foreach (['Muy buena', 'Buena', 'Regular', 'Deficiente'] as $situacion): ?>
                    <label class="opcion-radio">
                        <input type="radio" name="situacion_escolar" value="<?= escapar($situacion) ?>"
                            <?= valorHistoria('situacion_escolar') === $situacion ? 'checked' : '' ?>>
                        <span><?= escapar($situacion) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="row g-4 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Curso(s) repetido(s)</label>
                    <input type="text" name="cursos_repetidos" class="form-control" maxlength="150" value="<?= escapar(valorHistoria('cursos_repetidos')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dificultad escolar</label>
                    <input type="text" name="dificultad_escolar" class="form-control" maxlength="5000" value="<?= escapar(valorHistoria('dificultad_escolar')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Materia que más le agrada</label>
                    <input type="text" name="materia_agrada" class="form-control" maxlength="150" value="<?= escapar(valorHistoria('materia_agrada')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Materia que menos le agrada</label>
                    <input type="text" name="materia_desagrada" class="form-control" maxlength="150" value="<?= escapar(valorHistoria('materia_desagrada')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Relación con compañeros(as) y profesores(as)</label>
                    <textarea name="relacion_escolar" class="form-control textarea-auto" rows="2" maxlength="5000"><?= escapar(valorHistoria('relacion_escolar')) ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================
         5. CONDUCTAS PROBLEMA
         ===================================================== -->
    <section class="historia-bloque" id="seccion-conductas">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">4</span>
            <span>
                <strong>Conducta(s) problema(s)</strong>
                <small>Atención, actividad motora y comportamiento socioemocional</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>

        <div class="historia-bloque-contenido">
            <!-- A. ATENCIÓN -->
            <div class="subseccion-clinica">
                <h3>A. Atención / distracción</h3>
                <div class="opciones-check-grid">
                    <?php foreach (opcionesHistoria('atencion_distraccion') as $opcion): ?>
                        <label class="opcion-check">
                            <input type="checkbox" name="atencion_distraccion[]"
                                value="<?= escapar($opcion) ?>"
                                <?= marcado('atencion_distraccion', $opcion) ?>>
                            <span><?= escapar($opcion) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- B. ACTIVIDAD MOTORA -->
            <div class="subseccion-clinica">
                <h3>B. Actividad motora en exceso</h3>
                <div class="opciones-check-grid">
                    <?php foreach (opcionesHistoria('actividad_motora') as $opcion): ?>
                        <label class="opcion-check">
                            <input type="checkbox" name="actividad_motora[]"
                                value="<?= escapar($opcion) ?>"
                                <?= marcado('actividad_motora', $opcion) ?>>
                            <span><?= escapar($opcion) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- D. SOCIOEMOCIONALES -->
            <div class="subseccion-clinica">
                <h3>Dificultades socioemocionales</h3>
                <div class="opciones-check-grid">
                    <?php foreach (opcionesHistoria('dificultades_socioemocionales') as $opcion): ?>
                        <label class="opcion-check">
                            <input type="checkbox" name="dificultades_socioemocionales[]"
                                value="<?= escapar($opcion) ?>"
                                <?= marcado('dificultades_socioemocionales', $opcion) ?>>
                            <span><?= escapar($opcion) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- E. ESTRATEGIAS -->
            <div class="subseccion-clinica">
                <h3>Estrategias de intervención antes de la derivación</h3>
                <div class="opciones-check-grid">
                    <?php foreach (opcionesHistoria('estrategias_previas') as $opcion): ?>
                        <label class="opcion-check">
                            <input type="checkbox" name="estrategias_previas[]"
                                value="<?= escapar($opcion) ?>"
                                <?= marcado('estrategias_previas', $opcion) ?>>
                            <span><?= escapar($opcion) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================
         6. CONTEXTO FAMILIAR
         ===================================================== -->
    <section class="historia-bloque" id="seccion-familia">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">5</span>
            <span>
                <strong>Contexto familiar</strong>
                <small>Antecedentes y composición familiar</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>

        <div class="historia-bloque-contenido">
            <label class="form-label">Antecedentes familiares</label>
            <small class="d-block text-muted mb-2">
                Alcoholismo, farmacodependencia, antecedentes psiquiátricos,
                enfermedades crónicas y/o congénitas.
            </small>
            <textarea name="antecedentes" class="form-control textarea-auto"
                rows="3" maxlength="5000"><?= escapar(valorHistoria('antecedentes')) ?></textarea>

            <div class="familiares-head mt-4">
                <div>
                    <h3>Grupo familiar</h3>
                    <p>Registre los integrantes necesarios.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="agregarFamiliar">
                    <i class="bi bi-plus-lg me-1"></i> Agregar familiar
                </button>
            </div>

            <div class="table-responsive">
                <table class="table familiares-tabla" id="tablaFamiliares">
                    <thead>
                        <tr>
                            <th>Nombre y apellido</th>
                            <th>Edad</th>
                            <th>Relación</th>
                            <th>Estudio / Profesión</th>
                            <th>Ocupación</th>
                            <th>Observaciones</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="familiaresBody">
                        <?php
                        $familiaresFormulario = valorHistoria('familiares', []);
                        if (!is_array($familiaresFormulario) || $familiaresFormulario === []) {
                            $familiaresFormulario = [[]];
                        }
                        ?>
                        <?php foreach (array_values($familiaresFormulario) as $indice => $familiar): ?>
                            <tr>
                                <td><input type="text" name="familiares[<?= $indice ?>][nombre]" class="form-control" value="<?= escapar($familiar['nombre'] ?? '') ?>"></td>
                                <td><input type="number" name="familiares[<?= $indice ?>][edad]" class="form-control" min="0" max="120" value="<?= escapar($familiar['edad'] ?? '') ?>"></td>
                                <td><input type="text" name="familiares[<?= $indice ?>][relacion]" class="form-control" value="<?= escapar($familiar['relacion'] ?? '') ?>"></td>
                                <td><input type="text" name="familiares[<?= $indice ?>][profesion]" class="form-control" value="<?= escapar($familiar['profesion'] ?? '') ?>"></td>
                                <td><input type="text" name="familiares[<?= $indice ?>][ocupacion]" class="form-control" value="<?= escapar($familiar['ocupacion'] ?? '') ?>"></td>
                                <td><input type="text" name="familiares[<?= $indice ?>][observaciones]" class="form-control" value="<?= escapar($familiar['observaciones'] ?? '') ?>"></td>
                                <td>
                                    <button type="button" class="btn btn-outline-danger btn-sm quitar-familiar" title="Eliminar fila">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <label class="form-label fw-semibold">¿Cómo califica a su familia?</label>
                <div class="opciones-clinicas">
                    <?php foreach (
                        ['Muy buena', 'Buena', 'Regular', 'Conflictiva'] as $opcion
                    ): ?>
                        <label class="opcion-radio">
                            <input type="radio" name="valoracion_familiar"
                                value="<?= escapar($opcion) ?>"
                                <?= valorHistoria('valoracion_familiar') === $opcion
                                    ? 'checked' : '' ?>>
                            <span><?= escapar($opcion) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Descripción del contexto familiar</label>
                <textarea name="contexto_familiar" class="form-control textarea-auto"
                    rows="3"><?= escapar(valorHistoria('contexto_familiar')) ?></textarea>
            </div>
        </div>
    </section>

    <!-- =====================================================
         7. DIAGNÓSTICO
         ===================================================== -->
    <section class="historia-bloque" id="seccion-diagnostico">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">6</span>
            <span>
                <strong>Resultados del diagnóstico psicológico</strong>
                <small>Intelectual, emocional, organicidad y personalidad</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <label class="form-label">Evaluación inicial</label>
            <textarea name="evaluacion_inicial" class="form-control textarea-auto mb-3" rows="4" maxlength="5000"><?= escapar(valorHistoria('evaluacion_inicial')) ?></textarea>
            <label class="form-label">Impresión diagnóstica</label>
            <textarea name="impresion_diagnostica" class="form-control textarea-auto"
                rows="4" maxlength="5000"
                placeholder="Registre los resultados del diagnóstico psicológico..."
            ><?= escapar(valorHistoria('impresion_diagnostica')) ?></textarea>
        </div>
    </section>

    <!-- =====================================================
         8. ACUERDOS
         ===================================================== -->
    <section class="historia-bloque" id="seccion-acuerdos">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">7</span>
            <span>
                <strong>Acuerdos con estudiante y/o Padre-Madre</strong>
                <small>Compromisos acordados durante la atención</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <textarea name="plan_intervencion" class="form-control textarea-auto"
                rows="4" maxlength="5000"
                placeholder="Registre los acuerdos establecidos..."
            ><?= escapar(valorHistoria('plan_intervencion')) ?></textarea>
        </div>
    </section>

    <!-- =====================================================
         9. EVOLUCIÓN
         ===================================================== -->
    <section class="historia-bloque" id="seccion-evolucion">
        <button type="button" class="historia-bloque-titulo" data-historia-toggle>
            <span class="historia-numero">8</span>
            <span>
                <strong>Evolución del caso</strong>
                <small>Evaluación general del progreso del estudiante</small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>

        <div class="historia-bloque-contenido">
            <label class="form-label mt-3">Observaciones clínicas</label>
            <textarea name="observaciones" class="form-control textarea-auto" rows="4" maxlength="5000"><?= escapar(valorHistoria('observaciones')) ?></textarea>
        </div>
    </section>

    <!-- BOTONES -->
    <input type="hidden" name="formulario_completo" value="1">
    <div class="historia-form-acciones">
        <a href="listar.php" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle me-2"></i>
            <?= $esEdicion ? 'Guardar cambios' : 'Guardar historia clínica' ?>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const esEdicion = <?= $esEdicion ? 'true' : 'false' ?>;
    const recuperando = <?= $datosSesion ? 'true' : 'false' ?>;

    /* =====================================================
       1. TOGGLE DE SECCIONES
       ===================================================== */
    document.querySelectorAll('[data-historia-toggle]').forEach(boton => {
        boton.addEventListener('click', function () {
            const bloque = this.closest('.historia-bloque');
            const contenido = bloque.querySelector('.historia-bloque-contenido');
            const icono = this.querySelector('.bi-chevron-up, .bi-chevron-down');

            contenido.classList.toggle('d-none');

            if (contenido.classList.contains('d-none')) {
                icono.classList.remove('bi-chevron-up');
                icono.classList.add('bi-chevron-down');
            } else {
                icono.classList.remove('bi-chevron-down');
                icono.classList.add('bi-chevron-up');
            }
        });
    });

    /* =====================================================
       2. TEXTAREA AUTOMÁTICO
       ===================================================== */
    const ajustarTextarea = textarea => {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    };

    document.querySelectorAll('.textarea-auto').forEach(textarea => {
        ajustarTextarea(textarea);
        textarea.addEventListener('input', () => ajustarTextarea(textarea));
    });

    /* =====================================================
       3. DATOS DEL ESTUDIANTE
       ===================================================== */
    const estudianteSelect = document.getElementById('id_estudiante');
    const nombreCompleto = document.getElementById('nombre_completo');
    const fechaNacimiento = document.getElementById('fecha_nacimiento_estudiante');
    const lugarNacimiento = document.getElementById('lugar_nacimiento');
    const celularEstudiante = document.getElementById('celular_estudiante');
    const padreMadre = document.getElementById('padre_madre');

    function completarDatosEstudiante(copiarFicha = false) {
        if (!estudianteSelect) return;

        const opcion = estudianteSelect.options[estudianteSelect.selectedIndex];

        if (!opcion || !opcion.value) {
            [nombreCompleto, fechaNacimiento].forEach(campo => {
                if (campo) campo.value = '';
            });
            if (copiarFicha) [lugarNacimiento, celularEstudiante, padreMadre].forEach(campo => { if (campo) campo.value = ''; });
            return;
        }

        if (nombreCompleto) nombreCompleto.value = opcion.dataset.nombre || '';

        if (fechaNacimiento) {
            const fecha = opcion.dataset.fechaNacimiento || '';
            fechaNacimiento.value = fecha
                ? fecha.split('-').reverse().join('/')
                : '';
        }

        if (copiarFicha) {
            if (lugarNacimiento) lugarNacimiento.value = opcion.dataset.lugarNacimiento || '';
            if (celularEstudiante) celularEstudiante.value = opcion.dataset.celular || '';
            if (padreMadre) padreMadre.value = opcion.dataset.padreMadre || '';
        }
    }

    if (estudianteSelect) {
        estudianteSelect.addEventListener('change', () => completarDatosEstudiante(!esEdicion));
        completarDatosEstudiante(!esEdicion && !recuperando);
    }

    /* =====================================================
       4. FAMILIARES
       ===================================================== */
    const cuerpoFamiliares = document.getElementById('familiaresBody');
    const botonAgregar = document.getElementById('agregarFamiliar');

    if (cuerpoFamiliares && botonAgregar) {
        let indiceFamiliar = cuerpoFamiliares.querySelectorAll('tr').length;

        botonAgregar.addEventListener('click', function () {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td><input type="text" name="familiares[${indiceFamiliar}][nombre]" class="form-control"></td>
                <td><input type="number" name="familiares[${indiceFamiliar}][edad]" class="form-control" min="0" max="120"></td>
                <td><input type="text" name="familiares[${indiceFamiliar}][relacion]" class="form-control"></td>
                <td><input type="text" name="familiares[${indiceFamiliar}][profesion]" class="form-control"></td>
                <td><input type="text" name="familiares[${indiceFamiliar}][ocupacion]" class="form-control"></td>
                <td><input type="text" name="familiares[${indiceFamiliar}][observaciones]" class="form-control"></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm quitar-familiar"><i class="bi bi-trash"></i></button></td>
            `;
            cuerpoFamiliares.appendChild(fila);
            indiceFamiliar++;
        });

        cuerpoFamiliares.addEventListener('click', function (evento) {
            const boton = evento.target.closest('.quitar-familiar');
            if (!boton) return;

            const filas = cuerpoFamiliares.querySelectorAll('tr');

            if (filas.length === 1) {
                filas[0].querySelectorAll('input').forEach(input => input.value = '');
                return;
            }

            boton.closest('tr').remove();
        });
    }

    /* =====================================================
       5. NAVEGACIÓN SUAVE
       ===================================================== */
    document.querySelectorAll('.historia-navegacion a').forEach(enlace => {
        enlace.addEventListener('click', function (evento) {
            evento.preventDefault();

            const destino = document.querySelector(this.getAttribute('href'));

            if (destino) {
                destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    /* =====================================================
       6. CARGAR Y SELECCIONAR DERIVACIONES (NUEVO)
       ===================================================== */
    const bloqueDeriv   = document.getElementById('bloqueDerivacion');
    const selDeriv      = document.getElementById('selector_derivacion');
    const inputDeriv    = document.getElementById('input_id_derivacion');
    const infoDeriv     = document.getElementById('infoDerivacion');
    const bloqueMatDer  = document.getElementById('bloqueMateriaDeriv');
    const bloquePriDer  = document.getElementById('bloquePrioridadDeriv');
    const bloqueObsDer  = document.getElementById('bloqueObservacionesDeriv');

    // El motivo precargado por URL también pertenece a la derivación elegida.
    // Al cambiar de estudiante se limpia; el texto recuperado de un error se conserva.
    if (!esEdicion && !recuperando && Number(inputDeriv.value) > 0) {
        document.getElementById('motivo_consulta').dataset.autofill = '1';
    }

    let cacheDerivaciones = [];
    let peticionDerivaciones = 0;
    let controladorDerivaciones;
    let derivacionesCargando = false;
    let errorDerivaciones = false;
    const formHistoria = document.getElementById('formHistoriaClinica');
    formHistoria.addEventListener('submit', (evento) => {
        if (derivacionesCargando || errorDerivaciones) {
            evento.preventDefault();
            alert('Espere a que se carguen las derivaciones. Si ocurrió un error, vuelva a seleccionar al estudiante.');
        }
    });

    const ocultarCamposDeriv = () => {
        [bloqueMatDer, bloquePriDer, bloqueObsDer].forEach(b => {
            if (b) b.style.display = 'none';
        });
    };

    const cargarDerivaciones = async (conservarEntrada = false) => {
        if (!bloqueDeriv || !selDeriv || !estudianteSelect) return;
        const peticionActual = ++peticionDerivaciones;
        if (controladorDerivaciones) controladorDerivaciones.abort();
        controladorDerivaciones = new AbortController();
        const idEst = estudianteSelect.value;
        const seleccionAnterior = conservarEntrada ? inputDeriv.value : '';
        if (!conservarEntrada) {
            inputDeriv.value = '';
            ['derivado_por', 'fecha_derivacion'].forEach(id => { document.getElementById(id).value = ''; });
            const motivo = document.getElementById('motivo_consulta');
            if (motivo.dataset.autofill === '1') motivo.value = '';
        }

        selDeriv.innerHTML = '<option value="">— Sin derivación / sin vincular —</option>';
        bloqueDeriv.style.display = 'none';
        if (infoDeriv) infoDeriv.textContent = '';
        cacheDerivaciones = [];
        ocultarCamposDeriv();
        errorDerivaciones = false;
        derivacionesCargando = false;
        if (!idEst) return;
        derivacionesCargando = true;
        selDeriv.disabled = true;
        bloqueDeriv.style.display = 'block';
        if (infoDeriv) infoDeriv.textContent = 'Cargando derivaciones…';

        try {
            const resp = await fetch(
                `ajax_derivaciones.php?id_estudiante=${encodeURIComponent(idEst)}`,
                { headers: { 'Accept': 'application/json' }, signal: controladorDerivaciones.signal }
            );

            if (!resp.ok) throw new Error('HTTP ' + resp.status);

            const data = await resp.json();
            if (peticionActual !== peticionDerivaciones || idEst !== estudianteSelect.value) return;
            if (!Array.isArray(data.derivaciones)) throw new Error('Respuesta inválida');
            const lista = data.derivaciones;
            cacheDerivaciones = lista;

            if (lista.length === 0) {
                if (infoDeriv) {
                    infoDeriv.textContent =
                        'Este estudiante no tiene derivaciones registradas.';
                }
                bloqueDeriv.style.display = 'block';
                inputDeriv.value = '';
                return;
            }

            bloqueDeriv.style.display = 'block';

            lista.forEach((d) => {
                const opt = document.createElement('option');
                opt.value = d.id;
                const fecha = d.fecha
                    ? d.fecha.split('-').reverse().join('/')
                    : 'sin fecha';
                opt.textContent =
                    `#${d.id} · ${fecha} · ${d.docente || 'Docente no asignado'}`;
                selDeriv.appendChild(opt);
            });

            if (infoDeriv) {
                infoDeriv.textContent = `${lista.length} derivación(es) disponible(s).`;
            }

            /* Si ya viene un id_derivacion preseleccionado (por URL), aplicarlo */
            if (seleccionAnterior && seleccionAnterior !== '0') {
                const existe = lista.some(
                    (x) => String(x.id) === String(seleccionAnterior)
                );
                if (existe) {
                    selDeriv.value = seleccionAnterior;
                    inputDeriv.value = seleccionAnterior;
                    autocompletarDesdeDeriv(lista.find(x => String(x.id) === String(seleccionAnterior)), conservarEntrada);
                } else {
                    inputDeriv.value = '';
                }
            }

        } catch (err) {
            if (peticionActual !== peticionDerivaciones || err.name === 'AbortError') return;
            errorDerivaciones = true;
            inputDeriv.value = '';
            console.error(err);
            if (infoDeriv) {
                infoDeriv.textContent = 'No se pudieron cargar las derivaciones.';
            }
        } finally {
            if (peticionActual === peticionDerivaciones) {
                derivacionesCargando = false;
                selDeriv.disabled = errorDerivaciones;
            }
        }
    };

    const autocompletarDesdeDeriv = (d, conservarEntrada = false) => {
        const dp = document.getElementById('derivado_por');
        if (dp && !conservarEntrada) dp.value = d.docente || '';

        const fd = document.getElementById('fecha_derivacion');
        if (fd && !conservarEntrada) fd.value = d.fecha || '';

        if (bloqueMatDer) {
            const mt = document.getElementById('materia_derivacion');
            if (mt) mt.value = d.materia || '';
            bloqueMatDer.style.display = d.materia ? 'block' : 'none';
        }

        if (bloquePriDer) {
            const pr = document.getElementById('prioridad_derivacion');
            if (pr) pr.value = d.prioridad || '';
            bloquePriDer.style.display = d.prioridad ? 'block' : 'none';
        }

        const motivo = document.getElementById('motivo_consulta');
        if (!conservarEntrada && motivo && (!motivo.value || motivo.dataset.autofill === '1')) {
            motivo.value = d.motivo || '';
            motivo.dataset.autofill = '1';
            motivo.dispatchEvent(new Event('input'));
        }

        if (bloqueObsDer) {
            const obs = document.getElementById('observaciones_derivacion');
            if (obs) obs.value = d.observaciones || '';
            bloqueObsDer.style.display = d.observaciones ? 'block' : 'none';
        }
    };

    if (selDeriv) {
        selDeriv.addEventListener('change', () => {
            const id = selDeriv.value;
            if (inputDeriv) inputDeriv.value = id || '';

            if (!id) {
                ['derivado_por', 'fecha_derivacion'].forEach((campo) => {
                    const el = document.getElementById(campo);
                    if (el) el.value = '';
                });

                const motivo = document.getElementById('motivo_consulta');
                if (motivo && motivo.dataset.autofill === '1') {
                    motivo.value = '';
                    delete motivo.dataset.autofill;
                }

                ocultarCamposDeriv();
                return;
            }

            const d = cacheDerivaciones.find((x) => String(x.id) === String(id));
            if (d) autocompletarDesdeDeriv(d);
        });
    }

    if (estudianteSelect) {
        estudianteSelect.addEventListener('change', () => cargarDerivaciones(false));
        if (estudianteSelect.value) cargarDerivaciones(true);
    }
    document.getElementById('motivo_consulta').addEventListener('input', function (evento) {
        if (evento.isTrusted) delete this.dataset.autofill;
    });

});
</script>
