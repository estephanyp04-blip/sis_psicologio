<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('informes/registrar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/datos.php';

$datosInforme = $_SESSION['datos_informe'] ?? [];
$datosInforme += ['cod_estudiante' => (int)filter_var($_GET['cod_estudiante'] ?? 0,FILTER_VALIDATE_INT)];
$seguimientoSolicitado = (int)($datosInforme['cod_seguimiento'] ?? filter_var($_GET['cod_seguimiento'] ?? 0,FILTER_VALIDATE_INT));
if (isset($_GET['cod_seguimiento']) && !isset($_SESSION['datos_informe'])) {
    $origen = flujo_fila($conexion,'SELECT s.cod_seguimiento,h.cod_estudiante FROM seguimientos s JOIN historias_clinicas h ON h.cod_historia=s.cod_historia WHERE s.cod_seguimiento=?',[$seguimientoSolicitado]);
    if (!$origen || ($datosInforme['cod_estudiante'] && (int)$datosInforme['cod_estudiante'] !== (int)$origen['cod_estudiante'])) {
        http_response_code(404); exit('El seguimiento no corresponde al estudiante.');
    }
    $datosInforme['cod_estudiante']=(int)$origen['cod_estudiante'];
}
unset($_SESSION['datos_informe']);

function e($valor):string{
    return htmlspecialchars((string)$valor,ENT_QUOTES,'UTF-8');
}

// Permisos
$rolActual=(int)($_SESSION['cod_rol']??0);
$rolesPermitidos=[1,2];
$mensaje=$_SESSION['mensaje']??'';
$tipoMensaje=$_SESSION['tipo_mensaje']??'info';
unset($_SESSION['mensaje'],$_SESSION['tipo_mensaje']);

if($rolActual>0&&!in_array($rolActual,$rolesPermitidos,true)){
    $_SESSION['mensaje']='No tiene permiso para registrar informes.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

// Usuario
$idUsuario=(int)($_SESSION['cod_usuario']??0);
$nombrePsicologa='Psicóloga';

if($idUsuario>0){
    $stmt=$conexion->prepare("SELECT p.nombres AS nombre,p.apellidos AS apellido
        FROM usuarios u INNER JOIN personas p ON p.cod_persona=u.cod_persona
        WHERE u.cod_usuario=? LIMIT 1");
    $stmt->bind_param('i',$idUsuario);
    $stmt->execute();
    $usuario=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if($usuario)$nombrePsicologa=trim($usuario['nombre'].' '.$usuario['apellido']);
}else{
    $stmt=$conexion->prepare("SELECT u.cod_usuario,p.nombres AS nombre,p.apellidos AS apellido
        FROM usuarios u INNER JOIN personas p ON p.cod_persona=u.cod_persona
        WHERE u.cod_rol=2 AND u.estado='Activo' ORDER BY u.cod_usuario ASC LIMIT 1");
    $stmt->execute();
    $usuario=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if($usuario){
        $idUsuario=(int)$usuario['cod_usuario'];
        $nombrePsicologa=trim($usuario['nombre'].' '.$usuario['apellido']);
    }
}

// El número definitivo se reserva dentro de la transacción de guardado.
$numeroFicha = 'Se asignará al guardar';

// Consulta
$sqlEstudiantes="SELECT e.cod_estudiante,e.nombres,e.apellidos,e.curso,e.paralelo,
    h.cod_historia,h.motivo_consulta,h.impresion_diagnostica diagnostico,
    s.cod_seguimiento,s.recomendaciones,d.cod_derivacion,d.motivo motivo_derivacion,
    CONCAT(doc_persona.nombres,' ',doc_persona.apellidos) docente_referente,
    (SELECT COUNT(*) FROM citas c WHERE c.cod_estudiante=e.cod_estudiante AND c.estado='Atendida') numero_atenciones
    FROM vista_estudiantes e LEFT JOIN historias_clinicas h ON h.cod_estudiante=e.cod_estudiante
    LEFT JOIN seguimientos s ON s.cod_seguimiento=(SELECT s2.cod_seguimiento FROM seguimientos s2 WHERE s2.cod_historia=h.cod_historia
        ORDER BY (s2.cod_seguimiento=$seguimientoSolicitado) DESC,s2.fecha DESC,s2.cod_seguimiento DESC LIMIT 1)
    LEFT JOIN citas cs ON cs.cod_cita=s.cod_cita
    LEFT JOIN derivaciones d ON d.cod_derivacion=COALESCE(cs.cod_derivacion,h.cod_derivacion_origen,
        CASE WHEN h.cod_historia IS NULL THEN (SELECT d2.cod_derivacion FROM derivaciones d2 WHERE d2.cod_estudiante=e.cod_estudiante ORDER BY d2.fecha DESC,d2.cod_derivacion DESC LIMIT 1) ELSE NULL END)
    LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
    LEFT JOIN personas doc_persona ON doc_persona.cod_persona=doc.cod_persona
    WHERE e.estado='Activo' ORDER BY e.apellidos,e.nombres";
$resultadoEstudiantes=$conexion->query($sqlEstudiantes);

if(!$resultadoEstudiantes){
    die('Error al consultar estudiantes: '.htmlspecialchars($conexion->error));
}

// HTML
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="listar.php">Informes</a></li>
            <li class="breadcrumb-item active">Registrar</li>
        </ol>
    </nav>

    <?php if($mensaje!==''): ?>
        <div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show">
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="formulario-derivacion">
        <div class="form-header mb-4">
            <div>
                <span class="text-primary fw-semibold">
                    <i class="bi bi-file-earmark-medical me-1"></i>
                    Departamento de Psicología
                </span>
                <h2 class="mt-2">Nueva ficha psicológica</h2>
                <p class="mb-0">Registro de informe psicológico según la ficha del Anexo 4.</p>
            </div>
        </div>

        <form action="guardar.php" method="POST" id="formInforme" autocomplete="off">
            <?= login_campo_csrf() ?>
            <input type="hidden" name="cod_historia" id="cod_historia"><input type="hidden" name="cod_seguimiento" id="cod_seguimiento"><p id="seguimiento_origen" class="text-muted"></p>
            <input type="hidden" name="cod_derivacion" id="cod_derivacion">
            <input type="hidden" name="estado" value="<?= e($datosInforme['estado'] ?? 'Borrador') ?>">

            <!-- Datos generales -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-person-vcard me-2"></i>1. Datos generales</h5>
                <hr>
                <div class="row g-4">
                    <div class="col-12">
                        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" id="titulo" class="form-control" maxlength="180" required value="<?= e($datosInforme['titulo'] ?? 'Informe psicológico individual') ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Ficha psicológica</label>
                        <input type="text" class="form-control" value="<?= e($numeroFicha) ?>" readonly>
                        <small class="text-muted">Se genera automáticamente.</small>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?= e($datosInforme['fecha'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-lg-6">
                        <label for="cod_estudiante" class="form-label">Estudiante <span class="text-danger">*</span></label>
                        <select name="cod_estudiante" id="cod_estudiante" class="form-select" required>
                            <option value="">Seleccione un estudiante...</option>

                            <?php while($estudiante=$resultadoEstudiantes->fetch_assoc()): ?>
                                <?php $nombreCompleto=trim($estudiante['apellidos'].' '.$estudiante['nombres']); ?>

                                <option
                                    value="<?= (int)$estudiante['cod_estudiante'] ?>"
                                    <?= (int)($datosInforme['cod_estudiante'] ?? 0) === (int)$estudiante['cod_estudiante'] ? 'selected' : '' ?>
                                    data-curso="<?= e($estudiante['curso']??'') ?>"
                                    data-paralelo="<?= e($estudiante['paralelo']??'') ?>"
                                    data-seguimiento="<?= (int)($estudiante['cod_seguimiento']??0) ?>" data-historia="<?= (int)($estudiante['cod_historia']??0) ?>"
                                    data-derivacion="<?= (int)($estudiante['cod_derivacion']??0) ?>"
                                    data-atenciones="<?= (int)($estudiante['numero_atenciones']??0) ?>"
                                    data-docente="<?= e($estudiante['docente_referente']??'') ?>"
                                    data-motivo-derivacion="<?= e($estudiante['motivo_derivacion']??'') ?>"
                                    data-motivo-historia="<?= e($estudiante['motivo_consulta']??'') ?>"
                                    data-diagnostico="<?= e($estudiante['diagnostico']??'') ?>"
                                    data-recomendaciones="<?= e($estudiante['recomendaciones']??'') ?>">
                                    <?= e($nombreCompleto) ?> — <?= e($estudiante['curso']??'') ?> <?= e($estudiante['paralelo']??'') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Curso</label>
                        <input type="text" id="curso" class="form-control bg-light" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Paralelo</label>
                        <input type="text" id="paralelo" class="form-control bg-light" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Colegio</label>
                        <input type="text" class="form-control bg-light" value='U.E. Cañada Pailita "B"' readonly>
                    </div>
                </div>
            </section>

            <!-- Información automática -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-magic me-2"></i>2. Información automática</h5>
                <hr>
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label">N.º de atenciones</label>
                        <input type="number" name="numero_atenciones" id="numero_atenciones" class="form-control bg-light" value="0" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Referido por</label>
                        <input type="text" name="referido_por" id="referido_por" class="form-control bg-light" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Docente que refiere</label>
                        <input type="text" id="docente_referente" class="form-control bg-light" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Historia clínica</label>
                        <input type="text" id="historia_estado" class="form-control bg-light" value="Seleccione un estudiante" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Psicólogo/a</label>
                        <input type="text" class="form-control bg-light" value="<?= e($nombrePsicologa) ?>" readonly>
                    </div>
                </div>
            </section>

            <!-- Atención -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-clipboard2-pulse me-2"></i>3. Datos de la atención</h5>
                <hr>

                <label class="form-label d-block">Tipo de atención <span class="text-danger">*</span></label>

                <div class="row g-3 mb-4">
                    <?php
                    $tiposAtencion = INFORME_ATENCIONES;
                    ?>

                    <?php foreach($tiposAtencion as $indice=>$tipo): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-check border rounded-3 p-3 h-100">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="tipo_atencion[]" value="<?= e($tipo) ?>" id="tipo_<?= $indice ?>" <?= in_array($tipo, $datosInforme['tipo_atencion'] ?? [], true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo_<?= $indice ?>"><?= e($tipo) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="5" maxlength="5000" placeholder="Se cargará desde la última derivación o historia clínica." required><?= e($datosInforme['motivo'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="diagnostico" class="form-label">Diagnóstico</label>
                        <textarea name="diagnostico" id="diagnostico" class="form-control" rows="5" maxlength="5000" placeholder="Se cargará desde la impresión diagnóstica de la historia clínica."><?= e($datosInforme['diagnostico'] ?? '') ?></textarea>
                    </div>
                </div>
            </section>

            <!-- Síntesis -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-person-lines-fill me-2"></i>4. Síntesis psicológica</h5>
                <hr>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="aspecto_cognitivo" class="form-label">Aspecto madurativo y/o cognitivo <span class="text-danger">*</span></label>
                        <textarea name="aspecto_cognitivo" id="aspecto_cognitivo" class="form-control" rows="6" maxlength="5000" placeholder="Describa los aspectos cognitivos y/o madurativos observados." required><?= e($datosInforme['aspecto_cognitivo'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="aspectos_afectivos" class="form-label">Aspectos afectivos <span class="text-danger">*</span></label>
                        <textarea name="aspectos_afectivos" id="aspectos_afectivos" class="form-control" rows="6" maxlength="5000" placeholder="Describa los aspectos emocionales y afectivos observados." required><?= e($datosInforme['aspectos_afectivos'] ?? '') ?></textarea>
                    </div>
                </div>
            </section>

            <!-- Acuerdos -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-check2-square me-2"></i>5. Diagnóstico, acuerdos y/o compromisos</h5>
                <hr>
                <textarea name="diagnostico_acuerdos" id="diagnostico_acuerdos" class="form-control" rows="6" maxlength="5000" placeholder="Registre acuerdos, compromisos y acciones establecidas."><?= e($datosInforme['diagnostico_acuerdos'] ?? '') ?></textarea>
            </section>

            <!-- Recomendaciones -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-lightbulb me-2"></i>6. Recomendaciones y/o sugerencias</h5>
                <hr>
                <textarea name="recomendaciones" id="recomendaciones" class="form-control" rows="6" maxlength="5000" placeholder="Las recomendaciones del último seguimiento se cargarán automáticamente."><?= e($datosInforme['recomendaciones'] ?? '') ?></textarea>
            </section>

            <!-- Recepción -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-person-check me-2"></i>7. Recepción del informe</h5>
                <hr>
                <div class="row g-4">
                    <div class="col-md-8">
                        <label for="recibido_por" class="form-label">Recibido por</label>
                        <input type="text" name="recibido_por" id="recibido_por" value="<?= e($datosInforme['recibido_por'] ?? '') ?>" class="form-control" maxlength="150" placeholder="Nombre de la persona que recibe el informe">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <input type="text" class="form-control bg-light" value="Borrador" readonly>
                    </div>
                </div>
            </section>

            <!-- Botones -->
            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mb-4">
                <a href="listar.php" class="btn btn-light border px-4">
                    <i class="bi bi-x-circle me-1"></i>Cancelar
                </a>

                <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
                    <i class="bi bi-save me-1"></i>Guardar informe
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// JavaScript
document.addEventListener('DOMContentLoaded',function(){
    const estudiante=document.getElementById('cod_estudiante');
    const curso=document.getElementById('curso');
    const paralelo=document.getElementById('paralelo');
    const atenciones=document.getElementById('numero_atenciones');
    const referido=document.getElementById('referido_por');
    const docente=document.getElementById('docente_referente');
    const historia=document.getElementById('cod_historia');
    const derivacion=document.getElementById('cod_derivacion');
    const historiaEstado=document.getElementById('historia_estado');
    const motivo=document.getElementById('motivo');
    const diagnostico=document.getElementById('diagnostico');
    const recomendaciones=document.getElementById('recomendaciones');
    const form=document.getElementById('formInforme');
    const btnGuardar=document.getElementById('btnGuardar');

    function limpiarDatos(){
        document.getElementById('cod_seguimiento').value='0';
        document.getElementById('seguimiento_origen').textContent='';
        curso.value='';
        paralelo.value='';
        atenciones.value='0';
        referido.value='';
        docente.value='';
        historia.value='';
        derivacion.value='';
        historiaEstado.value='Seleccione un estudiante';
        motivo.value='';
        diagnostico.value='';
        recomendaciones.value='';
    }

    function cargarDatos(){
        const opcion=estudiante.options[estudiante.selectedIndex];

        if(!opcion||!opcion.value){
            limpiarDatos();
            return;
        }

        curso.value=opcion.dataset.curso||'';
        paralelo.value=opcion.dataset.paralelo||'';
        atenciones.value=opcion.dataset.atenciones||'0';
        docente.value=opcion.dataset.docente||'';
        historia.value=opcion.dataset.historia||'';
        document.getElementById('cod_seguimiento').value=opcion.dataset.seguimiento||'0';
        document.getElementById('seguimiento_origen').textContent=Number(opcion.dataset.seguimiento) ? 'Seguimiento de origen #'+opcion.dataset.seguimiento : 'Sin seguimiento de origen';
        derivacion.value=opcion.dataset.derivacion||'';

        if(opcion.dataset.historia&&opcion.dataset.historia!=='0'){
            historiaEstado.value='Historia clínica disponible - N.º '+opcion.dataset.historia;
        }else{
            historiaEstado.value='Sin historia clínica registrada';
        }

        if(opcion.dataset.derivacion&&opcion.dataset.derivacion!=='0'){
            referido.value='Profesor';
        }else{
            referido.value='Voluntario';
        }

        motivo.value=opcion.dataset.motivoDerivacion||opcion.dataset.motivoHistoria||'';
        diagnostico.value=opcion.dataset.diagnostico||'';
        recomendaciones.value=opcion.dataset.recomendaciones||'';
    }

    estudiante.addEventListener('change',cargarDatos);
    cargarDatos();
    const datosRecuperados = <?= json_encode($datosInforme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    for (const campo of ['motivo','diagnostico','aspecto_cognitivo','aspectos_afectivos','diagnostico_acuerdos','recomendaciones','recibido_por','numero_atenciones','referido_por']) {
        if (Object.prototype.hasOwnProperty.call(datosRecuperados, campo)) {
            document.getElementById(campo).value = datosRecuperados[campo];
        }
    }

    form.addEventListener('submit',function(event){
        const tipos=form.querySelectorAll('input[name="tipo_atencion[]"]:checked');

        if(!estudiante.value){
            event.preventDefault();
            alert('Seleccione un estudiante.');
            estudiante.focus();
            return;
        }

        if(tipos.length===0){
            event.preventDefault();
            alert('Seleccione al menos un tipo de atención.');
            return;
        }

        if(!historia.value||historia.value==='0'){
            if(!confirm('El estudiante no tiene una historia clínica registrada. ¿Desea continuar igualmente?')){
                event.preventDefault();
                return;
            }
        }

        btnGuardar.disabled=true;
        btnGuardar.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
