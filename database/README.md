# Base de datos e instalación

## Esquema actual

El archivo [psicologia_db.sql](psicologia_db.sql) contiene el esquema normalizado que usa la aplicación:

- `personas` comparte los datos de usuarios y docentes.
- `secciones` e `inscripciones` relacionan curso, paralelo, turno y gestión de los estudiantes.
- `historias_clinicas` usa `id_psicologa`, `id_derivacion_origen` e `id_cita_origen`.
- `informes` guarda la cabecera y `informe_individual` el detalle y las relaciones del informe.
- Las opciones clínicas, materias y categorías de derivación se guardan en catálogos relacionados.

El SQL es una exportación que incluye catálogos, secciones para la gestión 2026 y registros iniciales de personas/usuarios. No es una plantilla sin datos. Sus vistas declaran `DEFINER=root@localhost`; al instalar con otra cuenta hay que adaptar ese definidor al servidor de destino.

## Instalación en una base nueva

Con MySQL iniciado, desde la raíz del proyecto:

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_nueva --aplicar --instalar
```

El comando crea la base si falta y exige que esté vacía antes de importar el SQL. Después, configurar `base_datos` en [config/login.php](../config/login.php), o `DB_NAME` en el entorno del proceso PHP, con el nombre elegido.

Para establecer la contraseña de una cuenta importada:

```powershell
& C:\xampp\php\php.exe herramientas\establecer_clave.php nombre_usuario
```

La herramienta usa la base configurada, solicita la contraseña por terminal y guarda su hash. No crea cuentas.

## Comprobación y migraciones históricas

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
```

Si detecta las tablas y columnas identificativas del esquema normalizado, el ejecutor termina indicando que omite las migraciones 001/002. Esta detección no comprueba exhaustivamente todas las relaciones ni todos los datos.

Los archivos de [migrations/](migrations/) corresponden al esquema anterior. Se conservan para las bases antiguas y las pruebas históricas. **No convierten el esquema anterior al normalizado.** Respaldar una base existente antes de aplicar cualquier migración; las operaciones DDL de MariaDB pueden confirmar cambios aunque estén dentro de una transacción.

Abrir una página de la aplicación no instala ni modifica el esquema. La conexión está centralizada en `login_bd()`.

## CSV de estudiantes

Archivo UTF-8 (BOM opcional), separado por `;`, máximo 5 MB:

```csv
codigo;ci;nombres;apellidos;fecha_nacimiento;genero;curso;paralelo;turno;estado
EJEMPLO001;EJEMPLOCI001;Ana;Ejemplo;2011-05-12;Femenino;1;A;Mañana;Activo
```

Columnas opcionales: `padre`, `madre`, `tutor`, `telefono`, `direccion`. Curso, paralelo y turno deben identificar una sección activa de la gestión actual; la institución y sus catálogos también deben estar activos. Género: `Masculino`/`Femenino`; turno: `Mañana`/`Tarde`; estado: `Activo`/`Retirado`. La fecha debe existir y no ser futura.

Cada fila válida se guarda con su inscripción y responsables dentro de una transacción. Las filas inválidas o duplicadas se rechazan y el procesamiento continúa; no se actualizan estudiantes existentes. Un encabezado inválido impide comenzar. El resumen muestra las cantidades y los primeros cinco errores por fila.

## Pruebas

Consultar [tests/README.md](../tests/README.md) para las pruebas vigentes y las pendientes de adaptación. Las pruebas de acceso y edición de informes utilizan datos sintéticos en tablas `TEMPORARY`; no escriben en los registros reales.
