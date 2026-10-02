# Login del sistema de psicología

Paquete para integrar en `C:\xampp\htdocs\proyecto_vercionII` (PHP 8.0 o superior con mysqli/mysqlnd). Creado con los campos de los PHP adjuntos y los roles de la captura más reciente. No se ha conectado a tu XAMPP ni modificado tu sistema instalado.

## 1. Copiar los archivos

Extrae este ZIP. Copia el CONTENIDO de la carpeta `login_psicologia` dentro de `proyecto_vercionII`, conservando las subcarpetas. `login.php` debe quedar junto a tu `index.php`, no dentro de otra carpeta `login_psicologia`.

Revisa `config/login.php`: base_url, base_datos, usuario_bd, clave_bd y puerto deben corresponder a tu instalación. Los valores iniciales coinciden con el archivo de conexión adjunto. Si tu carpeta se llama `proyecto`, cambia base_url a `/proyecto`. Si está en la raíz de un dominio, usa una cadena vacía. En HTTPS público activa `cookie_segura`; en localhost por HTTP mantenla en false.

El archivo original `config/conexion.php` se conserva. El login usa `config/conexion_login.php`, que solo conecta, porque el original ejecuta cambios de esquema en cada petición. Un error de clave externa en el original todavía debe corregirse en los módulos que lo cargan.

## 2. Usuarios, roles y contraseñas

No importes el antiguo `db.txt`: tiene `ENaUM` y nombres de base de datos distintos. Tampoco vuelvas a insertar los roles. La captura actual confirma:

| id_rol | Rol | Destino inicial configurable |
|---|---|---|
| 1 | Administrador | index.php |
| 2 | Psicóloga | index.php |
| 3 | Docente | derivaciones/listar.php |
| 4 | Director | informes/listar.php |

El login consulta `roles.estado`, `usuarios.estado` y `usuarios.password`. Ambos estados deben ser `Activo`. Las rutas de destino se ajustan en `config/login.php` si tu listado se llama `index.php` en lugar de `listar.php`.

La contraseña se verifica con `password_verify`; no se aceptan contraseñas guardadas como texto, MD5 ni SHA1. Si tus usuarios ya tienen un hash válido de `password_hash`, usa su contraseña habitual. Si no la recuerdas o están guardadas en otro formato, abre PowerShell en la carpeta del proyecto y ejecuta, para la cuenta existente `psicologa`:

```powershell
cd C:\xampp\htdocs\proyecto_vercionII
& C:\xampp\php\php.exe herramientas\establecer_clave.php psicologa
```

Escribe una contraseña nueva de al menos 12 caracteres (máximo 72 bytes) y repítela. La entrada será visible en esa terminal local, pero la herramienta no imprime el hash ni guarda la contraseña en texto. Solo cambia la contraseña de la cuenta indicada; no crea usuarios, no activa cuentas y no cambia roles. Para un docente, sustituye `psicologa` por su nombre real en `usuarios.usuario`. Esta herramienta responde 404 desde el navegador.

## 3. Vincular al docente

El docente necesita una sola fila en `docentes` cuyo `id_usuario` coincida con su cuenta. El login consulta esa relación y guarda el `id_docente` real. No usa el id del usuario como si fuera id de docente.

Consulta en phpMyAdmin, con tu base seleccionada:

```sql
SELECT u.id_usuario, u.usuario, u.nombre, u.apellido, u.estado,
       u.id_rol, d.id_docente, d.nombres, d.apellidos
FROM usuarios u
LEFT JOIN docentes d ON d.id_usuario = u.id_usuario
WHERE u.id_rol = 3;
```

Si falta el vínculo, edita el registro correcto de `docentes` en phpMyAdmin y pon en `id_usuario` el identificador de su cuenta. Verifica nombres antes de guardar. No crees un segundo docente ni asignes por coincidencia aproximada de nombres. Si aparece un error de columna inexistente, la tabla actual no coincide con la conexión adjunta y debe revisarse antes de vincular.

La psicóloga necesita su cuenta activa con `id_rol=2`; no necesita una tabla adicional llamada `psicologas`.

## 4. Proteger las páginas y las acciones

Copiar el login no protege automáticamente los archivos existentes. Añade los controles ANTES de cualquier HTML, header.php, sidebar.php y navbar.php. Deben estar tanto en formularios como en guardar.php, actualizar.php, eliminar.php, ver.php y los otros puntos de entrada, según la acción. Las rutas se basan en archivos dentro de la carpeta de cada módulo.

Para registrar una derivación (docente y administrador):

```php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_roles([1, 3]);
require_once __DIR__ . '/../config/conexion.php';
```

Para registrar, consultar o actualizar historias clínicas (psicóloga y administrador):

```php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_roles([1, 2]);
require_once __DIR__ . '/../config/conexion.php';
```

Para el panel `index.php`, ubicado en la raíz:

```php
require_once __DIR__ . '/includes/autenticacion.php';
requerir_roles([1, 2]);
```

Los destinos de docente y director van directamente a sus módulos. Si el panel actual tiene contenido clínico, no lo abras a todos los roles. Usa esta distribución por acción:

| Acción | Roles permitidos |
|---|---|
| Registrar derivación | [1, 3] |
| Listar/ver derivaciones | [1, 2, 3], filtrando las del docente |
| Editar derivación | [1, 3], verificando autoría del docente |
| Cambiar estado de derivación | [1, 2] |
| Gestionar citas | [1, 2] |
| Consultar/registrar/editar historias clínicas | [1, 2] |
| Generar/editar informes | [1, 2] |
| Consultar/imprimir informes | [1, 2, 4] |
| Gestionar usuarios y roles | [1] |

Los permisos se verifican en PHP, aunque ocultes botones. El docente solo consulta la información de estudiantes necesaria para derivar; no se le habilita su CRUD ni el acceso a historias clínicas.

En `derivaciones/guardar.php`, para un docente, usa el identificador de sesión:

```php
if ((int) $_SESSION['id_rol'] === 3) {
    $idDocente = (int) $_SESSION['id_docente'];
}
```

No reemplaces este valor con un `id_docente` enviado por el formulario. Si el administrador registra una derivación, debe seleccionar un docente existente y el servidor debe validar esa selección. Su sesión no representa a un docente.

En listados, detalle y edición de derivaciones de un docente, añade la restricción `id_docente = ?`, vinculada a `$_SESSION['id_docente']`. En detalle/edición combina `id_derivacion = ? AND id_docente = ?` en una consulta preparada; no basta con filtrar el listado. Psicóloga y administrador usan el alcance previsto por sus permisos.

En `historias_clinicas/guardar.php`, usa:

```php
$idUsuario = (int) $_SESSION['id_usuario'];
```

Vincula `$idUsuario` al parámetro `id_usuario` del INSERT existente. No lo tomes de POST, no uses un valor fijo de 2 (2 identifica el ROL, no a la persona) y no lo dejes nulo. Al editar, conserva el autor original; si se necesita registrar quién editó, utiliza un campo de auditoría distinto.

Los handlers de citas adjuntos todavía guardan `id_usuario=NULL`; deben adaptarse de la misma manera cuando quien registra es la psicóloga. Eso pertenece a la integración de citas y no se cambia automáticamente con el login.

Este paquete no contiene los archivos completos actuales de derivaciones, historias clínicas, navbar o panel. Por eso incluye puntos de integración en vez de sustituirlos por versiones antiguas. También deben conservar sus validaciones de datos y protecciones CSRF para cada operación que modifica datos.

## 5. Cerrar sesión

En `includes/navbar.php`, dentro del lugar donde irá el botón y después de haber protegido la página, añade:

```php
<?php include __DIR__ . '/boton_cerrar_sesion.php'; ?>
```

El botón usa POST y token CSRF. No uses un enlace GET a `cerrar_sesion.php`.

## 6. Probar en XAMPP

Inicia Apache y MySQL. Abre `http://localhost/proyecto_vercionII/login.php`.

1. Psicóloga activa con clave válida: entra al panel; al guardar historia, `id_usuario` debe ser el de esa cuenta.
2. Docente vinculado con clave válida: entra a derivaciones; al guardar, `id_docente` debe coincidir con su registro.
3. Contraseña incorrecta, usuario inactivo o rol inactivo: se rechaza el acceso.
4. Docente sin vínculo o con dos vínculos: se rechaza el acceso y se explica que el administrador debe revisarlo.
5. Sin sesión, abrir directamente un formulario o enviar a un guardar protegido: vuelve al login.
6. Con sesión de docente, abrir una historia clínica protegida: respuesta 403.
7. Cambiar el id de una derivación de otro docente: el módulo debe rechazarlo con su consulta filtrada.
8. Cerrar sesión y volver atrás: una nueva petición a un archivo protegido debe pedir login.
9. Cinco fallos por cuenta: bloqueo de cinco minutos, aunque cambies de sesión. Los registros se guardan fuera del proyecto, en el directorio temporal de PHP, que debe ser escribible. Para varias instancias de servidor se necesita un almacén compartido.
10. Cambiar contraseña, desactivar usuario/rol o desvincular docente: la siguiente petición protegida invalida la sesión.

La sesión vence tras 30 minutos sin actividad o a las 8 horas desde el acceso. El paquete no registra cuentas públicas ni contiene contraseñas predeterminadas.

## Validación realizada

Revisión del flujo de autenticación, correspondencia de IDs con la captura, consultas preparadas, estados activos, relación del docente, tokens, regeneración y cierre de sesión. Pruebas en tu MySQL y en los módulos actuales: pendientes de instalar e integrar en XAMPP. Consulta `VALIDACION.txt` para los controles locales ejecutados.
