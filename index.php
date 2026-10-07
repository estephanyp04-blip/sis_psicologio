<?php
require_once __DIR__ . '/includes/autenticacion.php';
requerir_acceso('index.php');


require_once 'config/conexion.php';

date_default_timezone_set('America/La_Paz');


function obtenerTotalDashboard(mysqli $conexion, string $sql): int
{
    try {
        $resultado = $conexion->query($sql);

        if (!$resultado) {
            return 0;
        }

        $fila = $resultado->fetch_assoc();

        return (int) ($fila['total'] ?? 0);
    } catch (mysqli_sql_exception $e) {
        error_log('Dashboard: ' . $e->getMessage());
        return 0;
    }
}

$totalEstudiantes = obtenerTotalDashboard(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM estudiantes
     WHERE estado = 'Activo'"
);

$derivacionesPendientes = obtenerTotalDashboard(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM derivaciones
     WHERE estado = 'Pendiente'"
);

$citasHoy = obtenerTotalDashboard(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM citas
     WHERE fecha = CURDATE()
       AND estado <> 'Cancelada'"
);

$totalHistorias = obtenerTotalDashboard(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM historias_clinicas"
);

$nombreUsuario = trim((string) ($_SESSION['nombre'] ?? ''));
$fechaActual = date('d/m/Y');

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';
?>

<main class="main-content dashboard-page">
    <div class="container-fluid">

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active" aria-current="page">
                    Inicio
                </li>
            </ol>
        </nav>

        <section class="dashboard-welcome">
            <div>
                <span class="dashboard-welcome-label">
                    <i class="bi bi-grid-1x2-fill"></i>
                    Panel principal
                </span>

                <h1>
                    <?= $nombreUsuario !== ''
                        ? 'Bienvenida, ' . htmlspecialchars($nombreUsuario)
                        : 'Sistema Psicológico Estudiantil'; ?>
                </h1>

                <p>
                    Administre estudiantes, derivaciones, citas e historias
                    clínicas desde un solo lugar.
                </p>
            </div>

            <div class="dashboard-date">
                <i class="bi bi-calendar3"></i>
                <div>
                    <small>Fecha actual</small>
                    <strong><?= htmlspecialchars($fechaActual); ?></strong>
                </div>
            </div>
        </section>

        <section aria-labelledby="titulo-resumen" class="mb-5">
            <div class="dashboard-section-heading">
                <div>
                    <h2 id="titulo-resumen">Resumen general</h2>
                    <p>Información actual del sistema.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-stat dashboard-stat-blue">
                        <div class="dashboard-stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="dashboard-stat-content">
                            <span>Estudiantes activos</span>
                            <strong><?= number_format($totalEstudiantes); ?></strong>
                            <a href="estudiantes/listar.php">
                                Ver estudiantes
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-stat dashboard-stat-orange">
                        <div class="dashboard-stat-icon">
                            <i class="bi bi-exclamation-circle-fill"></i>
                        </div>
                        <div class="dashboard-stat-content">
                            <span>Derivaciones pendientes</span>
                            <strong><?= number_format($derivacionesPendientes); ?></strong>
                            <a href="derivaciones/listar.php">
                                Ver derivaciones
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-stat dashboard-stat-green">
                        <div class="dashboard-stat-icon">
                            <i class="bi bi-calendar-check-fill"></i>
                        </div>
                        <div class="dashboard-stat-content">
                            <span>Citas de hoy</span>
                            <strong><?= number_format($citasHoy); ?></strong>
                            <a href="citas/listar.php">
                                Ver citas
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-stat dashboard-stat-purple">
                        <div class="dashboard-stat-icon">
                            <i class="bi bi-file-earmark-medical-fill"></i>
                        </div>
                        <div class="dashboard-stat-content">
                            <span>Historias clínicas</span>
                            <strong><?= number_format($totalHistorias); ?></strong>
                            <a href="historias_clinicas/listar.php">
                                Ver historias
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section aria-labelledby="titulo-accesos">
            <div class="dashboard-section-heading">
                <div>
                    <h2 id="titulo-accesos">Accesos principales</h2>
                    <p>Seleccione la función que desea utilizar.</p>
                </div>
            </div>

            <div class="row g-4 dashboard-modules">
                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-blue"
                       href="estudiantes/listar.php">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-people"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Estudiantes</strong>
                            <small>Registrar, consultar y actualizar estudiantes.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-orange"
                       href="derivaciones/listar.php">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-send"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Derivaciones</strong>
                            <small>Registrar derivaciones y revisar su estado.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-green"
                       href="citas/listar.php">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-calendar2-week"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Citas</strong>
                            <small>Programar y consultar las citas psicológicas.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-purple"
                       href="historias_clinicas/listar.php">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-file-earmark-medical"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Historias clínicas</strong>
                            <small>Registrar y consultar la atención psicológica.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-cyan"
                       href="docentes/listar.php">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-person-badge"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Docentes</strong>
                            <small>Consultar los docentes registrados.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-red"
                       href="informes/listar.php">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Informes</strong>
                            <small>Generar y consultar informes mensuales.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="dashboard-module dashboard-module-indigo"
                       href="<?= login_html(login_url('estadisticas/index.php')) ?>">
                        <span class="dashboard-module-icon">
                            <i class="bi bi-bar-chart-line"></i>
                        </span>
                        <span class="dashboard-module-text">
                            <strong>Estadísticas</strong>
                            <small>Consultar las sesiones y el seguimiento de los estudiantes.</small>
                        </span>
                        <i class="bi bi-arrow-up-right dashboard-module-arrow"></i>
                    </a>
                </div>
            </div>
        </section>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
