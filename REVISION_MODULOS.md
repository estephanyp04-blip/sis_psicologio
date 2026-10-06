# Revisión de módulos, conexiones y formularios

Fecha: 2 de octubre de 2026.

## Actualización del 5 de octubre de 2026: punto 5 completado

**H14, H15 y H20 están corregidos y verificados.** Se completó la trazabilidad derivación → cita → historia → seguimiento → informe, conservando autores y vínculos de origen y validando la pertenencia al estudiante. Los detalles permiten navegar entre registros según el rol. Se agregó el registro de seguimientos y las transiciones explícitas de inicio, cierre y reapertura de derivaciones; emitir un informe no cierra automáticamente la atención.

La agenda conserva un turno vigente por fecha/hora, con restricción única en la base, liberación al cancelar y protección de atenciones registradas. Se aplicó `002_trazabilidad` a la base local después de respaldarla fuera del directorio público: las huellas de los valores originales de **18 tablas** coinciden. No se inventaron relaciones históricas y no quedan migraciones pendientes.

Validación: **119 comprobaciones del flujo y 11 dentro de Chrome**, **837 de seguridad sobre 52 rutas**, **190 clínicas y 14 dentro de Chrome**, y **87 de esquema/informes/importación**, correctas. Las escrituras de prueba se limitaron a bases aisladas o tablas temporales. Chrome recorrió los formularios reales desde la cita hasta el informe.

Reglas y matriz: [TRAZABILIDAD.md](TRAZABILIDAD.md). Comandos: [tests/README.md](tests/README.md). El punto 4 y las verificaciones restantes del punto 6 continúan pendientes.

## Actualización del 5 de octubre de 2026: punto 3 completado

**H05, H06, H09, H10, H16 y H17 están corregidos y verificados.**

- El endpoint de derivaciones es una consulta GET autenticada con respuestas JSON, incluidos los errores de sesión, permisos, método e identificador. Devuelve solo las derivaciones del estudiante solicitado, ordenadas por fecha e ID.
- La edición de derivaciones envía categorías y observaciones como campos propios. El servidor reconstruye el texto, conserva los metadatos de solicitud/profesional y mantiene las observaciones originales ante envíos incompletos. No depende de JavaScript ni del formulario de cierre de sesión.
- Alta y edición de historias comparten validación y guardado transaccional de los campos clínicos, opciones y familiares. La evolución admite 1–5 o NULL; los campos omitidos en una edición se conservan y los grupos vaciados explícitamente se eliminan. Se rechazan formularios truncados, vínculos ajenos y cambios del estudiante o de la derivación original.
- La edición conserva `id_usuario` como autor original. La tabla `auditoria` registra por separado quién crea/modifica, el expediente y la fecha, dentro de la misma transacción. Un fallo revierte tanto la historia como sus datos asociados.
- El formulario recupera las referencias de la derivación y respeta los datos clínicos frente a los datos actuales de la ficha estudiantil. Conserva opciones históricas y recupera los datos escritos tras errores. El detalle muestra evaluación inicial, impresión diagnóstica, observaciones y evolución.
- Se verificó el flujo en Chrome: alta sin derivación, edición, preselección por URL, cambio de estudiante, respuestas JSON atrasadas y bloqueo del envío cuando falla la consulta. Se corrigieron el rechazo de la derivación vacía, la reaparición de familiares retirados tras un error y el arrastre del motivo precargado al cambiar de estudiante.

Validación: **190 comprobaciones en la suite clínica con navegador, más 14 comprobaciones dentro de Chrome**, **773 comprobaciones de seguridad** y **87 de esquema/informes/importación** correctas. Los **75 archivos PHP** pasan la revisión de sintaxis. La base local tiene **0 operaciones de migración pendientes**, comprobado en modo de solo lectura. Las escrituras de pruebas se realizaron en bases aisladas o tablas temporales, sin modificar registros reales. Comandos y alcance en [tests/README.md](tests/README.md).

Al completar el punto 3 quedaron pendientes los puntos 4 y 5 y las verificaciones restantes del punto 6. El punto 5 se completó en la actualización indicada arriba.

## Actualización: esquema, informes e importación alineados

Se completó el **punto 2**. **H03, H04 y H11 están corregidos** para la base activa y las instalaciones nuevas:

- La conexión normal comparte la configuración del login y ya no ejecuta instalación ni migraciones. `database/psicologia_db.sql` reproduce el esquema completo con catálogos y relaciones. La actualización es explícita, versionada, comprobable sin escribir y repetible desde CLI.
- Informes guarda título, autor autenticado y tipo individual; usa `Borrador`/`Finalizado` en formulario, servidor, detalle, listado y estadísticas. Se agregó un campo independiente para recomendaciones de seguimientos. La numeración es única y se reserva en una transacción, incluso con solicitudes concurrentes.
- La importación resuelve y valida los IDs reales de curso y paralelo. Comparte reglas con el alta manual, captura errores SQL por fila y presenta un resumen de importación parcial. Se rechazan cursos/paralelos inexistentes, inactivos o ambiguos y valores demasiado largos.
- También se corrigieron **H08**, **H13** y **H18**: estados de matrícula coherentes, confirmación de informes sin historia y recuperación de datos tras errores en alta de estudiantes y alta/edición de informes. En **H07** quedó corregido el alta; la edición y los selectores basados en catálogos siguen en el punto 4. Para **H10** se agregó la columna de evolución; su lectura y guardado se completaron posteriormente en el punto 3.

Se aplicó `001_alinear_esquema` a la base local tras generar un respaldo fuera de la carpeta pública. Se compararon huellas de los valores originales de **17 tablas**, sin cambios. No se crearon ni modificaron registros reales mediante pruebas de formularios. Las pruebas de escritura se ejecutaron únicamente en bases aisladas después de alinear el esquema.

Validación: **87 comprobaciones de integración** en modo SQL estricto, incluyendo instalación vacía, actualización conservando datos, repetición de la migración, importación parcial, receptores HTTP, errores recuperables y cuatro informes concurrentes; **769 comprobaciones de seguridad** correctas. La configuración horaria se unificó en `America/La_Paz`, también en las pruebas, para evitar validaciones de fechas distintas a las locales. Instrucciones, alcance de actualización y política CSV en [database/README.md](database/README.md).

## Actualización: autenticación, autorización y CSRF integrados

Se completó el punto 1 del orden de corrección. **H01 y H02 están corregidos**: 48 rutas validan sesión, rol, método HTTP y CSRF para POST antes de conectar a la base de los módulos. Se retiraron los accesos por “modo desarrollo”, se protegieron los archivos internos y se limitó cada docente a sus derivaciones, también en detalle y edición.

Los permisos se declaran en `config/permisos.php`. Usuarios/docentes son de administración; estudiantes, citas e historias de administrador/psicóloga; director consulta informes. Los enlaces del menú y de Inicio respetan el rol. Cancelar una cita ahora requiere POST y token. El buscador de estudiantes para derivar usa un formulario GET separado, sin enviar el token por URL.

También se corrigió el selector de formulario de **H06**, que confundía la edición de derivaciones con el cierre de sesión. Las contradicciones de permisos indicadas en H20 quedaron alineadas con la política; las transiciones y el registro de seguimientos siguen pendientes.

Validación: 766 comprobaciones HTTP en una copia aislada con tablas MySQL temporales y 61 comprobaciones adicionales en Apache local, todas correctas. Las pruebas no modificaron los registros reales. Detalles y comando reproducible en [tests/README.md](tests/README.md).

El resto del documento conserva el diagnóstico original y sus referencias de línea, que pueden haberse desplazado con la implementación. Los demás hallazgos siguen pendientes salvo las correcciones expresamente señaladas en las actualizaciones anteriores.

## Diagnóstico original

Se revisaron login, panel, usuarios, docentes, estudiantes, derivaciones, citas, historias clínicas, informes, componentes compartidos y configuración de base de datos. Hay fallas de acceso, pérdida de información y operaciones incompatibles con la base activa. Que los archivos tengan sintaxis válida no significa que sus flujos funcionen correctamente.

## Comprobaciones realizadas

- Los **61 archivos PHP** pasan `php -l` con PHP 8.2.12.
- La conexión configurada con `psicologia_db` funciona; el servidor responde como MariaDB 10.4.32.
- Se consultaron columnas, índices, claves externas y roles de la base activa. Se contrastaron con `database/psicologia_db.sql` y `config/conexion.php`.
- Se prepararon **118 sentencias SQL estáticas**, sin ejecutarlas. Una falla por columna inexistente. Preparar una sentencia no verifica todos los errores que pueden aparecer al guardar; por eso también se contrastaron los campos obligatorios de los INSERT con el esquema.
- Se comprobaron **130 referencias locales estáticas** de enlaces, formularios y recursos; las rutas dinámicas relevantes se revisaron adicionalmente.
- Ocho solicitudes HTTP de consulta confirmaron que login y los recursos existentes en `asset/` responden 200, y que las cinco rutas señaladas abajo responden 404.
- No se ejecutaron altas, modificaciones, eliminaciones, importaciones ni migraciones. No se probaron credenciales ni se realizaron pruebas completas de navegación con sesión. Las consecuencias de escritura descritas se deducen del código y del esquema, salvo donde se indica una comprobación específica.
- No se cargaron los módulos para hacer pruebas HTTP de lectura porque `config/conexion.php` ejecuta cambios de esquema al incluirse. La revisión de SQL usó la conexión de login, que no ejecuta esas migraciones.

## Hallazgos prioritarios

### H01 — Crítico: el login no protege los módulos

**Ubicación:** `includes/autenticacion.php:143`, `usuarios/guardar.php:2`, `usuarios/actualizar.php:2`, `usuarios/cambiar_estado.php:2`, `historias_clinicas/listar.php:10`, `historias_clinicas/guardar.php:28`, `derivaciones/actualizar.php:29`, `index.php:3`.

`requerir_roles()` está implementada, pero ningún punto de entrada de los módulos la llama. Incluir `navbar.php` carga las funciones, pero no verifica la sesión. Usuarios, docentes, estudiantes y citas tienen acciones sin comprobación de autorización; otros módulos habilitan un “modo desarrollo” precisamente cuando no hay rol en sesión.

**Consecuencia:** acceder por URL o enviar un formulario directamente evita el control del login. Hay rutas que permiten consultar información clínica o gestionar cuentas sin la autorización prevista. La expiración de sesión, la desactivación de cuentas y el cambio de contraseña tampoco se revalidan al entrar en esos módulos.

**Corrección:** ejecutar el control central antes de conexión, HTML y cualquier operación; eliminar los permisos por ausencia de sesión y aplicar los roles también a los receptores de formularios.

### H02 — Alta: operaciones sin CSRF y cancelación de citas por GET

**Ubicación:** `citas/cancelar.php:5`, `citas/listar.php:179`, `usuarios/cambiar_estado.php:6`, `estudiantes/listar.php:18` y formularios de escritura de los módulos.

El token CSRF se valida en login y cierre de sesión, pero no en las altas, ediciones, importaciones ni cambios de estado. `citas/cancelar.php?id=...` modifica directamente la base mediante GET.

**Consecuencia:** una petición no originada en el formulario autorizado puede modificar registros. Abrir el enlace de cancelación ya tiene efectos de escritura.

**Corrección:** exigir POST y validar un token en el servidor para cada modificación. El cuadro de confirmación JavaScript no sustituye ese control.

### H03 — Alta: informes no coincide con la base de datos activa

**Ubicación:** `informes/registrar.php:57`, `informes/guardar.php:166`, `informes/actualizar.php:61`.

Se confirmaron tres incompatibilidades independientes:

1. El formulario consulta `seguimientos.recomendaciones`, columna inexistente en la base activa. La preparación de esa consulta devuelve **`Unknown column 's.recomendaciones' in 'field list'`**. La página falla antes de presentar el formulario.
2. El INSERT omite `informes.id_usuario` y `informes.titulo`, ambos obligatorios y sin valor predeterminado. Guardar `elaborado_por` no satisface `id_usuario`, que tiene su propia clave externa. No existe un usuario con ID 0 que pueda satisfacer un valor implícito.
3. El código admite `Borrador`, `Emitido` y `Anulado`; el ENUM real admite `Borrador` y `Finalizado`. La interfaz y el almacenamiento no representan los mismos estados.

**Corrección:** definir un único modelo de informe y adaptar consulta, formulario, INSERT, UPDATE y migración. No basta con agregar un alias a la primera consulta.

### H04 — Alta: la importación CSV omite curso y paralelo obligatorios

**Ubicación:** `estudiantes/importar.php:59` y `estudiantes/importar.php:120`.

El INSERT escribe los textos `curso` y `paralelo`, pero omite `id_curso` e `id_paralelo`. En la base activa son obligatorios, no tienen valor predeterminado y tienen claves externas. No existen registros con ID 0 en los catálogos.

**Consecuencia:** una fila válida para el formulario no puede completar esas relaciones al importarse. Además, con las excepciones mysqli activadas, el `else` de `execute()` no captura un error SQL: la importación puede terminar sin el resumen previsto.

**Corrección:** resolver y validar ambos IDs por cada fila, compartir las reglas con el alta manual y manejar los errores con una política explícita de importación parcial o transacción completa.

### H05 — Alta: el servicio de derivaciones de la historia clínica no devuelve JSON

**Ubicación:** `historias_clinicas/formulario.php:1005`, `historias_clinicas/ajax_derivaciones.php:8`.

El formulario hace GET con `id_estudiante` y espera `{ derivaciones: [...] }`. El receptor rechaza GET, redirige a `registrar.php` y contiene lógica de INSERT de historias clínicas para POST.

**Consecuencia:** la carga del selector recibe HTML en lugar de JSON y falla. La ruta destinada a consulta también duplica una operación de guardado.

**Corrección:** convertir ese archivo en un endpoint de consulta autenticada, validar el estudiante y devolver JSON con la estructura esperada. Mantener el guardado en su receptor correspondiente.

### H06 — Alta: editar una derivación puede borrar sus observaciones

**Ubicación:** `derivaciones/editar.php:430`, `includes/navbar.php:17`, `derivaciones/actualizar.php:55` y `derivaciones/actualizar.php:179`.

El JavaScript usa `document.querySelector('form')`. El primer formulario de la página es el de cerrar sesión, incluido por el navbar. Por tanto, la rutina que reúne categorías y observaciones se ejecuta al intentar salir, no al guardar la derivación.

El campo oculto `observaciones` empieza vacío. Al guardar la edición, el servidor recibe ese vacío y reemplaza las observaciones anteriores. Las categorías están codificadas dentro de ese texto, por lo que también se pierden.

**Corrección:** seleccionar el formulario de edición por un ID exclusivo y construir/validar los datos en el servidor. Conservar los valores previos ante envíos incompletos.

### H07 — Alta: los datos académicos pueden quedar asociados al curso equivocado

**Ubicación:** `estudiantes/guardar.php:140`, `estudiantes/guardar.php:153`, `estudiantes/guardar.php:195`, `estudiantes/guardar.php:217`, `estudiantes/actualizar.php:42`.

El alta intenta usar el número visible del curso como ID y, si no encuentra coincidencia, toma el primer curso disponible. Para el paralelo llega a buscar solo por nombre, sin restringirlo al curso, o toma otro paralelo del curso.

La edición modifica únicamente `curso` y `paralelo`, dejando intactos `id_curso` e `id_paralelo`.

**Consecuencia:** la pantalla puede mostrar un curso y las relaciones/vistas SQL representar otro; un paralelo puede pertenecer a un curso diferente. Son fallas de lógica confirmadas, no una afirmación de que ya existan alumnos afectados.

**Corrección:** usar catálogos con IDs reales, verificar que el paralelo pertenezca al curso y actualizar de forma coherente las relaciones. Rechazar una selección inexistente en vez de sustituirla.

### H08 — Alta: estudiantes permite estados que su tabla no admite

**Ubicación:** `estudiantes/registrar.php:124`, `estudiantes/guardar.php:45`, `estudiantes/importar.php:57`.

Alta e importación admiten `En seguimiento` y `Baja`, pero el ENUM activo es `Activo`/`Retirado`. Edición y retiro sí trabajan con esos dos estados.

**Consecuencia:** guardar/importar esos valores no puede conservar el estado solicitado. Según el modo SQL puede producir rechazo o conversión con advertencia; el servidor revisado no tiene habilitado el modo estricto de datos.

**Corrección:** unificar el catálogo. Si “En seguimiento” representa la atención psicológica, definir su relación con la historia clínica sin mezclarla accidentalmente con la matrícula.

### H09 — Alta: una edición cambia al autor de la historia clínica

**Ubicación:** `historias_clinicas/actualizar.php:32` y `historias_clinicas/actualizar.php:161`.

Cada edición reemplaza `historias_clinicas.id_usuario` por el usuario de la sesión. El listado presenta ese campo como profesional.

**Consecuencia:** si otra psicóloga o un administrador edita el registro, cambia la atribución de la historia. Sin sesión se intenta escribir NULL en un campo obligatorio de la base activa.

**Corrección:** conservar al autor original y registrar separadamente quién modifica el registro y cuándo, después de validar la sesión.

### H10 — Alta: la evolución del caso se captura pero no se guarda

**Ubicación:** `historias_clinicas/formulario.php:814`, `historias_clinicas/guardar.php:225`, `historias_clinicas/actualizar.php:159`.

El formulario envía `evolucion_caso`, pero ninguno de los dos receptores lo lee ni lo persiste. La base activa tampoco tiene esa columna, aunque sí aparece en el SQL exportado.

**Consecuencia:** la selección se pierde al volver a abrir el registro, pese a que la interfaz anuncia su uso para estadísticas.

**Corrección:** decidir dónde se almacena esa evaluación, aplicar una migración, validar los valores de 1 a 5 y comprobar su recuperación tras alta y edición.

### H11 — Alta: la instalación no reproduce el esquema que necesita el código

**Ubicación:** `config/conexion.php:17`, `config/conexion.php:39`, `config/conexion.php:206`, `config/conexion.php:370`, `database/psicologia_db.sql:281`, `includes/autenticacion.php:48`.

Existen tres esquemas distintos: la base activa, el SQL exportado y las tablas creadas automáticamente al conectar.

- El SQL y la creación automática de `roles` no incluyen `estado`, pero login lo consulta. La base activa sí lo tiene: este bloqueo corresponde a reinstalación, no a la conexión actual.
- El alta de estudiantes usa `cursos`, `paralelos`, `id_curso` e `id_paralelo`; ni el SQL exportado ni la inicialización automática crean ese modelo completo.
- La inicialización de `historias_clinicas` omite `id_derivacion`, requerido por sus INSERT.
- La tabla `historias_clinicas` del SQL exportado omite `observaciones`, que usan los receptores de escritura; la inicialización tampoco agrega esa columna a una tabla existente.
- La conexión ejecuta CREATE, INSERT y comprobaciones/ALTER de esquema en las peticiones de los módulos, antes de sus controles de acceso.

**Corrección:** mantener migraciones explícitas y versionadas que produzcan un único esquema; separar la instalación de la conexión normal. Validar después una instalación vacía y una actualización desde la base actual.

## Enlaces y flujos incompletos

### H12 — Media: cinco destinos devuelven HTTP 404

| Origen | Destino inexistente | Efecto |
|---|---|---|
| `estudiantes/listar.php:543` | `estudiantes/ver.php` | El botón Ver estudiante falla. |
| `docentes/listar.php:254` | `docentes/eliminar.php` | El botón Eliminar docente falla. |
| `informes/listar.php:304` | `informes/imprimir.php` | El botón Imprimir informe falla. |
| `login.php:35` | `assets/css/login.css` | Solicitud CSS duplicada con ruta incorrecta; el CSS de `asset/` sí carga. |
| `login.php:75` | `assets/js/login.js` | No carga el JavaScript que habilita Mostrar/Ocultar contraseña. |

Los cinco 404 se comprobaron por HTTP. Los archivos reales de login están en `asset/`, en singular. Los otros tres destinos no existen en el proyecto. Cualquier futura acción de eliminación debe aplicar los controles de H01/H02 y respetar sus relaciones.

### H13 — Media: confirmar un informe sin historia no permite enviarlo

**Ubicación:** `informes/registrar.php:434`.

Si el estudiante no tiene historia clínica, se ejecuta `event.preventDefault()` antes de preguntar si se desea continuar. Aceptar la confirmación no deshace esa cancelación; luego el botón queda deshabilitado mostrando “Guardando...”.

**Corrección:** cancelar únicamente cuando se rechace continuar, o realizar explícitamente el envío validado. Este fallo quedará visible después de corregir la consulta de H03.

### H14 — Media: citas pierde la identificación del profesional y de la derivación

**Ubicación:** `citas/procesar_registrar.php:115`, `citas/registrar.php:18`.

El INSERT guarda `id_usuario=NULL` y omite `id_derivacion`. La pantalla consulta una psicóloga activa, pero el receptor no persiste esa asociación. La solicitud de cita de una derivación guarda una bandera/profesional solicitado, sin un flujo que transfiera esa relación a la cita.

**Consecuencia:** falta trazabilidad entre profesional, solicitud y atención. El índice único activo `(id_usuario, fecha, hora)` tampoco evita citas concurrentes con usuario NULL; la comprobación previa por SELECT deja una ventana de concurrencia. Este riesgo se deduce del flujo, sin haber generado duplicados de prueba.

**Corrección:** registrar al responsable autenticado, validar y conservar la derivación de origen cuando corresponda, y definir una reserva de horario atómica compatible con las cancelaciones.

### H15 — Media: editar citas aplica menos validaciones que registrarlas

**Ubicación:** `citas/procesar_registrar.php:56`, `citas/procesar_editar.php:10`.

El alta restringe las horas a intervalos de 30 minutos entre 07:00 y 18:00. La edición recibe `hora` directamente y no valida ese catálogo en PHP. Un POST directo puede evitar las opciones del selector. Tampoco comprueba que exista la cita antes de comunicar éxito; ejecutar un UPDATE que no encuentra filas devuelve éxito de ejecución.

**Corrección:** compartir las validaciones de horario y verificar la existencia del registro. Definir expresamente qué modificaciones históricas se permiten.

### H16 — Media: al editar una historia desaparecen datos de la derivación vinculada

**Ubicación:** `historias_clinicas/registrar.php:79`, `historias_clinicas/editar.php:27`, `historias_clinicas/formulario.php:204`, `historias_clinicas/formulario.php:296`.

El alta carga `materia_derivacion`, `prioridad_derivacion` y `observaciones_derivacion`. La consulta de edición solo combina historia y estudiante, sin recuperar esos datos de la derivación. Esos campos no existen en la tabla activa de historias; su fuente disponible es la derivación relacionada. El formulario los oculta cuando no tienen valor.

**Consecuencia:** información de referencia visible durante el alta desaparece al abrir la edición, aunque `id_derivacion` se conserve. El selector de derivación se presenta solo en el alta; no se considera un error que la edición mantenga ese vínculo sin cambiarlo.

**Corrección:** recuperar esos datos mediante el `id_derivacion` de la historia al preparar la edición y mostrarlos como referencia.

### H17 — Media: al editar una historia se puede sobrescribir el lugar de nacimiento

**Ubicación:** `historias_clinicas/editar.php:27`, `historias_clinicas/editar.php:35`, `historias_clinicas/formulario.php:886`, `historias_clinicas/formulario.php:915`.

La consulta selecciona `h.*` y también `e.lugar_nacimiento` sin alias. Ambas columnas comparten nombre y el resultado asociativo conserva la del estudiante. El JavaScript además completa los datos del estudiante al cargar el formulario.

**Consecuencia:** si la historia tiene un lugar de nacimiento y la ficha estudiantil está vacía o es diferente, guardar la edición puede sustituir el valor clínico sin que el usuario lo haya cambiado.

**Corrección:** usar alias diferentes y definir qué datos son referencias vivas y cuáles son una copia conservada en la historia; respetar esa decisión al mostrar y guardar.

### H18 — Media: los formularios no recuperan todos los datos después de un error

**Ubicación:** `estudiantes/guardar.php:59`, `estudiantes/registrar.php:23`, `informes/guardar.php:34`, `informes/registrar.php:152`.

Estudiantes guarda errores y datos en sesión, pero su formulario solo presenta errores mediante `GET error` y no recupera `datos_estudiante`. Informes guarda `datos_informe`, pero el formulario de registro tampoco utiliza esos datos.

**Consecuencia:** tras un rechazo, el usuario puede perder lo escrito; en estudiantes puede regresar sin una explicación visible.

**Corrección:** usar un único mecanismo de mensajes y repoblar cada campo desde los datos conservados, escapando la salida.

### H19 — Media: límites incompatibles entre formulario, servidor y almacenamiento

**Ubicación:** `docentes/guardar.php:188`, `docentes/actualizar.php:162`, `usuarios/guardar.php:55`, `usuarios/actualizar.php:111`, `login.php:14`.

- Docentes permite hasta 500 caracteres en la lista de materias, pero escribe en `docentes.materia`, que admite 150 en la base activa. Seleccionar suficientes materias puede causar truncamiento o error.
- Alta y cambio de contraseña no aplican el máximo de 72 bytes exigido por login. Es posible aceptar al guardar una contraseña que el formulario de ingreso después rechaza por longitud.

**Corrección:** compartir los límites reales entre formularios y receptores. Para materias, valorar una relación de varias materias en vez de un texto concatenado.

### H20 — Media: permisos y continuidad del flujo no están definidos de manera uniforme

**Ubicación:** `derivaciones/editar.php:17`, `derivaciones/actualizar.php:34`, `historias_clinicas/listar.php:16`, `historias_clinicas/ver.php:166`, `LEEME.md`.

- El formulario de edición de derivaciones admite psicóloga; el receptor de actualización permite administrador/docente. El mismo flujo presenta permisos distintos.
- El listado/detalle de historias admite Director, mientras la matriz de integración de `LEEME.md` reserva las historias a administrador/psicóloga. Hay que resolver esta contradicción al definir la política, en vez de copiar uno de los criterios accidentalmente.
- No hay un receptor que cambie el estado de una derivación a “En seguimiento” o “Atendido”. Crear una historia o atender una cita tampoco lo actualiza. Los estados se muestran y filtran, pero su transición no está implementada en los archivos revisados.
- Se muestran seguimientos existentes, pero no se encontró un formulario/receptor para registrarlos. La adaptación de lectura tampoco contempla `proxima_sesion`, que es el nombre de la columna activa.

**Corrección:** definir una matriz de permisos por acción y un flujo de atención con transiciones explícitas. Completar los puntos de entrada necesarios sin inferir que todas las acciones deben ocurrir automáticamente.

## Conexiones que sí están presentes en el código

Estas observaciones describen correspondencias comprobadas; no certifican una prueba completa de cada operación.

| Flujo | Correspondencia encontrada |
|---|---|
| Login → base de datos | Host, puerto y base coinciden con la conexión de módulos; el servidor responde. Los roles activos 1–4 existen. |
| Login → docente | El vínculo se consulta por `docentes.id_usuario` y exige una coincidencia única; no confunde el ID de usuario con el de docente. |
| Guardar derivación → docente | Para rol docente se resuelve el autor desde su cuenta; para administrador se valida el docente elegido. |
| Guardar historia → estudiante/derivación | El alta comprueba que la derivación exista y corresponda al estudiante; historia, opciones y familiares se guardan dentro de una transacción. |
| Formularios principales → receptores | Existen los destinos de alta/edición de los siete módulos; citas usa `procesar_registrar.php` y `procesar_editar.php`. Los problemas de contrato y persistencia están detallados arriba. |
| Cierre de sesión | Usa POST, token CSRF y una ruta existente. |
| Consultas revisadas | Se utilizan sentencias preparadas en las operaciones revisadas; esto no reemplaza la autorización ni las validaciones de datos. |

## Observaciones menores

- `estadisticas/` está vacío y su opción de menú está deshabilitada: funcionalidad pendiente, no un enlace activo roto.
- `.gitinore` está mal nombrado; Git no lo reconoce como `.gitignore`.
- `index.php:292` tiene una `s` después del include del pie y la enviará como texto al navegador.
- `VALIDACION.txt` describe un entorno anterior sin PHP/MySQL disponibles. Esta revisión sí pudo usar ambos; conviene conservar claramente las fechas y alcances de cada validación.
- La URL base se repite en varios archivos además de `config/login.php`. Hoy coincide con esta carpeta, pero cambiar solo la configuración de login dejaría enlaces apuntando al nombre anterior.

## Orden de corrección y verificación

1. Integrar autenticación/autorización y CSRF en todos los puntos de entrada.
2. Alinear el esquema y corregir informes e importación antes de probar escrituras.
3. Reparar el endpoint JSON y el envío de edición de derivaciones; conservar autoría y todos los campos clínicos.
4. Unificar catálogos, IDs académicos y estados; reparar los cinco destinos inexistentes.
5. Completar la trazabilidad derivación → cita → historia → seguimiento → informe y verificar la matriz de permisos.
6. Probar en una base de pruebas: sesiones ausentes/vencidas, acceso de cada rol, altas/ediciones con recuperación de errores, cambios de estado, importación y lectura posterior de todos los valores guardados. Probar concurrencia de citas y una instalación desde cero.

La revisión inicial fue de diagnóstico y no modificó la aplicación ni sus datos. Las correcciones posteriores de los puntos 1, 2 y 3 se registran al principio de este documento.
