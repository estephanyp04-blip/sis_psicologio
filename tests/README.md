# Pruebas de integración, acceso y CSRF

## Pruebas vigentes

Ejecutar desde la raíz del proyecto, con MySQL iniciado y la conexión de `config/login.php` configurada:

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\estadisticas.php --navegador
```

Últimos resultados con el esquema normalizado: seguridad y estadísticas, 7 de octubre de 2026; las demás suites, 6 de octubre de 2026.

| Suite | Comprobaciones |
|---|---:|
| Seguridad y flujos | 927 |
| Actualización de informes | 31 |
| Esquema, importación y edición de estudiantes | 287 |
| Historias y derivaciones, con `--navegador` | 183 + 14 verificaciones dentro de Chrome |
| Trazabilidad, con `--navegador` | 120 + 11 verificaciones dentro de Chrome |
| Estadísticas, con `--navegador` | 58 + 12 verificaciones en Chrome a 1440 px y 12 a 390 px |

Las dos primeras usan tablas MySQL `TEMPORARY`. Las otras cuatro crean y eliminan bases `psicologia_test_*` con sufijo aleatorio, usando solo datos sintéticos. La cuenta necesita permisos para crear tablas temporales, bases, vistas y triggers, aplicar el esquema y eliminar las bases de prueba. No se copian pacientes ni cuentas de la base real.

La opción `--navegador` busca Chrome en `C:/Program Files/Google/Chrome/Application/chrome.exe`. Se puede omitir para ejecutar solo las comprobaciones de base de datos y HTTP. El navegador usa un perfil temporal y el servidor de prueba bloquea recursos externos mediante CSP.

### Seguridad y flujos

`seguridad.php` crea una copia temporal de los PHP y un servidor en `127.0.0.1`, con puerto aleatorio y un secreto exclusivo. Al terminar elimina servidor, sesiones y archivos temporales, verificando la ruta de limpieza. `seguridad_router.php` prepara las cuentas mediante `personas` y también oculta las tablas/vistas de negocio con datos temporales.

Cobertura:

- Las 53 rutas, los cuatro roles, métodos HTTP y tokens CSRF ausentes, incorrectos, enviados como arreglo y válidos.
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
| `esquema_informes_importacion.php` | Instalación, migración del tutor, conservación de datos, CSV parcial, informes concurrentes y edición de estudiantes |
| `historias_derivaciones.php` | Persistencia clínica, tutor, medidas, recuperación de errores, categorías de catálogo y conservación de valores históricos |
| `trazabilidad.php` | Citas, historias, seguimientos e informes; episodios distintos, permisos, rollback y reserva concurrente por profesional |
| `estadisticas.php` | Conteos por historia, curso y fechas; inscripciones múltiples, paginación, permisos, estados vacíos y filtros en Chrome |
| `historias_navegador.php`, `trazabilidad_navegador.php` | Interfaz y JavaScript en Chrome, invocados por sus suites |
| `integracion_router.php` | Servidor auxiliar de las suites de integración |
| `datos_sinteticos.php` | Personas, usuarios, docentes e inscripciones artificiales para las cuatro suites |

La migración se ensaya sobre una instalación sintética sin la columna del tutor: primero se comprueba que `--comprobar` no escribe, después se compara la estructura con una instalación nueva y se verifica que cada fila conserve sus columnas previas. También se comprueba la idempotencia.

La edición de estudiantes cubre CI duplicado, recuperación de entradas escapadas, fichas retiradas, CI opcional, consistencia con la inscripción y rollback ante un fallo SQL. Los datos recuperados quedan asociados al estudiante correspondiente. Las categorías y materias inactivas pueden conservarse si ya estaban vinculadas; no se aceptan como nuevas selecciones.

Estas pruebas no sustituyen una revisión de los datos reales ni prueban la conversión desde el esquema anterior al normalizado. Detalles en [database/README.md](../database/README.md).

### Estadísticas

La fixture contiene historias sin sesiones, estudiantes retirados o sin inscripción, cambios de curso, sesiones en los límites del período y dos seguimientos con la misma fecha. Comprueba que las citas no inflen los totales, que los filtros afecten al resumen, gráfico y detalle, y que el último seguimiento no recupere una próxima fecha obsoleta. Los errores SQL o de filtros no se muestran como ceros. Se verifica el escape de nombres y que no se expongan notas clínicas en el resumen.

`--navegador` prueba filtros, restablecimiento, paginación, apertura de la historia, barras visibles y ausencia de desbordamiento a 1440 y 390 px. Para conservar capturas, establecer `CAPTURAS_ESTADISTICAS` con una carpeta existente fuera del proyecto.

## Matriz de permisos

La política ejecutable está en [config/permisos.php](../config/permisos.php).

| Área o acción | Roles permitidos |
|---|---|
| Panel, estudiantes y citas | Administrador, psicóloga |
| Usuarios y docentes | Administrador |
| Historias clínicas y seguimientos | Administrador, psicóloga |
| Estadísticas de seguimiento por estudiante | Administrador, psicóloga |
| Iniciar/cerrar/reabrir derivaciones | Administrador, psicóloga |
| Listar/ver derivaciones | Administrador, psicóloga, docente; docente solo propias |
| Registrar/editar derivaciones | Administrador, docente; docente solo propias |
| Eliminar derivación pendiente | Docente, solo propia y sin citas, historias o informes vinculados |
| Listar/ver informes | Administrador, psicóloga, director |
| Registrar/editar informes | Administrador, psicóloga |

Para agregar una página, declarar sus roles y métodos y llamar a `requerir_acceso('modulo/archivo.php')` antes de conectar o producir HTML. Todo POST permitido valida CSRF automáticamente; su formulario debe incluir `<?= login_campo_csrf() ?>`. El menú consulta esa misma política.
