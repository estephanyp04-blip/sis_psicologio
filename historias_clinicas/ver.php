<?php
require_once '../config/conexion.php';
if(session_status()===PHP_SESSION_NONE)session_start();

$modo=!isset($_SESSION['id_rol']);
$rol=(int)($_SESSION['id_rol']??0);

if(!$modo&&!in_array($rol,[1,2,4],true)){
    header('Location: ../index.php');
    exit;
}

$puedeEditar=$modo||in_array($rol,[1,2],true);

function escapar($v):string{
    return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
}

function mostrarDato($valor,string $vacio='No registrado'):string{
    $valor=trim((string)$valor);
    return $valor!==''?escapar($valor):'<span class="sin-dato">'.$vacio.'</span>';
}

$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);

if(!$id){
    header('Location: listar.php');
    exit;
}

/* HISTORIA + ESTUDIANTE + PROFESIONAL */
$stmt=$conexion->prepare("
    SELECT
        h.*,
        e.codigo,
        e.ci,
        e.nombres,
        e.apellidos,
        e.fecha_nacimiento,
        e.genero,
        e.curso,
        e.paralelo,
        e.turno,
        e.tutor,
        e.telefono,
        u.nombre AS profesional_nombre,
        u.apellido AS profesional_apellido
    FROM historias_clinicas h
    INNER JOIN estudiantes e ON e.id_estudiante=h.id_estudiante
    LEFT JOIN usuarios u ON u.id_usuario=h.id_usuario
    WHERE h.id_historia=?
    LIMIT 1
");

$stmt->bind_param('i',$id);
$stmt->execute();
$h=$stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$h){
    header('Location: listar.php');
    exit;
}

/* OPCIONES MARCADAS */
$opciones=[
    'conductas_riesgo'=>[],
    'atencion_distraccion'=>[],
    'actividad_motora'=>[],
    'adaptacion_normas'=>[],
    'dificultades_socioemocionales'=>[],
    'estrategias_previas'=>[]
];

$stmt=$conexion->prepare("
    SELECT grupo,valor
    FROM historia_opciones
    WHERE id_historia=?
    ORDER BY id_opcion
");

$stmt->bind_param('i',$id);
$stmt->execute();
$rsOpciones=$stmt->get_result();

while($o=$rsOpciones->fetch_assoc()){
    if(isset($opciones[$o['grupo']])){
        $opciones[$o['grupo']][]=$o['valor'];
    }
}

$stmt->close();

/* GRUPO FAMILIAR */
$stmt=$conexion->prepare("
    SELECT nombre,edad,relacion,profesion,ocupacion,observaciones
    FROM historia_familiares
    WHERE id_historia=?
    ORDER BY id_familiar
");

$stmt->bind_param('i',$id);
$stmt->execute();
$familiares=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* SEGUIMIENTOS / EVOLUCIÓN */
$stmt=$conexion->prepare("
    SELECT fecha,evolucion,recomendaciones,proxima_cita
    FROM seguimientos
    WHERE id_historia=?
    ORDER BY fecha ASC,id_seguimiento ASC
");

$stmt->bind_param('i',$id);
$stmt->execute();
$seguimientos=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* EDAD */
$edad='No registrada';

if(!empty($h['fecha_nacimiento'])){
    try{
        $nacimiento=new DateTime($h['fecha_nacimiento']);
        $edad=$nacimiento->diff(new DateTime())->y.' años';
    }catch(Throwable $e){}
}

$profesional=trim(($h['profesional_nombre']??'').' '.($h['profesional_apellido']??''));

$mensaje=$_SESSION['mensaje']??'';
$tipo=$_SESSION['tipo_mensaje']??'info';

unset($_SESSION['mensaje'],$_SESSION['tipo_mensaje']);

$tituloPagina='Historia clínica';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content historia-detalle">
<div class="container-fluid">

<nav aria-label="breadcrumb" class="d-print-none">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="listar.php">Historias clínicas</a></li>
        <li class="breadcrumb-item active">Expediente #<?= (int)$id ?></li>
    </ol>
</nav>

<?php if($mensaje):?>
<div class="alert alert-<?= escapar($tipo) ?> d-print-none">
    <?= nl2br(escapar($mensaje)) ?>
</div>
<?php endif;?>

<header class="expediente-head">
    <div class="expediente-identidad">
        <span class="expediente-avatar">
            <?= escapar(mb_strtoupper(mb_substr($h['nombres'],0,1))) ?>
        </span>

        <div>
            <small>HISTORIA CLÍNICA PSICOLÓGICA #<?= (int)$id ?></small>
            <h1><?= escapar($h['nombres'].' '.$h['apellidos']) ?></h1>
            <p>
                <?= escapar(($h['curso']??'').'° '.($h['paralelo']??'')) ?>
                <?= !empty($h['turno'])?' · '.escapar($h['turno']):'' ?>
            </p>
        </div>
    </div>

    <div class="expediente-acciones d-print-none">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-2"></i>Imprimir
        </button>

        <?php if($puedeEditar):?>
        <a href="editar.php?id=<?= (int)$id ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-2"></i>Editar
        </a>
        <?php endif;?>
    </div>
</header>

<section class="expediente-meta">
    <div>
        <span>Código</span>
        <strong><?= mostrarDato($h['codigo']??'') ?></strong>
    </div>

    <div>
        <span>Fecha de apertura</span>
        <strong>
            <?= !empty($h['fecha_apertura'])?date('d/m/Y',strtotime($h['fecha_apertura'])):'No registrada' ?>
        </strong>
    </div>

    <div>
        <span>Estado</span>
        <strong><?= escapar($h['estado']) ?></strong>
    </div>

    <div>
        <span>Profesional</span>
        <strong><?= $profesional!==''?escapar($profesional):'Sin asignar' ?></strong>
    </div>
</section>

<!-- 1. DATOS GENERALES -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>1</span>
        <div>
            <h2>Datos generales</h2>
            <p>Información del estudiante</p>
        </div>
    </div>

    <div class="datos-clinicos-grid">
        <div><span>Nombre completo</span><strong><?= escapar($h['nombres'].' '.$h['apellidos']) ?></strong></div>
        <div><span>Edad</span><strong><?= escapar($edad) ?></strong></div>
        <div><span>Curso</span><strong><?= escapar(($h['curso']??'').'° '.($h['paralelo']??'')) ?></strong></div>

        <div>
            <span>Fecha de nacimiento</span>
            <strong><?= !empty($h['fecha_nacimiento'])?date('d/m/Y',strtotime($h['fecha_nacimiento'])):'No registrada' ?></strong>
        </div>

        <div><span>Lugar de nacimiento</span><strong><?= mostrarDato($h['lugar_nacimiento']??'') ?></strong></div>
        <div><span>Celular</span><strong><?= mostrarDato($h['celular_estudiante']??($h['telefono']??'')) ?></strong></div>
        <div><span>Padre / Madre</span><strong><?= mostrarDato($h['padre_madre']??'') ?></strong></div>
        <div><span>Derivado por</span><strong><?= mostrarDato($h['derivado_por']??'') ?></strong></div>

        <div>
            <span>Fecha de derivación</span>
            <strong><?= !empty($h['fecha_derivacion'])?date('d/m/Y',strtotime($h['fecha_derivacion'])):'No registrada' ?></strong>
        </div>

        <div><span>Tutor de curso</span><strong><?= mostrarDato($h['tutor_curso']??($h['tutor']??'')) ?></strong></div>
        <div><span>Talla</span><strong><?= mostrarDato($h['talla']??'') ?></strong></div>
        <div><span>Peso</span><strong><?= mostrarDato($h['peso']??'') ?></strong></div>
        <div><span>Valoración</span><strong><?= mostrarDato($h['valoracion']??'') ?></strong></div>
    </div>

    <div class="texto-clinico mt-3">
        <strong>Enfermedades actuales</strong>
        <p><?= !empty($h['enfermedades_actuales'])?nl2br(escapar($h['enfermedades_actuales'])):'Sin información registrada.' ?></p>
    </div>
</section>

<!-- 2. MOTIVO -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>2</span>
        <div>
            <h2>Motivo de derivación</h2>
        </div>
    </div>

    <div class="texto-clinico">
        <?= !empty($h['motivo_consulta'])
            ?nl2br(escapar($h['motivo_consulta']))
            :'<span class="sin-dato">Sin información registrada.</span>' ?>
    </div>
</section>

<!-- 3. SITUACIÓN ESCOLAR -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>3</span>
        <div><h2>Situación escolar</h2></div>
    </div>

    <div class="datos-clinicos-grid">
        <div><span>Valoración</span><strong><?= mostrarDato($h['situacion_escolar']??'') ?></strong></div>
        <div><span>Curso(s) repetido(s)</span><strong><?= mostrarDato($h['cursos_repetidos']??'') ?></strong></div>
        <div><span>Dificultad escolar</span><strong><?= mostrarDato($h['dificultad_escolar']??'') ?></strong></div>
        <div><span>Materia que más le agrada</span><strong><?= mostrarDato($h['materia_agrada']??'') ?></strong></div>
        <div><span>Materia que menos le agrada</span><strong><?= mostrarDato($h['materia_desagrada']??'') ?></strong></div>
    </div>

    <div class="texto-clinico mt-3">
        <strong>Relación con compañeros(as) y profesores(as)</strong>
        <p><?= !empty($h['relacion_escolar'])?nl2br(escapar($h['relacion_escolar'])):'Sin información registrada.' ?></p>
    </div>
</section>

<!-- 4. CONDUCTAS DE RIESGO -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>4</span>
        <div><h2>Conductas de riesgo</h2></div>
    </div>

    <div class="opciones-registradas">
        <?php if($opciones['conductas_riesgo']):?>
            <?php foreach($opciones['conductas_riesgo'] as $valor):?>
                <span><i class="bi bi-check2"></i><?= escapar($valor) ?></span>
            <?php endforeach;?>
        <?php else:?>
            <span class="sin-dato">Sin conductas de riesgo registradas.</span>
        <?php endif;?>
    </div>
</section>

<!-- 5. CONDUCTAS PROBLEMA -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>5</span>
        <div><h2>Conducta(s) problema(s)</h2></div>
    </div>

    <?php
    $grupos=[
        'atencion_distraccion'=>'A. Atención / distracción',
        'actividad_motora'=>'B. Actividad motora en exceso',
        'adaptacion_normas'=>'C. Adaptación a normas',
        'dificultades_socioemocionales'=>'Dificultades socioemocionales',
        'estrategias_previas'=>'Estrategias de intervención antes de la derivación'
    ];
    ?>

    <?php foreach($grupos as $grupo=>$titulo):?>
    <div class="subseccion-clinica ver">
        <h3><?= escapar($titulo) ?></h3>

        <div class="opciones-registradas">
            <?php if($opciones[$grupo]):?>
                <?php foreach($opciones[$grupo] as $valor):?>
                    <span><i class="bi bi-check2"></i><?= escapar($valor) ?></span>
                <?php endforeach;?>
            <?php else:?>
                <span class="sin-dato">Sin opciones marcadas.</span>
            <?php endif;?>
        </div>
    </div>
    <?php endforeach;?>
</section>

<!-- 6. CONTEXTO FAMILIAR -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>6</span>
        <div><h2>Contexto familiar</h2></div>
    </div>

    <div class="texto-clinico">
        <strong>Antecedentes familiares</strong>
        <p><?= !empty($h['antecedentes'])?nl2br(escapar($h['antecedentes'])):'Sin información registrada.' ?></p>
    </div>

    <div class="table-responsive mt-4">
        <table class="table familiares-tabla">
            <thead>
                <tr>
                    <th>Nombre y apellido</th>
                    <th>Edad</th>
                    <th>Relación</th>
                    <th>Estudio / Profesión</th>
                    <th>Ocupación</th>
                    <th>Observaciones</th>
                </tr>
            </thead>

            <tbody>
            <?php if($familiares):?>
                <?php foreach($familiares as $f):?>
                <tr>
                    <td><?= escapar($f['nombre']) ?></td>
                    <td><?= $f['edad']!==null?(int)$f['edad']:'—' ?></td>
                    <td><?= escapar($f['relacion']?:'—') ?></td>
                    <td><?= escapar($f['profesion']?:'—') ?></td>
                    <td><?= escapar($f['ocupacion']?:'—') ?></td>
                    <td><?= escapar($f['observaciones']?:'—') ?></td>
                </tr>
                <?php endforeach;?>
            <?php else:?>
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        No se registraron integrantes familiares.
                    </td>
                </tr>
            <?php endif;?>
            </tbody>
        </table>
    </div>

    <div class="datos-clinicos-grid mt-3">
        <div>
            <span>¿Cómo califica a su familia?</span>
            <strong><?= mostrarDato($h['valoracion_familiar']??'') ?></strong>
        </div>
    </div>

    <div class="texto-clinico mt-3">
        <strong>Descripción del contexto familiar</strong>
        <p><?= !empty($h['contexto_familiar'])?nl2br(escapar($h['contexto_familiar'])):'Sin información registrada.' ?></p>
    </div>
</section>

<!-- 7. DIAGNÓSTICO -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>7</span>
        <div>
            <h2>Resultados del diagnóstico psicológico</h2>
            <p>Intelectual, emocional, organicidad y personalidad</p>
        </div>
    </div>

    <div class="texto-clinico">
        <?= !empty($h['impresion_diagnostica'])
            ?nl2br(escapar($h['impresion_diagnostica']))
            :'<span class="sin-dato">Sin información registrada.</span>' ?>
    </div>
</section>

<!-- 8. ACUERDOS -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>8</span>
        <div><h2>Acuerdos con estudiante y/o Padre-Madre</h2></div>
    </div>

    <div class="texto-clinico">
        <?= !empty($h['plan_intervencion'])
            ?nl2br(escapar($h['plan_intervencion']))
            :'<span class="sin-dato">Sin información registrada.</span>' ?>
    </div>
</section>

<!-- 9. EVOLUCIÓN -->
<section class="expediente-seccion">
    <div class="expediente-seccion-titulo">
        <span>9</span>
        <div>
            <h2>Evolución del caso</h2>
            <p>Registro cronológico del seguimiento</p>
        </div>
    </div>

    <?php if(!empty($h['observaciones'])):?>
    <div class="evolucion-item">
        <div class="evolucion-fecha">
            <?= !empty($h['fecha_apertura'])?date('d/m/Y',strtotime($h['fecha_apertura'])):'Inicio' ?>
        </div>
        <div>
            <strong>Registro inicial</strong>
            <p><?= nl2br(escapar($h['observaciones'])) ?></p>
        </div>
    </div>
    <?php endif;?>

    <?php foreach($seguimientos as $s):?>
    <div class="evolucion-item">
        <div class="evolucion-fecha">
            <?= !empty($s['fecha'])?date('d/m/Y',strtotime($s['fecha'])):'Sin fecha' ?>
        </div>

        <div>
            <strong>Evolución</strong>
            <p><?= nl2br(escapar($s['evolucion']?:'Sin detalle registrado.')) ?></p>

            <?php if(!empty($s['recomendaciones'])):?>
                <small><b>Recomendaciones:</b> <?= escapar($s['recomendaciones']) ?></small>
            <?php endif;?>

            <?php if(!empty($s['proxima_cita'])):?>
                <small>
                    <b>Próxima cita:</b>
                    <?= date('d/m/Y',strtotime($s['proxima_cita'])) ?>
                </small>
            <?php endif;?>
        </div>
    </div>
    <?php endforeach;?>

    <?php if(empty($h['observaciones'])&&!$seguimientos):?>
        <div class="sin-dato">Todavía no existen registros de evolución.</div>
    <?php endif;?>
</section>

<div class="confidencial-box mt-4">
    <i class="bi bi-lock-fill"></i>
    <div>
        <strong>Documento confidencial</strong>
        <p>Uso exclusivo del personal autorizado del área de psicología.</p>
    </div>
</div>

<div class="d-flex justify-content-between mt-4 d-print-none">
    <a href="listar.php" class="btn btn-light border">
        <i class="bi bi-arrow-left me-2"></i>Volver
    </a>

    <?php if($puedeEditar):?>
    <a href="editar.php?id=<?= (int)$id ?>" class="btn btn-primary">
        <i class="bi bi-pencil me-2"></i>Editar historia
    </a>
    <?php endif;?>
</div>

</div>
</main>

<?php include '../includes/footer.php'; ?>