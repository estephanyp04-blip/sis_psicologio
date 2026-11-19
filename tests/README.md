# Pruebas de integración, acceso y CSRF

## Avisos centralizados (19/11/2026)

```powershell
& C:\xampp\php\php.exe tests\avisos.php
```

No necesita MySQL. Comprueba el puente PHP y 39 condiciones de interfaz dentro de Chrome a 1440, 768, 390 y 320 px, con formularios y datos sintéticos. Incluye foco, teclado, cola, privacidad, recuperación, validación, cambios sin guardar, envíos repetidos y sesión 401. Para guardar capturas, configurar `CAPTURAS_AVISOS` con una carpeta existente.

La fixture del recorrido `historias_derivaciones.php --navegador` ahora copia también los recursos locales CSS y JavaScript para verificar la integración real. Inventario y resultados: [informe de avisos](../documentacion/AVISOS_CENTRALIZADOS_2026-11-19.md).

## Modelo vigente: historias y versiones (19/11/2026)

Los conteos anteriores basados en filas de `seguimientos` son antecedentes. La definición vigente cuenta historias clínicas por fecha de atención y distingue sus versiones documentales. `estadisticas.php` delega en `seguimiento_clinico.php` para evitar dos implementaciones de la misma comprobación.

```powershell
& C:\xampp\php\php.exe tests\seguimiento_clinico.php --navegador
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\fases.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe -d extension=sodium tests\endurecimiento.php
```

Cobertura nueva: alta de historia → edición con estado anterior completo → consulta individual → diferencias por apartado → estadísticas e informe mensual coherentes; ausencia de duplicados al cambiar de curso; casillas no seleccionadas como datos no registrados; archivo anterior conservado; POST retirado sin escrituras; cierre de derivación con historia; comparación ajena o fuera del período denegada; error SQL distinto de población vacía; consultas auditadas; vista agregada sin identidades ni notas clínicas.

Las pruebas de fases y trazabilidad usan inserciones directas de archivo únicamente en sus bases sintéticas para verificar compatibilidad; esas inserciones no restauran un endpoint de escritura en la aplicación. El navegador recorre ahora el formulario de historia y sus versiones.

Resultados de esta entrega se consolidan en el [informe técnico](../documentacion/SEGUIMIENTO_PSICOLOGICO_2026-11-19.md). Chrome verificó la comparación a 1440, 390 y 360 px (16 comprobaciones por ancho), y el recorrido de trazabilidad (35). Para conservar capturas sintéticas, configurar `CAPTURAS_SEGUIMIENTO` con una carpeta privada existente.

En una ejecución de historias con Chrome, las 213 comprobaciones funcionales y 19 del navegador pasaron, pero Windows mantuvo bloqueados dos archivos de su perfil temporal durante la limpieza. La revisión automática rechazó la retirada posterior de esa carpeta («blocked by policy», sin detalle). No contiene expedientes reales. Esta incidencia de limpieza no modifica los resultados funcionales ni la aplicación.

Actualización del 14/11/2026: resultados vigentes, cambios de actores y límites de cobertura en [la revisión técnica](../seguridad/08_REVISION_TECNICA_2026-11-14.md). Los totales del 2 de noviembre más abajo son antecedentes. La matriz anterior con acceso clínico de Administrador/Director queda sustituida por [la matriz actual](../seguridad/10_PRIVACIDAD_ACCESOS.md).

Nueva prueba de seguridad y recuperación:

```powershell
& C:\xampp\php\php.exe -d extension=sodium tests\endurecimiento.php
```

Usa exclusivamente bases `psicologia_test_seguridad_*` y `psicologia_restore_*`, clave/copia/código/adjunto sintéticos y carpetas privadas temporales. Prueba actualización aditiva desde estructura previa, reanudación, permisos excesivos, rehash, revocación tras reactivar, versiones y rollback, identidad histórica, manipulación de bitácora, cifrado, copia completa y restauración con 10000 sesiones ficticias. No copia datos reales a pruebas.

Las suites de Chrome usan un iframe; su router temporal habilita SAMEORIGIN únicamente con el secreto de la fixture. La aplicación real mantiene X-Frame-Options DENY. El router cambia de cuenta de prueba al pasar entre tareas administrativas, docentes y clínicas, conservando roles reales distintos; las pruebas HTTP de denegación usan el actor explícito.

## Pruebas vigentes

Las pruebas de historias y de fases cubren varias historias por estudiante: migración 014 sin pérdida de datos, altas desde una derivación existente, selección de historia en informes, separación de seguimientos y conteos del panel sin duplicar estudiantes. La opción `--navegador` de `historias_derivaciones.php` también comprueba el selector y el guardado de un informe asociado a una historia anterior.

Ejecutar desde la raíz del proyecto, con MySQL iniciado y la conexión de `config/login.php` configurada:

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\fases.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
& C:\xampp\php\php.exe tests\migracion_identidad_academica.php
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php --navegador
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\estadisticas.php --navegador
```

Resultados completos con el esquema normalizado y migración 013: 2 de noviembre de 2026.

| Suite | Comprobaciones |
|---|---:|
| Seguridad y flujos | 1116 |
| Correcciones por fases y migración 013 | 44 |
| Actualización de informes | 31 |
| Identidad e historial académico | 12 |
| Esquema, importación, estudiantes y docentes, con `--navegador` | 342 + 10 verificaciones dentro de Chrome |
| Historias y derivaciones, con `--navegador` | 183 + 14 verificaciones dentro de Chrome |
| Trazabilidad, con `--navegador` | 190 + 34 verificaciones dentro de Chrome; calendario integrado a 320, 390, 768, 1024 y 1440 px |
| Estadísticas y panel, con `--navegador` | 74 + 19 verificaciones en Chrome a 1440 px y 19 a 390 px |

`seguridad.php` e `informes_actualizacion.php` usan tablas MySQL `TEMPORARY`. `migracion_identidad_academica.php`, `esquema_informes_importacion.php`, `historias_derivaciones.php`, `trazabilidad.php` y `estadisticas.php` crean y eliminan bases `psicologia_test_*` con sufijo aleatorio y solo datos sintéticos. La cuenta necesita permisos para crear tablas temporales, bases, vistas y triggers, aplicar el esquema y eliminar las bases de prueba. No se copian pacientes ni cuentas de la base real.

La opción `--navegador` busca Chrome en `C:/Program Files/Google/Chrome/Application/chrome.exe`. Se puede omitir para ejecutar solo las comprobaciones de base de datos y HTTP. El navegador usa un perfil temporal y el servidor de prueba bloquea recursos externos mediante CSP.

### Seguridad y flujos

`seguridad.php` crea una copia temporal de los PHP y un servidor en `127.0.0.1`, con puerto aleatorio y un secreto exclusivo. Al terminar elimina servidor, sesiones y archivos temporales, verificando la ruta de limpieza. `seguridad_router.php` prepara las cuentas mediante tablas temporales, reemplaza también `modulos` y `permisos` en la conexión de prueba y oculta las tablas/vistas de negocio. La fixture concede intencionalmente permisos excesivos a Docente y Director en Dashboard, Estadísticas y Estudiantes, y permiso de edición de derivaciones a Docente sin cambio de estado.

Cobertura:

- Las 60 rutas, los cuatro roles, métodos HTTP y tokens CSRF ausentes, incorrectos, enviados como arreglo y válidos.
- Rechazo por servidor de concesiones de base excesivas a Docente y Director en Dashboard, Estadísticas y Estudiantes; edición de una derivación propia no habilita cambiar su estado.
- El editor y el guardado de permisos mantienen bloqueados esos módulos para Docente y Director, incluso ante un POST manipulado.
- Sesiones vencidas/revocadas, usuarios y roles inactivos, cambios de contraseña/rol y vínculo docente inválido.
- Acceso directo por GET y POST rechazado a configuración y archivos internos, incluidos `datos.php`, `csv.php` y `formulario.php` dentro de los módulos.
- Lectura, edición y eliminación de derivaciones propias; rechazo de operaciones sobre derivaciones ajenas.
- Listado único de derivaciones y compatibilidad de `mis_derivaciones.php`, aislamiento por docente, materia, prioridad y formulario de eliminación.
- Retiro y reactivación por administrador/psicóloga: estudiante y última inscripción cambian juntos; se conservan inscripciones históricas y otros estudiantes.
- Entradas inválidas, repetición de estado y rollback ante un error al actualizar la inscripción. El listado de estudiantes rechaza escrituras.
- Cancelación de citas por POST con CSRF, formularios renderizados, menú por rol y cierre de sesión.
- Cobertura de permisos para todos los PHP de módulos y ausencia de salida/BOM antes de la protección.

Para ejecutar únicamente los controles de acceso, sin los casos de negocio:

```powershell
& C:\xampp\php\php.exe tests\seguridad.php --solo-acceso
```

### Actualización de informes

`informes_actualizacion.php` verifica la persistencia de cabecera y detalle, campos opcionales vacíos, conservación de autor y relaciones, auditoría del editor y aislamiento de otros informes. Provoca errores SQL en detalle y auditoría para comprobar el rollback completo. La regresión del detalle que no se guardaba se reprodujo antes de aplicar la corrección.

## Migración, formularios y flujo completo

| Archivo | Cobertura |
|---|---|
| `migracion_identidad_academica.php` | Protección ante registros huérfanos, migración de identidad e historial académico, eliminación de tablas/vistas/columnas heredadas, claves foráneas e idempotencia |
| `esquema_informes_importacion.php` | Instalación, migraciones de tutor y materias, conservación de datos, CSV parcial, informes concurrentes, estudiantes y docentes |
| `historias_derivaciones.php` | Persistencia clínica, tutor, medidas, recuperación de errores, categorías de catálogo y conservación de valores históricos |
| `trazabilidad.php` | Citas, historias, seguimientos e informes; episodios distintos, permisos, rollback y reserva concurrente por profesional |
| `estadisticas.php` | Conteos por historia, curso y fechas; inscripciones múltiples, paginación, permisos, estados vacíos y filtros en Chrome |
| `historias_navegador.php`, `trazabilidad_navegador.php` | Interfaz y JavaScript en Chrome, invocados por sus suites |
| `integracion_router.php` | Servidor auxiliar de las suites de integración |
| `datos_sinteticos.php` | Personas, usuarios, docentes e inscripciones artificiales para las cuatro suites |

La migración se ensaya sobre una instalación sintética sin la columna del tutor: primero se comprueba que `--comprobar` no escribe, después se compara la estructura con una instalación nueva y se verifica que cada fila conserve sus columnas previas. También se comprueba la idempotencia.

La edición de estudiantes cubre CI duplicado, recuperación de entradas escapadas, fichas retiradas, CI opcional, consistencia con la inscripción y rollback ante un fallo SQL. Los datos recuperados quedan asociados al estudiante correspondiente. Las categorías y materias inactivas pueden conservarse si ya estaban vinculadas; no se aceptan como nuevas selecciones.

La cobertura de docentes comprueba cuentas disponibles, rechazo de materias inválidas, guardado de cuatro materias sin duplicados, recuperación de casillas al editar, cambio de asignaciones, búsqueda sin ocultar las otras materias y rollback. Con `--navegador` se verifica el autocompletado de nombres y contactos, cambio o limpieza de cuenta sin arrastrar datos ajenos, campos editables y persistencia real de varias materias. La migración del catálogo también se ensaya con una materia existente sin acento e inactiva, conservando su identificador y estado.

Estas pruebas no sustituyen una revisión de los datos reales ni prueban la conversión desde el esquema anterior al normalizado. Detalles en [database/README.md](../database/README.md).

### Estadísticas

La fixture contiene historias sin sesiones, estudiantes retirados o sin inscripción, cambios de curso, sesiones en los límites del período y dos seguimientos con la misma fecha. Comprueba que las citas no inflen los totales, que los filtros afecten al resumen, gráfico y detalle, y que el último seguimiento no recupere una próxima fecha obsoleta. Los errores SQL o de filtros no se muestran como ceros. Se verifica el escape de nombres y que no se expongan notas clínicas en el resumen.

El panel principal se prueba con derivaciones repetidas por estudiante, estudiantes retirados o sin historia, prioridades y estados distintos, sesiones futuras, límites de 7/30 días y citas atendidas, canceladas y reprogramadas. Se verifican el orden de prioridad, la agenda, el límite de cinco estudiantes y los accesos según el rol.

`--navegador` prueba filtros, restablecimiento, paginación, apertura de la historia, barras visibles y ausencia de desbordamiento a 1440 y 390 px. También comprueba las tarjetas del panel y abre sus enlaces a derivaciones, citas y estadísticas. Para conservar capturas del panel, establecer `CAPTURAS_ESTADISTICAS` con una carpeta existente fuera del proyecto.

## Matriz de permisos

La política ejecutable está en [config/permisos.php](../config/permisos.php).

| Área o acción | Roles permitidos |
|---|---|
| Panel y citas | Psicóloga |
| Estudiantes | Administrador, psicóloga; sujeto a permisos por acción |
| Usuarios y docentes | Administrador |
| Historias clínicas y seguimientos | Psicóloga |
| Estadísticas de seguimiento por estudiante | Psicóloga |
| Iniciar/cerrar/reabrir derivaciones | Psicóloga |
| Listar/ver derivaciones | Psicóloga, docente; docente solo propias con autoría compatible |
| Registrar/editar derivaciones | Docente solo propias con autoría compatible |
| Eliminar derivación pendiente | Docente solo propia, con autoría compatible y sin registros vinculados |
| Listar/ver/registrar/editar informes | Psicóloga |

Para agregar una página, declarar sus roles y métodos y llamar a `requerir_acceso('modulo/archivo.php')` antes de conectar o producir HTML. Todo POST permitido valida CSRF automáticamente; su formulario debe incluir `<?= login_campo_csrf() ?>`. El menú consulta esa misma política.

La política superior de `includes/politica_acceso.php` impide ampliar acceso clínico mediante concesiones de BD, incluso para administradores, dirección y roles personalizados. Asignación de casos entre profesionales y autorización independiente de impresiones siguen pendientes institucionales.

## Endurecimiento y recuperación

Ejecutar `C:\xampp\php\php.exe -d extension=sodium tests\endurecimiento.php`. Crea bases aleatorias ficticias, ensaya 016 desde un esquema anterior y su reanudación, revocación/rehash, permisos excesivos, versiones clínicas completas, auditoría protegida y recuperación cifrada con comparación de filas, adjuntos, código y SQL. No usa expedientes reales. Consulta resultados, fechas y límites en [la revisión técnica](../seguridad/08_REVISION_TECNICA_2026-11-14.md); las cifras anteriores de este README son antecedentes.
