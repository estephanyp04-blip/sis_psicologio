# Privacidad y acceso a información psicológica

Borrador del 14/11/2026, pendiente de aprobación institucional. Complementa [alcance y política](01_ALCANCE_POLITICA.md), [riesgos](02_RIESGOS.md), [procedimientos](04_PROCEDIMIENTOS.md) y [registros](06_REGISTROS.md). No acredita base jurídica, consentimientos ni autorizaciones existentes.

## Inventario de tratamientos propuesto

| Datos / fuente técnica | Finalidad propuesta que debe validar el propietario | Acceso operativo y minimización | Decisión institucional pendiente |
|---|---|---|---|
| Identificación, nacimiento, domicilio y contacto de estudiantes; `estudiantes` | Identificar al estudiante y coordinar atención | Gestión estudiantil autorizada; no enviar el domicilio en avisos | Validar necesidad de cada campo, fuente y exactitud |
| Curso, sección, gestión y estados; `historial_academico` | Vincular contexto académico y conservar trayectoria | Administración/psicología según permiso; no equivaler retirada a borrado | Determinar responsables de actualización y reglas históricas |
| Familiares, tutores y teléfonos; `responsables`, relaciones y `historia_familiares` | Contacto y contexto de atención | Los datos de la historia tienen confidencialidad clínica adicional | Comprobar representación, necesidad y comunicaciones permitidas |
| Derivaciones, motivos, categorías y evidencias | Solicitar y coordinar atención psicológica | Docente solo propias, psicología por permiso; imágenes a través del controlador autenticado | Reglas de evidencia, contenido mínimo y rectificación |
| Citas, horario, profesional, observaciones | Organizar atención | Rol Psicóloga con permiso; evitar diagnósticos en recordatorios | Canal, destinatarios y contenido de avisos |
| Historia, valoración, opciones clínicas, sesiones y versiones | Atención, evolución y rectificación del expediente | Solo rol Psicóloga habilitado; contenido y versiones dentro de BD | Asignación de profesionales por expediente, suplencias y acceso excepcional |
| Informes individuales, resumen mensual y estadísticas | Documentar atenciones y apoyar decisiones autorizadas | Consulta profesional; no se concede dirección por defecto | Destinatarios, autorización por informe, anonimización y supresión de grupos pequeños |
| Cuentas, hashes, roles y versiones de sesión | Controlar acceso y revocar sesiones | Administración autorizada; claves no recuperables en claro | Alta de profesionales y separación de funciones |
| Auditoría, identidad histórica de actor, acciones, recurso y fecha | Trazabilidad, investigación y revisión | Consulta por operador autorizado, sin contenido clínico en detalle | Responsable de revisión, plazos y repositorio externo protegido |
| Copias cifradas y claves separadas | Recuperar el servicio y datos | Operador/custodio nominados; origen sintético en pruebas ordinarias | Segundo soporte, presupuesto, retención y restauración autorizada |

No se verificaron tratamientos fuera de la aplicación: expedientes en papel, mensajería, correo, dispositivos personales, otras instituciones ni proveedores. Deben incorporarse al inventario institucional cuando existan.

## Matriz de acceso aplicada

Roles identificados: 1 Administrador, 2 Psicóloga, 3 Docente, 4 Director; pueden existir roles personalizados. Las acciones efectivas dependen de `permisos`, además de los límites obligatorios de `includes/politica_acceso.php`.

| Recurso / acción | Administrador | Psicóloga | Docente | Director / rol personalizado |
|---|---|---|---|---|
| Cuentas y perfiles docentes | Según permiso; gestión administrativa original | Sin concesión inicial | Sin concesión inicial | Solo si se concede una acción administrativa; nunca acceso clínico implícito |
| Roles y permisos | Exclusivo de administrador | No | No | No |
| Estudiantes / altas / edición / estado | Según acción concedida | Según acción concedida | Bloqueado | Director bloqueado; personalizado según permiso |
| Derivaciones: consultar | Bloqueado | Según permiso | Solo propias y autoría vigente del perfil | Bloqueado |
| Derivaciones: crear / editar | Bloqueado | Sin concesión inicial de creación/edición | Según permiso, solo propias | Bloqueado |
| Derivaciones: cambiar estado | Bloqueado | Según permiso y transición válida | Bloqueado inicialmente | Bloqueado |
| Derivaciones: eliminar pendiente | Bloqueado | Sin concesión inicial | Solo propia y sin relaciones de atención | Bloqueado |
| Citas: consultar / crear / editar / estado | Bloqueado | Según cada permiso; programación propia y controles existentes | Bloqueado | Bloqueado |
| Historias clínicas: consultar / crear / editar | Bloqueado | Según cada permiso | Bloqueado | Bloqueado |
| Anexos clínicos históricos: lectura dentro de su historia | Bloqueado | Requiere consulta de historias | Bloqueado | Bloqueado |
| Panel y estadísticas por estudiante | Bloqueado | Según permiso de consulta | Bloqueado | Bloqueado |
| Informes individuales / mensuales | Bloqueado | Consulta, creación o edición según cada permiso | Bloqueado | Bloqueado hasta definir divulgación autorizada |
| Exportación electrónica independiente | No se identificó un endpoint de exportación | No se añadió uno | No | No |
| Consulta de auditoría / recuperación de versiones | Sin interfaz web general; requiere acceso técnico autorizado al servidor/BD | Solicitud al custodio según procedimiento | No | No |

Un rol renombrado conserva su identificador y no cambia automáticamente su función de seguridad. No conceder a un rol personalizado acceso clínico copiando casillas. Administración puede gestionar cuentas y el servidor, y por ello sigue siendo un actor privilegiado: retirar el acceso en rutas no impide que un administrador de BD lea datos o que un administrador de cuentas restablezca una clave profesional. Se requieren autorización independiente, supervisión de altas/cambios y separación de credenciales. No hay doble aprobación electrónica implementada.

La autoría de una historia no equivale necesariamente a asignación vigente del caso. **No se inventó una regla que impida a toda profesional suplente consultar una historia de otra autora**. El modelo actual sigue ofreciendo acceso al equipo con rol Psicóloga y permiso de módulo; la separación por caso/profesional y el acceso excepcional necesitan una política y una relación explícita de asignación. Este aspecto se mantiene parcial y es un pendiente alto antes de un despliegue con varios profesionales.

Las sesiones comprueban estado, rol, vínculo docente, hash y versiones de revocación. Desactivar/reactivar una cuenta o rol no recupera sesiones anteriores. Cambiar contraseña o rol invalida sesiones anteriores; cambiar permisos se aplica al consultar nuevamente la autorización. Cada operación de escritura continúa exigiendo POST y CSRF. Dar de baja una cuenta es desactivarla; no elimina sus expedientes ni su atribución previa.

Desde el 20/11/2026 no existe módulo ni permiso de Seguimientos. El seguimiento psicológico es una consulta de Estadísticas; la consulta individual requiere también lectura de historias. Los permisos antiguos se retiran mediante 017 y las rutas eliminadas responden 404. Los anexos no admiten altas, edición ni eliminación desde la aplicación.

## Entrega de informes y rectificaciones

1. Recibir solicitud por canal institucional designado, comprobar identidad y autoridad/representación; no conceder acceso por relación familiar, puesto directivo o casilla de consentimiento sin evaluar el fundamento aplicable.
2. La persona responsable de psicología revisa finalidad, destinatario, alcance y posibles restricciones. Entregar el mínimo necesario; preferir resumen adecuado al propósito. No enviar automáticamente la historia completa, imágenes, notas de sesiones o versiones anteriores.
3. Registrar autorización, documento/versión entregada, destinatario, canal, fecha, quien entrega y resultado en registro restringido institucional. En Git y bitácora técnica usar solo identificadores y metadatos.
4. Aprobar el canal seguro y las reglas de impresión/custodia antes de entregar. Las vistas existentes tienen impresión del navegador: una vez entregado el contenido al navegador, el programa no puede impedir copias, capturas ni Ctrl+P. **No hay permiso independiente de exportación/impresión ni prueba de entrega física realizada**. No interpretar el evento Consultar como Exportar o Entregar.
5. Las correcciones de historia conservan una copia clínica previa con editor y fecha en `historia_versiones`, dentro de la transacción. Son datos clínicos y se incluyen cifrados en las copias; no se muestran en el menú ni en auditoría general. Solicitudes de rectificación requieren verificar el dato y registrar la decisión; no sobrescribir el pasado sin conservarlo. No se reconstruyen versiones anteriores a esta implementación.
6. Los informes conservan su autor y relaciones; las consultas retrospectivas existentes limitan sesiones al corte y evitan precargar textos modificados después. No reconstruyen automáticamente todos los estados/curso/diagnósticos históricos. No afirmar que un informe es una reconstrucción clínica completa del pasado.

## Conservación, eliminación y solicitudes sobre datos

Dirección y el responsable designado de privacidad deben aprobar una tabla por tipo de dato: finalidad, fundamento aplicable, evento inicial, plazo, excepciones/suspensiones, responsable, método, copia afectada y evidencia. **No se fija ningún plazo legal**. Incluir historias/versiones, informes, evidencias, cuentas, auditoría, sesiones, registros de incidentes y copias. Mientras no haya decisión, no implementar purgas automáticas ni borrar datos por una simple baja.

La eliminación definitiva debe ser un procedimiento autorizado, con revisión de obligaciones, relaciones, suspensiones y copias. Los triggers bloquean borrado de auditoría/versiones por la aplicación; una eliminación institucional autorizada necesitará un procedimiento técnico específico, supervisado y ensayado. No se entrega un botón para retirar estos controles.

Canal y responsable para solicitudes: **[por designar y publicar por el colegio]**. Registrar fecha, identidad verificada de forma proporcional, alcance, representación cuando corresponda, fundamento examinado, áreas consultadas, decisión, comunicación segura y cierre. Distinguir acceso, corrección, oposición/restricción y eliminación según las obligaciones que realmente sean aplicables; derivar conflictos al responsable designado y asesoramiento competente. No prometer plazos de respuesta ni derechos sin verificar su marco aplicable. No conservar copias innecesarias del documento de identidad usado para verificar.

## Borrador de información de privacidad

> La U.E. Cañada Pailita «B» utiliza un sistema de registro de derivaciones, citas y seguimiento psicológico. La institución debe completar y validar: responsable del tratamiento y contacto; finalidades y fundamento aplicable; datos necesarios y opcionales; destinatarios y condiciones de entrega; prestadores de hosting y ubicación; criterios de conservación; canales para ejercer solicitudes y resolver inquietudes; condiciones aplicables a estudiantes menores de edad y sus representantes. El acceso a expedientes se restringe por función. Las comunicaciones y entregas de informes se evaluarán y registrarán por el personal autorizado. **Este texto es un borrador y no debe publicarse como aviso completo hasta aprobar y completar esos datos.**

No se añadió una casilla genérica de consentimiento. Su necesidad, validez, representación, revocación y relación con otras obligaciones deben determinarse para cada tratamiento.

## Riesgos y decisiones para registrar

Actualizar R02 (copias) y los riesgos de acceso del [registro existente](02_RIESGOS.md), utilizando el método institucional allí propuesto. Prioridades técnicas internas de esta revisión, sin inventar aceptación de riesgo:

| Pendiente | Riesgo / tratamiento propuesto | Responsable propuesto / evidencia esperada |
|---|---|---|
| Profesionales por caso | Acceso excesivo dentro del equipo; aprobar asignaciones, suplencias y eventual relación de autorización por registro | Dirección + propietario clínico; política y pruebas entre profesionales |
| Privilegio técnico | Root local y administrador capaz de restablecer claves; separar credenciales, mínimos privilegios y revisión independiente | Administración + responsable SGSI; permisos efectivos y revisión de eventos |
| Protección operativa | TLS, parches, ACL, logs externos y protección de dispositivos aún dependen del despliegue | Administración; comprobación desde el hosting |
| Entregas/impresiones | Copias sin control o datos innecesarios; aprobar minimización y registro de entrega | Psicología + Dirección; autorizaciones y recibos restringidos |
| Continuidad y claves | Pérdida de equipo/clave; segundo soporte, custodio y ensayos programados | Dirección + custodio; BAK-01 real |
| Privacidad/conservación | Uso o borrado sin fundamento y registros demasiado extensos; inventario y tabla aprobada | Responsable de privacidad por designar; GOV-01 y política aprobada |

Para altas, cambios y bajas usar ACC-01 y SGSI-04; para incidentes usar INC-01, conservar evidencia fuera del repositorio y determinar comunicaciones/obligaciones caso por caso. Para copias usar [el procedimiento técnico](09_OPERACION_RECUPERACION.md) y BAK-01. No duplicar los registros ni considerar preparados estos borradores como evidencia de capacitación, aprobación o ejecución institucional.
