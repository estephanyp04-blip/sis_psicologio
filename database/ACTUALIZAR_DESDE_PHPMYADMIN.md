# Actualizar una base existente

La aplicacion corresponde a una sola escuela (U.E. Cañada Pailita B). `instituciones` deja de ser una tabla y las secciones quedan identificadas por curso, paralelo, turno y gestion.

## Respaldo y migracion

1. En phpMyAdmin, seleccione la base usada por el proyecto y elija **Exportar → SQL**. Guarde el archivo fuera de la carpeta del proyecto y confirme que incluya estructura y datos.
2. Inicie MySQL en XAMPP. Desde la raiz del proyecto, revise el plan de solo lectura:

   ```powershell
   & C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
   ```

3. Si el plan se detiene por instituciones adicionales o secciones huérfanas, revise esos datos antes de migrar. No borre esas filas para forzar la migracion.
4. Aplique el plan:

   ```powershell
   & C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
   ```

La migracion retira la clave de institucion y su columna de `secciones`, conservando `cod_seccion` y las relaciones del historial academico. El respaldo SQL permite restaurar la base completa si fuera necesario.

No importe `psicologia_db.sql` sobre una base existente: ese archivo elimina y vuelve a crear las tablas. Para una base nueva, use el instalador documentado en `README.md`.

## phpMyAdmin

Si no puede ejecutar el migrador PHP, use `--comprobar` para revisar el SQL que genera, y ejecútelo desde la pestaña **SQL** de phpMyAdmin después del respaldo. Aplique las sentencias en el orden mostrado. El SQL de comprobación no sustituye la validación previa de datos; el migrador comprueba que no existan referencias inválidas ni más de una institución antes de producir el plan.
