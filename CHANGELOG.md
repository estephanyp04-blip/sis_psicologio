# Registro de cambios

## v2.0.0 — 2026-10-30

- Limpieza del dump SQL y esquema de una sola escuela: retirados `instituciones` y `secciones.cod_institucion`; la secuencia de informes usa `cod_secuencia`.
- Normalizadas las claves identificadoras y alineadas las tres vistas. La base activa terminó con 31 tablas, 41 claves foráneas y 3 vistas; el comprobador no dejó migraciones pendientes.
- Centralizado el escape HTML: 16 helpers repetidos y 305 llamadas pasan por `login_html()`.
- Alineado el acceso del Administrador para conservar acceso total, incluida la eliminación de derivaciones pendientes; Docente mantiene la restricción a las propias.
- Retirado el endpoint obsoleto `derivaciones/evidencias.php`, sin referencias activas; la lectura protegida usa `derivaciones/evidencia.php`.
- Conservadas las pruebas de seguridad e integración. No se eliminaron campos clínicos ni datos de estudiantes, citas, derivaciones, seguimientos o informes durante la limpieza.
- Las instalaciones nuevas no publican una contraseña predeterminada: deben asignarla mediante `herramientas/establecer_clave.php`.

### Verificación

- Seguridad y flujos: 1.041 comprobaciones.
- Actualización de informes: 31.
- Migración sintética de identidad e historial: 12.
- Esquema/importación con Chrome: 342 + 10 comprobaciones de navegador.
- Historias/derivaciones con Chrome: 183 + 14.
- Trazabilidad con Chrome: 134 + 17.
- Estadísticas: 74; Chrome: 19 a 1440 px y 19 a 390 px.
- Sintaxis PHP: 113 archivos activos. Rutas: 60/60; recursos locales: 8/8.

### Pendiente

- Cotejar la impresión con el Anexo 4 oficial, que no está en el repositorio.
- Confirmar si hace falta importar `.xlsx`; actualmente se admite CSV exportado desde Excel.
- Probar el inicio de sesión con las cuentas institucionales y validar disponibilidad de CDN en el entorno final.

## v1.0.0 — 2026-10-21

- Trasladada la identidad de cuentas a `usuarios` y la relación docente a `docentes.cod_usuario`.
- Trasladadas las inscripciones de estudiantes a `historial_academico`.
- Eliminadas de la estructura final las tablas y vistas `personas` e `inscripciones`, y las columnas de compatibilidad `cod_persona`.
- Adaptadas consultas y formularios PHP para el modelo final; el alcance actual sigue siendo una unidad educativa.
- Añadida la migración de limpieza para bases que conservaran objetos de compatibilidad de una versión preliminar.
- Conservados los registros existentes al actualizar la base configurada; se creó un respaldo SQL local fuera de la carpeta pública.

## Plantilla para futuras entradas

Al cerrar una versión, reemplaza el encabezado “Sin publicar” por la etiqueta y fecha de release. En cada entrada futura resume las funciones, correcciones y migraciones, e indica si la actualización requiere respaldo o una acción manual.
