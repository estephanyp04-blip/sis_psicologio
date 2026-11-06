# Revisión de interfaz — 6 de noviembre de 2026

Trabajo sobre la copia local, con `git status` inicialmente limpio. No se hicieron commits, push ni despliegues. Se conservan rutas, consultas, validaciones del servidor, formularios de escritura y permisos. Las pruebas utilizan bases temporales o aisladas con datos sintéticos.

## Revisión de las pantallas existentes

Se revisaron las plantillas `includes/header.php`, `sidebar.php`, `navbar.php` y `footer.php`; el acceso; Inicio; listados y formularios de estudiantes, docentes, derivaciones y citas; historia clínica y evolución; informes; estadísticas; usuarios y permisos. La comparación existente está en Estadísticas, no hay un módulo separado de comparación. Las notificaciones son avisos calculados en la barra superior, no una bandeja persistente.

Hallazgos del código y de las comprobaciones con datos ficticios: los indicadores precedían a la agenda; el calendario mensual alargaba el recorrido en celular; Estudiantes no tenía una pantalla independiente de consulta; las acciones de Citas usaban emojis y sus filtros carecían de etiquetas visibles. Al revisar las capturas se corrigieron también el contraste del botón de la agenda y de los títulos del menú. No se afirma una auditoría visual completa de todas las pantallas.

## Cambios por módulo

- **Acceso y navegación:** logo existente sobre fondo claro, acceso con etiquetas visibles, nombre de la institución, morado para acciones principales, indicador de página actual, enlace para saltar al contenido y foco visible. Se conserva el menú móvil con Escape y retorno del foco.
- **Inicio:** agenda y casos prioritarios antes de los indicadores. En celular aparece primero la agenda diaria con hora, estudiante y estado. El calendario mensual se despliega a petición y queda abierto cuando se consulta otro mes. “Nueva cita” conserva la fecha seleccionada y los permisos anteriores.
- **Estudiantes:** tarjetas en celular con nombre, curso/paralelo, estado y “Ver ficha”; se conservan los demás datos y acciones. La ficha es el formulario existente `editar.php`, ahora identificado por estudiante y dividido en identificación, inscripción y familia/contacto. Consultarla no guarda cambios.
- **Docentes:** tarjetas en celular; “Ver ficha” y “Editar” con texto. Se conservan filtros y acciones de cuenta.
- **Citas:** título y acción principal visibles, etiquetas de filtros, resultados y filtros activos anunciados, iconos locales con texto para editar/cancelar y confirmación antes de cancelar, también al cambiar el estado desde Inicio. Tarjetas en celular y tablas en escritorio.
- **Derivaciones:** acciones delicadas en rojo y estilos comunes; se conservan las confirmaciones y filtros existentes.
- **Historias y seguimiento:** estilos comunes y fechas de evolución destacadas. Las tablas de comparación y seguimiento en Estadísticas se presentan como tarjetas etiquetadas en celular; se mantienen fechas, filtros y consultas existentes.
- **Informes y estadísticas:** se preservan los períodos y filtros existentes; los controles sin etiqueta reciben una etiqueta visible cuando existe un nombre conocido o un texto de búsqueda. Se mantienen gráficos y permisos originales.
- **Usuarios y permisos:** se identifica la cuenta editada; el editor de permisos ya identifica el rol y conserva su tabla.
- **Formularios:** campos obligatorios identificados según sus atributos existentes, controles de al menos 44 px y explicación de la validación nativa junto al campo. Los errores del servidor siguen usando sus avisos existentes, sin reinterpretar validaciones.
- **Notificaciones:** distinción de avisos consultados en esta pestaña mediante `sessionStorage`, separada por cuenta. Se recuerda solo el identificador de destino; no se guardan nombres ni textos clínicos. Esto no cambia el estado de las citas o derivaciones ni crea un estado de lectura en la base.

## Archivos principales

`asset/css/interfaz.css` y `asset/js/interfaz.js` reúnen las mejoras compartidas. Se cargan desde `includes/header.php` y `includes/footer.php`. También se ajustaron `includes/sidebar.php`, `asset/css/login.css`, `login.php`, `index.php`, `citas/listar.php`, `estudiantes/listar.php`, `estudiantes/editar.php`, `docentes/listar.php`, `derivaciones/listar.php` y `usuarios/editar.php`.

`tests/estadisticas.php` amplía las comprobaciones de interfaz a 360, 390 y 1440 px, búsqueda y apertura de ficha, filtros de citas y menú móvil. Ahora copia también fuentes e imágenes locales a su entorno aislado. Si se define `CAPTURAS_ESTADISTICAS`, conserva capturas de Inicio, Citas y ficha en los tres anchos.

## Comprobar en XAMPP

1. Iniciar Apache y MySQL en XAMPP. No ejecutar migraciones para esta mejora de interfaz.
2. Abrir `http://localhost/proyecto_vercionII/login.php` y recargar con **Ctrl+F5**. Entrar con las cuentas habituales.
3. Como psicóloga, entrar a Inicio: revisar la agenda diaria, desplegar el calendario y volver a Hoy. Abrir una cita y comprobar sus datos.
4. Entrar a Estudiantes, buscar por nombre o código, filtrar por curso y pulsar **Ver ficha**. Comprobar nombre, curso/paralelo y las tres secciones. Usar **Volver** para salir sin guardar.
5. En una instalación de prueba, entrar a Citas y pulsar **Nueva cita**. Registrar con datos ficticios y comprobar que aparece al filtrar por fecha y estudiante. Probar la confirmación de cancelación y pulsar Cancelar en el diálogo para conservarla.
6. En las herramientas del navegador, usar anchos de **360**, **390** y **1440 px**. Revisar tarjetas, filtros, botones y ausencia de desplazamiento horizontal de toda la página. Las tablas extensas que se conservan pueden desplazarse dentro de su propio contenedor.
7. Con teclado: Tab hasta “Saltar al contenido”, Enter, recorrer filtros y acciones; en móvil abrir el menú, recorrer sus enlaces y usar Escape. Revisar el foco visible. Comprobar también los controles con un teléfono real.
8. Entrar como docente y director para revisar sus destinos habituales: derivaciones e informes respectivamente. No se amplían sus permisos para acceder al panel clínico.
9. Desconectar Internet manteniendo XAMPP local disponible y recargar: Bootstrap, JavaScript, logo y fuentes de iconos se sirven desde `asset/vendor` y `asset/img`.

## Pruebas y límites

Comandos ejecutados desde la raíz con PHP de XAMPP:

```powershell
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php --navegador
$env:CAPTURAS_ESTADISTICAS='C:/Users/usuario/.codex/artifacts/interfaz-2026-11-06'
& C:\xampp\php\php.exe tests\estadisticas.php --navegador
```

Se comprobó sintaxis PHP de todos los archivos PHP modificados. Seguridad pasó 1116 comprobaciones; historias y derivaciones 200 y 19 dentro del navegador; trazabilidad 190 y 34 dentro del navegador; esquema e importación 342 y 10 dentro del navegador. La ejecución final de Estadísticas pasó 81 comprobaciones y, en Chrome, 27 por recorrido de escritorio y 30 por recorrido móvil: Inicio, Citas y ficha a 1440, 390 y 360 px. `git diff --check` no detectó errores de espacios.

La suite de esquema finalizó con éxito y avisos de limpieza: Windows mantuvo bloqueado `CrashpadMetrics-active.pma` en su perfil temporal de Chrome. La política de ejecución rechazó el intento posterior de eliminar la carpeta `C:/Users/usuario/AppData/Local/Temp/psicologia-integracion-000470746355`; esa limpieza quedó pendiente. El aviso no corresponde al proyecto ni a la base real.

Las capturas son de Chrome sin interfaz visible, mediante las pruebas existentes y ampliadas, con datos ficticios. Están en `C:/Users/usuario/.codex/artifacts/interfaz-2026-11-06`. En capturas móviles, la aplicación está dentro de un marco del ancho indicado; puede quedar una franja exterior del navegador de prueba. El pequeño texto inferior es el resultado de la prueba, no pertenece al sistema.

Pendiente de revisión manual: interacción táctil en dispositivo físico; lectura con lector de pantalla; todas las variantes de formularios y mensajes del servidor; sesión real de docente/director y edición completa de usuarios/permisos; paginación y contenidos reales. No se utilizaron credenciales ni datos clínicos reales para la revisión visual.
