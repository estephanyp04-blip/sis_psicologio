<?php
require_once '../config/conexion.php';
if(session_status()===PHP_SESSION_NONE)session_start();
$modo=!isset($_SESSION['id_rol']);
$rol=(int)($_SESSION['id_rol']??0);
if(!$modo&&!in_array($rol,[1,2],true)){
    header('Location: listar.php');
    exit;
}
function escapar($v):string{
    return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
}
$idDerivacion=filter_input(INPUT_GET,'id_derivacion',FILTER_VALIDATE_INT);
$idEstudiante=filter_input(INPUT_GET,'id_estudiante',FILTER_VALIDATE_INT);
$historia=[];
$estudiantes=[];
$datosIniciales=[
    'fecha_apertura'=>date('Y-m-d'),
    'estado'=>'Activa'
];
/* SI VIENE DESDE UNA DERIVACIÓN */
if($idDerivacion){
    $stmt=$conexion->prepare("
        SELECT
            d.id_derivacion,
            d.id_estudiante,
            d.fecha AS fecha_derivacion,
            d.motivo,
            d.materia,
            d.prioridad,
            d.observaciones AS observaciones_derivacion,
            e.nombres,
            e.apellidos,
            e.curso,
            e.paralelo,
            e.fecha_nacimiento,
            e.lugar_nacimiento,
            e.telefono,
            e.nombre_tutor,
            doc.nombres AS docente_nombres,
            doc.apellidos AS docente_apellidos,
            h.id_historia
        FROM derivaciones d
        INNER JOIN estudiantes e ON e.id_estudiante=d.id_estudiante
        LEFT JOIN docentes doc ON doc.id_docente=d.id_docente
        LEFT JOIN historias_clinicas h ON h.id_estudiante=e.id_estudiante
        WHERE d.id_derivacion=?
        AND e.estado='Activo'
        LIMIT 1
    ");
    if(!$stmt){
        die('Error al preparar derivación: '.$conexion->error);
    }
    $stmt->bind_param('i',$idDerivacion);
    $stmt->execute();
    $derivacion=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$derivacion){
        $_SESSION['mensaje']='La derivación no existe o el estudiante no está activo.';
        $_SESSION['tipo_mensaje']='danger';
        header('Location: listar.php');
        exit;
    }
    if(!empty($derivacion['id_historia'])){
        $_SESSION['mensaje']='Este estudiante ya tiene una historia clínica.';
        $_SESSION['tipo_mensaje']='warning';
        header('Location: ver.php?id='.(int)$derivacion['id_historia']);
        exit;
    }
    $idEstudiante=(int)$derivacion['id_estudiante'];
    $datosIniciales=array_merge($datosIniciales,[
        'id_derivacion'=>(int)$derivacion['id_derivacion'],
        'id_estudiante'=>$idEstudiante,
        'lugar_nacimiento'=>$derivacion['lugar_nacimiento']??'',
        'celular_estudiante'=>$derivacion['telefono']??'',
        'padre_madre'=>$derivacion['nombre_tutor']??'',
        'derivado_por'=>trim(($derivacion['docente_nombres']??'').' '.($derivacion['docente_apellidos']??'')),
        'fecha_derivacion'=>$derivacion['fecha_derivacion']??'',
        'materia_derivacion'=>$derivacion['materia']??'',
        'prioridad_derivacion'=>$derivacion['prioridad']??'',
        'observaciones_derivacion'=>$derivacion['observaciones_derivacion']??'',
        'motivo_consulta'=>$derivacion['motivo']??''
    ]);
}
/* SI VIENE DIRECTAMENTE CON UN ESTUDIANTE */
elseif($idEstudiante){
    $stmt=$conexion->prepare("
        SELECT
            e.id_estudiante,
            e.fecha_nacimiento,
            e.lugar_nacimiento,
            e.telefono,
            e.nombre_tutor,
            d.id_derivacion,
            d.fecha AS fecha_derivacion,
            d.motivo,
            d.materia,
            d.prioridad,
            d.observaciones AS observaciones_derivacion,
            doc.nombres AS docente_nombres,
            doc.apellidos AS docente_apellidos,
            h.id_historia
        FROM estudiantes e
        LEFT JOIN historias_clinicas h ON h.id_estudiante=e.id_estudiante
        LEFT JOIN derivaciones d ON d.id_derivacion=(
            SELECT d2.id_derivacion
            FROM derivaciones d2
            WHERE d2.id_estudiante=e.id_estudiante
            ORDER BY d2.fecha DESC,d2.id_derivacion DESC
            LIMIT 1
        )
        LEFT JOIN docentes doc ON doc.id_docente=d.id_docente
        WHERE e.id_estudiante=?
        AND e.estado='Activo'
        LIMIT 1
    ");
    if(!$stmt){
        die('Error al preparar estudiante: '.$conexion->error);
    }
    $stmt->bind_param('i',$idEstudiante);
    $stmt->execute();
    $estudianteActual=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$estudianteActual){
        $_SESSION['mensaje']='El estudiante no existe o no está activo.';
        $_SESSION['tipo_mensaje']='danger';
        header('Location: listar.php');
        exit;
    }
    if(!empty($estudianteActual['id_historia'])){
        $_SESSION['mensaje']='Este estudiante ya tiene una historia clínica.';
        $_SESSION['tipo_mensaje']='warning';
        header('Location: ver.php?id='.(int)$estudianteActual['id_historia']);
        exit;
    }
    $datosIniciales=array_merge($datosIniciales,[
        'id_estudiante'=>(int)$estudianteActual['id_estudiante'],
        'id_derivacion'=>(int)($estudianteActual['id_derivacion']??0),
        'lugar_nacimiento'=>$estudianteActual['lugar_nacimiento']??'',
        'celular_estudiante'=>$estudianteActual['telefono']??'',
        'padre_madre'=>$estudianteActual['nombre_tutor']??'',
        'derivado_por'=>trim(($estudianteActual['docente_nombres']??'').' '.($estudianteActual['docente_apellidos']??'')),
        'fecha_derivacion'=>$estudianteActual['fecha_derivacion']??'',
        'materia_derivacion'=>$estudianteActual['materia']??'',
        'prioridad_derivacion'=>$estudianteActual['prioridad']??'',
        'observaciones_derivacion'=>$estudianteActual['observaciones_derivacion']??'',
        'motivo_consulta'=>$estudianteActual['motivo']??''
    ]);
}
/* ESTUDIANTES ACTIVOS QUE AÚN NO TIENEN HISTORIA */
$sql="
    SELECT
        e.id_estudiante,
        e.nombres,
        e.apellidos,
        e.curso,
        e.paralelo,
        e.fecha_nacimiento,
        e.lugar_nacimiento,
        e.nombre_tutor,
        e.telefono,
        d.id_derivacion,
        d.fecha AS fecha_derivacion,
        d.motivo AS motivo_derivacion,
        d.materia AS materia_derivacion,
        d.prioridad AS prioridad_derivacion,
        d.observaciones AS observaciones_derivacion,
        TRIM(CONCAT(COALESCE(doc.nombres,''),' ',COALESCE(doc.apellidos,''))) AS derivado_por
    FROM estudiantes e
    LEFT JOIN historias_clinicas h ON h.id_estudiante=e.id_estudiante
    LEFT JOIN derivaciones d ON d.id_derivacion=(
        SELECT d2.id_derivacion
        FROM derivaciones d2
        WHERE d2.id_estudiante=e.id_estudiante
        ORDER BY d2.fecha DESC,d2.id_derivacion DESC
        LIMIT 1
    )
    LEFT JOIN docentes doc ON doc.id_docente=d.id_docente
    WHERE e.estado='Activo'
    AND h.id_historia IS NULL
    ORDER BY e.curso,e.paralelo,e.apellidos,e.nombres
";
$rs=$conexion->query($sql);
if(!$rs){
    die('Error al consultar estudiantes: '.$conexion->error);
}
$estudiantes=$rs->fetch_all(MYSQLI_ASSOC);
$mensaje=$_SESSION['mensaje']??'';
$tipo=$_SESSION['tipo_mensaje']??'danger';
unset($_SESSION['mensaje'],$_SESSION['tipo_mensaje']);
$tituloPagina='Nueva historia clínica';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>
<main class="main-content historia-edicion">
<div class="container-fluid">
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="listar.php">Historias clínicas</a></li>
        <li class="breadcrumb-item active">Nueva</li>
    </ol>
</nav>
<header class="historia-form-head">
    <div>
        <span>NUEVO EXPEDIENTE</span>
        <h1>Crear historia clínica</h1>
        <p>Registre la información psicológica del estudiante.</p>
    </div>
    <i class="bi bi-file-earmark-medical"></i>
</header>
<?php if($mensaje):?>
<div class="alert alert-<?= escapar($tipo) ?>">
    <?= nl2br(escapar($mensaje)) ?>
</div>
<?php endif;?>
<?php include 'formulario.php';?>
</div>
</main>
<?php include '../includes/footer.php';?>