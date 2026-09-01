<?php

// 1) Conectar con la base de datos
require_once '../config/conexion.php';

// 2) Cargar partes comunes de la interfaz
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// 3) Leer el id pasado por la URL y convertirlo en entero
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 4) Preparar la consulta para traer el estudiante solicitado
$sql = "SELECT * FROM estudiantes WHERE id_estudiante = ?";
$stmt = $conexion->prepare($sql);

// 5) Vincular el parámetro y ejecutar la consulta
$stmt->bind_param("i", $id);
$stmt->execute();

// 6) Obtener el resultado de la consulta
$resultado = $stmt->get_result();
$fila = $resultado->fetch_assoc();
?>

<main class="main-content">
    <div class="container-fluid py-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Editar estudiante</h1>
                <p class="text-muted mb-0">Actualice los datos del estudiante.</p>
            </div>

            <a href="listar.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4"></div>

    <!-- 7) Si existe el estudiante, mostrar el formulario con los datos cargados -->
                    <?php if ($fila): ?>
                        <form action="actualizar.php" method="POST">
                            <!-- id oculto para saber qué estudiante se actualiza -->
                            <input type="hidden" name="id_estudiante" value="<?= htmlspecialchars($fila['id_estudiante'], ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="form-select">
                                <label for="ci">CI</label>
                                <input type="text" id="ci" name="ci" class="form-control" value="<?= htmlspecialchars($fila['ci'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-select">
                                <label for="nombres">Nombres</label>
                                <input type="text" id="nombres" name="nombres" class="form-control" value="<?= htmlspecialchars($fila['nombres'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-select">
                                <label for="apellidos">Apellidos</label>
                                <input type="text" id="apellidos" name="apellidos" class="form-control" value="<?= htmlspecialchars($fila['apellidos'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-select">
                                <label for="curso">Curso</label>
                                <select id="curso" name="curso" class="form-control" required>
                                    <option value="1" <?= $fila['curso'] == "1" ? "selected" : ""; ?>>1°</option>
                                    <option value="2" <?= $fila['curso'] == "2" ? "selected" : ""; ?>>2°</option>
                                    <option value="3" <?= $fila['curso'] == "3" ? "selected" : ""; ?>>3°</option>
                                    <option value="4" <?= $fila['curso'] == "4" ? "selected" : ""; ?>>4°</option>
                                    <option value="5" <?= $fila['curso'] == "5" ? "selected" : ""; ?>>5°</option>
                                    <option value="6" <?= $fila['curso'] == "6" ? "selected" : ""; ?>>6°</option>
                                </select>
                            </div>

                            <div class="form-select">
                                <label for="paralelo">Paralelo</label>
                                <select id="paralelo" name="paralelo" class="form-control" required>
                                    <option value="A" <?= $fila['paralelo'] == "A" ? "selected" : ""; ?>>A</option>
                                    <option value="B" <?= $fila['paralelo'] == "B" ? "selected" : ""; ?>>B</option>
                                    <option value="C" <?= $fila['paralelo'] == "C" ? "selected" : ""; ?>>C</option>
                                    <option value="D" <?= $fila['paralelo'] == "D" ? "selected" : ""; ?>>D</option>
                                </select>
                            </div>

                            <div class="form-select">
                                <div class="mb-3">
                                    <label for="estado" class="form-label">Estado</label>

                                    <select id="estado" name="estado" class="form-select" required>
                                        <option value="Activo" <?= $fila['estado'] === 'Activo' ? 'selected' : ''; ?>>
                                            Activo
                                        </option>
                                        <option value="Retirado" <?= $fila['estado'] === 'Retirado' ? 'selected' : ''; ?>>
                                            Retirado
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </form>
                    <?php else: ?>
                        <!-- 8) Si no encuentra el estudiante, muestra un aviso -->
                        <div class="alert alert-warning">Estudiante no encontrado.</div>
                    <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
// 9) Incluir el pie de página
include '../includes/footer.php';
?>