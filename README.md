# Sistema de seguimiento psicológico estudiantil

Aplicación PHP para gestionar estudiantes, docentes, derivaciones, citas, historias clínicas, seguimientos e informes de la U.E. Cañada Pailita «B».

## Requisitos

- PHP 8.2 con `mysqli`, `mysqlnd` y `mbstring`; las pruebas de formularios también usan `dom`.
- MariaDB; el entorno local utiliza XAMPP y MariaDB 10.4.
- Apache para abrir la aplicación. Las pruebas HTTP arrancan su propio servidor PHP local.

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
| `derivaciones/`, `citas/`, `historias_clinicas/`, `seguimientos/`, `informes/` | Atención y documentación psicológica |
| `estadisticas/` | Consulta de sesiones e historias por estudiante, curso y período |
| `asset/` | Estilos y JavaScript |
| `database/` | SQL de instalación, migraciones normalizadas e históricas |
| `herramientas/` | Administración desde terminal |
| `tests/` | Pruebas aisladas y documentación de su cobertura |

`estudiantes/cambiar_estado.php` centraliza retirar/reactivar estudiantes y su última inscripción no finalizada. `derivaciones/listar.php` aplica el filtro de propiedad del docente; `mis_derivaciones.php` conserva la ruta anterior utilizando ese mismo listado.

Los archivos específicos viven dentro de su módulo: `datos.php` reúne la validación y persistencia de estudiantes, citas, historias clínicas, derivaciones e informes; `estudiantes/csv.php` procesa la importación. Los formularios compartidos de alta y edición están en `citas/formulario.php` e `historias_clinicas/formulario.php`. Estos archivos internos rechazan el acceso directo por HTTP; las páginas del módulo los incluyen cuando corresponde.

Los roles y métodos HTTP se declaran en [config/permisos.php](config/permisos.php). Las escrituras requieren sesión, permiso y token CSRF.

Al registrar un docente, seleccionar una cuenta activa con rol Docente completa nombres, apellidos, correo y teléfono disponible. Los campos se pueden corregir antes de guardar; comparten la persona de la cuenta. Las casillas permiten asignar varias materias y recuperarlas al editar. El catálogo incluye Física, Química, Tecnología y Religión; para una base existente se agregan con la migración normalizada de materias.

## Panel principal

Disponible para administrador y psicóloga. Sus tarjetas muestran estudiantes activos con historia en seguimiento, estudiantes activos con derivaciones abiertas de prioridad alta, citas pendientes o reprogramadas de hoy a seis días después y sesiones acumuladas hasta hoy, con el subtotal de los últimos 30 días (hoy y los 29 anteriores).

El seguimiento prioritario muestra hasta cinco estudiantes activos con derivaciones pendientes o en seguimiento. Se elige una derivación por estudiante: la de mayor prioridad y, en caso de empate, la más antigua. La tabla ordena primero por prioridad y después por última sesión, situando al inicio de cada prioridad a quienes no tienen sesiones. Usa la última inscripción para el curso y enlaza la derivación y la historia disponible. La prioridad procede de la derivación; no representa una evaluación clínica de riesgo.

La agenda muestra todas las citas del día salvo las canceladas, ordenadas por hora, con su estado y profesional. No se inventan horas de finalización ni porcentajes de evolución. Los errores de consulta se muestran como tales, sin sustituirlos por ceros. Los accesos principales respetan los permisos del usuario.

## Estadísticas de seguimiento

Disponible en el menú **Estadísticas** para administrador y psicóloga. Cada seguimiento guardado cuenta como una sesión; las citas no se suman al conteo. La pantalla muestra sesiones por mes, estado de las historias y detalle por estudiante, con enlaces a la historia y al último seguimiento.

Los filtros de fechas admiten hasta 366 días y comienzan, por defecto, en enero del año actual. Se incluyen historias abiertas hasta la fecha final, aunque no tengan sesiones en el período. El curso corresponde a la última inscripción según gestión, fecha e identificador; el estado de la historia es el actual. Se conservan en la consulta estudiantes retirados o sin inscripción.

La última sesión y el acumulado se calculan hasta la fecha final seleccionada. La próxima fecha es la prevista en ese último seguimiento, no una confirmación de cita. El número de sesiones no determina una mejoría clínica: las observaciones y acuerdos se consultan en la historia. Sin registros se muestran mensajes de ausencia de datos; los errores de consulta se presentan por separado.

## Verificación

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php --navegador
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\estadisticas.php --navegador
```

Cobertura, resultados y requisitos en [tests/README.md](tests/README.md). Las seis suites usan el esquema normalizado; `--navegador` requiere Chrome instalado. Para una base existente, aplicar también la migración del tutor de curso según [database/README.md](database/README.md).
