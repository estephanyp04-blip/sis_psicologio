<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/listar.php');


require_once __DIR__ . '/../config/conexion.php';

/*  CONFIGURACIÓN Y SESIÓN */

$baseUrl = rtrim(login_config()['base_url'], '/');

$idDocente = (int) ($_SESSION['cod_docente'] ?? 0);

$esDocente      = ($idDocente > 0);

$puedeCrear = login_puede('derivaciones/registrar.php');


/* FUNCIONES AUXILIARES */


function claseEstado(string $estado): string
{
    return match ($estado) {
        'Pendiente' => 'estado-pendiente',
        'En seguimiento' => 'estado-proceso',
        'Atendido' => 'estado-atendido',
        default => 'estado-neutro',
    };
}

function etiquetaEstado(string $estado): string
{
    return match ($estado) {
        'Pendiente' => 'Pendiente de revision',
        'En seguimiento' => 'En seguimiento',
        'Atendido' => 'Atendido',
        default => $estado,
    };
}



require_once __DIR__.'/../includes/trazabilidad_datos.php';
try {
    [$desde,$hasta]=flujo_filtro_fechas($_GET);
    $prioridadFiltro=flujo_texto($_GET['prioridad']??'',10);
    if(!in_array($prioridadFiltro,['','Alta','Media','Baja'],true))throw new InvalidArgumentException('Prioridad no válida.');
}catch(InvalidArgumentException $e){http_response_code(422);exit(login_html($e->getMessage()));}
$condicionesFiltro=($desde!==''?" AND d.fecha>='$desde'":'').($hasta!==''?" AND d.fecha<='$hasta'":'').($prioridadFiltro!==''?" AND d.prioridad='$prioridadFiltro'":'');
$sql = "
    SELECT
        d.cod_derivacion,
        d.fecha,
        d.estado,
        d.prioridad,
        m.nombre AS materia,

        e.nombres,
        e.apellidos,
        e.curso,
        e.paralelo,

        COALESCE(d.autor_nombres,persona.nombres) AS docente_nombres,
        COALESCE(d.autor_apellidos,persona.apellidos) AS docente_apellidos

    FROM derivaciones d

    LEFT JOIN materias m ON m.cod_materia = d.cod_materia

    LEFT JOIN vista_estudiantes e
        ON e.cod_estudiante = d.cod_estudiante

    LEFT JOIN docentes doc
        ON doc.cod_docente = d.cod_docente
    LEFT JOIN usuarios persona
        ON persona.cod_usuario = COALESCE(d.cod_usuario_autor,doc.cod_usuario)
    WHERE 1=1 $condicionesFiltro
";




if ($esDocente) {

    $sql .= "
        AND d.cod_docente = ? AND (d.cod_usuario_autor IS NULL OR d.cod_usuario_autor=(SELECT cod_usuario FROM docentes WHERE cod_docente=d.cod_docente))
    ";

    $sql .= "
        ORDER BY
            CASE d.estado
                WHEN 'Pendiente' THEN 1
                WHEN 'Atendido' THEN 3
                ELSE 4
            END,
            d.fecha DESC,
            d.cod_derivacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        die(
            "Error al preparar la consulta: " .
            login_html($conexion->error)
        );
    }

    $stmt->bind_param('i', $idDocente);
    $stmt->execute();

    $resultado = $stmt->get_result();

} else {

    $sql .= "
        ORDER BY
            CASE d.estado
                WHEN 'Pendiente' THEN 1
                WHEN 'Atendido' THEN 3
                ELSE 4
            END,
            d.fecha DESC,
            d.cod_derivacion DESC
    ";

    $resultado = $conexion->query($sql);
}


/*RESULTADOS */

$filas = [];

if ($resultado) {
    $filas = $resultado->fetch_all(MYSQLI_ASSOC);
}

$total = count($filas);


/*  CONTADORES */

$pendientes = count(
    array_filter(
        $filas,
        fn($fila) => $fila['estado'] === 'Pendiente'
    )
);

$atendidas = count(
    array_filter(
        $filas,
        fn($fila) => in_array(
            $fila['estado'],
            ['Atendido'],
            true
        )
    )
);


/* CURSOS PARA FILTRO */

$cursos = [];

foreach ($filas as $fila) {

    $curso = trim($fila['curso'] ?? '');

    if ($curso !== '') {
        $cursos[$curso] = true;
    }
}

uksort($cursos, 'strnatcasecmp');


/*MENSAJES DE SESIÓN */

$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';

unset(
    $_SESSION['mensaje'],
    $_SESSION['tipo_mensaje']
);


/* TÍTULO */

$tituloPagina = $esDocente
    ? 'Mis derivaciones'
    : 'Derivaciones recibidas';



include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

?>


<main class="main-content derivaciones-page">

    <div class="container-fluid">

        <!--BREADCRUMB-->

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb">

                <li class="breadcrumb-item">
                    <a href="<?= login_html(login_inicio_url()) ?>">
                        Inicio
                    </a>
                </li>

                <li class="breadcrumb-item active">
                    Derivaciones
                </li>

            </ol>

        </nav>


        <!-- ENCABEZADO-->

        <section class="derivaciones-hero">

            <div>

                <span class="derivaciones-eyebrow">
                    <i class="bi bi-send-fill"></i>
                    Psicopedagogía
                </span>

                <h1>
                    <?= $esDocente
                        ? 'Mis derivaciones'
                        : 'Derivaciones recibidas'
                    ?>
                </h1>

                <p>
                    <?php if ($esDocente): ?>

                        Revise las derivaciones que envió.
                        Podrá corregirlas mientras continúen pendientes.

                    <?php else: ?>

                        Consulte las situaciones reportadas por los docentes
                        y realice el seguimiento correspondiente.

                    <?php endif; ?>
                </p>

            </div>


            <?php if ($puedeCrear): ?>

                <a
                    class="btn btn-light btn-lg"
                    href="registrar.php"
                >
                    <i class="bi bi-plus-lg me-2"></i>
                    Nueva derivación
                </a>

            <?php endif; ?>

        </section>


        <!-- MENSAJES -->

        <?php if ($mensaje !== ''): ?>

            <div
                class="alert alert-<?= login_html($tipoMensaje) ?>
                       alert-dismissible fade show"
            >

                <?= nl2br(login_html($mensaje)) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Cerrar"
                ></button>

            </div>

        <?php endif; ?>


        <!-- RESUMEN PARA PSICÓLOGA / ADMINISTRADOR-->

        <?php if (!$esDocente): ?>

            <section class="derivaciones-resumen">

                <!-- PENDIENTES -->

                <article>

                    <span class="resumen-icono pendiente">
                        <i class="bi bi-hourglass-split"></i>
                    </span>

                    <div>
                        <strong><?= $pendientes ?></strong>
                        <span>Por revisar</span>
                    </div>

                </article>


                <!-- ATENDIDAS -->

                <article>

                    <span class="resumen-icono atendida">
                        <i class="bi bi-check2-circle"></i>
                    </span>

                    <div>
                        <strong><?= $atendidas ?></strong>
                        <span>Atendidos</span>
                    </div>

                </article>


                <!-- TOTAL -->

                <article>

                    <span class="resumen-icono total">
                        <i class="bi bi-inboxes"></i>
                    </span>

                    <div>
                        <strong><?= $total ?></strong>
                        <span>Total recibido</span>
                    </div>

                </article>

            </section>

        <?php endif; ?>


        <!-- FILTROS-->

        <form method="get" class="card p-3 mb-3"><div class="row g-3"><div class="col-md-3"><label for="desde" class="form-label">Desde</label><input type="date" id="desde" name="desde" class="form-control" value="<?= login_html($desde) ?>"></div><div class="col-md-3"><label for="hasta" class="form-label">Hasta</label><input type="date" id="hasta" name="hasta" class="form-control" value="<?= login_html($hasta) ?>"></div><div class="col-md-3"><label for="prioridad" class="form-label">Prioridad</label><select name="prioridad" id="prioridad" class="form-select"><option value="">Todas</option><?php foreach(['Alta','Media','Baja'] as $p): ?><option <?= $p===$prioridadFiltro?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div><div class="col-md-3 d-flex gap-2 align-items-end"><button class="btn btn-primary">Aplicar</button><a class="btn btn-light" href="listar.php">Restablecer</a></div></div></form>
        <section class="derivaciones-filtros">

            <!-- BÚSQUEDA -->

            <div class="filtro-busqueda">

                <i class="bi bi-search"></i>

                <input
                    id="buscarDerivacion"
                    type="search"
                    placeholder="<?= $esDocente
                        ? 'Buscar estudiante...'
                        : 'Buscar estudiante o docente...'
                    ?>"
                >

            </div>


            <!-- ESTADO -->

            <select
                id="filtroEstado"
                class="form-select"
            >

                <option value="">
                    Estado: todos
                </option>

                <?php
                $estados = ['Pendiente', 'En seguimiento', 'Atendido'];
                ?>

                <?php foreach ($estados as $estado): ?>

                    <option value="<?= login_html($estado) ?>">
                        <?= login_html($estado) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <!-- CURSO -->

            <select
                id="filtroCurso"
                class="form-select"
            >

                <option value="">
                    Curso: todos
                </option>

                <?php foreach (array_keys($cursos) as $curso): ?>

                    <option value="<?= login_html($curso) ?>">
                        <?= login_html($curso) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </section>


        <!--TABLA -->

        <section class="derivaciones-tabla-card">

            <div class="tabla-titulo">

                <div>

                    <h2>
                        <?= $esDocente
                            ? 'Derivaciones enviadas'
                            : 'Bandeja de derivaciones'
                        ?>
                    </h2>

                    <p>
                        <span id="cantidadDerivaciones">
                            <?= $total ?>
                        </span>

                        registros visibles
                    </p>

                </div>

            </div>


            <div class="table-responsive">

                <table
                    class="table derivaciones-tabla"
                    id="tablaDerivaciones"
                >

                    <thead>

                        <tr>

                            <th>
                                Estudiante
                            </th>

                            <th>
                                Curso
                            </th>

                            <?php if (!$esDocente): ?>

                                <th>
                                    Docente
                                </th>

                            <?php endif; ?>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Estado
                            </th>

                            <th class="text-end">
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($filas as $fila): ?>

                            <?php

                            $estudiante = trim(
                                ($fila['nombres'] ?? '') . ' ' .
                                ($fila['apellidos'] ?? '')
                            );

                            if ($estudiante === '') {
                                $estudiante = 'Estudiante no disponible';
                            }


                            $docenteNombre = trim(
                                ($fila['docente_nombres'] ?? '') . ' ' .
                                ($fila['docente_apellidos'] ?? '')
                            );

                            if ($docenteNombre === '') {
                                $docenteNombre = 'Docente no disponible';
                            }


                            $cursoCompleto = trim(
                                ($fila['curso'] ?? '') . ' ' .
                                ($fila['paralelo'] ?? '')
                            );

                            if ($cursoCompleto === '') {
                                $cursoCompleto = 'Sin curso';
                            }


                            $fecha = !empty($fila['fecha'])
                                ? date(
                                    'd/m/Y',
                                    strtotime($fila['fecha'])
                                )
                                : 'Sin fecha';


                            $editable = $fila['estado'] === 'Pendiente'
                                && login_puede('derivaciones/editar.php');

                            ?>


                            <tr
                                data-registro="1"
                                data-derivacion-id="<?= (int) $fila['cod_derivacion'] ?>"
                                data-estado="<?= login_html(etiquetaEstado($fila['estado'])) ?>"
                                data-curso="<?= login_html($fila['curso'] ?? '') ?>"
                            >

                                <!-- ESTUDIANTE -->

                                <td>

                                    <div class="estudiante-celda">

                                        <span class="avatar-estudiante">

                                            <?= login_html(
                                                mb_strtoupper(
                                                    mb_substr(
                                                        $estudiante,
                                                        0,
                                                        1
                                                    )
                                                )
                                            ) ?>

                                        </span>


                                        <div>

                                            <strong>
                                                <?= login_html($estudiante) ?>
                                            </strong>

                                            <small>
                                                Derivación #
                                                <?= (int) $fila['cod_derivacion'] ?>
                                            </small>
                                            <small><?= login_html($fila['materia'] ?? 'Materia no registrada') ?></small>
                                            <small>Prioridad: <?= login_html($fila['prioridad']) ?></small>

                                        </div>

                                    </div>

                                </td>


                                <!-- CURSO -->

                                <td>

                                    <span class="curso-chip">
                                        <?= login_html($cursoCompleto) ?>
                                    </span>

                                </td>


                                <!-- DOCENTE -->

                                <?php if (!$esDocente): ?>

                                    <td>
                                        <?= login_html($docenteNombre) ?>
                                    </td>

                                <?php endif; ?>


                                <!-- FECHA -->

                                <td>

                                    <span class="fecha-celda">

                                        <i class="bi bi-calendar3"></i>

                                        <?= login_html($fecha) ?>

                                    </span>

                                </td>


                                <!-- ESTADO -->

                                <td>

                                    <span
                                        class="estado-chip
                                        <?= claseEstado(
                                            $fila['estado']
                                        ) ?>"
                                    >

                                        <i></i>

                                        <?= login_html(etiquetaEstado($fila['estado'])) ?>

                                    </span>

                                </td>


                                <!-- ACCIONES -->

                                <td class="text-end">

                                    <div class="acciones-tabla">

                                        <!-- VER -->

                                        <a
                                            class="btn-accion"
                                            href="ver.php?id=<?= (int) $fila['cod_derivacion'] ?>"
                                            title="Ver derivación"
                                        >

                                            <i class="bi bi-eye"></i>

                                            <span>
                                                Ver
                                            </span>

                                        </a>


                                        <!-- EDITAR -->

                                        <?php if ($editable): ?>

                                            <a
                                                class="btn-accion editar"
                                                href="editar.php?id=<?= (int) $fila['cod_derivacion'] ?>"
                                                title="Editar derivación"
                                            >

                                                <i class="bi bi-pencil"></i>

                                                <span>
                                                    Editar
                                                </span>

                                            </a>

                                        <?php endif; ?>

                                        <?php if ($fila['estado'] === 'Pendiente' && login_puede('derivaciones/eliminar.php')): ?>
                                            <form action="eliminar.php" method="POST" class="d-inline"
                                                onsubmit="return confirm('¿Eliminar esta derivación pendiente?');">
                                                <?= login_campo_csrf() ?>
                                                <input type="hidden" name="cod_derivacion" value="<?= (int)$fila['cod_derivacion'] ?>">
                                                <button type="submit" class="btn-accion" title="Eliminar derivación">
                                                    <i class="bi bi-trash"></i><span>Eliminar</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <!-- SIN RESULTADOS DE FILTROS -->

                        <tr
                            id="filaSinResultados"
                            style="display:none;"
                        >

                            <td
                                colspan="<?= $esDocente ? 5 : 6 ?>"
                                class="estado-vacio"
                            >

                                <i class="bi bi-search"></i>

                                <strong>
                                    No se encontraron derivaciones
                                </strong>

                                <span>
                                    Pruebe con otros filtros.
                                </span>

                            </td>

                        </tr>


                        <!-- SIN REGISTROS -->

                        <?php if (!$filas): ?>

                            <tr>

                                <td
                                    colspan="<?= $esDocente ? 5 : 6 ?>"
                                    class="estado-vacio"
                                >

                                    <i class="bi bi-inbox"></i>

                                    <strong>
                                        Aún no hay derivaciones
                                    </strong>

                                    <span>
                                        Los nuevos registros aparecerán aquí.
                                    </span>

                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>


<script>

document.addEventListener('DOMContentLoaded', () => {

    const buscador =
        document.getElementById('buscarDerivacion');

    const filtroEstado =
        document.getElementById('filtroEstado');

    const filtroCurso =
        document.getElementById('filtroCurso');

    const filas =
        [...document.querySelectorAll(
            'tr[data-registro="1"]'
        )];

    const contador =
        document.getElementById('cantidadDerivaciones');

    const filaSinResultados =
        document.getElementById('filaSinResultados');


    /* Normalizar texto */

    const normalizar = texto => {

        return String(texto)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();

    };


    /*Aplicar filtros */

    function filtrarDerivaciones() {

        let visibles = 0;

        filas.forEach(fila => {

            const coincideBusqueda =
                !buscador.value
                ||
                normalizar(fila.textContent)
                    .includes(
                        normalizar(buscador.value)
                    );


            const coincideEstado =
                !filtroEstado.value
                ||
                fila.dataset.estado ===
                filtroEstado.value;


            const coincideCurso =
                !filtroCurso.value
                ||
                fila.dataset.curso ===
                filtroCurso.value;


            const mostrar =
                coincideBusqueda
                &&
                coincideEstado
                &&
                coincideCurso;


            fila.style.display =
                mostrar ? '' : 'none';


            if (mostrar) {
                visibles++;
            }

        });
        contador.textContent = visibles;
        filaSinResultados.style.display =
            filas.length > 0 && visibles === 0
                ? ''
                : 'none';

    }

    /*Eventos*/

    buscador.addEventListener(
        'input',
        filtrarDerivaciones
    );

    filtroEstado.addEventListener(
        'change',
        filtrarDerivaciones
    );

    filtroCurso.addEventListener(
        'change',
        filtrarDerivaciones
    );

});

</script>


<?php include '../includes/footer.php'; ?>
