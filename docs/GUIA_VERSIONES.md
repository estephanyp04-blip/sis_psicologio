# Cómo guardar versiones del sistema

## Qué se guarda en cada lugar

- **Git** guarda el código PHP, el SQL de instalación, las migraciones y la documentación. Permite ver diferencias y volver a una versión del código.
- **Un respaldo SQL privado** guarda los datos reales de la base. Guárdalo fuera de `htdocs`; puede contener información psicológica y no se debe subir a Git.
- **`CHANGELOG.md`** explica qué cambió para que una versión se pueda revisar sin leer todos los archivos.

## Antes de guardar una versión

Abre PowerShell en `C:\xampp\htdocs\proyecto_vercionII` y revisa los cambios:

```powershell
git status
git diff --check
git diff
```

Ejecuta las comprobaciones del proyecto antes de publicar. Revisa también que no vayas a incluir contraseñas, archivos de configuración privada, datos de estudiantes ni respaldos SQL.

Cuando los cambios estén revisados, prepara y verifica lo que entrará al commit:

```powershell
git add -A
git status
git diff --cached --check
git diff --cached
```

Si en la revisión aparece un respaldo, información real o un archivo privado, quítalo del área preparada antes de continuar. `database/backups/` está excluida por `.gitignore`; mantenla solo como archivo local protegido.

Guarda el cambio con un mensaje claro:

```powershell
git commit -m "fix(db): elimina personas e inscripciones del esquema final"
```

El commit guarda una fotografía revisable del código. Para ver el historial después:

```powershell
git log --oneline --decorate -10
```

## Cómo nombrar las versiones

Usa el formato `MAJOR.MINOR.PATCH`, por ejemplo `v1.2.0`:

- **MAJOR** (`v2.0.0`): cambio incompatible o que requiere una migración especial.
- **MINOR** (`v1.3.0`): módulo o función nueva que mantiene lo existente.
- **PATCH** (`v1.2.1`): corrección pequeña compatible.

Después de guardar el commit que cierra una versión, crea una etiqueta:

```powershell
git tag -a v1.0.0 -m "Primera versión estable"
git tag
```

El número es un ejemplo: asigna el siguiente número después de revisar `git tag` y el alcance del cambio. Una etiqueta nombra un commit; no sustituye al commit ni al respaldo de la base.

## Cambios de base de datos

Cada actualización futura del esquema debe agregar una nueva migración numerada en `database/migrations_normalizadas/`, sin editar una migración que ya se aplicó en una instalación. La migración debe conservar los datos y poder ejecutarse más de una vez sin repetir cambios.

Antes de actualizar una base real:

1. Genera un respaldo SQL fuera de `htdocs`. Con XAMPP y usuario `root` sin contraseña:

   ```powershell
   $fecha = Get-Date -Format 'yyyyMMdd_HHmmss'
   & C:\xampp\mysql\bin\mysqldump.exe --host=127.0.0.1 --user=root --single-transaction psicologia_db > "$env:USERPROFILE\Documents\psicologia_db_$fecha.sql"
   ```

   Si MySQL tiene contraseña, añade `-p` para que la solicite en la terminal; no escribas la contraseña en el comando.

2. Comprueba primero el plan sin modificar la base:

   ```powershell
   & C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
   ```

3. Aplica solo después de revisar el plan y tener el respaldo:

   ```powershell
   & C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
   & C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
   ```

Anota la migración y el resultado en `CHANGELOG.md`. Para recuperar datos de una versión anterior se necesita el respaldo SQL correspondiente; cambiar de etiqueta Git por sí solo no revierte una base migrada.

## Subir una versión a otro equipo

Si el repositorio tiene un remoto configurado, sube el commit y la etiqueta después de verificarlos:

```powershell
git push
git push origin v1.0.0
```

El código puede viajar por Git; la base con datos reales se entrega y resguarda por un canal privado autorizado, separada del repositorio.
