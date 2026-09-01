<?php
$historia = isset($historia) && is_array($historia)
    ? $historia
    : [];
$estudiantes = isset($estudiantes) && is_array($estudiantes)
    ? $estudiantes
    : [];
$datosIniciales = isset($datosIniciales) && is_array($datosIniciales)
    ? $datosIniciales
    : [];
$datosSesion = isset($_SESSION['datos_historia'])
    && is_array($_SESSION['datos_historia'])
        ? $_SESSION['datos_historia']
        : [];
$esEdicion = !empty($historia['id_historia']);
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
    return valorHistoria($campo) === $valor
        ? 'selected'
        : '';
}
function marcado(string $campo, string $valor): string
{
    $actual = valorHistoria($campo, []);
    return is_array($actual)
        && in_array($valor, $actual, true)
            ? 'checked'
            : '';
}
$accionFormulario = $esEdicion
    ? 'actualizar.php'
    : 'guardar.php';
$vieneDerivacion =
    (int)valorHistoria('id_derivacion', 0) > 0;
?>
<form
    action="<?= escapar($accionFormulario) ?>"
    method="POST"
    id="formHistoriaClinica"
    autocomplete="off"
>
    <?php if ($esEdicion): ?>
        <input
            type="hidden"
            name="id_historia"
            value="<?= (int)valorHistoria('id_historia') ?>"
        >
    <?php endif; ?>
    <?php if ($vieneDerivacion): ?>
        <input
            type="hidden"
            name="id_derivacion"
            value="<?= (int)valorHistoria('id_derivacion') ?>"
        >
    <?php endif; ?>
    <section class="historia-formulario-top">
        <div>
            <span class="historia-etiqueta">
                <i class="bi bi-heart-pulse"></i>
                Área Psicológica
            </span>
            <h2>Historia Clínica Psicológica</h2>
      >
        </div>
        <div class="historia-estado-form">
            <label for="estado">
                Estado del expediente
            </label>
            <select
                name="estado"
                id="estado"
                class="form-select"
                required
            >
                <option
                    value="Activa"
                    <?= seleccionado('estado', 'Activa') ?>
                >
                    Activa
                </option>
                <option
                    value="En seguimiento"
                    <?= seleccionado(
                        'estado',
                        'En seguimiento'
                    ) ?>
                >
                    En seguimiento
                </option>
                <option
                    value="Cerrada"
                    <?= seleccionado('estado', 'Cerrada') ?>
                >
                    Cerrada
                </option>
            </select>
        </div>
    </section>
    <nav class="historia-navegacion">
        <a href="#seccion-datos">
            Datos
        </a>
        <a href="#seccion-motivo">
            Motivo
        </a>
        <a href="#seccion-escolar">
            Situación escolar
        </a>
        <a href="#seccion-conductas">
            Conductas
        </a>
        <a href="#seccion-familia">
            Familia
        </a>
        <a href="#seccion-diagnostico">
            Diagnóstico
        </a>
        <a href="#seccion-acuerdos">
            Acuerdos
        </a>
        <a href="#seccion-evolucion">
            Evolución
        </a>
    </nav>
    <!-- 1. DATOS GENERALES -->
    <section
        class="historia-bloque"
        id="seccion-datos"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                1
            </span>
            <span>
                <strong>
                    Datos generales
                </strong>
                <small>
                    Información básica del estudiante
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <div class="row g-4">
                <!-- ESTUDIANTE -->
                <div class="col-lg-8">
                    <label
                        for="id_estudiante"
                        class="form-label"
                    >
                        Estudiante
                        <span class="text-danger">*</span>
                    </label>
                    <select
                        name="id_estudiante"
                        id="id_estudiante"
                        class="form-select"
                        required
                        <?= ($esEdicion || $vieneDerivacion)
                            ? 'disabled'
                            : '' ?>
                    >
                        <option value="">
                            Seleccione estudiante...
                        </option>
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <?php
                            $seleccionadoEstudiante =
                                (int)valorHistoria(
                                    'id_estudiante'
                                )
                                ===
                                (int)$estudiante[
                                    'id_estudiante'
                                ];
                            ?>
                            <option
                                value="<?= (int)$estudiante['id_estudiante'] ?>"
                                data-nombre="<?= escapar(
                                    $estudiante['nombres']
                                    . ' '
                                    . $estudiante['apellidos']
                                ) ?>"
                                data-fecha-nacimiento="<?= escapar(
                                    $estudiante[
                                        'fecha_nacimiento'
                                    ] ?? ''
                                ) ?>"
                                data-lugar-nacimiento="<?= escapar(
                                    $estudiante[
                                        'lugar_nacimiento'
                                    ] ?? ''
                                ) ?>"
                                data-celular="<?= escapar(
                                    $estudiante[
                                        'telefono'
                                    ] ?? ''
                                ) ?>"
                                data-padre-madre="<?= escapar(
                                    $estudiante[
                                        'nombre_tutor'
                                    ] ?? ''
                                ) ?>"
                                data-curso="<?= escapar(
                                    $estudiante['curso'] ?? ''
                                ) ?>"
                                data-paralelo="<?= escapar(
                                    $estudiante[
                                        'paralelo'
                                    ] ?? ''
                                ) ?>"
                                <?= $seleccionadoEstudiante
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapar(
                                    $estudiante['apellidos']
                                    . ' '
                                    . $estudiante['nombres']
                                ) ?>
                                -
                                <?= escapar(
                                    ($estudiante['curso'] ?? '')
                                    . ' '
                                    . ($estudiante['paralelo'] ?? '')
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (
                        $esEdicion
                        || $vieneDerivacion
                    ): ?>
                        <input
                            type="hidden"
                            name="id_estudiante"
                            value="<?= (int)valorHistoria(
                                'id_estudiante'
                            ) ?>"
                        >
                    <?php endif; ?>
                </div>
                <!-- FECHA APERTURA -->
                <div class="col-lg-4">
                    <label
                        for="fecha_apertura"
                        class="form-label"
                    >
                        Fecha de apertura
                        <span class="text-danger">*</span>
                    </label>
                    <input
                        type="date"
                        name="fecha_apertura"
                        id="fecha_apertura"
                        class="form-control"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= escapar(
                            valorHistoria(
                                'fecha_apertura',
                                date('Y-m-d')
                            )
                        ) ?>"
                        required
                    >
                </div>
                <!-- NOMBRE COMPLETO -->
                <div class="col-md-6">
                    <label class="form-label">
                        Nombre completo
                    </label>
                    <input
                        type="text"
                        id="nombre_completo"
                        class="form-control"
                        readonly
                    >
                </div>
                <!-- FECHA NACIMIENTO -->
                <div class="col-md-3">
                    <label class="form-label">
                        Fecha de nacimiento
                    </label>
                    <input
                        type="text"
                        id="fecha_nacimiento_estudiante"
                        class="form-control"
                        readonly
                    >
                </div>
                <!-- LUGAR NACIMIENTO -->
                <div class="col-md-3">
                    <label class="form-label">
                        Lugar de nacimiento
                    </label>
                    <input
                        type="text"
                        name="lugar_nacimiento"
                        id="lugar_nacimiento"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'lugar_nacimiento'
                            )
                        ) ?>"
                        readonly
                    >
                </div>
                <!-- CELULAR -->
                <div class="col-md-6">
                    <label class="form-label">
                        Celular
                    </label>
                    <input
                        type="text"
                        name="celular_estudiante"
                        id="celular_estudiante"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'celular_estudiante'
                            )
                        ) ?>"
                        readonly
                    >
                </div>
                <!-- TUTOR -->
                <div class="col-md-6">
                    <label class="form-label">
                        Padre / Madre / Tutor
                    </label>
                    <input
                        type="text"
                        name="padre_madre"
                        id="padre_madre"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria('padre_madre')
                        ) ?>"
                        readonly
                    >
                </div>
                <!-- DERIVADO POR -->
                <div class="col-md-6">
                    <label class="form-label">
                        Derivado por
                    </label>
                    <input
                        type="text"
                        name="derivado_por"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria('derivado_por')
                        ) ?>"
                        <?= $vieneDerivacion
                            ? 'readonly'
                            : '' ?>
                    >
                </div>
                <!-- FECHA DERIVACION -->
                <div class="col-md-6">
                    <label class="form-label">
                        Fecha de derivación
                    </label>
                    <input
                        type="date"
                        name="fecha_derivacion"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'fecha_derivacion'
                            )
                        ) ?>"
                        <?= $vieneDerivacion
                            ? 'readonly'
                            : '' ?>
                    >
                </div>
                <!-- MATERIA -->
                <?php if (
                    valorHistoria(
                        'materia_derivacion'
                    ) !== ''
                    || $vieneDerivacion
                ): ?>
                    <div class="col-md-6">
                        <label class="form-label">
                            Materia de derivación
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            value="<?= escapar(
                                valorHistoria(
                                    'materia_derivacion'
                                )
                            ) ?>"
                            readonly
                        >
                    </div>
                <?php endif; ?>
                <!-- PRIORIDAD -->
                <?php if (
                    valorHistoria(
                        'prioridad_derivacion'
                    ) !== ''
                    || $vieneDerivacion
                ): ?>
                    <div class="col-md-6">
                        <label class="form-label">
                            Prioridad de derivación
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            value="<?= escapar(
                                valorHistoria(
                                    'prioridad_derivacion'
                                )
                            ) ?>"
                            readonly
                        >
                    </div>
                <?php endif; ?>
                <!-- TUTOR DE CURSO -->
                <div class="col-md-6">
                    <label class="form-label">
                        Tutor de curso
                    </label>
                    <input
                        type="text"
                        name="tutor_curso"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria('tutor_curso')
                        ) ?>"
                    >
                </div>
                <!-- TALLA -->
                <div class="col-md-3">
                    <label class="form-label">
                        Talla
                    </label>
                    <input
                        type="text"
                        name="talla"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria('talla')
                        ) ?>"
                        placeholder="Ej.: 1.55 m"
                    >
                </div>
                <!-- PESO -->
                <div class="col-md-3">
                    <label class="form-label">
                        Peso
                    </label>
                    <input
                        type="text"
                        name="peso"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria('peso')
                        ) ?>"
                        placeholder="Ej.: 48 kg"
                    >
                </div>
                <!-- VALORACION -->
                <div class="col-md-6">
                    <label class="form-label">
                        Valoración
                    </label>
                    <input
                        type="text"
                        name="valoracion"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria('valoracion')
                        ) ?>"
                    >
                </div>
                <!-- ENFERMEDADES -->
                <div class="col-12">
                    <label class="form-label">
                        Enfermedades actuales
                    </label>
                    <textarea
                        name="enfermedades_actuales"
                        class="form-control textarea-auto"
                        rows="2"
                        placeholder="Registre enfermedades actuales si corresponde..."
                    ><?= escapar(
                        valorHistoria(
                            'enfermedades_actuales'
                        )
                    ) ?></textarea>
                </div>
            </div>
        </div>
    </section>
    <!-- 2. MOTIVO DE DERIVACIÓN -->
    <section
        class="historia-bloque"
        id="seccion-motivo"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                2
            </span>
            <span>
                <strong>
                    Motivo de derivación
                </strong>
                <small>
                    Razón por la que el estudiante fue derivado
                </small>
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
                <?= $vieneDerivacion
                    ? 'readonly'
                    : '' ?>
            ><?= escapar(
                valorHistoria(
                    'motivo_consulta'
                )
            ) ?></textarea>
            <?php if (
                valorHistoria(
                    'observaciones_derivacion'
                ) !== ''
            ): ?>
                <div class="mt-4">
                    <label class="form-label fw-semibold">
                        Información registrada por el docente
                    </label>
                    <textarea
                        class="form-control textarea-auto"
                        rows="4"
                        readonly
                    ><?= escapar(
                        valorHistoria(
                            'observaciones_derivacion'
                        )
                    ) ?></textarea>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <!-- 3. SITUACIÓN ESCOLAR -->
    <section
        class="historia-bloque"
        id="seccion-escolar"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                3
            </span>
            <span>
                <strong>
                    Situación escolar
                </strong>
                <small>
                    Rendimiento y adaptación escolar
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <label class="form-label fw-semibold">
                Valoración de la situación escolar
            </label>
            <div class="opciones-clinicas">
                <?php foreach (
                    [
                        'Muy buena',
                        'Buena',
                        'Regular',
                        'Deficiente'
                    ] as $situacion
                ): ?>
                    <label class="opcion-radio">
                        <input
                            type="radio"
                            name="situacion_escolar"
                            value="<?= escapar(
                                $situacion
                            ) ?>"
                            <?= valorHistoria(
                                'situacion_escolar'
                            ) === $situacion
                                ? 'checked'
                                : '' ?>
                        >
                        <span>
                            <?= escapar($situacion) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="row g-4 mt-1">
                <div class="col-md-6">
                    <label class="form-label">
                        Curso(s) repetido(s)
                    </label>
                    <input
                        type="text"
                        name="cursos_repetidos"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'cursos_repetidos'
                            )
                        ) ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        Dificultad escolar
                    </label>
                    <input
                        type="text"
                        name="dificultad_escolar"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'dificultad_escolar'
                            )
                        ) ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        Materia que más le agrada
                    </label>
                    <input
                        type="text"
                        name="materia_agrada"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'materia_agrada'
                            )
                        ) ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        Materia que menos le agrada
                    </label>
                    <input
                        type="text"
                        name="materia_desagrada"
                        class="form-control"
                        value="<?= escapar(
                            valorHistoria(
                                'materia_desagrada'
                            )
                        ) ?>"
                    >
                </div>
                <div class="col-12">
                    <label class="form-label">
                        Relación con compañeros(as) y profesores(as)
                    </label>
                    <textarea
                        name="relacion_escolar"
                        class="form-control textarea-auto"
                        rows="2"
                    ><?= escapar(
                        valorHistoria(
                            'relacion_escolar'
                        )
                    ) ?></textarea>
                </div>
            </div>
        </div>
    </section>
    <!-- 4. CONDUCTAS DE RIESGO -->
    <section class="historia-bloque">
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                4
            </span>
            <span>
                <strong>
                    Conductas de riesgo
                </strong>
                <small>
                    Seleccione las opciones observadas
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <?php
            $conductasRiesgo = [
                'Delictiva',
                'Pandillaje',
                'Sospecha de consumo/droga',
                'Problemática sexual'
            ];
            ?>
            <div class="opciones-check-grid">
                <?php foreach (
                    $conductasRiesgo as $opcion
                ): ?>
                    <label class="opcion-check">
                        <input
                            type="checkbox"
                            name="conductas_riesgo[]"
                            value="<?= escapar(
                                $opcion
                            ) ?>"
                            <?= marcado(
                                'conductas_riesgo',
                                $opcion
                            ) ?>
                        >
                        <span>
                            <?= escapar($opcion) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <!--  5. CONDUCTAS PROBLEMA-->
    <section
        class="historia-bloque"
        id="seccion-conductas"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                5
            </span>
            <span>
                <strong>
                    Conducta(s) problema(s)
                </strong>
                <small>
                    Atención, actividad motora y adaptación
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <!-- ATENCIÓN -->
            <div class="subseccion-clinica">
                <h3>
                    A. Atención / distracción
                </h3>
                <?php
                $atencion = [
                    'Se distrae mirando a cualquier lado',
                    'Se olvida sus carpetas o tareas',
                    'Habla constantemente',
                    'Se equivoca por descuido',
                    'No termina de hacer tareas / se atrasa',
                    'Lenguaje inapropiado / conducta obscena'
                ];
                ?>
                <div class="opciones-check-grid">
                    <?php foreach (
                        $atencion as $opcion
                    ): ?>
                        <label class="opcion-check">
                            <input
                                type="checkbox"
                                name="atencion_distraccion[]"
                                value="<?= escapar(
                                    $opcion
                                ) ?>"
                                <?= marcado(
                                    'atencion_distraccion',
                                    $opcion
                                ) ?>
                            >
                            <span>
                                <?= escapar($opcion) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- ACTIVIDAD MOTORA -->
            <div class="subseccion-clinica">
                <h3>
                    B. Actividad motora en exceso
                </h3>
                <?php
                $actividadMotora = [
                    'Se mueve constantemente en su asiento',
                    'Se para y se sienta constantemente'
                ];
                ?>
                <div class="opciones-check-grid">
                    <?php foreach (
                        $actividadMotora as $opcion
                    ): ?>
                        <label class="opcion-check">
                            <input
                                type="checkbox"
                                name="actividad_motora[]"
                                value="<?= escapar(
                                    $opcion
                                ) ?>"
                                <?= marcado(
                                    'actividad_motora',
                                    $opcion
                                ) ?>
                            >
                            <span>
                                <?= escapar($opcion) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- ADAPTACIÓN -->
            <div class="subseccion-clinica">
                <h3>
                    C. Adaptación a normas
                </h3>
                <?php
                $adaptacionNormas = [
                    'No obedece el reglamento del colegio',
                    'Le es difícil seguir indicaciones / protesta',
                    'No cumple normas establecidas',
                    'Propicia el desorden',
                    'Miente',
                    'Agrede de manera verbal',
                    'Agrede de manera física',
                    'Llama por apodos / bullying',
                    'Sus compañeros lo rechazan'
                ];
                ?>
                <div class="opciones-check-grid">
                    <?php foreach (
                        $adaptacionNormas as $opcion
                    ): ?>
                        <label class="opcion-check">
                            <input
                                type="checkbox"
                                name="adaptacion_normas[]"
                                value="<?= escapar(
                                    $opcion
                                ) ?>"
                                <?= marcado(
                                    'adaptacion_normas',
                                    $opcion
                                ) ?>
                            >
                            <span>
                                <?= escapar($opcion) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- SOCIOEMOCIONALES -->
            <div class="subseccion-clinica">
                <h3>
                    Dificultades socioemocionales
                </h3>
                <?php
                $socioemocionales = [
                    'Falta de interés por aprender',
                    'Se le observa triste, deprimido, desanimado',
                    'Cambia de humor frecuentemente',
                    'Prefiere estar solo(a)',
                    'No participa en las actividades',
                    'Se calla y aguanta si lo molestan',
                    'Tartamudea / habla nerviosamente',
                    'Se muerde / come las uñas',
                    'Duda al expresar su opinión',
                    'Se mantiene callado'
                ];
                ?>
                <div class="opciones-check-grid">
                    <?php foreach (
                        $socioemocionales as $opcion
                    ): ?>
                        <label class="opcion-check">
                            <input
                                type="checkbox"
                                name="dificultades_socioemocionales[]"
                                value="<?= escapar(
                                    $opcion
                                ) ?>"
                                <?= marcado(
                                    'dificultades_socioemocionales',
                                    $opcion
                                ) ?>
                            >
                            <span>
                                <?= escapar($opcion) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- ESTRATEGIAS -->
            <div class="subseccion-clinica">
                <h3>
                    Estrategias de intervención antes de la derivación
                </h3>
                <?php
                $estrategias = [
                    'Conversación con él/ella',
                    'Llamadas de atención',
                    'Conversación con el tutor/a a cargo',
                    'Premios y sanciones',
                    'Mayor supervisión',
                    'Ninguna'
                ];
                ?>
                <div class="opciones-check-grid">
                    <?php foreach (
                        $estrategias as $opcion
                    ): ?>
                        <label class="opcion-check">
                            <input
                                type="checkbox"
                                name="estrategias_previas[]"
                                value="<?= escapar(
                                    $opcion
                                ) ?>"
                                <?= marcado(
                                    'estrategias_previas',
                                    $opcion
                                ) ?>
                            >
                            <span>
                                <?= escapar($opcion) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <!-- 6. CONTEXTO FAMILIAR-->
    <section
        class="historia-bloque"
        id="seccion-familia"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                6
            </span>
            <span>
                <strong>
                    Contexto familiar
                </strong>
                <small>
                    Antecedentes y composición familiar
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <label class="form-label">
                Antecedentes familiares
            </label>
            <small class="d-block text-muted mb-2">
                Alcoholismo, farmacodependencia,
                antecedentes psiquiátricos,
                enfermedades crónicas y/o congénitas.
            </small>
            <textarea
                name="antecedentes"
                class="form-control textarea-auto"
                rows="3"
                maxlength="5000"
            ><?= escapar(
                valorHistoria('antecedentes')
            ) ?></textarea>
            <div class="familiares-head mt-4">
                <div>
                    <h3>
                        Grupo familiar
                    </h3>
                    <p>
                        Registre los integrantes necesarios.
                    </p>
                </div>
                <button
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    id="agregarFamiliar"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Agregar familiar
                </button>
            </div>
            <div class="table-responsive">
                <table
                    class="table familiares-tabla"
                    id="tablaFamiliares"
                >
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
                        $familiaresFormulario =
                            valorHistoria(
                                'familiares',
                                []
                            );
                        if (
                            !is_array(
                                $familiaresFormulario
                            )
                            ||
                            $familiaresFormulario === []
                        ) {
                            $familiaresFormulario = [[]];
                        }
                        ?>
                        <?php foreach (
                            array_values(
                                $familiaresFormulario
                            )
                            as $indice => $familiar
                        ): ?>
                            <tr>
                                <td>
                                    <input
                                        type="text"
                                        name="familiares[<?= $indice ?>][nombre]"
                                        class="form-control"
                                        value="<?= escapar(
                                            $familiar[
                                                'nombre'
                                            ] ?? ''
                                        ) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="familiares[<?= $indice ?>][edad]"
                                        class="form-control"
                                        min="0"
                                        max="120"
                                        value="<?= escapar(
                                            $familiar[
                                                'edad'
                                            ] ?? ''
                                        ) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="familiares[<?= $indice ?>][relacion]"
                                        class="form-control"
                                        value="<?= escapar(
                                            $familiar[
                                                'relacion'
                                            ] ?? ''
                                        ) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="familiares[<?= $indice ?>][profesion]"
                                        class="form-control"
                                        value="<?= escapar(
                                            $familiar[
                                                'profesion'
                                            ] ?? ''
                                        ) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="familiares[<?= $indice ?>][ocupacion]"
                                        class="form-control"
                                        value="<?= escapar(
                                            $familiar[
                                                'ocupacion'
                                            ] ?? ''
                                        ) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="familiares[<?= $indice ?>][observaciones]"
                                        class="form-control"
                                        value="<?= escapar(
                                            $familiar[
                                                'observaciones'
                                            ] ?? ''
                                        ) ?>"
                                    >
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm quitar-familiar"
                                        title="Eliminar fila"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- VALORACION FAMILIAR -->
            <div class="mt-4">
                <label class="form-label fw-semibold">
                    ¿Cómo califica a su familia?
                </label>
                <div class="opciones-clinicas">
                    <?php foreach (
                        [
                            'Muy buena',
                            'Buena',
                            'Regular',
                            'Conflictiva'
                        ] as $opcion
                    ): ?>
                        <label class="opcion-radio">
                            <input
                                type="radio"
                                name="valoracion_familiar"
                                value="<?= escapar(
                                    $opcion
                                ) ?>"
                                <?= valorHistoria(
                                    'valoracion_familiar'
                                ) === $opcion
                                    ? 'checked'
                                    : '' ?>
                            >
                            <span>
                                <?= escapar($opcion) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- CONTEXTO FAMILIAR -->
            <div class="mt-3">
                <label class="form-label">
                    Descripción del contexto familiar
                </label>
                <textarea
                    name="contexto_familiar"
                    class="form-control textarea-auto"
                    rows="3"
                ><?= escapar(
                    valorHistoria(
                        'contexto_familiar'
                    )
                ) ?></textarea>
            </div>
        </div>
    </section>
    <!-- 7. DIAGNÓSTICO -->
    <section
        class="historia-bloque"
        id="seccion-diagnostico"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                7
            </span>
            <span>
                <strong>
                    Resultados del diagnóstico psicológico
                </strong>
                <small>
                    Intelectual, emocional, organicidad
                    y personalidad
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <textarea
                name="impresion_diagnostica"
                class="form-control textarea-auto"
                rows="4"
                maxlength="5000"
                placeholder="Registre los resultados del diagnóstico psicológico..."
            ><?= escapar(
                valorHistoria(
                    'impresion_diagnostica'
                )
            ) ?></textarea>
        </div>
    </section>
    <!--8. ACUERDOS-->
    <section
        class="historia-bloque"
        id="seccion-acuerdos"
    >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                8
            </span>
            <span>
                <strong>
                    Acuerdos con estudiante y/o Padre-Madre
                </strong>
                <small>
                    Compromisos acordados durante la atención
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <textarea
                name="plan_intervencion"
                class="form-control textarea-auto"
                rows="4"
                maxlength="5000"
                placeholder="Registre los acuerdos establecidos..."
            ><?= escapar(
                valorHistoria(
                    'plan_intervencion'
                )
            ) ?></textarea>
        </div>
    </section>
    <!-- 9. EVOLUCIÓN DEL CASO -->
    <section
        class="historia-bloque"
        id="seccion-evolucion" >
        <button
            type="button"
            class="historia-bloque-titulo"
            data-historia-toggle
        >
            <span class="historia-numero">
                9
            </span>
            <span>
                <strong>
                    Evolución del caso
                </strong>
                <small>
                    Evaluación general del progreso del estudiante
                </small>
            </span>
            <i class="bi bi-chevron-up"></i>
        </button>
        <div class="historia-bloque-contenido">
            <label class="form-label fw-semibold mb-3">
                Seleccione la evolución observada
            </label>
            <?php
            $evoluciones = [
                1 => 'Empeoró',
                2 => 'Sin mejoría',
                3 => 'Estable',
                4 => 'Mejoró',
                5 => 'Mejoró significativamente'
            ];
            ?>
            <div class="opciones-clinicas">
                <?php foreach (
                    $evoluciones
                    as $valor => $texto
                ): ?>
                    <label class="opcion-radio">
                        <input
                            type="radio"
                            name="evolucion_caso"
                            value="<?= (int)$valor ?>"
                            <?= (int)valorHistoria(
                                'evolucion_caso',
                                0
                            ) === (int)$valor
                                ? 'checked'
                                : '' ?>
                        >
                        <span>
                            <?= (int)$valor ?>
                            -
                            <?= escapar($texto) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small class="text-muted d-block mt-3">
                Esta evaluación permitirá generar
                estadísticas sobre la evolución de los casos.
            </small>
        </div>
    </section>
    <!-- BOTONES -->
    <div class="historia-form-acciones">
        <a
            href="listar.php"
            class="btn btn-light border"
        >
            Cancelar
        </a>
        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="bi bi-check2-circle me-2"></i>
            <?= $esEdicion
                ? 'Guardar cambios'
                : 'Guardar historia clínica' ?>
        </button>
    </div>
</form>
<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {
        /* ABRIR / CERRAR SECCIONES*/
        document
            .querySelectorAll(
                '[data-historia-toggle]'
            )
            .forEach(boton => {
                boton.addEventListener(
                    'click',
                    function () {
                        const bloque =
                            this.closest(
                                '.historia-bloque'
                            );
                        const contenido =
                            bloque.querySelector(
                                '.historia-bloque-contenido'
                            );
                        const icono =
                            this.querySelector(
                                '.bi-chevron-up, .bi-chevron-down'
                            );
                        contenido.classList.toggle(
                            'd-none'
                        );
                        if (
                            contenido.classList.contains(
                                'd-none'
                            )
                        ) {
                            icono.classList.remove(
                                'bi-chevron-up'
                            );
                            icono.classList.add(
                                'bi-chevron-down'
                            );
                        } else {
                            icono.classList.remove(
                                'bi-chevron-down'
                            );
                            icono.classList.add(
                                'bi-chevron-up'
                            );
                        }
                    }
                );
            });
        /* TEXTAREA AUTOMÁTICO */
        const ajustarTextarea = textarea => {
            textarea.style.height = 'auto';
            textarea.style.height =
                textarea.scrollHeight + 'px';
        };
        document
            .querySelectorAll(
                '.textarea-auto'
            )
            .forEach(textarea => {
                ajustarTextarea(textarea);
                textarea.addEventListener(
                    'input',
                    () => ajustarTextarea(
                        textarea
                    )
                );
            });
        /*DATOS DEL ESTUDIANTE*/
        const estudianteSelect =
            document.getElementById(
                'id_estudiante'
            );
        const nombreCompleto =
            document.getElementById(
                'nombre_completo'
            );
        const fechaNacimiento =
            document.getElementById(
                'fecha_nacimiento_estudiante'
            );
        const lugarNacimiento =
            document.getElementById(
                'lugar_nacimiento'
            );
        const celularEstudiante =
            document.getElementById(
                'celular_estudiante'
            );
        const padreMadre =
            document.getElementById(
                'padre_madre'
            );
        function completarDatosEstudiante() {
            if (!estudianteSelect) {
                return;
            }
            const opcion =
                estudianteSelect.options[
                    estudianteSelect.selectedIndex
                ];
            if (
                !opcion
                || !opcion.value
            ) {
                if (nombreCompleto) {
                    nombreCompleto.value = '';
                }
                if (fechaNacimiento) {
                    fechaNacimiento.value = '';
                }
                if (lugarNacimiento) {
                    lugarNacimiento.value = '';
                }
                if (celularEstudiante) {
                    celularEstudiante.value = '';
                }
                if (padreMadre) {
                    padreMadre.value = '';
                }
                return;
            }
            if (nombreCompleto) {
                nombreCompleto.value =
                    opcion.dataset.nombre
                    || '';
            }
            if (fechaNacimiento) {
                const fecha =
                    opcion.dataset.fechaNacimiento
                    || '';
                fechaNacimiento.value =
                    fecha
                    ? fecha
                        .split('-')
                        .reverse()
                        .join('/')
                    : '';
            }
            if (lugarNacimiento) {
                lugarNacimiento.value =
                    opcion.dataset.lugarNacimiento
                    || '';
            }
            if (celularEstudiante) {
                celularEstudiante.value =
                    opcion.dataset.celular
                    || '';
            }
            if (padreMadre) {
                padreMadre.value =
                    opcion.dataset.padreMadre
                    || '';
            }
        }
        if (estudianteSelect) {
            estudianteSelect.addEventListener(
                'change',
                completarDatosEstudiante
            );
            completarDatosEstudiante();
        }
        /* FAMILIARES */
        const cuerpoFamiliares =
            document.getElementById(
                'familiaresBody'
            );
        const botonAgregar =
            document.getElementById(
                'agregarFamiliar'
            );
        if (
            cuerpoFamiliares
            && botonAgregar
        ) {
            let indiceFamiliar =
                cuerpoFamiliares
                    .querySelectorAll('tr')
                    .length;
            botonAgregar.addEventListener(
                'click',
                function () {
                    const fila =
                        document.createElement(
                            'tr'
                        );
                    fila.innerHTML = `
                        <td>
                            <input
                                type="text"
                                name="familiares[${indiceFamiliar}][nombre]"
                                class="form-control"
                            >
                        </td>
                        <td>
                            <input
                                type="number"
                                name="familiares[${indiceFamiliar}][edad]"
                                class="form-control"
                                min="0"
                                max="120"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                name="familiares[${indiceFamiliar}][relacion]"
                                class="form-control"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                name="familiares[${indiceFamiliar}][profesion]"
                                class="form-control"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                name="familiares[${indiceFamiliar}][ocupacion]"
                                class="form-control"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                name="familiares[${indiceFamiliar}][observaciones]"
                                class="form-control"
                            >
                        </td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-outline-danger btn-sm quitar-familiar"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    cuerpoFamiliares.appendChild(
                        fila
                    );
                    indiceFamiliar++;
                }
            );
            cuerpoFamiliares.addEventListener(
                'click',
                function (evento) {
                    const boton =
                        evento.target.closest(
                            '.quitar-familiar'
                        );
                    if (!boton) {
                        return;
                    }
                    const filas =
                        cuerpoFamiliares
                            .querySelectorAll(
                                'tr'
                            );
                    if (
                        filas.length === 1
                    ) {
                        filas[0]
                            .querySelectorAll(
                                'input'
                            )
                            .forEach(
                                input =>
                                    input.value = ''
                            );
                        return;
                    }
                    boton
                        .closest('tr')
                        .remove();
                }
            );
        }
        /* NAVEGACIÓN*/
        document
            .querySelectorAll(
                '.historia-navegacion a'
            )
            .forEach(enlace => {
                enlace.addEventListener(
                    'click',
                    function (evento) {
                        evento.preventDefault();
                        const destino =
                            document.querySelector(
                                this.getAttribute(
                                    'href'
                                )
                            );
                        if (destino) {
                            destino.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }
                );
            });
    }
);
</script>