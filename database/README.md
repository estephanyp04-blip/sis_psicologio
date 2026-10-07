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

## Comprobación y actualización

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
```

El comando muestra las operaciones pendientes sin aplicarlas. Si detecta el esquema normalizado, usa únicamente [migrations_normalizadas/](migrations_normalizadas/). La migración `001_tutor_historia.php` agrega el campo opcional `historias_clinicas.tutor_curso`; conserva los registros existentes y no vuelve a agregarlo si ya existe. El SQL de instalación ya incluye este campo.

Después de respaldar una base existente, aplicar las operaciones pendientes:

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
```

La detección del esquema no comprueba exhaustivamente todas las relaciones ni todos los datos. Las operaciones DDL de MariaDB pueden confirmar cambios aunque estén dentro de una transacción; guardar el respaldo fuera de la carpeta pública de Apache.

Los archivos de [migrations/](migrations/) corresponden al esquema anterior y solo se ejecutan en esa rama del migrador. **No convierten el esquema anterior al normalizado.**

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

Consultar [tests/README.md](../tests/README.md) para las pruebas vigentes. Las pruebas de acceso y edición de informes usan tablas `TEMPORARY`; las de migración, importación, historias y flujo completo crean bases con nombres aleatorios y datos sintéticos. Ninguna escribe datos de prueba en los registros reales.
