# Migración 013: continuidad de la atención

La actualización se implementa en `migrations_normalizadas/013_continuidad_atencion.php`. El SQL de instalación ya incorpora las columnas y claves nuevas. El migrador completa la carga histórica y el marcador correspondiente. No se ejecutan migraciones al abrir páginas web.

## Cambios

| Tabla/objeto | Cambio |
|---|---|
| `derivaciones` | Cuenta del autor y copia de sus nombres/apellidos. La carga inicial conserva `fecha_actualizacion`. |
| `seguimientos` | Derivación del episodio y cita próxima confirmada, ambas con claves foráneas. |
| `historias_clinicas` | Motivo del último cierre. |
| `informe_resumen` | Total de seguimientos y JSON con filtros/totales de la consulta mensual. NULL identifica resúmenes anteriores sin ese dato. |
| `vista_derivaciones` | Presenta al autor conservado, con compatibilidad para registros todavía sin instantánea. |
| `permisos` | Habilita cambio de estado de Docentes para Administrador. Otros roles conservan su configuración. |
| `esquema_migraciones_normalizadas` | Marca `013_continuidad_atencion`, evitando repetir la carga histórica. |

No borra tablas, expedientes, notas, citas, derivaciones ni archivos. El vínculo histórico de sesiones se carga con la relación que utilizaba el sistema anterior. No puede reconstruir episodios o autores que ya hubieran sido atribuidos incorrectamente antes de la actualización.

## Aplicación

Desde la raíz del proyecto, con MySQL iniciado y después de respaldar la base y evidencias:

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
```

Cambiar `psicologia_db` por el nombre de la base que corresponda. El nombre no altera las credenciales configuradas en `config/login.php`. No usar `--instalar` en una base con información.

**Estado local del 02/11/2026:** aplicada, nueve operaciones y comprobación posterior con cero pendientes. Las filas y columnas originales de 29 tablas de negocio conservaron su huella; las dos tablas administrativas restantes tienen los cambios de permisos y marcador previstos. Las tres vistas se consultaron correctamente. El respaldo protegido está en:

`C:\xampp\private_backups\continuidad_20261102_e77a1037557845e4abe1fc3c1d105547`

Incluye `base_antes.sql`, SHA-256 y verificaciones anterior/posterior. No contiene un respaldo de las evidencias; la migración no modifica esos archivos. Para una recuperación integral deben conservarse también las evidencias del sistema.

## Reversión y recuperación

Los cambios de estructura son aditivos y las columnas nuevas aceptan NULL. La reversión menos invasiva es recuperar la versión previa del código conservando las columnas adicionales, después de revisar la compatibilidad con los registros generados durante la actualización. No ejecutar `git reset` ni restaurar todo el espacio de trabajo: existen eliminaciones y cambios ajenos a esta tarea.

Para restaurar exactamente el esquema y los valores anteriores, usar el SQL de respaldo **primero en una base distinta**, comprobarlo y planificar el cambio de base. Si ya hay actividad posterior, respaldarla y conciliarla antes: restaurar el SQL anterior sobre la base actual perdería esa actividad. No se proporciona ni ejecuta un borrado automático de las columnas nuevas porque eliminaría autoría, episodios, reservas y motivos registrados después de migrar. El DDL de MariaDB no tiene rollback transaccional integral.

## Ensayo aislado

```powershell
& C:\xampp\php\php.exe tests\fases.php
```

La suite crea una base `psicologia_test_fases_*`, simula la estructura anterior con datos ficticios, aplica la migración, verifica conservación e idempotencia, ejercita los flujos nuevos y elimina únicamente su base de prueba.
