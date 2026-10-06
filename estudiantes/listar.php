<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/listar.php');

require_once '../config/conexion.php';

function e($v):string{
    return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
}

/* CONSULTAR ESTUDIANTES */
$sql="
    SELECT
        id_estudiante,
        codigo,
        ci,
        nombres,
        apellidos,
        curso,
        paralelo,
        turno,
        estado
    FROM vista_estudiantes
    ORDER BY
        CASE estado
            WHEN 'Activo' THEN 1
            WHEN 'Retirado' THEN 2
            ELSE 3
        END,
        curso,
        paralelo,
        apellidos,
        nombres
";

$resultado=$conexion->query($sql);
$estudiantes=$resultado?$resultado->fetch_all(MYSQLI_ASSOC):[];

$total=count($estudiantes);
$activos=0;
$retirados=0;
$cursos=[];

foreach($estudiantes as $fila){
    $estado=$fila['estado']??'';

    if($estado==='Activo')$activos++;
    if($estado==='Retirado')$retirados++;

    $curso=trim((string)($fila['curso']??''));

    if($curso!==''){
        $cursos[$curso]=true;
    }
}

uksort($cursos,'strnatcasecmp');

/* MENSAJES */
$mensaje=$_SESSION['mensaje']??'';
$tipoMensaje=$_SESSION['tipo_mensaje']??'info';

unset($_SESSION['mensaje'],$_SESSION['tipo_mensaje']);

$tiposPermitidos=['success','danger','warning','info'];

if(!in_array($tipoMensaje,$tiposPermitidos,true)){
    $tipoMensaje='info';
}

$tituloPagina='Estudiantes';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content">
<div class="container-fluid">

    <!-- BREADCRUMB -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item active">
                Estudiantes
            </li>
        </ol>
    </nav>

    <!-- ENCABEZADO -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                <div>
                    <span class="text-primary fw-semibold">
                        <i class="bi bi-people-fill me-1"></i>
                        Gestión estudiantil
                    </span>

                    <h1 class="h3 fw-bold mt-1 mb-1">
                        Estudiantes
                    </h1>

                    <p class="text-muted mb-0">
                        Administre la información de los estudiantes registrados en la unidad educativa.
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2">

                    <a href="registrar.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>
                        Registrar estudiante
                    </a>

                    <form
                        action="importar.php"
                        method="POST"
                        enctype="multipart/form-data"
                        id="formImportar"
                    >
                        <?= login_campo_csrf() ?>
                        <input
                            type="file"
                            name="archivo"
                            id="archivoImportacion"
                            accept=".csv,text/csv"
                            class="d-none"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            id="btnImportar"
                        >
                            <i class="bi bi-upload me-2"></i>
                            Importar datos
                        </button>
                    </form>

                    <small class="text-muted">CSV UTF-8 separado por punto y coma. Se importan las filas válidas y se informan las rechazadas. Estados: Activo o Retirado.</small>

                </div>

            </div>

        </div>
    </div>

    <!-- MENSAJE -->
    <?php if($mensaje!==''):?>

        <div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show" role="alert">

            <?php if($tipoMensaje==='success'):?>
                <i class="bi bi-check-circle-fill me-2"></i>
            <?php elseif($tipoMensaje==='danger'):?>
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php else:?>
                <i class="bi bi-info-circle-fill me-2"></i>
            <?php endif;?>

            <?= nl2br(e($mensaje)) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar">
            </button>

        </div>

    <?php endif;?>

    <!-- RESUMEN -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">

                    <i class="bi bi-people fs-2 text-primary"></i>

                    <div>
                        <div class="fs-4 fw-bold">
                            <?= $total ?>
                        </div>

                        <div class="text-muted">
                            Total registrados
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">

                    <i class="bi bi-person-check fs-2 text-success"></i>

                    <div>
                        <div class="fs-4 fw-bold">
                            <?= $activos ?>
                        </div>

                        <div class="text-muted">
                            Activos
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">

                    <i class="bi bi-person-dash fs-2 text-secondary"></i>

                    <div>
                        <div class="fs-4 fw-bold">
                            <?= $retirados ?>
                        </div>

                        <div class="text-muted">
                            Retirados
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <div class="row g-3">

                <div class="col-lg-6">

                    <label for="buscador" class="form-label">
                        Buscar estudiante
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="search"
                            id="buscador"
                            class="form-control"
                            placeholder="Nombre, apellido, CI o código..."
                            autocomplete="off"
                        >

                    </div>

                </div>

                <div class="col-lg-3">

                    <label for="filtroCurso" class="form-label">
                        Curso
                    </label>

                    <select id="filtroCurso" class="form-select">

                        <option value="">
                            Todos los cursos
                        </option>

                        <?php foreach(array_keys($cursos) as $curso):?>

                            <option value="<?= e($curso) ?>">
                                <?= e($curso) ?>°
                            </option>

                        <?php endforeach;?>

                    </select>

                </div>

                <div class="col-lg-3">

                    <label for="filtroEstado" class="form-label">
                        Estado
                    </label>

                    <select id="filtroEstado" class="form-select">

                        <option value="">
                            Todos los estados
                        </option>

                        <option value="Activo">
                            Activo
                        </option>

                        <option value="Retirado">
                            Retirado
                        </option>

                    </select>

                </div>

            </div>

        </div>
    </div>

    <!-- TABLA -->
    <div class="card shadow-sm">

        <div class="card-header bg-white d-flex justify-content-between align-items-center">

            <div>
                <h2 class="h5 mb-1">
                    Listado de estudiantes
                </h2>

                <small class="text-muted">
                    <span id="cantidadEstudiantes"><?= $total ?></span>
                    estudiantes encontrados
                </small>
            </div>

        </div>

        <div class="table-responsive">

            <table
                class="table table-hover align-middle mb-0"
                id="tablaEstudiantes"
            >

                <thead class="table-light">

                    <tr>
                        <th>Estudiante</th>
                        <th>Código</th>
                        <th>CI</th>
                        <th>Curso</th>
                        <th>Turno</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach($estudiantes as $fila):?>

                    <?php
                    $nombre=trim(
                        ($fila['nombres']??'').' '.
                        ($fila['apellidos']??'')
                    );

                    if($nombre===''){
                        $nombre='Sin nombre';
                    }

                    $curso=trim((string)($fila['curso']??''));
                    $paralelo=trim((string)($fila['paralelo']??''));

                    $cursoCompleto=$curso;

                    if($curso!==''){
                        $cursoCompleto.='°';
                    }

                    if($paralelo!==''){
                        $cursoCompleto.=' '.$paralelo;
                    }

                    if($cursoCompleto===''){
                        $cursoCompleto='—';
                    }

                    $codigo=!empty($fila['codigo'])
                        ?$fila['codigo']
                        :$fila['id_estudiante'];
                    ?>

                    <tr
                        data-registro="1"
                        data-curso="<?= e($curso) ?>"
                        data-estado="<?= e($fila['estado']??'') ?>"
                        data-busqueda="<?= e(
                            $nombre.' '.
                            ($fila['ci']??'').' '.
                            $codigo
                        ) ?>"
                    >

                        <td>
                            <strong><?= e($nombre) ?></strong>

                            <div class="small text-muted">
                                <?= e($cursoCompleto) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($codigo) ?>
                        </td>

                        <td>
                            <?= e($fila['ci']??'—') ?>
                        </td>

                        <td>
                            <?= e($cursoCompleto) ?>
                        </td>

                        <td>
                            <?= e($fila['turno']??'—') ?>
                        </td>

                        <td>

                            <?php if(($fila['estado']??'')==='Activo'):?>

                                <span class="badge bg-success">
                                    Activo
                                </span>

                            <?php else:?>

                                <span class="badge bg-secondary">
                                    <?= e($fila['estado']??'Retirado') ?>
                                </span>

                            <?php endif;?>

                        </td>

                        <td class="text-end">

                            <div class="d-inline-flex align-items-center gap-1">

                                <?php if(($fila['estado']??'')==='Activo'):?>

                                    <a
                                        href="editar.php?id=<?= (int)$fila['id_estudiante'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        title="Editar estudiante"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <form
                                        action="cambiar_estado.php"
                                        method="POST"
                                        class="d-inline formulario-retirar"
                                    >
                                        <?= login_campo_csrf() ?>

                                        <input
                                            type="hidden"
                                            name="id_estudiante"
                                            value="<?= (int)$fila['id_estudiante'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="retirar"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Retirar estudiante"
                                        >
                                            <i class="bi bi-person-dash"></i>
                                        </button>

                                    </form>

                                <?php else:?>

                                    <form
                                        action="cambiar_estado.php"
                                        method="POST"
                                        class="d-inline formulario-reactivar"
                                    >
                                        <?= login_campo_csrf() ?>

                                        <input
                                            type="hidden"
                                            name="id_estudiante"
                                            value="<?= (int)$fila['id_estudiante'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="reactivar"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-success"
                                            title="Reactivar estudiante"
                                        >
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>

                                    </form>

                                <?php endif;?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach;?>

                    <tr id="sinResultados" style="display:none">

                        <td colspan="7" class="text-center py-5">

                            <i class="bi bi-search fs-3 text-muted"></i>

                            <div class="fw-semibold mt-2">
                                No se encontraron estudiantes
                            </div>

                            <small class="text-muted">
                                Cambie la búsqueda o los filtros.
                            </small>

                        </td>

                    </tr>

                    <?php if(!$estudiantes):?>

                        <tr>

                            <td colspan="7" class="text-center py-5">

                                <i class="bi bi-people fs-3 text-muted"></i>

                                <div class="fw-semibold mt-2">
                                    No hay estudiantes registrados
                                </div>

                                <small class="text-muted">
                                    Registre el primer estudiante para comenzar.
                                </small>

                            </td>

                        </tr>

                    <?php endif;?>

                </tbody>

            </table>

        </div>

        <!-- PAGINACIÓN -->
        <div class="card-footer bg-white">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                <small
                    class="text-muted"
                    id="textoPaginacion"
                >
                    Mostrando 0 estudiantes
                </small>

                <div class="d-flex align-items-center gap-1">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        id="paginaAnterior"
                    >
                        <i class="bi bi-chevron-left"></i>
                        Anterior
                    </button>

                    <div
                        id="numerosPaginacion"
                        class="d-flex align-items-center gap-1"
                    ></div>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        id="paginaSiguiente"
                    >
                        Siguiente
                        <i class="bi bi-chevron-right"></i>
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>
</main>

<script>
document.addEventListener('DOMContentLoaded',()=>{

    const porPagina=15;

    const buscador=document.getElementById('buscador');
    const filtroCurso=document.getElementById('filtroCurso');
    const filtroEstado=document.getElementById('filtroEstado');

    const cantidad=document.getElementById('cantidadEstudiantes');
    const sinResultados=document.getElementById('sinResultados');

    const anterior=document.getElementById('paginaAnterior');
    const siguiente=document.getElementById('paginaSiguiente');

    const numeros=document.getElementById('numerosPaginacion');
    const texto=document.getElementById('textoPaginacion');

    const filas=[
        ...document.querySelectorAll(
            '#tablaEstudiantes tr[data-registro="1"]'
        )
    ];

    let filtradas=[...filas];
    let paginaActual=1;

    const normalizar=valor=>
        String(valor??'')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g,'')
            .trim();

    function filtrar(){

        const busqueda=normalizar(buscador.value);
        const curso=filtroCurso.value;
        const estado=filtroEstado.value;

        filtradas=filas.filter(fila=>{

            const textoFila=normalizar(
                fila.dataset.busqueda
            );

            return (
                (!busqueda||textoFila.includes(busqueda)) &&
                (!curso||fila.dataset.curso===curso) &&
                (!estado||fila.dataset.estado===estado)
            );
        });

        paginaActual=1;
        cantidad.textContent=filtradas.length;

        mostrar();
    }

    function mostrar(){

        filas.forEach(fila=>{
            fila.style.display='none';
        });

        const total=filtradas.length;
        const totalPaginas=Math.max(
            1,
            Math.ceil(total/porPagina)
        );

        if(paginaActual>totalPaginas){
            paginaActual=totalPaginas;
        }

        const inicio=(paginaActual-1)*porPagina;
        const fin=inicio+porPagina;

        filtradas
            .slice(inicio,fin)
            .forEach(fila=>{
                fila.style.display='';
            });

        sinResultados.style.display=
            filas.length>0&&total===0
                ?''
                :'none';

        anterior.disabled=
            total===0||paginaActual===1;

        siguiente.disabled=
            total===0||paginaActual===totalPaginas;

        if(total===0){
            texto.textContent='No hay estudiantes para mostrar';
        }else{
            texto.textContent=
                `Mostrando ${inicio+1} - ${Math.min(fin,total)} de ${total} estudiantes`;
        }

        crearPaginas(totalPaginas,total);
    }

    function crearPaginas(totalPaginas,total){

        numeros.innerHTML='';

        if(total===0)return;

        let inicio=Math.max(1,paginaActual-2);
        let fin=Math.min(totalPaginas,inicio+4);

        if(fin-inicio<4){
            inicio=Math.max(1,fin-4);
        }

        if(inicio>1){
            crearBoton(1);

            if(inicio>2){
                crearSeparador();
            }
        }

        for(let pagina=inicio;pagina<=fin;pagina++){
            crearBoton(pagina);
        }

        if(fin<totalPaginas){

            if(fin<totalPaginas-1){
                crearSeparador();
            }

            crearBoton(totalPaginas);
        }
    }

    function crearBoton(numero){

        const boton=document.createElement('button');

        boton.type='button';
        boton.textContent=numero;

        boton.className=
            numero===paginaActual
                ?'btn btn-sm btn-primary'
                :'btn btn-sm btn-outline-secondary';

        boton.addEventListener('click',()=>{
            paginaActual=numero;
            mostrar();
        });

        numeros.appendChild(boton);
    }

    function crearSeparador(){

        const span=document.createElement('span');

        span.className='px-1 text-muted';
        span.textContent='...';

        numeros.appendChild(span);
    }

    anterior.addEventListener('click',()=>{
        if(paginaActual>1){
            paginaActual--;
            mostrar();
        }
    });

    siguiente.addEventListener('click',()=>{

        const totalPaginas=Math.ceil(
            filtradas.length/porPagina
        );

        if(paginaActual<totalPaginas){
            paginaActual++;
            mostrar();
        }
    });

    buscador.addEventListener('input',filtrar);
    filtroCurso.addEventListener('change',filtrar);
    filtroEstado.addEventListener('change',filtrar);

    document.querySelectorAll('.formulario-retirar').forEach(form=>{

        form.addEventListener('submit',evento=>{

            if(!confirm(
                '¿Está segura de retirar este estudiante?\n\n'+
                'El estudiante dejará de estar activo, pero se conservarán sus datos, derivaciones, citas e historia clínica.'
            )){
                evento.preventDefault();
            }
        });
    });

    document.querySelectorAll('.formulario-reactivar').forEach(form=>{

        form.addEventListener('submit',evento=>{

            if(!confirm('¿Desea reactivar este estudiante?')){
                evento.preventDefault();
            }
        });
    });

    const btnImportar=document.getElementById('btnImportar');
    const archivo=document.getElementById('archivoImportacion');
    const formImportar=document.getElementById('formImportar');

    btnImportar.addEventListener('click',()=>{
        archivo.click();
    });

    archivo.addEventListener('change',()=>{
        if(archivo.files.length){
            formImportar.submit();
        }
    });

    filtrar();
});
</script>

<?php include '../includes/footer.php'; ?>
