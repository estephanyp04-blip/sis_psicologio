# Pruebas de integración, acceso y CSRF

## Trazabilidad y matriz de permisos: punto 5

```powershell
& C:\xampp\php\php.exe tests\trazabilidad.php
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
```

Resultado del 5 de octubre de 2026: **117 comprobaciones**; con `--navegador`, **119 más 11 dentro de Chrome**. Se crean una base aleatoria `psicologia_test_flujo_*` y una copia temporal, y se eliminan al terminar. No se prueban escrituras en la base real.

Cobertura: formularios y receptores HTTP, campos y autores de seguimientos, segunda derivación dentro del mismo expediente, conservación de origen de informes, cierre/reapertura, relaciones ajenas, fechas, duplicación por cita, rollback ante fallo de auditoría, cancelación/reutilización de horarios, cuatro reservas concurrentes, recuperación de textos escapados y permisos reales de los cuatro roles. Chrome completa cita → historia → seguimiento → informe con sus formularios y JavaScript.

La [matriz y reglas del flujo](../TRAZABILIDAD.md) corresponden a la política central de **52 rutas**.

## Historias clínicas y derivaciones: punto 3

Ejecutar con MySQL iniciado:

```powershell
& C:\xampp\php\php.exe tests\historias_derivaciones.php
# Incluye la interfaz real en Chrome instalado en su ruta estándar de Windows.
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
```

Resultado del 5 de octubre de 2026: **187 comprobaciones** sin navegador; **190 comprobaciones más 14 dentro de Chrome** con `--navegador`.

La suite instala una base aleatoria `psicologia_test_clinica_*`, usa datos sintéticos y una copia temporal de la aplicación. El servidor escucha únicamente en `127.0.0.1` y requiere un secreto aleatorio; el acceso del navegador se habilita solo en la copia de prueba. Se eliminan base, servidor, sesiones y perfil temporal de Chrome al terminar. No se leen datos personales ni se escriben registros de la base real.

Cobertura:

- Alta, edición y lectura posterior de todos los campos clínicos; evolución 1–5 y sin evaluar, opciones, familiares y edad cero.
- Conservación del autor y auditoría del editor, con rollback de historia e hijos ante errores SQL; rechazo de cambios de estudiante/derivación y formularios truncados.
- JSON válido, orden y aislamiento por estudiante, listas vacías, errores de identificador y método.
- Envío de los formularios renderizados, campos omitidos conservados, eliminación explícita de hijos, opciones históricas y recuperación escapada de datos tras errores.
- Observaciones/categorías de derivaciones guardadas sin JavaScript; conservación de la solicitud y del profesional; permisos de propiedad y rechazo de derivaciones atendidas.
- En Chrome: datos clínicos conservados al abrir/guardar edición, preselección por URL, autocompletado y cambio de estudiante, alta sin derivación, respuestas fuera de orden y bloqueo ante fallo del endpoint. La preservación del motivo propio simula la marca de edición del usuario; no automatiza pulsaciones de teclado.

## Esquema, informes e importación

```powershell
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php
```

**87 comprobaciones correctas** con SQL estricto: instalación vacía, actualización de una copia de estructura con datos sintéticos, igualdad de esquema, conservación de datos, idempotencia, curso con ID distinto del grado, CSV con BOM/duplicados/errores por fila, informes con y sin historia, validación de estados y longitudes, rollback, numeración concurrente, receptores HTTP y recuperación de errores en formularios.

Se crean dos bases aleatorias `psicologia_test_*` y una copia temporal de la aplicación con conexión exclusiva a esas bases. Se eliminan al terminar. La base configurada se consulta únicamente para obtener su estructura; no se copian datos personales ni se realizan escrituras de prueba en ella. Detalles de instalación/migración en [database/README.md](../database/README.md).

## Acceso y CSRF

Ejecutar desde la raíz del proyecto, con MySQL iniciado y los datos de conexión de `config/login.php` disponibles:

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
```

La suite crea una copia temporal de los archivos PHP fuera del proyecto y un servidor PHP en `127.0.0.1`, con puerto aleatorio y un secreto exclusivo para las peticiones de prueba. El servidor y los archivos temporales se eliminan al terminar.

Las cuentas, derivaciones y citas de prueba se crean en tablas MySQL **TEMPORARY**, limitadas a cada conexión. Esas tablas ocultan a las permanentes solo durante la prueba. No se modifican usuarios, contraseñas ni registros reales. La conexión necesita permiso para crear tablas temporales; no hace falta importar una base de prueba.

La conexión normal de módulos se sustituye únicamente en la copia temporal. La matriz de acceso se comprueba antes de llegar al código de negocio. Los casos de propiedad y cancelación sí ejecutan los receptores originales contra las tablas temporales. No se ejecuta la inicialización de esquema de `config/conexion.php`.

Cobertura:

- Las 52 rutas declaradas en `config/permisos.php`, sin sesión y con los cuatro roles.
- Métodos HTTP permitidos; tokens CSRF ausentes, incorrectos, enviados como arreglo y válidos.
- Sesiones vencidas, duración máxima, cuenta/rol inactivos, cambio de contraseña/rol y vínculo docente inválido.
- Acceso directo rechazado a configuración y componentes internos.
- Lectura, edición y eliminación de derivaciones propias y ajenas.
- Cancelación de citas por POST válido; GET o POST sin token no cambian el registro.
- Formularios renderizados con un solo token, búsqueda GET separada, menú docente y cierre de sesión.
- Cobertura de políticas para todos los PHP de módulos y ausencia de salida/BOM antes de sus controles.

Resultado actualizado: **837 comprobaciones correctas**, incluyendo las nuevas rutas y archivos internos. En la implementación anterior del punto 1 también se verificaron 61 peticiones en Apache local, sin sesión: sus 48 puntos de entrada redirigían al login y los 13 archivos internos/de prueba comprobados respondían 404.

La suite `seguridad.php` verifica acceso y CSRF. Los guardados e importaciones del punto 2 se prueban en `esquema_informes_importacion.php`; historias y derivaciones del punto 3, en `historias_derivaciones.php`; la continuidad y los permisos de negocio del punto 5, en `trazabilidad.php`. Los flujos restantes siguen pendientes según `REVISION_MODULOS.md`.

## Matriz aplicada

| Área o acción | Roles permitidos |
|---|---|
| Panel, estudiantes y citas | Administrador, psicóloga |
| Usuarios y docentes | Administrador |
| Historias clínicas | Administrador, psicóloga |
| Registrar/ver seguimientos | Administrador, psicóloga |
| Iniciar/cerrar/reabrir derivaciones | Administrador, psicóloga |
| Listar/ver derivaciones | Administrador, psicóloga, docente; docente solo propias |
| Registrar/editar derivaciones | Administrador, docente; docente solo propias |
| Eliminar derivación pendiente | Docente, solo propia y sin citas, historias o informes vinculados |
| Listar/ver informes | Administrador, psicóloga, director |
| Registrar/editar informes | Administrador, psicóloga |

Para agregar una página, declarar sus roles y métodos en `config/permisos.php` y llamar a `requerir_acceso('modulo/archivo.php')` antes de conectar o producir HTML. Todo POST permitido valida CSRF automáticamente; su formulario debe incluir `<?= login_campo_csrf() ?>`. El menú consulta esa misma política. El parcial de historias y los archivos compartidos solo se incluyen desde páginas protegidas.
