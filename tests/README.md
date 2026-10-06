# Pruebas de integración, acceso y CSRF

## Pruebas vigentes: 6 de octubre de 2026

Ejecutar desde la raíz del proyecto, con MySQL iniciado y la conexión de `config/login.php` configurada:

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
```

Resultados: **891 comprobaciones de seguridad y flujos** y **31 de actualización de informes**, con el esquema normalizado. Se usan datos sintéticos en tablas MySQL `TEMPORARY`, sin escrituras en datos reales. La cuenta de conexión necesita permiso para crear tablas temporales.

### Seguridad y flujos

`seguridad.php` crea una copia temporal de los PHP y un servidor en `127.0.0.1`, con puerto aleatorio y un secreto exclusivo. Al terminar elimina servidor, sesiones y archivos temporales, verificando la ruta de limpieza. `seguridad_router.php` prepara las cuentas mediante `personas` y también oculta las tablas/vistas de negocio con datos temporales.

Cobertura:

- Las 52 rutas, los cuatro roles, métodos HTTP y tokens CSRF ausentes, incorrectos, enviados como arreglo y válidos.
- Sesiones vencidas/revocadas, usuarios y roles inactivos, cambios de contraseña/rol y vínculo docente inválido.
- Acceso directo rechazado a configuración y archivos internos.
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

## Suites históricas pendientes de adaptación

| Archivo | Cobertura prevista |
|---|---|
| `esquema_informes_importacion.php` | Instalación, migraciones anteriores, importación CSV e informes |
| `historias_derivaciones.php` | Formularios y persistencia de historias clínicas y derivaciones |
| `trazabilidad.php` | Cadena de citas, historias, seguimientos e informes |
| `historias_navegador.php`, `trazabilidad_navegador.php` | Interfaz y JavaScript en Chrome, invocados por sus suites |
| `integracion_router.php` | Servidor auxiliar de las suites de integración |

Estas suites conservan referencias al esquema anterior y no pasan actualmente con el esquema normalizado. Sus resultados del 5 de octubre de 2026 son históricos, no una certificación del estado actual. Se mantienen para adaptar su cobertura; no deben borrarse por no formar parte de las páginas de la aplicación.

Los casos vigentes no certifican todavía toda la importación, historias clínicas ni la trazabilidad completa. Detalles del esquema en [database/README.md](../database/README.md).

## Matriz de permisos

La política ejecutable está en [config/permisos.php](../config/permisos.php).

| Área o acción | Roles permitidos |
|---|---|
| Panel, estudiantes y citas | Administrador, psicóloga |
| Usuarios y docentes | Administrador |
| Historias clínicas y seguimientos | Administrador, psicóloga |
| Iniciar/cerrar/reabrir derivaciones | Administrador, psicóloga |
| Listar/ver derivaciones | Administrador, psicóloga, docente; docente solo propias |
| Registrar/editar derivaciones | Administrador, docente; docente solo propias |
| Eliminar derivación pendiente | Docente, solo propia y sin citas, historias o informes vinculados |
| Listar/ver informes | Administrador, psicóloga, director |
| Registrar/editar informes | Administrador, psicóloga |

Para agregar una página, declarar sus roles y métodos y llamar a `requerir_acceso('modulo/archivo.php')` antes de conectar o producir HTML. Todo POST permitido valida CSRF automáticamente; su formulario debe incluir `<?= login_campo_csrf() ?>`. El menú consulta esa misma política.
