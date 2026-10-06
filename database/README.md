# Esquema e instalación

Versión: `002_trazabilidad`, 5 de octubre de 2026. PHP 8.2 con mysqli/mysqlnd y mbstring; validado con MariaDB 10.4.32.

La migración 002 agrega `historias_clinicas.id_cita` e `informes.id_seguimiento`, ambos opcionales y con clave externa. La columna generada `citas.turno_reservado` y su índice único reservan fecha/hora para citas no canceladas; se retira el índice antiguo por profesional, que impedía reutilizar citas canceladas. La agenda sigue siendo única y compartida. Si existen horarios vigentes duplicados, el plan se detiene antes de aplicar cambios y exige resolverlos: nunca borra ni reprograma datos por su cuenta.

El ejecutor aplica los planes de `database/migrations/` en orden y comprueba su idempotencia. No rellena relaciones históricas. Reglas de uso y permisos en [TRAZABILIDAD.md](../TRAZABILIDAD.md).

La versión 002 quedó aplicada en la base local, sin operaciones pendientes. Respaldo previo: `%LOCALAPPDATA%\Psicologia\respaldos\2026-10-05-trazabilidad-002-9819439a\`. `validacion.txt` y `huellas-antes.json` certifican que los valores originales de las 18 tablas de datos se conservaron; solo se agregaron las columnas, restricciones y la versión de migración.

`config/login.php` concentra conexión, URL y zona horaria (`America/La_Paz`). Tanto login como módulos usan `login_bd()`. Abrir una página **no crea bases, tablas, columnas ni registros de catálogo**.

## Instalación vacía

Desde la raíz del proyecto, con MySQL iniciado:

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_nueva --aplicar --instalar
```

El comando crea la base si falta y exige que esté vacía antes de importar `database/psicologia_db.sql`. El SQL es autocontenido, sin `USE`, `DROP` ni datos personales: incluye todas las tablas y vistas, cuatro roles activos, seis cursos y sus paralelos A–D, claves externas, numeración de informes y versión de esquema. También puede importarse directamente en una base vacía seleccionada en phpMyAdmin.

La instalación no crea cuentas ni contraseñas predeterminadas. Aprovisionar el administrador con un hash generado por `password_hash`, luego configurar `base_datos` en `config/login.php`. `herramientas/establecer_clave.php` permite cambiar la contraseña de una cuenta ya existente.

## Actualización de la base existente

Respaldar antes de ejecutar DDL; MariaDB confirma `ALTER TABLE` aunque la operación se haya iniciado dentro de una transacción. Mantener la aplicación sin escrituras durante el respaldo y la actualización.

```powershell
# Solo lectura: enumera operaciones pendientes y verifica compatibilidad.
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar

# Aplicación explícita, exclusivamente desde CLI.
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
```

El origen admitido es la estructura de la base activa revisada el 02-10-2026: IDs unsigned, cursos/paralelos relacionados y modelo de informes con `id_usuario`/`titulo`. El antiguo SQL exportado y las tablas incompletas creadas por la conexión anterior **no se convierten automáticamente**: el preanálisis los rechaza antes de aplicar cambios. Las instalaciones nuevas deben usar el SQL actual.

La migración agrega `seguimientos.recomendaciones`, `historias_clinicas.evolucion_caso`, unicidad de `informes.numero_ficha`, las cuatro relaciones de informes que faltaban y `informes_secuencia`. La secuencia empieza en el mayor sufijo `INF-...` existente; su fila se bloquea dentro de la transacción de guardado. Se registra la versión en `esquema_migraciones`. Repetir el comando verifica la estructura y no duplica los cambios. Una interrupción puede dejar DDL parcial: corregir su causa y repetir el comando; no se promete rollback del DDL.

Antes de escribir, se rechazan fichas vacías/duplicadas, relaciones huérfanas y estados de origen incompatibles. No se reinterpreta `acuerdos` como recomendaciones ni se sustituyen autores o cursos de registros existentes. La persistencia de `evolucion_caso` en historias se completó en el punto 3: admite valores 1–5 o NULL cuando no se ha evaluado.

## Contratos de los módulos corregidos

- Historias clínicas: alta y edición transaccionales, autor original conservado y editor registrado en `auditoria`. La evolución, los campos clínicos, opciones y familiares se recuperan en edición y detalle. La edición mantiene el estudiante y la derivación original; los campos omitidos se conservan. Las referencias de la derivación se consultan desde su registro y la ficha estudiantil no sobrescribe los datos clínicos al editar. No se requiere una migración adicional para el punto 3.
- Informes individuales: título de hasta 180 caracteres, autor de la sesión en `id_usuario` y `elaborado_por`, `tipo=Individual`, historia opcional, estados `Borrador`/`Finalizado`. La edición conserva autor y relaciones, valida existencia y comparte las validaciones con el alta. El listado, detalle, filtros y estadísticas usan los mismos estados y el autor canónico.
- Seguimientos: se conservan `descripcion`, `acuerdos` y `proxima_sesion`; `recomendaciones` es un campo independiente, inicialmente NULL para registros previos.
- Estudiantes: alta manual e importación comparten límites, fechas, género, turno, estados `Activo`/`Retirado` y resolución de catálogos activos. El grado visible no se interpreta como ID; una combinación inexistente o ambigua se rechaza. Se guardan texto e IDs correspondientes, además de sexo coherente con el género del formulario. La edición académica antigua sigue pendiente del punto 4.

## CSV de estudiantes

Archivo UTF-8 (BOM opcional), separado por `;`, máximo 5 MB. Encabezados requeridos:

```csv
codigo;ci;nombres;apellidos;fecha_nacimiento;genero;curso;paralelo;turno;estado
EJEMPLO001;EJEMPLOCI001;Ana;Ejemplo;2011-05-12;Femenino;1;A;Mañana;Activo
```

Opcionales: `padre`, `madre`, `tutor`, `telefono`, `direccion`. Curso: grado 1–6 o nombre exacto del catálogo compatible con el campo de curso. Paralelo: nombre activo perteneciente a ese curso. Género: `Masculino`/`Femenino`; turno: `Mañana`/`Tarde`; estado: `Activo`/`Retirado`. Fechas reales no futuras, en `YYYY-MM-DD`.

**Política parcial:** cada fila válida se guarda; las filas inválidas o duplicadas se rechazan y el proceso continúa. El resumen indica cantidades y los primeros cinco errores con número de fila. No se actualizan registros existentes. Un error de encabezados impide comenzar; una pérdida de conexión interrumpe el procesamiento y conserva el resumen de lo procesado.

## Verificación

```powershell
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php
& C:\xampp\php\php.exe tests\seguridad.php
```

La primera suite crea bases con prefijo `psicologia_test_` y nombres aleatorios. Compara instalación limpia con actualización desde una copia de **solo la estructura** local, usando datos sintéticos; verifica conservación de datos previos, importación y operaciones HTTP reales en una copia temporal de la aplicación. También comprueba conflictos, rollback y cuatro creaciones concurrentes de informes. Borra únicamente sus bases y archivos temporales al terminar. Requiere permisos de creación/eliminación de bases; nunca ejecuta escrituras de prueba en `psicologia_db`.

La migración local se aplicó después de esas pruebas, con respaldo fuera del DocumentRoot en `%LOCALAPPDATA%\Psicologia\respaldos\2026-10-02-esquema-001-d415380b\`. `validacion.txt` y `huellas-antes.json` acompañan el respaldo: los valores de todas las columnas originales en las 17 tablas existentes se conservaron.
