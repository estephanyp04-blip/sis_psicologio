# Configuración, migración y recuperación

Revisión técnica del 14/11/2026. Complementa [SGSI-04](04_PROCEDIMIENTOS.md); la institución debe designar titulares y suplentes en GOV-01 y BAK-01. Los comandos siguientes no publican el sistema.

## Requisitos y separación de responsabilidades

Código: PHP 8.2, mysqli/mysqlnd, mbstring, fileinfo; DOM y Chrome para pruebas. Respaldo: extensión **sodium**, `proc_open`, clientes `mysqldump` y `mysql` compatibles con el servidor. XAMPP incluye `php_sodium.dll`, pero no estaba habilitada por defecto; usar `php -d extension=sodium` o habilitarla en el `php.ini` del proceso de respaldo. No hace falta habilitarla en Apache para atender solicitudes normales.

Servidor: Apache 2.4 con `mod_rewrite` y `AllowOverride All` para las reglas del proyecto. `.user.ini` corresponde a CGI/FastCGI; mod_php requiere configurar PHP en su `php.ini`. La aplicación además desactiva visualización de errores en su arranque. Mantener logs fuera de la raíz web y sin cuerpos HTTP ni consultas con nombres o notas. No activar `display_errors` en producción.

Administración técnica: mantener PHP/MariaDB/Apache con parches vigentes, cuenta de ejecución restringida y cuenta de migraciones separada. La ejecución normal no debe tener CREATE, ALTER, DROP, TRIGGER, FILE, GRANT ni privilegios administrativos. Conceder SELECT/INSERT/UPDATE/DELETE solo por tabla cuando el módulo lo necesite; **auditoria** e **historia_versiones** necesitan INSERT y la consulta autorizada, sin UPDATE/DELETE. Las claves foráneas y triggers no protegen contra una cuenta que puede borrar tablas o quitar triggers. No se implementó almacenamiento externo inmutable.

Los archivos de código son de solo lectura para la identidad web, salvo el directorio de adjuntos y el almacenamiento de sesiones. Los adjuntos actuales permanecen en `derivaciones/` por compatibilidad y Apache bloquea sus nombres; otro servidor necesita una regla equivalente. Preferir su futura migración a almacenamiento privado, con migración de rutas y pruebas propias.

## Configuración de producción

Configurar en el entorno del proceso PHP:

| Variable | Uso |
|---|---|
| `APP_ENV=produccion` | Rechaza root/clave vacía y las solicitudes HTTP sin TLS |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión; los secretos se administran fuera del repositorio |
| `APP_BASE_URL` | Prefijo real de instalación |
| `COOKIE_SEGURA=true` | Cookie Secure; HTTPS también la activa automáticamente |
| `APP_WEB_ROOT` | Raíz pública absoluta para las herramientas de copia; en XAMPP se deduce `htdocs` |
| `DB_CLIENT_DIR` | Carpeta de clientes MariaDB/MySQL; XAMPP usa `C:/xampp/mysql/bin` |

Apache y CLI pueden tener entornos distintos. `.env` no se carga automáticamente. Si TLS termina en un proxy, configurar HTTPS en el servidor de confianza; el código no confía en `X-Forwarded-Proto` suministrado por el cliente. Producción devuelve 426 ante HTTP y envía HSTS en HTTPS. Configurar la redirección HTTP→HTTPS en el servidor usando el nombre oficial del servicio. Comprobar certificado, cookies, HSTS y cabeceras desde fuera del hosting antes de habilitarlo.

En Nginx u otro servidor, bloquear `.git`, `.vscode`, `config`, `includes`, `database`, `tests`, `herramientas`, `seguridad`, archivos SQL/Markdown/copias/secretos e imágenes `derivaciones/evidencia_*`. No suponer que `.htaccess` se interpreta allí. Desactivar listados de directorios y excluir respaldos del paquete público. Estos bloqueos deben probarse mediante HTTP y con sesión, además de comprobar las rutas de PHP.

## Migración aditiva 016

**Estado local al 19/11/2026: aplicada y verificada.** Se creó una copia real cifrada fuera de la raíz web, con clave en una carpeta separada; se verificaron sus hashes y el descifrado de los archivos. Se aplicaron exclusivamente las 16 operaciones pendientes de 016. La comparación de los datos previos fue idéntica, el comprobador quedó en cero y las consultas de acceso y auditoría funcionaron. Se retiró `.mantenimiento`. No se ensayó restauración de esa copia real; la restauración funcional está validada con datos sintéticos. Este resultado sustituye el estado pendiente y el rechazo automático registrados el 17/11/2026.

La redefinición del seguimiento del 19/11/2026 utiliza `historia_versiones` de 016 sin nuevas operaciones DDL. Los registros de `seguimientos` permanecen para lectura y trazabilidad. Consultar [el informe de redefinición](../documentacion/SEGUIMIENTO_PSICOLOGICO_2026-11-19.md).

La aplicación requiere 016 antes del siguiente uso. Se incorporan versiones de sesión para usuarios/roles, metadatos históricos de auditoría, `historia_versiones` y ocho triggers. No se reescriben expedientes ni se intenta reconstruir la identidad de eventos antiguos. Los campos históricos añadidos quedan nulos para esos eventos. Las sesiones existentes se invalidan al faltarles la versión nueva.

1. Suspender escrituras durante la ventana de mantenimiento; en local, detener Apache mientras se respalda y migra. No ejecutar el SQL de instalación sobre la base existente.
2. Obtener y custodiar una copia cifrada. El mecanismo se ensayó con datos ficticios; la recuperabilidad de cada copia real requiere su validación autorizada.
3. Revisar y aplicar **solo 016**, con la cuenta de migraciones:

```powershell
& C:\xampp\php\php.exe herramientas\migrar_seguridad.php --comprobar
& C:\xampp\php\php.exe herramientas\migrar_seguridad.php --aplicar
& C:\xampp\php\php.exe herramientas\migrar_seguridad.php --comprobar
```

Puede añadirse `--base=NOMBRE`. Esta herramienta evita ejecutar otras migraciones históricas, algunas de las cuales eliminan estructuras antiguas. Requiere el esquema normalizado previo. Obtiene el mismo bloqueo de migración que `database/migrar.php`. Las operaciones DDL no se revierten como una transacción: ante interrupción, corregir la causa y repetir; el plan detecta columnas/tablas/triggers ya creados. Si existen triggers del mismo nombre modificados externamente, su contenido requiere revisión manual; la detección no compara sus definiciones.

4. Verificar cero operaciones pendientes, inicio/cierre de sesión, acceso profesional y rechazo administrativo al contenido clínico. Volver a iniciar sesión después de cambiar el esquema. Reabrir el servicio cuando las verificaciones sean satisfactorias.

Las instalaciones nuevas usan `database/migrar.php --base=NUEVA --aplicar --instalar`, que exige base vacía. El SQL final incorpora 016 y define las vistas como `SQL SECURITY INVOKER`, sin `DEFINER=root` fijo. Las vistas de bases existentes no se convierten automáticamente; revisar sus definidores al trasladar hosting.

Para mantenimiento web acotado al proyecto, crear el archivo vacío `.mantenimiento` en la raíz y retirarlo al finalizar: el arranque compartido devuelve 503 y `Retry-After`, sin iniciar operaciones nuevas. Dejar terminar las solicitudes ya iniciadas y suspender también tareas CLI/DDL; el archivo no bloquea procesos externos. La herramienta de respaldo excluye este indicador. No detener todo XAMPP si sirve otras aplicaciones.

La reversión segura ante problemas es mantener el servicio restringido, conservar la ampliación de esquema y restaurar una versión compatible del código. No quitar triggers, columnas o versiones clínicas como una reversión automática. Una reversión de datos exige plan de recuperación aprobado.

## Gestión de claves, antes de utilizar cifrado

Se usa **libsodium secretstream XChaCha20-Poly1305**, mediante la extensión oficial de PHP, sin algoritmos propios. Cada archivo tiene cabecera aleatoria, marcos autenticados y terminador obligatorio. Se comprueban truncamiento, manipulación, clave equivocada y datos sobrantes. Los SHA-256 del manifiesto cifrado permiten cotejar archivos y contenido recuperado; un hash por sí solo no demuestra procedencia.

Modelo de custodia propuesto:

- Administración técnica ejecuta copias; un custodio designado por Dirección controla la clave y un suplente conserva una copia de recuperación en otro soporte protegido. Registrar personas, ACL y entrega en BAK-01; actualmente no hay nombramientos ni segunda custodia institucional acreditada.
- Crear una clave aleatoria de 32 bytes en una carpeta **fuera de la raíz web**, separada y sin anidamiento respecto de las copias. No guardarla en Git, `htdocs`, junto al respaldo o dentro de su archivo cifrado. No imprimirla, enviarla por correo ni incluirla en argumentos de consola; los argumentos contienen solamente su ruta.
- Limitar lectura de la clave y escritura de copias mediante ACL del sistema. `chmod(0600)` no sustituye ACL en Windows. En Windows usar una carpeta del perfil con herencia retirada, acceso para el operador y SYSTEM; comprobar permisos efectivos. El proceso que respalda necesita leer la clave: esto no protege frente al compromiso de ese proceso o del administrador del equipo.
- Probar recuperación con la copia custodiada de la clave; pérdida de ambas claves implica pérdida de recuperabilidad. Para rotar, generar otra clave, identificar externamente qué copias usa cada una y conservar las anteriores mientras existan copias que las necesiten. No reemplazar una clave existente a ciegas. Retiro y destrucción requieren aprobación y evidencia.
- Antes del uso institucional, aprobar ubicación externa, segundo soporte, responsables, RPO/RTO, custodia y recuperación de claves. Las claves de pruebas sintéticas se eliminan al terminar; no son claves institucionales.

Ejemplo de carpetas ya creadas y protegidas por el operador, ambas fuera del servidor web:

```powershell
& C:\xampp\php\php.exe -d extension=sodium herramientas\clave_respaldo.php --destino=C:\ResguardoClaves\psicologia.key
& C:\xampp\php\php.exe -d extension=sodium herramientas\respaldar.php --destino=C:\CopiasPsicologia --clave=C:\ResguardoClaves\psicologia.key
```

La herramienta rechaza archivos existentes al crear la clave y crea una carpeta única por copia. La contraseña de BD se transmite a `mysqldump/mysql` en el entorno del subproceso, sin argumentos ni archivo de contraseña; operadores con capacidad de inspeccionar procesos siguen siendo privilegiados. No se imprime la salida de errores SQL ni contenido del respaldo. Una copia incompleta no se usa: verificar el manifiesto y la restauración, no solo la existencia de archivos.

## Alcance de la copia y recuperación

Se cifra la base completa con tablas InnoDB, vistas, triggers, rutinas y eventos; los adjuntos referenciados; y código/recursos de ejecución de las carpetas seleccionadas, incluido el SQL de instalación y ambos directorios de migraciones. `.git`, `database/backups`, respaldos anteriores, expedientes sueltos y herramientas externas no se recorren. El código recuperado se escribe en `codigo/` dentro del destino aislado. Conservar aparte los valores de entorno, secretos del hosting, configuración Apache/PHP y versiones de clientes: no los reconstruye el respaldo. Un anexo faltante hace fallar la copia.

`--single-transaction` protege la instantánea de BD; **no sincroniza por sí solo cambios concurrentes de archivos ni DDL**. Hacer la copia con escrituras y migraciones suspendidas, o implementar posteriormente una ventana/instantánea coordinada. El bloqueo operativo debe abarcar Apache y procesos de terminal; una variable fijada solo en la terminal no detiene el sitio. No se implantó un programador de tareas ni se afirma que existan copias automáticas.

Para ensayar, preparar una carpeta privada vacía. La restauración solo admite una base **nueva** `psicologia_restore_` más 12 dígitos hexadecimales. No sobrescribe bases ni carpetas pobladas, autentica todos los archivos antes de crear el destino y nunca apunta por defecto a la base operativa:

```powershell
& C:\xampp\php\php.exe -d extension=sodium herramientas\restaurar_prueba.php --copia=C:\CopiasPsicologia\respaldo-FECHA-SUFIJO --clave=C:\ResguardoClaves\psicologia.key --base=psicologia_restore_012345abcdef --archivos=C:\RestauracionAislada
& C:\xampp\php\php.exe -d extension=sodium tests\endurecimiento.php
```

La primera orden conserva los resultados aislados para validación; no los publica ni cambia la conexión de producción. La segunda genera sus propias bases, claves, copia y adjunto ficticios, compara filas de todas las tablas, archivo y código, prueba estadísticas y triggers recuperados y elimina **solo** sus recursos sintéticos. No restaurar datos reales en un entorno de pruebas ordinario.

Registrar en BAK-01 fecha/zona, origen, versión, ubicación, identificador de clave sin su valor, responsable, resultado, duración, comparación de integridad y aprobación de retorno. Validar además permisos y flujo funcional contra el entorno recuperado antes de activarlo. Una restauración real debe hacerse en infraestructura aislada y con el operador autorizado, no con root compartido.

Propuesta para aprobación: copia diaria tras suspender escrituras, copia externa adicional, revisión diaria de fallos y ensayo trimestral o tras cambios relevantes. **Frecuencia, retención, RPO y RTO son decisiones pendientes**, no obligaciones numéricas de ISO. No borrar copias por vencimiento hasta aprobar los plazos y suspensiones de eliminación aplicables.

Hosting compartido puede impedir `proc_open`, `sodium`, creación de otra base, triggers, eventos o acceso a los binarios. En ese caso acordar una herramienta equivalente del proveedor y repetir recuperación; no desactivar las protecciones para simular éxito. La automatización requiere un usuario/tarea del sistema, acceso seguro a clave/BD, espacio, detección de fallos y custodia fuera del equipo; no se configuró aquí.
