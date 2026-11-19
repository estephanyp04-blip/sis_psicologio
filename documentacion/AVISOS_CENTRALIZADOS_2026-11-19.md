# Sistema centralizado de avisos

Entrega: 19 de noviembre de 2026.

Se incorporó una única capa de presentación para los módulos existentes. Usa la paleta, las variables CSS y la tipografía del sistema. No introduce bibliotecas remotas, cambios de esquema, nuevas concesiones de acceso ni modificaciones de las reglas clínicas.

## Elección técnica

| Alternativa | Evaluación en este proyecto |
| --- | --- |
| SweetAlert2 | Requería añadir y mantener otra biblioteca y adaptar sus estilos. No estaba instalada. |
| Bootstrap Modal | Bootstrap ya está instalado localmente. Permite resolver las ventanas, pero los avisos requieren además una API de promesas, cola, recuperación de formularios y gestión de envíos. |
| Componentes propios con `<dialog>` | Alternativa implementada: aprovecha el diálogo modal del navegador, sin dependencias adicionales, con estilos propios, cola y API compartida. Los formularios conservan sus eventos y validaciones. |

Requiere un navegador moderno compatible con `HTMLDialogElement.showModal()` y `requestSubmit()`. La verificación ejecutada se realizó en Chrome de escritorio; otros motores y dispositivos físicos no se probaron.

## Componentes y comportamiento

- Éxito: toast discreto, cierre manual, caducidad de 6,5 segundos, pausa al pasar el puntero o recibir foco. Con movimiento reducido permanece hasta cerrarlo.
- Error y advertencia de operación: modal y detalle permanente dentro de la página para corregir la entrada.
- Información: modal reutilizable para consultas explícitas; los mensajes informativos de PHP pueden generar un toast. Las instrucciones, estados vacíos y contextos clínicos permanecen dentro de la pantalla.
- Confirmación: resolución asíncrona, foco inicial en Cancelar, botones con acciones específicas y Escape como cancelación. Cubre eliminación de derivaciones, retiro/reactivación de estudiantes, estados de usuarios/docentes/derivaciones, cancelación de citas y actualización de permisos.
- Cambios sin guardar: confirmación al seguir enlaces y enviar consultas u otros formularios de la página. Las precargas automáticas no se consideran ediciones. Las entradas recuperadas después de un error siguen pendientes de guardar.
- Procesamiento: estado accesible y modal de espera, con bloqueo inmediato de envíos repetidos. No deshabilita los campos ni altera nombre/valor del botón enviado. Se restaura al recuperar la página del historial.
- Sesión finalizada: detección de HTTP 401 en las consultas de historias e informes, y aviso al llegar al acceso mediante una redirección del servidor por sesión terminada. No se modifica la duración de sesión ni se renueva mediante sondeos. Los datos permanecen en la página hasta que el usuario decide ir al acceso.

Los modales tienen fondo atenuado, iconos SVG locales, animaciones de entrada/salida, adaptación móvil, foco contenido, retorno del foco y etiquetas accesibles. Se respeta `prefers-reduced-motion`.

Los mensajes emergentes de PHP usan textos generales. No se copia a un popup el contenido de nombres, entradas de formularios, notas clínicas ni errores técnicos. El detalle autorizado permanece en su pantalla. La campana muestra avisos generales y fecha/hora, con acceso a la ficha mediante los permisos existentes; ya no muestra nombres de estudiantes ni prioridad clínica.

## Archivos incorporados

| Archivo | Función |
| --- | --- |
| `asset/js/avisos.js` | API `Avisos`, cola de modales, toasts, confirmaciones, control de cambios, sesión y envíos. |
| `asset/css/avisos.css` | Diseño adaptable, estados, animaciones, foco y movimiento reducido. |
| `includes/avisos.php` | Mensajes PHP pendientes y recuperación de entradas permitidas. |
| `tests/avisos.php` | Pruebas PHP y ejecución aislada de Chrome; sin conexión a la base de datos. |
| `tests/avisos_fixture.php` | Formulario sintético y verificaciones dentro del navegador. |
| Este informe | Decisiones, inventario, validación y límites. |

## Archivos existentes modificados en esta entrega

El repositorio ya tenía cambios previos. Esta relación identifica exclusivamente los archivos intervenidos para los avisos; no atribuye a esta entrega las modificaciones previas de esos archivos.

| Área | Archivos | Integración |
| --- | --- | --- |
| Compartidos | `includes/header.php`, `includes/footer.php`, `includes/autenticacion.php`, `includes/navbar.php` | Carga local, puente PHP, indicación de sesión finalizada y privacidad de notificaciones. |
| JavaScript previo | `asset/js/interfaz.js`, `asset/js/respuestas.js` | Retirada de confirmación nativa y de la implementación duplicada. `respuestas.js` queda como adaptador mínimo para referencias anteriores. |
| Acceso | `login.php` | Errores y aviso de sesión finalizada con los componentes compartidos. |
| Panel y agenda | `index.php`, `citas/cancelar.php` | Errores, éxitos, confirmación de cancelación y recuperación del formulario correcto tras un error de agenda. |
| Estudiantes | `estudiantes/registrar.php`, `estudiantes/editar.php`, `estudiantes/listar.php` | Mensajes PHP, retiro/reactivación y `requestSubmit()` en la importación. Se corrigieron también accesos a botones de importación ausentes por permisos. |
| Docentes | `docentes/registrar.php`, `docentes/editar.php`, `docentes/listar.php` | Mensajes y confirmación central del cambio de estado. |
| Citas | `citas/formulario.php`, `citas/listar.php` | Errores, éxitos y cancelación con confirmación asíncrona. |
| Derivaciones | `derivaciones/registrar.php`, `derivaciones/editar.php`, `derivaciones/listar.php`, `derivaciones/ver.php`, `derivaciones/guardar.php` | Mensajes, eliminación, cambio de estado y uso del pie compartido en la selección intermedia del docente. |
| Historias clínicas | `historias_clinicas/registrar.php`, `historias_clinicas/editar.php`, `historias_clinicas/listar.php`, `historias_clinicas/ver.php`, `historias_clinicas/formulario.php` | Mensajes, sustitución de alerta de carga de derivaciones y tratamiento de 401. |
| Estadísticas | `estadisticas/index.php`, `estadisticas/individual.php` | Errores operativos integrados. Estados vacíos conservados dentro de la página. |
| Informes | `informes/registrar.php`, `informes/editar.php`, `informes/listar.php`, `informes/ver.php`, `informes/mensual.php`, `informes/guardar_mensual.php` | Mensajes, validación de atención, confirmación sin historia, tratamiento de 401, recuperación de filtros mensuales y aviso de generación correcta. |
| Usuarios | `usuarios/registrar.php`, `usuarios/editar.php`, `usuarios/listar.php`, `usuarios/guardar.php`, `usuarios/actualizar.php` | Confirmación de estado, mensajes pendientes y recuperación de campos administrativos ante un error. |
| Roles y permisos | `roles/registrar.php`, `roles/editar.php`, `roles/listar.php`, `roles/guardar.php`, `roles/actualizar.php` | Mensajes, confirmación de permisos y recuperación de nombre, descripción, estado y casillas permitidas. |
| Pruebas existentes | `tests/historias_derivaciones.php`, `tests/README.md` | Copia de recursos locales en la fixture clínica e instrucciones de ejecución. |

Seguimientos históricos y las demás pantallas que usan cabecera/pie reciben la capa común sin duplicar código. No se modificaron `config/permisos.php`, las consultas de `estadisticas/clinica_datos.php`, las transacciones clínicas ni las migraciones para esta tarea.

## Uso para nuevas pantallas

```javascript
Avisos.exito('La operación se completó correctamente.');
await Avisos.error('No se pudo completar la solicitud. Inténtalo nuevamente.');
await Avisos.advertencia('Selecciona al menos un tipo de atención.');
await Avisos.informacion('La consulta está disponible en su ficha.');

// Formulario POST: la capa compartida conserva validaciones, CSRF y submitter.
form.dataset.uiConfirmar = '¿Eliminar este registro? Esta acción no se puede deshacer.';
form.dataset.uiAceptar = 'Eliminar';
form.dataset.uiDestructivo = 'true';

// Operaciones AJAX nuevas deben cerrar siempre la carga y verificar la sesión.
const carga = Avisos.procesando();
try {
    const respuesta = await fetch(url);
    if (!Avisos.comprobarRespuesta(respuesta)) return;
    if (!respuesta.ok) throw new Error('Solicitud rechazada');
    // Procesar la respuesta; anunciar éxito únicamente tras confirmarlo el servidor.
} finally {
    carga.cerrar();
}
```

No pasar datos clínicos a esta API. En las plantillas, añadir `data-ui-aviso` al contenedor del mensaje operativo de PHP. El tipo se toma de `alert-success`, `alert-danger`, `alert-warning` o `alert-info`. Mantener fuera de ese atributo instrucciones y contenido clínico permanente. Los mensajes que todavía estén en `$_SESSION['mensaje']` al llegar al pie se consumen allí una sola vez.

`Avisos.marcarGuardado(form)` permite actualizar el punto de referencia después de un guardado AJAX confirmado. `Avisos.restablecer(form)` libera el estado de envío tras un error manejado por un módulo AJAX. No se intercepta ni se reemplaza globalmente `fetch`, `alert` o `confirm`.

## Pruebas realizadas

| Prueba | Resultado |
| --- | --- |
| `php tests/avisos.php` | 8 comprobaciones PHP; 39 comprobaciones dentro de Chrome por cada ancho: 1440, 768, 390 y 320 px. El arnés informa además 4 resultados de navegador: 12 controles externos. |
| `php tests/seguridad.php` | 1143 comprobaciones correctas: 66 rutas, cuatro roles, métodos, CSRF, sesiones revocadas, aislamiento, estados y rollback. |
| `php tests/esquema_informes_importacion.php --navegador` | 349 comprobaciones y recorrido de Chrome con 10 verificaciones de autocompletado y materias. |
| `php tests/historias_derivaciones.php --navegador` | 213 comprobaciones y recorrido de Chrome con 19 verificaciones clínicas. |
| `php tests/trazabilidad.php --navegador` | 184 comprobaciones y recorrido de Chrome con 35 verificaciones. |
| `php tests/seguimiento_clinico.php --navegador` | 106 comprobaciones; Chrome con 16 verificaciones por ancho: 1440, 390 y 360 px. |
| `php -l` sobre todos los PHP | 141 archivos sin errores de sintaxis. |
| Búsqueda de llamadas nativas en código de aplicación | Sin llamadas propias a `alert()`, `confirm()` ni `prompt()`. Se excluyen pruebas y dependencias de terceros; los errores de DataTables se derivan también al componente central. |
| Inspección de captura sintética móvil | Ventana de sesión finalizada sin desbordamiento, con botón de acceso y foco visible. |

La prueba de avisos verifica cola, Escape, ciclo y retorno del foco, texto sin interpretación HTML, privacidad del toast, consumo único del mensaje PHP, recuperación de casillas y campos, exclusión de contraseñas/CSRF, cambios sin guardar en enlaces y filtros GET, validadores propios, `required`, cancelación, POST real a un receptor sintético, conservación del botón enviado, un solo POST ante envíos repetidos, procesamiento, regreso del historial y sesión 401 sin perder los campos de la página.

La primera ejecución del recorrido clínico falló porque esa fixture copiaba únicamente PHP y omitía JavaScript/CSS locales. Se actualizó su preparación y el recorrido completo pasó. Todas las pruebas usaron tablas temporales o bases y cuentas sintéticas; no se escribieron expedientes ni cuentas reales.

Para conservar capturas sintéticas, configurar `CAPTURAS_AVISOS` con una carpeta existente antes de ejecutar la suite. Las capturas de esta revisión se guardaron fuera del repositorio, en `C:/Users/usuario/CodexArtifacts/avisos-2026-11-19`.

## Límites y decisiones de seguridad

- El cierre, la recarga y la salida mediante controles del navegador solo admiten el aviso nativo de `beforeunload`. Los enlaces y formularios del sistema usan el modal personalizado. El navegador puede suprimir su aviso si no hubo interacción del usuario.
- Las contraseñas se vuelven a introducir y los archivos deben volver a seleccionarse después de una redirección con error. No se guardan secretos ni entradas clínicas en almacenamiento del navegador.
- La recuperación nueva de entradas administrativas y de agenda dura hasta 15 minutos, es de un solo uso y se vincula al registro. Las recuperaciones clínicas existentes se conservan.
- Una sesión terminada no permite recuperar automáticamente el formulario después de ir al acceso; la entrada permanece únicamente hasta abandonar la página. No se conserva una copia clínica fuera de la sesión autorizada.
- El bloqueo de repetición es de interfaz. La coherencia, las validaciones, CSRF, los permisos y las transacciones siguen siendo responsabilidad del servidor y se comprobaron mediante las suites existentes.
- Se revisó estáticamente la integración de las pantallas y se automatizaron los recorridos citados. No se hizo una inspección manual de todas las combinaciones de pantalla/rol ni una auditoría con lector de pantalla o dispositivos físicos.
