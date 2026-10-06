<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/listar.php');


require_once '../config/conexion.php';

/*  CONFIGURACIÓN Y SESIÓN */

$baseUrl = rtrim(login_config()['base_url'], '/');

$rol       = (int) ($_SESSION['id_rol'] ?? 0);
$idDocente = (int) ($_SESSION['id_docente'] ?? 0);

$esDocente      = ($rol === 3);

$puedeCrear = login_puede('derivaciones/registrar.php');


/* FUNCIONES AUXILIARES */

function e($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function claseEstado(string $estado): string
{
    return match ($estado) {
        'Pendiente'               => 'estado-pendiente',
        'En seguimiento',
        'En atención'             => 'estado-proceso',
        'Atendido',
        'Finalizado'              => 'estado-atendido',
        'Rechazado'               => 'estado-rechazado',
        default                   => 'estado-neutro',
    };
}



$sql = "
    SELECT
        d.id_derivacion,
        d.fecha,
        d.estado,
        d.prioridad,
        m.nombre AS materia,

        e.nombres,
        e.apellidos,
        e.curso,
        e.paralelo,

        persona.nombres AS docente_nombres,
        persona.apellidos AS docente_apellidos

    FROM derivaciones d

    LEFT JOIN materias m ON m.id_materia = d.id_materia

    LEFT JOIN vista_estudiantes e
        ON e.id_estudiante = d.id_estudiante

    LEFT JOIN docentes doc
        ON doc.id_docente = d.id_docente
    LEFT JOIN personas persona
        ON persona.id_persona = doc.id_persona
";




if ($esDocente) {

    $sql .= "
        WHERE d.id_docente = ?
    ";

    $sql .= "
        ORDER BY
            CASE d.estado
                WHEN 'Pendiente' THEN 1
                WHEN 'En seguimiento' THEN 2
                WHEN 'En atención' THEN 2
                WHEN 'Atendido' THEN 3
                ELSE 4
            END,
            d.fecha DESC,
            d.id_derivacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        die(
            "Error al preparar la consulta: " .
            e($conexion->error)
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
                WHEN 'En seguimiento' THEN 2
                WHEN 'En atención' THEN 2
                WHEN 'Atendido' THEN 3
                ELSE 4
            END,
            d.fecha DESC,
            d.id_derivacion DESC
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
            ['Atendido', 'Finalizado'],
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
                class="alert alert-<?= e($tipoMensaje) ?>
                       alert-dismissible fade show"
            >

                <?= nl2br(e($mensaje)) ?>

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
                        <span>Pendientes</span>
                    </div>

                </article>


                <!-- ATENDIDAS -->

                <article>

                    <span class="resumen-icono atendida">
                        <i class="bi bi-check2-circle"></i>
                    </span>

                    <div>
                        <strong><?= $atendidas ?></strong>
                        <span>Atendidas</span>
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
                $estados = [
                    'Pendiente',
                    'En seguimiento',
                    'En atención',
                    'Atendido',
                    'Finalizado',
                    'Rechazado'
                ];
                ?>

                <?php foreach ($estados as $estado): ?>

                    <option value="<?= e($estado) ?>">
                        <?= e($estado) ?>
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

                    <option value="<?= e($curso) ?>">
                        <?= e($curso) ?>
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


                            $editable =
                                $fila['estado'] === 'Pendiente'
                                &&
                                (
                                    $esDocente
                                    ||
                                    $rol === 1

                                );

                            ?>


                            <tr
                                data-registro="1"
                                data-estado="<?= e($fila['estado']) ?>"
                                data-curso="<?= e($fila['curso'] ?? '') ?>"
                            >

                                <!-- ESTUDIANTE -->

                                <td>

                                    <div class="estudiante-celda">

                                        <span class="avatar-estudiante">

                                            <?= e(
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
                                                <?= e($estudiante) ?>
                                            </strong>

                                            <small>
                                                Derivación #
                                                <?= (int) $fila['id_derivacion'] ?>
                                            </small>
                                            <small><?= e($fila['materia'] ?? 'Materia no registrada') ?></small>
                                            <small>Prioridad: <?= e($fila['prioridad']) ?></small>

                                        </div>

                                    </div>

                                </td>


                                <!-- CURSO -->

                                <td>

                                    <span class="curso-chip">
                                        <?= e($cursoCompleto) ?>
                                    </span>

                                </td>


                                <!-- DOCENTE -->

                                <?php if (!$esDocente): ?>

                                    <td>
                                        <?= e($docenteNombre) ?>
                                    </td>

                                <?php endif; ?>


                                <!-- FECHA -->

                                <td>

                                    <span class="fecha-celda">

                                        <i class="bi bi-calendar3"></i>

                                        <?= e($fecha) ?>

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

                                        <?= e($fila['estado']) ?>

                                    </span>

                                </td>


                                <!-- ACCIONES -->

                                <td class="text-end">

                                    <div class="acciones-tabla">

                                        <!-- VER -->

                                        <a
                                            class="btn-accion"
                                            href="ver.php?id=<?= (int) $fila['id_derivacion'] ?>"
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
                                                href="editar.php?id=<?= (int) $fila['id_derivacion'] ?>"
                                                title="Editar derivación"
                                            >

                                                <i class="bi bi-pencil"></i>

                                                <span>
                                                    Editar
                                                </span>

                                            </a>

                                        <?php endif; ?>

                                        <?php if ($esDocente && $fila['estado'] === 'Pendiente'): ?>
                                            <form action="eliminar.php" method="POST" class="d-inline"
                                                onsubmit="return confirm('¿Eliminar esta derivación pendiente?');">
                                                <?= login_campo_csrf() ?>
                                                <input type="hidden" name="id_derivacion" value="<?= (int)$fila['id_derivacion'] ?>">
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
