# Sistema de seguimiento psicológico estudiantil

Aplicación PHP para gestionar estudiantes, docentes, derivaciones, citas, historias clínicas, seguimientos e informes de la U.E. Cañada Pailita «B».

Actualización del 19/11/2026: el seguimiento se consulta en **Estadísticas y análisis → Seguimiento psicológico individual**, comparando historias y sus versiones conservadas. El registro independiente está retirado y su archivo permanece en lectura. [Informe técnico, definiciones y propuesta académica](documentacion/SEGUIMIENTO_PSICOLOGICO_2026-11-19.md).

Revisión del 14/11/2026: [correcciones, matriz y pruebas](seguridad/08_REVISION_TECNICA_2026-11-14.md), [configuración/migración 016 y recuperación](seguridad/09_OPERACION_RECUPERACION.md), [roles y privacidad](seguridad/10_PRIVACIDAD_ACCESOS.md). El contenido clínico queda restringido al rol Psicóloga y sus permisos; Administrador y Director ya no reciben acceso clínico general. Consultar la nueva matriz antes de reutilizar políticas o resultados anteriores.

**Estado local al 19/11/2026:** se aplicó 016 tras crear y verificar la integridad de una copia cifrada. El comprobador devolvió cero pendientes y la consulta de acceso quedó operativa. La redefinición del seguimiento reutiliza ese esquema, sin nuevas operaciones DDL. No reinstalar el SQL sobre la base existente.

Las correcciones por fases, resultados y decisiones pendientes están en [CORRECCIONES_FASES_2026-11-02.md](CORRECCIONES_FASES_2026-11-02.md). Incluyen calendario, filtros, informes mensuales, recursos locales, baja del perfil docente y continuidad por episodio. Para actualizar una base existente, consultar [la migración 013](database/CONTINUIDAD_013.md).

El calendario está integrado en **Panel principal → Agenda y calendario**. Permite consultar meses y días, programar y gestionar estados desde la misma sección, con adaptación de 320 a 1440 px. Sus consultas se reutilizan desde `citas/datos.php` y las acciones de estado se centralizan en `citas/cancelar.php`; se retiraron las pantallas/acciones separadas de calendario y cambio de estado de citas.

## Requisitos

- PHP 8.2 con `mysqli`, `mysqlnd` y `mbstring`; las pruebas de formularios también usan `dom`.
- MariaDB; el entorno local utiliza XAMPP y MariaDB 10.4.
- Apache para abrir la aplicación. Las pruebas HTTP arrancan su propio servidor PHP local.

Para guardar versiones y actualizar el esquema de forma segura, consulta las [correcciones por fases](CORRECCIONES_FASES_2026-11-02.md) y el [registro de cambios](CHANGELOG.md).

## Uso local

1. Iniciar Apache y MySQL desde XAMPP.
2. Mantener el proyecto en `C:\xampp\htdocs\proyecto_vercionII`.
3. Configurar la conexión en [config/login.php](config/login.php).
4. Si se necesita una base nueva, seguir [database/README.md](database/README.md). No reinstalar sobre una base con datos.
5. Abrir `http://localhost/proyecto_vercionII/login.php`.

Para establecer la contraseña de una cuenta existente, desde la raíz del proyecto:

```powershell
& C:\xampp\php\php.exe herramientas\establecer_clave.php nombre_usuario
```

## Configuración

La base inicial del sistema de gestión de seguridad está en [seguridad/README.md](seguridad/README.md): alcance, política, activos, riesgos, tratamiento, procedimientos y registros. Está preparada para revisión y adopción institucional; no representa una certificación ISO. Para revisar configuración y conectividad sin modificar datos, ejecutar `C:\xampp\php\php.exe herramientas\diagnostico_sgsi.php`. Los documentos de esa carpeta se consultan localmente; Apache bloquea su acceso por HTTP.

[config/login.php](config/login.php) concentra los valores locales y admite estas variables del entorno del proceso PHP:

| Variable | Valor local predeterminado |
|---|---|
| `APP_BASE_URL` | `/proyecto_vercionII` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `psicologia_db` |
| `DB_USER` | `root` |
| `DB_PASS` | Vacío |
| `COOKIE_SEGURA` | `false` |

La zona horaria es `America/La_Paz`. La aplicación no carga archivos `.env` automáticamente. Si se usan variables de entorno, deben estar disponibles para Apache/PHP y, por separado, para los comandos de terminal.

## Organización

| Carpeta | Responsabilidad |
|---|---|
| `config/` | Configuración, conexión y matriz de permisos |
| `includes/` | Autenticación, cabecera, menú, pie y trazabilidad compartida entre módulos |
| `estudiantes/`, `docentes/`, `usuarios/` | Gestión de personas y cuentas |
| `roles/` | Creación de roles y configuración de permisos por módulo |
| `derivaciones/`, `citas/`, `historias_clinicas/`, `seguimientos/`, `informes/` | Atención y documentación psicológica |
| `estadisticas/` | Consulta de sesiones e historias por estudiante, curso y período |
| `asset/` | Estilos y JavaScript |
| `database/` | SQL de instalación, migraciones normalizadas e históricas |
| `herramientas/` | Administración desde terminal |
| `tests/` | Pruebas aisladas y documentación de su cobertura |

`estudiantes/cambiar_estado.php` centraliza retirar/reactivar estudiantes y su última inscripción no finalizada. `derivaciones/listar.php` aplica el filtro de propiedad del docente; `mis_derivaciones.php` conserva la ruta anterior utilizando ese mismo listado.

Los archivos específicos viven dentro de su módulo: `datos.php` reúne la validación y persistencia de estudiantes, citas, historias clínicas, derivaciones e informes; `estudiantes/csv.php` procesa la importación. Los formularios compartidos de alta y edición están en `citas/formulario.php` e `historias_clinicas/formulario.php`. Estos archivos internos rechazan el acceso directo por HTTP; las páginas del módulo los incluyen cuando corresponde.

Las rutas se declaran en [config/permisos.php](config/permisos.php), y los permisos efectivos se consultan en las tablas `modulos` y `permisos`. El administrador puede crear roles y marcar para cada módulo si el rol puede ver, crear, editar, cambiar estados o eliminar. En Dashboard, Estadísticas y Estudiantes, Docente y Director permanecen bloqueados aunque la tabla de permisos les conceda acceso; los demás roles siguen la configuración de la base. Cambiar estados es un permiso separado de editar; solo se ofrece en Usuarios, Docentes, Estudiantes, Derivaciones y Citas. El menú también se ajusta a esos permisos; las rutas comprueban el permiso en el servidor y las escrituras requieren token CSRF.

Además, [la política de confidencialidad](includes/politica_acceso.php) limita Dashboard, Estadísticas, Citas, Historias y Seguimientos e Informes al rol Psicóloga. Derivaciones admite Psicóloga según permisos y Docente solo en sus registros; no admite Administrador, Director ni roles personalizados. Las casillas del editor no pueden superar estos límites. El acceso profesional por caso y las entregas de informes requieren decisiones institucionales adicionales. La impresión del navegador no equivale a una exportación autorizada y auditada.

Para habilitar esta configuración en una base existente, aplicar la migración normalizada:

Para las mejoras de seguridad del 14/11 usar **solo** `herramientas/migrar_seguridad.php --comprobar` y, después de respaldar, `--aplicar`; no es necesario ejecutar otras migraciones históricas. Consultar el procedimiento enlazado al inicio. El siguiente comando general es para actualizaciones del esquema completo previamente revisadas:

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
```

Solo el rol Administrador puede gestionar roles y permisos, y ese rol está protegido contra cambios. Quien recibe permiso para crear cuentas solo puede asignarles su mismo rol; el administrador puede asignar cualquier rol activo. Una cuenta con rol Docente debe vincularse a un perfil en **Docentes** antes de iniciar sesión.

Al registrar un docente, seleccionar una cuenta activa con rol Docente completa nombres, apellidos, correo y teléfono disponible. Los campos se pueden corregir antes de guardar; comparten la persona de la cuenta. Las casillas permiten asignar varias materias y recuperarlas al editar. El catálogo incluye Física, Química, Tecnología y Religión; para una base existente se agregan con la migración normalizada de materias.

Las contraseñas se guardan como hash y no se pueden recuperar para mostrarlas. El administrador puede reemplazarlas desde la edición del usuario. Las nuevas contraseñas requieren al menos 8 caracteres y se aceptan hasta 72 bytes; las cuentas existentes conservan su clave hasta que se cambie.

## Panel principal

Disponible para Psicóloga con permiso del Dashboard. Muestra estudiantes activos con historias en seguimiento, prioridad de derivaciones, citas pendientes/reprogramadas y atenciones documentadas hasta hoy y en los últimos 30 días. Cada historia cuenta una vez; ediciones y seguimientos anteriores no se suman.

Se elige una derivación por estudiante, priorizando las de mayor prioridad y fecha más antigua. La tabla ordena por prioridad y fecha de última historia. El curso usa la inscripción actual. La prioridad procede de la derivación y no es una valoración clínica automática.

La agenda mantiene el calendario y sus acciones explícitas sobre citas. Los errores no se sustituyen por ceros y los enlaces respetan los permisos.

## Estadísticas y análisis

**Estadísticas generales** presenta historias, estudiantes distintos, frecuencias y porcentajes, distribuciones por mes/curso/paralelo y apartados clínicos. Solo accede Psicóloga con permiso del módulo. No muestra notas, identidades estudiantiles ni notificaciones nominales.

**Seguimiento psicológico individual** requiere también lectura de historias. Permite elegir estudiante y período, consultar cronología y comparar dos historias o versiones conservadas. Compara todos los apartados existentes, familiares y selecciones. Las nuevas marcas y las que dejan de constar son cambios documentales; no acreditan aparición, desaparición o mejoría clínica.

Las fechas delimitan inclusivamente la fecha de atención. Sin fecha inicial se incluye todo lo disponible hasta hoy. Curso y paralelo usan la última inscripción por gestión, fecha e identificador. Se incluyen estudiantes retirados o sin inscripción. Las estadísticas generales usan el contenido actual de las historias y no reconstruyen la base en una fecha pasada.

El archivo `seguimientos` conserva técnicas, acuerdos y relaciones anteriores. El GET antiguo de alta redirige a la historia y el POST responde 410 después de validar acceso y CSRF. Los mensuales nuevos guardan la definición 2: atención documentada = historia clínica. Los informes anteriores mantienen su definición y totales.

## Verificación

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\fases.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php --navegador
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\estadisticas.php --navegador
```

Cobertura, resultados y requisitos en [tests/README.md](tests/README.md). Las suites usan el esquema normalizado; `--navegador` requiere Chrome instalado. Para una base existente, aplicar también la migración del tutor de curso según [database/README.md](database/README.md).
