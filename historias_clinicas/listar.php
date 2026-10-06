<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/listar.php');

require_once '../config/conexion.php';

/* PERMISOS*/

$rolActual = isset($_SESSION['id_rol'])
    ? (int) $_SESSION['id_rol']
    : 0;

$rolesPermitidos = [1, 2];

if (
    !in_array($rolActual, $rolesPermitidos, true)
) {
    $_SESSION['mensaje'] =
        'No tiene permiso para consultar historias clínicas.';

    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: ../index.php');
    exit;
}

$puedeEditar = in_array($rolActual, [1, 2], true);

/*  FUNCIONES */

function e($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* CONSULTAR HISTORIAS CLÍNICAS*/

$sql = "
    SELECT
        h.id_historia,
        h.fecha_apertura,
        h.estado,
        e.ci,
        e.nombres,
        e.apellidos,
        e.curso,
        e.paralelo,
        CONCAT_WS(' ', p.nombres, p.apellidos) AS profesional
    FROM historias_clinicas h
    INNER JOIN vista_estudiantes e
        ON e.id_estudiante = h.id_estudiante
    LEFT JOIN usuarios u
        ON u.id_usuario = h.id_psicologa
    LEFT JOIN personas p
        ON p.id_persona = u.id_persona
    ORDER BY
        h.fecha_apertura DESC,
        h.id_historia DESC
";

$resultado = $conexion->query($sql);

$historias = $resultado
    ? $resultado->fetch_all(MYSQLI_ASSOC)
    : [];

$totalHistorias = count($historias);

$historiasActivas = count(
    array_filter(
        $historias,
        function ($historia) {
            return $historia['estado'] === 'Activa';
        }
    )
);

/* MENSAJES */

$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';

$tiposPermitidos = [
    'success',
    'danger',
    'warning',
    'info'
];

if (!in_array($tipoMensaje, $tiposPermitidos, true)) {
    $tipoMensaje = 'info';
}

unset(
    $_SESSION['mensaje'],
    $_SESSION['tipo_mensaje']
);

/* INCLUDES*/

$tituloPagina = 'Historias clínicas';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content historias-page">
    <div class="container-fluid">

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
                </li>
                <li class="breadcrumb-item active">
                    Historias clínicas
                </li>
            </ol>
        </nav>

        <section class="historias-hero">
            <div>
                <span>
                    <i class="bi bi-shield-lock-fill"></i>
                    Información confidencial
                </span>

                <h1>Historias clínicas</h1>

                <p>
                    Expedientes psicopedagógicos y seguimiento
                    profesional de estudiantes.
                </p>
            </div>

            <?php if ($puedeEditar): ?>
                <a
                    href="registrar.php"
                    class="btn btn-light">

                    <i class="bi bi-file-earmark-plus me-2"></i>
                    Nueva historia clínica
                </a>
            <?php endif; ?>
        </section>

        <?php if ($mensaje !== ''): ?>
            <div
                class="alert alert-<?= e($tipoMensaje) ?>
                alert-dismissible fade show"
                role="alert">

                <?= nl2br(e($mensaje)) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Cerrar">
                </button>
            </div>
        <?php endif; ?>

        <section class="historias-resumen">
            <article>
                <i class="bi bi-folder2-open"></i>

                <div>
                    <strong><?= $totalHistorias ?></strong>
                    <span>Expedientes registrados</span>
                </div>
            </article>

            <article>
                <i class="bi bi-activity"></i>

                <div>
                    <strong><?= $historiasActivas ?></strong>
                    <span>Historias activas</span>
                </div>
            </article>
        </section>

        <section class="historias-toolbar">
            <div class="historia-buscar">
                <i class="bi bi-search"></i>

                <input
                    type="search"
                    id="buscarHistoria"
                    placeholder="Buscar estudiante, CI o curso..."
                >
            </div>

            <select
                id="filtroHistoria"
                class="form-select">

                <option value="">Todos los estados</option>
                <option value="Activa">Activa</option>
                <option value="En seguimiento">
                    En seguimiento
                </option>
                <option value="Cerrada">Cerrada</option>
            </select>
        </section>

        <section class="historias-card">
            <div class="historias-card-head">
                <div>
                    <h2>Expedientes</h2>

                    <p>
                        <span id="cantidadHistorias">
                            <?= $totalHistorias ?>
                        </span>
                        resultados
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table
                    class="table historias-tabla"
                    id="tablaHistorias">

                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Curso</th>
                            <th>Fecha de apertura</th>
                            <th>Profesional</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($historias as $historia): ?>
                            <?php
                            $nombreCompleto = trim(
                                $historia['nombres'] . ' ' .
                                $historia['apellidos']
                            );

                            $inicial = mb_strtoupper(
                                mb_substr($nombreCompleto, 0, 1)
                            );

                            $estadoClase = mb_strtolower(
                                str_replace(
                                    ' ',
                                    '-',
                                    $historia['estado']
                                )
                            );
                            ?>

                            <tr
                                data-registro="1"
                                data-estado="<?= e($historia['estado']) ?>">

                                <td>
                                    <div class="historia-estudiante">
                                        <span><?= e($inicial) ?></span>

                                        <div>
                                            <strong>
                                                <?= e($nombreCompleto) ?>
                                            </strong>

                                            <small>
                                                CI:
                                                <?= e(
                                                    $historia['ci'] ?: '—'
                                                ) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <?= e(
                                        $historia['curso'] . '° ' .
                                        $historia['paralelo']
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $historia['fecha_apertura']
                                            )
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $historia['profesional']
                                        ?: 'Sin asignar'
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="historia-estado
                                        historia-<?= e($estadoClase) ?>">

                                        <?= e($historia['estado']) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <div class="acciones-tabla">
                                        <a
                                            class="btn-accion"
                                            href="ver.php?id=<?= (int)
                                                $historia['id_historia']
                                            ?>">

                                            <i class="bi bi-eye"></i>
                                            <span>Ver</span>
                                        </a>

                                        <?php if ($puedeEditar): ?>
                                            <a
                                                class="btn-accion editar"
                                                href="editar.php?id=<?= (int)
                                                    $historia['id_historia']
                                                ?>">

                                                <i class="bi bi-pencil"></i>
                                                <span>Editar</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr
                            id="sinHistorias"
                            style="display: none;">

                            <td
                                colspan="6"
                                class="estado-vacio">

                                <i class="bi bi-search"></i>
                                <strong>
                                    No se encontraron expedientes
                                </strong>
                                <span>
                                    Cambie la búsqueda o el estado.
                                </span>
                            </td>
                        </tr>

                        <?php if (empty($historias)): ?>
                            <tr>
                                <td
                                    colspan="6"
                                    class="estado-vacio">

                                    <i class="bi bi-folder2"></i>
                                    <strong>
                                        No hay historias clínicas
                                    </strong>
                                    <span>
                                        Registre el primer expediente clínico.
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
document.addEventListener('DOMContentLoaded', function () {
    const buscador = document.getElementById('buscarHistoria');
    const filtroEstado = document.getElementById('filtroHistoria');

    const filas = [
        ...document.querySelectorAll('tr[data-registro="1"]')
    ];

    const cantidad = document.getElementById('cantidadHistorias');
    const sinHistorias = document.getElementById('sinHistorias');

    function normalizar(valor) {
        return String(valor)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function filtrarHistorias() {
        let visibles = 0;

        filas.forEach(function (fila) {
            const textoFila = normalizar(fila.textContent);
            const textoBusqueda = normalizar(buscador.value);

            const coincideBusqueda =
                textoBusqueda === '' ||
                textoFila.includes(textoBusqueda);

            const coincideEstado =
                filtroEstado.value === '' ||
                fila.dataset.estado === filtroEstado.value;

            const mostrar =
                coincideBusqueda &&
                coincideEstado;

            fila.style.display = mostrar ? '' : 'none';

            if (mostrar) {
                visibles++;
            }
        });

        cantidad.textContent = visibles;

        sinHistorias.style.display =
            filas.length > 0 && visibles === 0
                ? ''
                : 'none';
    }

    buscador.addEventListener(
        'input',
        filtrarHistorias
    );

    filtroEstado.addEventListener(
        'change',
        filtrarHistorias
    );
});
</script>

<?php include '../includes/footer.php'; ?>