# Registro de cambios

Los cambios todavía no se han agrupado ni etiquetado en una versión publicada.

## Sin publicar — 2026-10-21

- Trasladada la identidad de cuentas a `usuarios` y la relación docente a `docentes.cod_usuario`.
- Trasladadas las inscripciones de estudiantes a `historial_academico`.
- Eliminadas de la estructura final las tablas y vistas `personas` e `inscripciones`, y las columnas de compatibilidad `cod_persona`.
- Adaptadas consultas y formularios PHP para el modelo final; el alcance actual sigue siendo una unidad educativa.
- Añadida la migración de limpieza para bases que conservaran objetos de compatibilidad de una versión preliminar.
- Conservados los registros existentes al actualizar la base configurada; se creó un respaldo SQL local fuera de la carpeta pública.

## Plantilla para futuras entradas

Al cerrar una versión, reemplaza el encabezado “Sin publicar” por la etiqueta y fecha de release. En cada entrada futura resume las funciones, correcciones y migraciones, e indica si la actualización requiere respaldo o una acción manual.
