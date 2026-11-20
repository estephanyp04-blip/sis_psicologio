# Base de datos e instalación

## Retiro definitivo del módulo independiente (20/11/2026)

El seguimiento psicológico se consulta en Estadísticas a partir de `historias_clinicas` y `historia_versiones`. La migración **017_retirar_seguimientos** transforma `seguimientos` en `historia_anexos`, conserva todas las filas y columnas y renombra su identificador a `cod_anexo`. La relación anterior de informes pasa a `informe_individual.cod_anexo_historico`. No convierte notas en historias, versiones ni diagnósticos. Los anexos solo se leen dentro de la historia; no tienen rutas ni formularios propios.

El ejecutor exige mantenimiento y un respaldo cifrado íntegro de esta base, creado en las últimas 24 horas. Verifica huellas de valores y relaciones antes/después e integridad de las claves foráneas. El planificador bloquea dependencias SQL adicionales en vistas, rutinas, triggers, eventos o claves foráneas. El DDL de MariaDB no es transaccional: se puede replanificar tras una interrupción y se mantiene mantenimiento hasta verificar el resultado.

Procedimiento para una base normalizada existente (rutas externas de ejemplo, carpetas previamente creadas):

```powershell
New-Item -ItemType File .mantenimiento
& C:\xampp\php\php.exe -d extension=sodium herramientas\clave_respaldo.php --destino=C:/privado/claves/respaldo.key
& C:\xampp\php\php.exe -d extension=sodium herramientas\respaldar.php --base=psicologia_db --destino=C:/privado/copias --clave=C:/privado/claves/respaldo.key
# Restaurar y validar la copia con herramientas/restaurar_prueba.php en un destino aislado.
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
& C:\xampp\php\php.exe -d extension=sodium database\migrar.php --base=psicologia_db --aplicar --respaldo=C:/privado/copias/CARPETA_GENERADA --clave=C:/privado/claves/respaldo.key
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
# Retirar .mantenimiento únicamente después de comprobar cero pendientes y conservación.
Remove-Item -LiteralPath .mantenimiento
```

No usar `--instalar` sobre datos existentes. En instalaciones nuevas, el SQL ya incluye el archivo clínico integrado y excluye el módulo y sus permisos. La migración 006 dejó de recrearlos y 013 reconoce la ausencia de la tabla retirada.

Estado local: 017 aplicada, cero filas históricas y un detalle de informe conservados, 45 claves sin huérfanos, tres vistas mantenidas y cero pendientes. Se compararon con la restauración cifrada historias, versiones, anexos, cabeceras y detalle de informes por huellas, sin exponer contenido clínico. [Informe y modelo](../documentacion/RETIRO_SEGUIMIENTOS_2026-11-20.md).
## Esquema actual

El archivo [psicologia_db.sql](psicologia_db.sql) contiene el esquema final que usa la aplicación:

- `usuarios` guarda los datos de identidad y acceso; `docentes` se vincula mediante `cod_usuario`.
- `secciones` e `historial_academico` relacionan curso, paralelo, turno y gestión de los estudiantes.
- `historias_clinicas` usa `cod_psicologa`, `cod_derivacion_origen` e `cod_cita_origen`. Un estudiante puede tener varias historias, con código, autor, estado y archivo anterior vinculado. La migración `014_historias_por_estudiante.php` sustituye el índice único del estudiante por uno no único, sin modificar registros. Los informes permiten elegir y conservar su historia de origen. La migración 015 es una marca histórica y no borra registros. Desde el 19/11/2026 las atenciones se documentan en historias y el seguimiento compara sus registros y versiones.
- Las columnas identificadoras que antes comenzaban con `id_` ahora comienzan con `cod_`.
- `informes` guarda la cabecera y `informe_individual` el detalle y las relaciones del informe.
- Las opciones clínicas, materias y categorías de derivación se guardan en catálogos relacionados.

El SQL incluye catálogos, secciones para la gestión 2026 y un usuario administrador inicial. No es una plantilla sin datos. No contiene tablas ni vistas `personas` o `inscripciones`, ni columnas `cod_persona`. Las vistas de instalación usan `SQL SECURITY INVOKER` sin un definidor root fijo. Las vistas de bases anteriores conservan sus definidores hasta una revisión específica.

La ampliación aditiva `016_auditoria_versiones.php` agrega versiones de revocación de sesiones en cuentas/roles, metadatos históricos de auditoría, versiones anteriores de historias y triggers protectores. No inventa identidades históricas para eventos antiguos. El SQL final ya la incluye. Para aplicar solo esta mejora a una base normalizada existente: `php herramientas/migrar_seguridad.php --comprobar` y, después de respaldo, `--aplicar`. El [procedimiento completo](../seguridad/09_OPERACION_RECUPERACION.md) evita ejecutar migraciones históricas destructivas e incluye recuperación.

## Instalación en una base nueva

La instalación crea la cuenta `admin` sin publicar una contraseña inicial. Después de configurar la base de datos, establece una contraseña propia desde la terminal antes del primer inicio de sesión:

```powershell
& C:\xampp\php\php.exe herramientas\establecer_clave.php admin
```

La herramienta solicita la contraseña dos veces y solo guarda su hash. No publiques ni reutilices una contraseña de instalación.

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

La migración normalizada `013_continuidad_atencion.php` conserva autoría de derivaciones, agrega el episodio y la próxima cita del seguimiento, motivo de cierre y totales/filtros del resumen mensual. El SQL de instalación incluye las columnas. Aplicación, recuperación y estado de la base local en [CONTINUIDAD_013.md](CONTINUIDAD_013.md).

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
```

El comando muestra las operaciones pendientes sin aplicarlas. Si detecta el esquema normalizado, usa únicamente [migrations_normalizadas/](migrations_normalizadas/). La migración `001_tutor_historia.php` agrega el campo opcional `historias_clinicas.tutor_curso`; conserva los registros existentes y no vuelve a agregarlo si ya existe. El SQL de instalación ya incluye este campo.

La migración `002_materias_docentes.php` incorpora Física, Química, Tecnología y Religión cuando faltan. Conserva los identificadores y estados de materias existentes, incluso si el nombre está escrito sin acento; no duplica registros al repetirse. El SQL de instalación también incluye las diez materias. Cada docente puede tener varias asignaciones en `docente_materias`.

La migración `003_opciones_historia_clinica.php` agrega las opciones de conductas de riesgo y adaptación a normas, y completa las listas de atención, dificultades socioemocionales y estrategias previas. El SQL de instalación incluye los mismos catálogos.

La migración `004_descripcion_evidencia_derivacion.php` agrega la descripción asociada a las imágenes adjuntas en una derivación.

La migración `005_categorias_derivacion_completas.php` incorpora Dinámica Familiar, Social / Emocional y Otro únicamente si no existen. Las descripciones de apoyo de las seis categorías se muestran en el formulario de registro.

La migración `007_permisos_cambio_estado.php` separa la autorización para cambiar estados de la edición general. También alinea los permisos iniciales de Dashboard y Estudiantes para Docente/Director y conserva la política de estado de los cuatro roles originales. Los roles personalizados reciben ese permiso desactivado hasta que el administrador lo habilite.

La migración `008_limites_permisos_datos_estudiantiles.php` limpia permisos previamente concedidos a Docente y Director en Dashboard, Estadísticas y Estudiantes. La aplicación también hace cumplir ese límite en sus rutas y en el formulario de permisos.

La migración `009_personas_historial_academico.php` copia la identidad a `usuarios`, vincula docentes con `docentes.cod_usuario` y traslada cada inscripción a `historial_academico`; luego elimina las tablas anteriores. La migración `010_eliminar_aliases_identidad.php` elimina las vistas y columnas de compatibilidad que quedaron en instalaciones actualizadas con versiones preliminares. El alcance actual es una sola unidad educativa.

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

Columnas opcionales: `padre`, `madre`, `tutor`, `telefono`, `direccion`. Curso, paralelo y turno deben identificar una sección activa de la gestión actual; los catalogos deben estar activos. Género: `Masculino`/`Femenino`; turno: `Mañana`/`Tarde`; estado: `Activo`/`Retirado`. La fecha debe existir y no ser futura.

Cada fila válida se guarda con su inscripción y responsables dentro de una transacción. Las filas inválidas o duplicadas se rechazan y el procesamiento continúa; no se actualizan estudiantes existentes. Un encabezado inválido impide comenzar. El resumen muestra las cantidades y los primeros cinco errores por fila.

## Pruebas

Consultar [tests/README.md](../tests/README.md) para las pruebas vigentes. Las pruebas de acceso y edición de informes usan tablas `TEMPORARY`; las de migración, importación, historias y flujo completo crean bases con nombres aleatorios y datos sintéticos. Ninguna escribe datos de prueba en los registros reales.

La migración `000_renombrar_identificadores.php` cambia las columnas `id_*` a `cod_*` en bases normalizadas existentes. Conserva los datos, las claves foráneas y las vistas; una instalación nueva ya usa los nombres `cod_*`.
