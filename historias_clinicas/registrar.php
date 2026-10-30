<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/registrar.php');

require_once '../config/conexion.php';
$idDerivacion=filter_input(INPUT_GET,'cod_derivacion',FILTER_VALIDATE_INT);
$idEstudiante=filter_input(INPUT_GET,'cod_estudiante',FILTER_VALIDATE_INT);
$idCita=filter_input(INPUT_GET,'cod_cita',FILTER_VALIDATE_INT);
$citaOrigen=null;
if ($idCita) {
    require_once __DIR__ . '/../includes/trazabilidad_datos.php';
    $citaOrigen=flujo_fila($conexion,'SELECT * FROM citas WHERE cod_cita=?',[$idCita]);
    if (!$citaOrigen || $citaOrigen['estado']==='Cancelada') { http_response_code(404); exit('Cita no disponible.'); }
    $idEstudiante=(int)$citaOrigen['cod_estudiante'];
    $idDerivacion=(int)($citaOrigen['cod_derivacion'] ?? 0);
}
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
            d.cod_derivacion,
            d.cod_estudiante,
            d.fecha AS fecha_derivacion,
            d.motivo,
            m.nombre AS materia,
            d.prioridad,
            d.observaciones AS observaciones_derivacion,
            e.nombres,
            e.apellidos,
            e.curso,
            e.paralelo,
            e.fecha_nacimiento,
            e.lugar_nacimiento,
            e.telefono,
            (SELECT r.nombres FROM estudiante_responsables er
                INNER JOIN responsables r ON r.cod_responsable=er.cod_responsable
                WHERE er.cod_estudiante=e.cod_estudiante AND er.parentesco='Tutor'
                ORDER BY er.es_principal DESC LIMIT 1) AS nombre_tutor,
            du.nombres AS docente_nombres,
            du.apellidos AS docente_apellidos,
            h.cod_historia
        FROM derivaciones d
        INNER JOIN vista_estudiantes e ON e.cod_estudiante=d.cod_estudiante
        LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
        LEFT JOIN usuarios du ON du.cod_usuario=doc.cod_usuario
        LEFT JOIN materias m ON m.cod_materia=d.cod_materia
        LEFT JOIN historias_clinicas h ON h.cod_estudiante=e.cod_estudiante
        WHERE d.cod_derivacion=?
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
    if(!empty($derivacion['cod_historia'])){
        $_SESSION['mensaje']='Este estudiante ya tiene una historia clínica.';
        $_SESSION['tipo_mensaje']='warning';
        header('Location: ver.php?id='.(int)$derivacion['cod_historia']);
        exit;
    }
    $idEstudiante=(int)$derivacion['cod_estudiante'];
    $datosIniciales=array_merge($datosIniciales,[
        'cod_derivacion'=>(int)$derivacion['cod_derivacion'],
        'cod_estudiante'=>$idEstudiante,
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
            e.cod_estudiante,
            e.fecha_nacimiento,
            e.lugar_nacimiento,
            e.telefono,
            (SELECT r.nombres FROM estudiante_responsables er
                INNER JOIN responsables r ON r.cod_responsable=er.cod_responsable
                WHERE er.cod_estudiante=e.cod_estudiante AND er.parentesco='Tutor'
                ORDER BY er.es_principal DESC LIMIT 1) AS nombre_tutor,
            d.cod_derivacion,
            d.fecha AS fecha_derivacion,
            d.motivo,
            m.nombre AS materia,
            d.prioridad,
            d.observaciones AS observaciones_derivacion,
            du.nombres AS docente_nombres,
            du.apellidos AS docente_apellidos,
            h.cod_historia
        FROM vista_estudiantes e
        LEFT JOIN historias_clinicas h ON h.cod_estudiante=e.cod_estudiante
        LEFT JOIN derivaciones d ON d.cod_derivacion=(
            SELECT d2.cod_derivacion
            FROM derivaciones d2
            WHERE d2.cod_estudiante=e.cod_estudiante
            ORDER BY d2.fecha DESC,d2.cod_derivacion DESC
            LIMIT 1
        )
        LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
        LEFT JOIN usuarios du ON du.cod_usuario=doc.cod_usuario
        LEFT JOIN materias m ON m.cod_materia=d.cod_materia
        WHERE e.cod_estudiante=?
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
    if(!empty($estudianteActual['cod_historia'])){
        $_SESSION['mensaje']='Este estudiante ya tiene una historia clínica.';
        $_SESSION['tipo_mensaje']='warning';
        header('Location: ver.php?id='.(int)$estudianteActual['cod_historia']);
        exit;
    }
    $datosIniciales=array_merge($datosIniciales,[
        'cod_estudiante'=>(int)$estudianteActual['cod_estudiante'],
        'cod_derivacion'=>(int)($estudianteActual['cod_derivacion']??0),
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
if ($citaOrigen) {
    $datosIniciales['cod_cita']=(int)$citaOrigen['cod_cita'];
    $datosIniciales['cod_derivacion']=(int)($citaOrigen['cod_derivacion'] ?? 0);
    if (!$datosIniciales['cod_derivacion']) {
        foreach (['derivado_por','fecha_derivacion','materia_derivacion','prioridad_derivacion','observaciones_derivacion','motivo_consulta'] as $campo) $datosIniciales[$campo]='';
    }
}
$sql="
    SELECT
        e.cod_estudiante,
        e.nombres,
        e.apellidos,
        e.curso,
        e.paralelo,
        e.fecha_nacimiento,
        e.lugar_nacimiento,
        (SELECT r.nombres FROM estudiante_responsables er
            INNER JOIN responsables r ON r.cod_responsable=er.cod_responsable
            WHERE er.cod_estudiante=e.cod_estudiante AND er.parentesco='Tutor'
            ORDER BY er.es_principal DESC LIMIT 1) AS nombre_tutor,
        e.telefono,
        d.cod_derivacion,
        d.fecha AS fecha_derivacion,
        d.motivo AS motivo_derivacion,
        m.nombre AS materia_derivacion,
        d.prioridad AS prioridad_derivacion,
        d.observaciones AS observaciones_derivacion,
        TRIM(CONCAT(COALESCE(du.nombres,''),' ',COALESCE(du.apellidos,''))) AS derivado_por
    FROM vista_estudiantes e
    LEFT JOIN historias_clinicas h ON h.cod_estudiante=e.cod_estudiante
    LEFT JOIN derivaciones d ON d.cod_derivacion=(
        SELECT d2.cod_derivacion
        FROM derivaciones d2
        WHERE d2.cod_estudiante=e.cod_estudiante
        ORDER BY d2.fecha DESC,d2.cod_derivacion DESC
        LIMIT 1
    )
    LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
    LEFT JOIN usuarios du ON du.cod_usuario=doc.cod_usuario
    LEFT JOIN materias m ON m.cod_materia=d.cod_materia
    WHERE e.estado='Activo'
    AND h.cod_historia IS NULL
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
        <li class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></li>
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
<div class="alert alert-<?= login_html($tipo) ?>">
    <?= nl2br(login_html($mensaje)) ?>
</div>
<?php endif;?>
<?php include 'formulario.php';?>
</div>
</main>
<?php include '../includes/footer.php';?>
