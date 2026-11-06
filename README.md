# Sistema de seguimiento psicológico estudiantil

Aplicación PHP para gestionar estudiantes, docentes, derivaciones, citas, historias clínicas, seguimientos e informes de la U.E. Cañada Pailita «B».

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

Para habilitar esta configuración en una base existente, aplicar la migración normalizada:

```powershell
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --aplicar
```

Solo el rol Administrador puede gestionar roles y permisos, y ese rol está protegido contra cambios. Quien recibe permiso para crear cuentas solo puede asignarles su mismo rol; el administrador puede asignar cualquier rol activo. Una cuenta con rol Docente debe vincularse a un perfil en **Docentes** antes de iniciar sesión.

Al registrar un docente, seleccionar una cuenta activa con rol Docente completa nombres, apellidos, correo y teléfono disponible. Los campos se pueden corregir antes de guardar; comparten la persona de la cuenta. Las casillas permiten asignar varias materias y recuperarlas al editar. El catálogo incluye Física, Química, Tecnología y Religión; para una base existente se agregan con la migración normalizada de materias.

Las contraseñas se guardan como hash y no se pueden recuperar para mostrarlas. El administrador puede reemplazarlas desde la edición del usuario. Las nuevas contraseñas requieren al menos 8 caracteres y se aceptan hasta 72 bytes; las cuentas existentes conservan su clave hasta que se cambie.

## Panel principal

Disponible para los roles que tengan permiso de ver el módulo Dashboard, excepto Docente y Director. Sus tarjetas muestran estudiantes activos con historia en seguimiento, estudiantes activos con derivaciones abiertas de prioridad alta, citas pendientes o reprogramadas de hoy a seis días después y sesiones acumuladas hasta hoy, con el subtotal de los últimos 30 días (hoy y los 29 anteriores).

El seguimiento prioritario muestra hasta cinco estudiantes activos con derivaciones pendientes o en seguimiento. Se elige una derivación por estudiante: la de mayor prioridad y, en caso de empate, la más antigua. La tabla ordena primero por prioridad y después por última sesión, situando al inicio de cada prioridad a quienes no tienen sesiones. Usa la última inscripción para el curso y enlaza la derivación y la historia disponible. La prioridad procede de la derivación; no representa una evaluación clínica de riesgo.

La agenda muestra todas las citas del día salvo las canceladas, ordenadas por hora, con su estado y profesional. No se inventan horas de finalización ni porcentajes de evolución. Los errores de consulta se muestran como tales, sin sustituirlos por ceros. Los accesos principales respetan los permisos del usuario.

## Estadísticas de seguimiento

Disponible en el menú **Estadísticas** para los roles con permiso de ver ese módulo. Cada seguimiento guardado cuenta como una sesión; las citas no se suman al conteo. La pantalla muestra sesiones por mes, estado de las historias y detalle por estudiante, con enlaces a la historia y al último seguimiento.

Los filtros de fechas admiten hasta 366 días y comienzan, por defecto, en enero del año actual. Se incluyen historias abiertas hasta la fecha final, aunque no tengan sesiones en el período. El curso corresponde a la última inscripción según gestión, fecha e identificador; el estado de la historia es el actual. Se conservan en la consulta estudiantes retirados o sin inscripción.

El resumen separa las sesiones del período de las sesiones acumuladas hasta la fecha final. Con todos los cursos y estados seleccionados, el acumulado debe coincidir con `SELECT COUNT(*) FROM seguimientos WHERE fecha <= 'AAAA-MM-DD'` usando la misma fecha final. El panel principal muestra ese acumulado hasta hoy; su cifra puede diferir del conteo del período.

La última sesión y el acumulado se calculan hasta la fecha final seleccionada. La próxima fecha es la prevista en ese último seguimiento, no una confirmación de cita. El número de sesiones no determina una mejoría clínica: las observaciones y acuerdos se consultan en la historia. Sin registros se muestran mensajes de ausencia de datos; los errores de consulta se presentan por separado.

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
