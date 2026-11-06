<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('informes/registrar.php');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$datosInforme = $_SESSION['datos_informe'] ?? [];
$datosInforme += ['cod_estudiante' => (int)filter_var($_GET['cod_estudiante'] ?? 0,FILTER_VALIDATE_INT)];
$datosInforme += ['cod_historia' => (int)filter_var($_GET['cod_historia'] ?? 0,FILTER_VALIDATE_INT)];
$seguimientoSolicitado = (int)($datosInforme['cod_seguimiento'] ?? filter_var($_GET['cod_seguimiento'] ?? 0,FILTER_VALIDATE_INT));
if (isset($_GET['cod_seguimiento']) && !isset($_SESSION['datos_informe'])) {
    $origen = flujo_fila($conexion,'SELECT s.cod_seguimiento,h.cod_estudiante,h.cod_historia FROM seguimientos s JOIN historias_clinicas h ON h.cod_historia=s.cod_historia WHERE s.cod_seguimiento=?',[$seguimientoSolicitado]);
    if (!$origen || ($datosInforme['cod_estudiante'] && (int)$datosInforme['cod_estudiante'] !== (int)$origen['cod_estudiante'])) {
        http_response_code(404); exit('El seguimiento no corresponde al estudiante.');
    }
    $datosInforme['cod_estudiante']=(int)$origen['cod_estudiante'];
    $datosInforme['cod_historia']=(int)$origen['cod_historia'];
}
unset($_SESSION['datos_informe']);


$mensaje=$_SESSION['mensaje']??'';
$tipoMensaje=$_SESSION['tipo_mensaje']??'info';
unset($_SESSION['mensaje'],$_SESSION['tipo_mensaje']);

// Usuario
$idUsuario=(int)($_SESSION['cod_usuario']??0);
$nombrePsicologa='Psicóloga';

if($idUsuario>0){
    $stmt=$conexion->prepare("SELECT u.nombres AS nombre,u.apellidos AS apellido
        FROM usuarios u
        WHERE u.cod_usuario=? LIMIT 1");
    $stmt->bind_param('i',$idUsuario);
    $stmt->execute();
    $usuario=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if($usuario)$nombrePsicologa=trim($usuario['nombre'].' '.$usuario['apellido']);
}else{
    $stmt=$conexion->prepare("SELECT u.cod_usuario,u.nombres AS nombre,u.apellidos AS apellido
        FROM usuarios u
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
$fechaCorte=flujo_fecha($datosInforme['fecha']??date('Y-m-d'));
$historiaSolicitada = (int)$datosInforme['cod_historia'];
$opcionesHistorias = $datosInforme['cod_estudiante'] ? informe_historias($conexion, (int)$datosInforme['cod_estudiante'], $fechaCorte) : [];
$sqlEstudiantes="SELECT e.cod_estudiante,e.nombres,e.apellidos,e.curso,e.paralelo,
    h.cod_historia,CASE WHEN DATE(h.fecha_actualizacion)<='$fechaCorte' THEN h.motivo_consulta ELSE '' END motivo_consulta,CASE WHEN DATE(h.fecha_actualizacion)<='$fechaCorte' THEN h.impresion_diagnostica ELSE '' END diagnostico,
    s.cod_seguimiento,s.recomendaciones,d.cod_derivacion,CASE WHEN DATE(d.fecha_actualizacion)<='$fechaCorte' THEN d.motivo ELSE '' END motivo_derivacion,
    CONCAT(COALESCE(d.autor_nombres,doc_persona.nombres),' ',COALESCE(d.autor_apellidos,doc_persona.apellidos)) docente_referente,
    (SELECT COUNT(*) FROM seguimientos sx JOIN historias_clinicas hx ON hx.cod_historia=sx.cod_historia WHERE hx.cod_estudiante=e.cod_estudiante AND sx.fecha<='$fechaCorte') numero_atenciones
    FROM vista_estudiantes e LEFT JOIN historias_clinicas h ON h.cod_historia=(
        SELECT MIN(hx.cod_historia) FROM historias_clinicas hx WHERE hx.cod_estudiante=e.cod_estudiante
        AND hx.fecha_apertura<='$fechaCorte' AND ($historiaSolicitada=0 OR hx.cod_historia=$historiaSolicitada)
        HAVING COUNT(*)=1)
    LEFT JOIN seguimientos s ON s.cod_seguimiento=(SELECT s2.cod_seguimiento FROM seguimientos s2 WHERE s2.cod_historia=h.cod_historia
        AND s2.fecha<='$fechaCorte' ORDER BY (s2.cod_seguimiento=$seguimientoSolicitado) DESC,s2.fecha DESC,s2.cod_seguimiento DESC LIMIT 1)
    LEFT JOIN citas cs ON cs.cod_cita=s.cod_cita
    LEFT JOIN derivaciones d ON d.cod_derivacion=COALESCE(s.cod_derivacion,cs.cod_derivacion,h.cod_derivacion_origen,
        CASE WHEN h.cod_historia IS NULL THEN (SELECT d2.cod_derivacion FROM derivaciones d2 WHERE d2.cod_estudiante=e.cod_estudiante AND d2.fecha<='$fechaCorte' ORDER BY d2.fecha DESC,d2.cod_derivacion DESC LIMIT 1) ELSE NULL END) AND d.fecha<='$fechaCorte'
    LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
    LEFT JOIN usuarios doc_persona ON doc_persona.cod_usuario=COALESCE(d.cod_usuario_autor,doc.cod_usuario)
    WHERE e.estado='Activo' ORDER BY e.apellidos,e.nombres";
$resultadoEstudiantes=$conexion->query($sqlEstudiantes);

if(!$resultadoEstudiantes){
    die('Error al consultar estudiantes: '.htmlspecialchars($conexion->error));
}

// HTML
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
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
        <div class="alert alert-<?= login_html($tipoMensaje) ?> alert-dismissible fade show">
            <?= login_html($mensaje) ?>
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
                <p class="mb-0">Registro de informe psicológico basado en la ficha del Anexo E.</p>
            </div>
        </div>

        <form action="guardar.php" method="POST" id="formInforme" autocomplete="off">
            <?= login_campo_csrf() ?>
            <input type="hidden" name="cod_seguimiento" id="cod_seguimiento" value="<?= $seguimientoSolicitado ?>"><p id="seguimiento_origen" class="text-muted" role="status"></p>
            <input type="hidden" name="cod_derivacion" id="cod_derivacion">
            <input type="hidden" name="estado" value="<?= login_html($datosInforme['estado'] ?? 'Borrador') ?>">

            <!-- Datos generales -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-person-vcard me-2"></i>1. Datos generales</h5>
                <hr>
                <div class="row g-4">
                    <div class="col-12">
                        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" id="titulo" class="form-control" maxlength="180" required value="<?= login_html($datosInforme['titulo'] ?? 'Informe psicológico individual') ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Ficha psicológica</label>
                        <input type="text" class="form-control" value="<?= login_html($numeroFicha) ?>" readonly>
                        <small class="text-muted">Se genera automáticamente.</small>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?= login_html($datosInforme['fecha'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
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
                                    data-curso="<?= login_html($estudiante['curso']??'') ?>"
                                    data-paralelo="<?= login_html($estudiante['paralelo']??'') ?>"
                                    data-seguimiento="<?= (int)($estudiante['cod_seguimiento']??0) ?>" data-historia="<?= (int)($estudiante['cod_historia']??0) ?>"
                                    data-derivacion="<?= (int)($estudiante['cod_derivacion']??0) ?>"
                                    data-atenciones="<?= (int)($estudiante['numero_atenciones']??0) ?>"
                                    data-docente="<?= login_html($estudiante['docente_referente']??'') ?>"
                                    data-motivo-derivacion="<?= login_html($estudiante['motivo_derivacion']??'') ?>"
                                    data-motivo-historia="<?= login_html($estudiante['motivo_consulta']??'') ?>"
                                    data-diagnostico="<?= login_html($estudiante['diagnostico']??'') ?>"
                                    data-recomendaciones="<?= login_html($estudiante['recomendaciones']??'') ?>">
                                    <?= login_html($nombreCompleto) ?> — <?= login_html($estudiante['curso']??'') ?> <?= login_html($estudiante['paralelo']??'') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="cod_historia" class="form-label">Historia clínica del informe</label>
                        <select name="cod_historia" id="cod_historia" class="form-select" <?= count($opcionesHistorias)>1 ? 'required' : '' ?>>
                            <option value="<?= $opcionesHistorias ? '' : '0' ?>"><?= $opcionesHistorias ? 'Seleccione una historia clínica' : 'Sin historia disponible en esta fecha' ?></option>
                            <?php foreach ($opcionesHistorias as $opcionHistoria): ?>
                            <option value="<?= (int)$opcionHistoria['cod_historia'] ?>" <?= $historiaSolicitada===(int)$opcionHistoria['cod_historia'] || (!$historiaSolicitada && count($opcionesHistorias)===1) ? 'selected' : '' ?>>Historia #<?= (int)$opcionHistoria['cod_historia'] ?> · <?= login_html($opcionHistoria['fecha_apertura'].' · '.$opcionHistoria['estado']) ?> · <?= $opcionHistoria['cod_derivacion_origen'] ? 'Derivación #'.(int)$opcionHistoria['cod_derivacion_origen'] : 'Sin derivación' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">El motivo, diagnóstico y seguimiento se cargarán desde la historia seleccionada.</small>
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
                        <label class="form-label">N.º total de atenciones del estudiante</label>
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
                        <input type="text" class="form-control bg-light" value="<?= login_html($nombrePsicologa) ?>" readonly>
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
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="tipo_atencion[]" value="<?= login_html($tipo) ?>" id="tipo_<?= $indice ?>" <?= in_array($tipo, $datosInforme['tipo_atencion'] ?? [], true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo_<?= $indice ?>"><?= login_html($tipo) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="5" maxlength="5000" placeholder="Se cargará desde la última derivación o historia clínica." required><?= login_html($datosInforme['motivo'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="diagnostico" class="form-label">Diagnóstico</label>
                        <textarea name="diagnostico" id="diagnostico" class="form-control" rows="5" maxlength="5000" placeholder="Se cargará desde la impresión diagnóstica de la historia clínica."><?= login_html($datosInforme['diagnostico'] ?? '') ?></textarea>
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
                        <textarea name="aspecto_cognitivo" id="aspecto_cognitivo" class="form-control" rows="6" maxlength="5000" placeholder="Describa los aspectos cognitivos y/o madurativos observados." required><?= login_html($datosInforme['aspecto_cognitivo'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="aspectos_afectivos" class="form-label">Aspectos afectivos <span class="text-danger">*</span></label>
                        <textarea name="aspectos_afectivos" id="aspectos_afectivos" class="form-control" rows="6" maxlength="5000" placeholder="Describa los aspectos emocionales y afectivos observados." required><?= login_html($datosInforme['aspectos_afectivos'] ?? '') ?></textarea>
                    </div>
                </div>
            </section>

            <!-- Acuerdos -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-check2-square me-2"></i>5. Diagnóstico, acuerdos y/o compromisos</h5>
                <hr>
                <textarea name="diagnostico_acuerdos" id="diagnostico_acuerdos" class="form-control" rows="6" maxlength="5000" placeholder="Registre acuerdos, compromisos y acciones establecidas."><?= login_html($datosInforme['diagnostico_acuerdos'] ?? '') ?></textarea>
            </section>

            <!-- Recomendaciones -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-lightbulb me-2"></i>6. Recomendaciones y/o sugerencias</h5>
                <hr>
                <textarea name="recomendaciones" id="recomendaciones" class="form-control" rows="6" maxlength="5000" placeholder="Las recomendaciones del último seguimiento se cargarán automáticamente."><?= login_html($datosInforme['recomendaciones'] ?? '') ?></textarea>
            </section>

            <!-- Recepción -->
            <section class="form-section mb-4">
                <h5 class="section-title"><i class="bi bi-person-check me-2"></i>7. Recepción del informe</h5>
                <hr>
                <div class="row g-4">
                    <div class="col-md-8">
                        <label for="recibido_por" class="form-label">Recibido por</label>
                        <input type="text" name="recibido_por" id="recibido_por" value="<?= login_html($datosInforme['recibido_por'] ?? '') ?>" class="form-control" maxlength="150" placeholder="Nombre de la persona que recibe el informe">
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

    let solicitudAntecedentes=0;
    let seguimientoElegido=<?= $seguimientoSolicitado ?>;
    let cargandoAntecedentes=false;
    let errorAntecedentes=false;
    async function actualizarAntecedentes(){
        const solicitud=++solicitudAntecedentes;
        if(!estudiante.value){btnGuardar.disabled=false;cargandoAntecedentes=false;return;}
        cargandoAntecedentes=true;
        errorAntecedentes=false;
        btnGuardar.disabled=true;
        try{
            const consulta=new URLSearchParams({cod_estudiante:estudiante.value,fecha:document.getElementById('fecha').value,cod_seguimiento:seguimientoElegido,cod_historia:historia.value||0});
            const respuesta=await fetch('antecedentes.php?'+consulta);
            const datos=await respuesta.json();
            if(solicitud!==solicitudAntecedentes)return;
            if(!respuesta.ok)throw new Error(datos.error||'No se pudieron consultar los antecedentes.');
            historia.replaceChildren(new Option(datos.historias.length?'Seleccione una historia clínica':'Sin historia disponible en esta fecha',datos.historias.length?'':'0'));
            for(const h of datos.historias){
                historia.add(new Option('Historia #'+h.cod_historia+' · '+h.fecha_apertura+' · '+h.estado+' · '+(h.cod_derivacion?'Derivación #'+h.cod_derivacion:'Sin derivación'),String(h.cod_historia)));
            }
            historia.required=datos.historias.length>0;
            atenciones.value=datos.numero_atenciones;historia.value=datos.cod_historia?String(datos.cod_historia):(datos.historias.length?'':'0');
            document.getElementById('cod_seguimiento').value=datos.cod_seguimiento||'0';
            derivacion.value=datos.cod_derivacion||'';
            docente.value=datos.docente_referente||'';
            referido.value=datos.referido_por||'';
            motivo.value=datos.motivo||'';diagnostico.value=datos.diagnostico||'';recomendaciones.value=datos.recomendaciones||'';
            document.getElementById('seguimiento_origen').textContent=(datos.cod_seguimiento?'Seguimiento #'+datos.cod_seguimiento+'. ':'Sin seguimiento al corte. ')+datos.limitacion;
            historiaEstado.value=datos.requiere_historia?'Seleccione una de las historias del estudiante':(datos.cod_historia?'Historia disponible al corte #'+datos.cod_historia:'Sin historia al corte');
            btnGuardar.disabled=datos.requiere_historia;
        }catch(error){if(solicitud===solicitudAntecedentes){errorAntecedentes=true;document.getElementById('seguimiento_origen').textContent=error.message;}}
        finally{if(solicitud===solicitudAntecedentes)cargandoAntecedentes=false;}
    }
    estudiante.addEventListener('change',()=>{seguimientoElegido=0;historia.replaceChildren(new Option('Seleccione una historia clínica',''));cargarDatos();actualizarAntecedentes();});
    historia.addEventListener('change',()=>{seguimientoElegido=0;actualizarAntecedentes();});
    document.getElementById('fecha').addEventListener('change',()=>{historia.value='';actualizarAntecedentes();});
    cargarDatos();
    const datosRecuperados = <?= json_encode($datosInforme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    function restaurarDatos(){
    for (const campo of ['motivo','diagnostico','aspecto_cognitivo','aspectos_afectivos','diagnostico_acuerdos','recomendaciones','recibido_por','numero_atenciones','referido_por']) {
        if (Object.prototype.hasOwnProperty.call(datosRecuperados, campo)) {
            document.getElementById(campo).value = datosRecuperados[campo];
        }
    }
    }
    const solicitudInicial=solicitudAntecedentes+1;
    actualizarAntecedentes().then(()=>{if(solicitudInicial===solicitudAntecedentes)restaurarDatos();});

    form.addEventListener('submit',function(event){
        if(cargandoAntecedentes || errorAntecedentes || (historia.required && !historia.value)){
            event.preventDefault();
            document.getElementById('seguimiento_origen').textContent='Seleccione la historia y espere a que se carguen sus antecedentes.';
            return;
        }
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
