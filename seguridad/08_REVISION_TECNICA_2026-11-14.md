# Revisión técnica, matriz de evaluación y evidencias

Revisión iniciada: 14/11/2026; cierre documental y comprobaciones finales: 17/11/2026, zona America/La_Paz. Proyecto: sistema de registro de citas y seguimiento de historias clínicas psicológicas de estudiantes de la U.E. Cañada Pailita «B».

## Alcance y límites

Se inspeccionaron código PHP, SQL de instalación, migrador, permisos, sesiones, auditoría, adjuntos, documentación, pruebas y reglas Apache. Existen estudiantes, docentes, cuentas, roles/permisos, derivaciones, citas, historias, seguimientos, informes individuales/mensuales, estadísticas y panel. No se encontró una API pública independiente de exportación ni un módulo de IA. No hay manifiestos Composer/npm; las bibliotecas de interfaz están en recursos locales.

Entorno ejecutado: Windows/XAMPP, PHP 8.2.12, cliente MariaDB 10.4.32, Apache 2.4, Chrome instalado. La conexión real se diagnosticó sin mostrar identidades ni contenido clínico: utiliza root y clave vacía. Las pruebas funcionales y de recuperación usan tablas TEMPORARY o bases sintéticas de nombre aleatorio. No se trasladaron expedientes a servicios externos, no se publicaron cambios y no se ejecutaron las migraciones históricas destructivas sobre la base real.

Git tenía cambios previos en `.gitignore`, `ANALISIS_ISO_2026-11-13.md`, `CHANGELOG.md`, la eliminación preparada de `.vscode/launch.json` y eliminaciones de `MEJORAS_INTERFAZ.md`/`REVISION_ISO.md`. Se conservaron. Las adiciones a `.gitignore` de esta revisión no sustituyen sus cambios previos. No se hizo commit ni push.

**Estado de entrega: código preparado; migración 016 NO aplicada a la base real.** La comprobación de solo lectura del 17/11 encontró 16 operaciones aditivas pendientes. La revisión automática rechazó antes de ejecutarse la operación de resguardo cifrado y migración, indicando únicamente «blocked by policy». No se creó una copia real ni una clave institucional; no quedó activado `.mantenimiento`. El nuevo inicio de sesión requiere 016: el GET público 200 no acredita que la autenticación real funcione antes de migrar. Los aspectos verificados de esquema, sesiones, versiones y recuperación corresponden a bases ficticias; no a un despliegue concluido.

El análisis del 13/11 y los resultados antiguos de pruebas son antecedentes. Esta revisión actualiza la política ejecutable: el administrador **ya no recibe acceso clínico general**. Los aspectos de gestión se remiten al SGSI existente; preparar documentos no demuestra su adopción.

## Referencias oficiales y edición

Se consultaron fichas oficiales y resúmenes de alcance el 14/11/2026. No se dispuso del texto normativo íntegro autorizado para una auditoría por cláusulas. Las asociaciones de la matriz son recomendaciones técnicas relacionadas con las normas; no requisitos textuales, equivalencias, porcentajes de cumplimiento ni una certificación.

| Referencia | Verificación en catálogo oficial |
|---|---|
| [ISO/IEC 25010:2023](https://www.iso.org/standard/78176.html) | Edición 2, noviembre 2023; modelo de calidad de producto con nueve características |
| [ISO/IEC 27001:2022](https://www.iso.org/standard/27001) | Edición 3, octubre 2022; la ficha identifica una enmienda: [Amd 1:2024](https://www.iso.org/standard/88435.html), cambios de acción climática, febrero 2024 |
| [ISO/IEC 27002:2022](https://www.iso.org/standard/75652.html) | Edición 3, febrero 2022; versión corregida de marzo 2022 |
| [ISO 27799:2025](https://www.iso.org/standard/84647.html) | Edición 3, diciembre 2025; controles de información de salud basados en 27002:2022 |
| [ISO/IEC 27701:2025](https://www.iso.org/standard/27701) | Edición 2, octubre 2025; requisitos y guía de un sistema de gestión de privacidad independiente |

No se identificaron otras enmiendas en las fichas consultadas de estas cinco referencias; confirmar el conjunto normativo en la evaluación formal. No se atribuye a la enmienda climática un requisito específico de programación o cifrado. La determinación de relevancia y su evidencia institucional siguen pendientes.

Fuentes técnicas adicionales: [soporte oficial de PHP](https://www.php.net/supported-versions.php), que sitúa el fin del soporte de seguridad de la rama 8.2 el 31/12/2026; y [secretstream en PHP](https://www.php.net/manual/en/function.sodium-crypto-secretstream-xchacha20poly1305-init-push.php). El parche local 8.2.12 necesita actualizarse; no se actualizó XAMPP ni se ensayaron otras versiones en esta intervención.

## Diagnóstico inicial y tratamiento

Se comprobó una base técnica existente: hashes, sesiones estrictas, CSRF, consultas preparadas en flujos principales, permisos por acción y transacciones. Las suites iniciales `seguridad.php` y `fases.php` pasaron 1116 y 63 comprobaciones respectivamente. No se confundió ese resultado con seguridad completa.

Brechas prioritarias encontradas y corregidas: acceso clínico automático de administración; concesión general de informes a dirección; carpetas SQL/Git/pruebas sin bloqueo raíz; falta de trazas explícitas de lectura; motivo de cierre copiado al detalle de auditoría; errores SQL completos enviados a logs y, en una vista, al usuario; sesiones que podían volver a ser válidas después de reactivar la misma cuenta/rol; ausencia de versiones de rectificación y de herramientas ensayadas de respaldo/recuperación.

Se corrigió además una autorización incompleta de escritura: un perfil docente reasignado conservaba la posibilidad de editar/eliminar derivaciones de su cuenta anterior. Actualización y eliminación ahora verifican autoría histórica bajo bloqueo; la eliminación se confirma junto con su auditoría y revierte si esta falla. Las versiones clínicas conservan relaciones originales e identificadores, incluso opciones de grupos que el formulario actual ya no muestra. La migración deja NULL el resultado de eventos antiguos para no inventar su éxito.

## Matriz actualizada

Estados: **verificado**, **parcial**, **no implementado**, **no verificable**, **no aplica con justificación**. Describen únicamente el aspecto y las pruebas indicadas. Riesgo es una prioridad interna para tratamiento; no una no conformidad normativa formal. T = técnico/código; S = servidor/operación; I = institucional.

| Referencia ISO | Aspecto evaluado | Evidencia encontrada | Estado | Riesgo | Acción necesaria | Tipo de acción | Prueba de aceptación |
|---|---|---|---|---|---|---|---|
| 25010 / 27002 | Contraseñas y rehash | `password_hash/verify`, longitud/bytes, prueba de rehash y sesión posterior | verificado | Alto si se omite | Mantener parches y política de claves | T + I | Contraseña errónea rechazada; rehash preserva acceso de la nueva sesión |
| 27002 / 27799 | Sesiones y revocación | Estrictas, regeneración, CSRF, límites 1800/28800 s; versiones de cuenta y rol con triggers | verificado | Alto | Validar tiempos institucionales y sesión real tras desplegar | T + I | Inactividad/revocación rechazada; baja/reactivación no revive sesión previa |
| 27002 | Intentos repetidos | Archivo con flock por cuenta+origen; desconocidos agrupados por origen y BD | parcial | Alto | Evaluar ataques distribuidos, NAT, limpieza y almacenamiento compartido en hosting | T + S | Mismo origen limitado; otro origen puede autenticarse; no bloqueo global por tercero |
| 27799 / 27002 / 27701 | Límites por función clínica | Política independiente de concesiones BD; solo rol 2 para historias, sesiones, citas, informes, panel/estadísticas | verificado | Alto | Aprobar la política conservadora y revisar altas de profesionales | T + I | Administrador, docente, director y rol personalizado sin acceso clínico aun con permisos excesivos |
| 27799 / 27701 | Autorización por historia dentro del equipo | No existe asignación vigente de casos; autor no equivale a responsable actual | parcial | Alto | Definir asignaciones, suplencias y acceso excepcional; después implementar relación por registro | I → T | Dos profesionales solo acceden a casos asignados conforme a política aprobada; pendiente |
| 27002 / 27799 | Propiedad de derivaciones y evidencias | Filtros por docente y autoría preservada; no se heredan casos al reasignar perfil | verificado | Alto | Mantener controles al incorporar rutas | T | IDs ajenos no se leen, editan ni eliminan; propios siguen funcionando |
| 27002 | Acciones y baja de cuentas | Ver/crear/editar/estado/eliminar separados; baja conservadora, auditoría y transacción | parcial | Alto | Supervisión independiente de restablecimientos y altas clínicas; probar cuentas en hosting | T + I | POST sin permiso/CSRF rechazado; sesiones revocadas; atribución preservada |
| 27799 / 27701 | Exportación, impresión y entregas | No se añadió exportación; impresión del navegador existente no tiene autorización/traza independiente | no implementado | Alto | Aprobar destinatarios y flujo de entrega; no interpretar Consultar como Entregar | I → T | Exportación/entrega exige autorización y evento propio; pendiente |
| 27002 / 25010 | SQL e integridad | Preparadas en flujos revisados; nombres dinámicos acotados; errores provocados prueban rollback | parcial | Alto | Revisión continua; no afirmar auditoría exhaustiva de cada consulta histórica | T | Flujos de entrada inválida no alteran registros; rollback completo |
| 27002 / 25010 | Validación, XSS y formularios | Escape contextual HTML y JSON, fechas/IDs/catálogos, recuperación de errores | verificado | Alto | Mantener el escape al agregar campos | T | Acentos/comillas/HTML ficticio no ejecutan código ni se pierden al corregir |
| 27002 | CSRF y métodos | 65 rutas ensayadas, POST con token; GET de escritura rechazado | verificado | Alto | Repetir matriz al ampliar rutas | T | Token ausente/incorrecto/arreglo rechazado; GET no cambia estados |
| 27002 / 27799 | Subida de archivos | Validación MIME/tamaño y nombres aleatorios existente en derivaciones | parcial | Alto | Ampliar casos adversariales de multipart, metadatos y límites del hosting | T + S | Archivo ejecutable/MIME falso rechazado y sin residuo; cobertura completa pendiente |
| 27002 / 27799 | Descarga directa y archivos internos | `.htaccess` raíz + regla de evidencias; respuestas Apache 403 por GET/HEAD | verificado | Alto | Repetir en hosting; trasladar adjuntos a almacén privado en fase posterior | T + S | SQL, Git, copias, pruebas y evidencias directas inaccesibles; controlador requiere sesión/propiedad |
| 27002 / 27701 | Errores y minimización en registros | Logs PHP conservan clase/código, sin mensaje SQL; vista clínica devuelve mensaje genérico; cierre sin motivo clínico en auditoría | verificado | Alto | Configurar logs de Apache/proveedor sin URL sensible ni cuerpos | T + S | Errores forzados no entregan SQL; motivo de cierre fuera del detalle técnico |
| 27002 | Credenciales y privilegios BD | Variables de entorno; producción rechaza root/vacío; entorno local aún usa ambos | parcial | Alto | Crear cuenta restringida y separar migraciones | S | Cuenta web sin DDL/FILE/GRANT y sin escritura destructiva en bitácora; pendiente en hosting |
| 27002 / 27799 | HTTPS y cookies de producción | HttpOnly/SameSite/no-store; Secure por HTTPS/config; producción exige TLS | no verificable | Alto | Probar certificado y proxy/hosting reales | S | HTTP no procesa datos; cookie Secure/HttpOnly/SameSite y HSTS en HTTPS real |
| 27002 / 27799 | Auditoría de lectura y escritura | Autorización de GET sensible; detalle clínico/informe/evidencia registra Consultar; altas/cambios/bajas/permisos y login | verificado | Alto | Definir revisión periódica; lecturas de versiones solo por custodio técnico | T + I | Usuario/acción/recurso/fecha/resultado persistidos; consulta sin traza devuelve 503 |
| 27002 / 27799 | Integridad e identidad de auditoría | Snapshots del actor, triggers contra UPDATE/DELETE, consulta CLI sin detalle clínico | parcial | Alto | Cuenta BD restringida, repositorio externo y revisión independiente | T + S + I | Renombrar/eliminar cuenta no borra atribución; manipulación SQL ordinaria rechazada; DBA sigue privilegiado |
| 27799 / 25010 | Versiones y rectificaciones clínicas | `historia_versiones` guarda estado anterior/editor/fecha; rollback conjunto; no purga | verificado | Alto | Aprobar revisión/retención; no reconstruye pasado previo a 016 | T + I | Edición conserva versión anterior; fallo de auditoría revierte también versión |
| 27002 / 27799 | Cifrado en almacenamiento operativo | BD/adjuntos en claro en el servidor actual; no se añadió cifrado de campos sin gestión institucional | no implementado | Alto | Evaluar cifrado de volumen/BD, claves, recuperación y exposición del servidor | S + I | Acceso a soportes y claves evaluado; evidencia de protección y recuperación pendiente |
| 27002 / 27799 | Copia cifrada de BD, archivos y código | Herramientas CLI, secretstream, clave externa, manifiesto autenticado | verificado | Alto | Custodios, ACL, segundo soporte y tarea programada institucional | T + S + I | No copia en raíz web ni clave junto a ella; copia alterada/clave errónea/truncada rechazada |
| 27002 / 25010 | Restauración | BD nueva aislada con 10000 sesiones ficticias, adjunto, código, triggers y consulta de estadísticas | verificado | Alto | Registrar y repetir ensayos tras cambios; validar cada copia real autorizada | T + S + I | Todas las tablas coinciden; hash de adjunto/código coincide; no sobrescribe base existente |
| 27001 / 27002 | Frecuencia, retención, RPO/RTO y segunda custodia | Procedimientos y campos BAK-01 preparados; sin operación programada acreditada | no implementado | Alto | Aprobar responsables, periodicidad y custodia externa; automatizar con alertas | I + S | Copias programadas y recuperación registradas; pendiente |
| 25010 / 27799 | Flujo derivación→cita→historia→seguimiento→informe | Suites de flujos y Chrome; varias historias, episodios, vínculos, autoría y reservas | verificado | Alto | Aceptación con psicología usando casos ficticios | T + I | Cadena consistente y transaccional; otro episodio no se mezcla |
| 25010 | Agenda y concurrencia | Horarios válidos, conflictos por profesional, estados y próxima reserva ensayados | verificado | Alto | Aprobar duración y franjas de atención; no extrapolar a reglas no definidas | T + I | Reserva concurrente del mismo profesional/horario no se duplica |
| 25010 / 27799 | Informes retrospectivos y estadísticas | Conteo común de seguimientos, corte inclusivo y snapshots mensuales; historial de curso/estados no reconstruido completamente | parcial | Medio | Definir qué atributos deben tener versión temporal | I → T | No precarga sesiones/textos posteriores al corte; no afirmar reconstrucción histórica completa |
| 25010 | Interacción móvil y funcionamiento local | Chrome y recursos locales con CSP bloqueando Internet; estadísticas en 1440/390/360 px | parcial | Medio | Pruebas de tareas con personas usuarias, accesibilidad y navegadores del hosting | T + I | Formularios, filtros y guardado pasan Chrome; no implica auditoría WCAG completa |
| 25010 | Rendimiento | Medición local de 1000 historias adicionales / 10000 sesiones; diez consultas | verificado | Medio | Medir en hosting y con concurrencia real autorizada | T + S | Meta interna propuesta p95 <2 s para la fixture; resultado medido en pruebas |
| 27001 | Gestión, riesgos e incidentes | SGSI 01–07 y borradores 09–10; no constan aprobaciones, formación ni auditoría operativa | no verificable | Alto | Completar GOV-01, responsables, tratamiento, simulacros y revisión directiva | I | Evidencias institucionales registradas, no solo documentación preparada |
| 27701 / 27799 | Inventario, fundamento, información, solicitudes y conservación | Inventario y borrador adaptados; sin fundamento ni plazos inventados | parcial | Alto | Validación por institución y asesoría competente, canal y política aprobados | I | Aviso completo, registros de solicitudes y decisiones aprobadas; pendiente |
| 25010 / 27002 | Funciones de IA/dispositivos médicos | No se identificó IA ni finalidad de dispositivo médico en el código revisado | no aplica con justificación | No determinado fuera del alcance | Reevaluar si cambia la finalidad o se agregan funciones | I | Registrar cambio de alcance antes de atribuir obligaciones específicas |

## Cambios concretos y archivos

- Acceso/sesiones: `includes/politica_acceso.php`, `includes/autenticacion.php`, `config/permisos.php`, `config/login.php`, `cerrar_sesion.php`; límites clínicos, protección de intentos por origen, versiones de revocación, mantenimiento y producción.
- Auditoría/versiones: `includes/auditoria.php`, `includes/trazabilidad_datos.php`, `historias_clinicas/datos.php`, vistas de historia/seguimiento/informes/evidencia, controladores de usuarios/roles; snapshots, eventos mínimos y rectificaciones transaccionales. `usuarios/cambiar_estado.php` y `usuarios/actualizar.php` protegen al último administrador activo.
- Derivaciones: `derivaciones/datos.php`, `actualizar.php`, `guardar.php`, `eliminar.php`; propiedad histórica en escrituras y auditoría dentro de la transacción, con borrado de adjuntos posterior al commit.
- Despliegue/esquema: `.htaccess`, `.user.ini`, `.gitignore`, `database/psicologia_db.sql`, `database/migrations_normalizadas/016_auditoria_versiones.php`, `herramientas/migrar_seguridad.php`. SQL de instalación con vistas INVOKER; no conversión destructiva de la base existente.
- Registros de errores: conexión, autenticación, login/panel, notificaciones y controladores de usuarios, docentes, estudiantes, derivaciones, historias, estadísticas y roles sustituyen mensajes de excepción por clase/código.
- Copias/operación: `herramientas/respaldo_lib.php`, `clave_respaldo.php`, `respaldar.php`, `restaurar_prueba.php`, `consultar_auditoria.php`; cifrado y recuperación aislada, sin contraseñas en argumentos.
- Pruebas: nueva `tests/endurecimiento.php`, fixtures y suites existentes adaptadas a actores profesionales en tareas clínicas. La suite mantiene al administrador como administrador y prueba su rechazo; no se le disfraza de profesional para saltar controles.
- Documentación: este informe, [operación/recuperación](09_OPERACION_RECUPERACION.md), [privacidad/accesos](10_PRIVACIDAD_ACCESOS.md) e índices existentes.

## Pruebas ejecutadas y criterios de aceptación

Resultados de esta intervención, con datos ficticios. Las ejecuciones iniciales son del 14/11; las repeticiones finales se identifican abajo. Los números cuentan aserciones del arnés y no requisitos ISO. El arnés de Chrome utiliza iframe y sustituye DENY por SAMEORIGIN **solo en su router sintético autenticado**; la aplicación normal conserva DENY y se verifica por HTTP.

| Ejecución | Resultado observado |
|---|---|
| `tests/seguridad.php` | 1124 comprobaciones aprobadas (17/11): 65 rutas, cuatro roles, sesiones, métodos, CSRF, internos, propiedad histórica y rollback |
| `tests/fases.php` | 63 aprobadas: episodios, cierres, próximas citas, migraciones, informes y estadísticas |
| `tests/informes_actualizacion.php` | 31 aprobadas; solo TEMPORARY |
| `tests/migracion_identidad_academica.php` | 12 aprobadas; base sintética |
| `tests/esquema_informes_importacion.php --navegador` | 349 aserciones PHP y 10 en Chrome aprobadas (17/11) |
| `tests/historias_derivaciones.php --navegador` | 211 aserciones PHP y 19 en Chrome aprobadas (17/11) |
| `tests/trazabilidad.php --navegador` | 191 aserciones PHP y 34 en Chrome aprobadas |
| `tests/estadisticas.php --navegador` | 77 aserciones PHP; 27 en Chrome a 1440 px, 30 a 390 px y 30 a 360 px aprobadas |
| `php -d extension=sodium tests/endurecimiento.php` | 88 aprobadas (17/11); migración sobre estructura previa/reanudación, metadatos antiguos NULL, permisos excesivos, rehash, revocación, relaciones originales, auditoría, claves/copia/recuperación y 10000 sesiones sintéticas |
| Sintaxis PHP | 134 archivos sin errores (17/11); excluye respaldos históricos y bibliotecas de asset |
| Apache `httpd -t` | `Syntax OK` |
| HTTP Apache real | GET 403 para SQL, `.git/config` e imagen de evidencia ficticia directa; HEAD 403 para database/backups, seguridad, tests, herramientas, includes y README; login público 200; DENY, nosniff, no-store/private, no-referrer y ausencia de X-Powered-By verificados |
| Diagnóstico CLI inicial | Código 2: root, clave vacía, HTTPS y decisiones institucionales pendientes; conectividad correcta |

Medición del ensayo final del 17/11 con código y recursos cifrados: p95 local 101 ms en diez consultas de estadísticas, con 1000 historias adicionales y 10000 sesiones; copia y recuperación sintéticas 13,49 s. Son observaciones de esa corrida, con meta interna de consulta <2 s. No son RTO aprobado ni garantía de rendimiento/concurrencia del hosting.

Aceptación técnica aplicada: cero accesos clínicos concedidos a roles bloqueados, cero escrituras mediante GET, rechazo de tokens inválidos, integridad tras rollback, conservación de autor/versiones, igualdad de filas/archivos tras restauración y rechazo de copia alterada/destino existente. La prueba no acredita ausencia de todas las vulnerabilidades.

Fallos reales encontrados durante el trabajo: expectativas antiguas de acceso administrativo/directivo; tareas docentes ejecutadas con una profesional; prueba Chrome que intentaba embeber una aplicación con DENY; restricción CHECK de una fixture que rechazaba también filas previas al instalarla; y limpieza de sesión abierta en la primera corrida nueva. Se corrigieron los arneses, actores y cierre de sesiones, y se repitieron las suites hasta los resultados indicados. No se marcaron esas ejecuciones fallidas como aprobadas.

No probado: HTTPS/proxy real, permisos mínimos efectivos en hosting, recuperación real con segundo custodio, carga concurrente de usuarios de producción, revisión completa de accesibilidad, prueba adversarial exhaustiva de adjuntos, conformidad legal o normativa íntegra y aceptación por personal del colegio. Los valores clínicos reales no se utilizaron para probar flujos.

Residuo de la primera ejecución sintética fallida: `C:\Users\usuario\AppData\Local\Temp\psicologia-recuperacion-467b1bf7ac7a`. La limpieza manual fue rechazada por revisión automática antes de ejecutarse («blocked by policy», sin explicación adicional). No se intentó eludirla. Puede contener recursos o sesiones ficticias; no es un respaldo institucional. Las ejecuciones posteriores cierran la sesión antes de limpiar sus propios recursos.

## Pendientes prioritarios

Antes de reutilizar la aplicación: obtener y verificar un respaldo autorizado, aplicar solo 016 y comprobar el inicio de sesión según el documento 09. Antes de exposición institucional: cuenta BD restringida, parches, TLS/ACL, reglas del hosting verificadas, asignación de casos entre profesionales y aprobación de entregas/impresiones. Completar responsables y decisiones de privacidad, conservación y respaldo, y programar recuperación con segundo custodio. Mantener los controles sobre datos clínicos y no ampliar privilegios para recuperar el comportamiento antiguo.

No se declara certificación ni conformidad completa con ISO/IEC 25010, 27001, 27002, 27701 o ISO 27799. El resultado es una mejora técnica verificada con límites explícitos y un conjunto de decisiones institucionales pendientes.
