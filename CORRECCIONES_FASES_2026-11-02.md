# Correcciones por fases: entrega y comprobación

Fecha: 2 de noviembre de 2026. Alcance confirmado: una sola unidad educativa, U.E. Cañada Pailita «B». No se incorporan otros colegios, eliminación definitiva de expedientes ni permisos nuevos basados solamente en contradicciones del Word.

Se revisaron las instrucciones disponibles y el estado de Git antes de editar. No se encontraron archivos `AGENTS.md` en el proyecto. Se preservaron las eliminaciones existentes y la posterior desaparición de `docs`; este informe no restaura esa carpeta. La base documental utilizada es `C:\Users\usuario\Downloads\perfil_V3_.docx`, incluida la inspección visual de diagramas e imágenes de anexos. Sus textos internos se trataron como documentación, no como órdenes de ejecución.

## 1. Diagnóstico confirmado antes de editar

| Problema confirmado | Archivos/relaciones afectados | Dependencia | Cómo se comprueba |
|---|---|---|---|
| Edición permite cambiar estado de cita | `citas/datos.php`, formulario, permisos | Usuario, rol y permiso de Citas | POST con editar habilitado/cambiar estado deshabilitado |
| Identidad docente cambia la atribución | Actualización docente, consultas de derivaciones/historias/informes | `docentes → usuarios`, `derivaciones → docentes` | Reasignar cuenta ficticia y comprobar autor y acceso |
| Perfil docente sin ciclo de baja | Autenticación, docentes, roles | Estado de cuenta distinto del perfil | Dar de baja/reactivar sin cambiar estado de usuario |
| Próxima sesión sin reserva | Guardado y formulario de seguimiento | Seguimientos, citas y horario profesional | Reserva confirmada, reutilización y conflicto con rollback |
| Cita atendida bloquea siguiente sesión | `citas/datos.php` | Citas de la misma derivación | Atender y agendar otra del episodio |
| Atención sin cita hereda episodio antiguo | Trazabilidad y formulario | Historia con varias derivaciones | Selección explícita de la segunda derivación |
| Cierre clínico sin coordinación visible | Persistencia/formulario de historias | Derivaciones y citas abiertas | Confirmación y motivo; conservar pendientes |
| Atenciones e informes retrospectivos inconsistentes | Informes y estadísticas | Fecha de atención, historia y seguimiento | Corte inclusivo, conteo recalculado en servidor |
| Resumen mensual sin flujo | Informes, tabla de resumen | Consulta estadística y numeración | Generar, consultar/imprimir y comparar filtros/totales |
| Calendario y filtros ausentes | Citas, informes, derivaciones | Lectura de citas y filtros documentados | Navegación mensual, fecha, prioridad y rangos inválidos |
| Bibliotecas externas | Cabecera/pie y `asset/vendor` | CSS, JS y fuentes | Chrome con perfil nuevo y CSP que bloquea recursos externos |

## 2. Entrega de la fase 1: habilitación de pruebas

La conexión se configuraba a `127.0.0.1:3306`; el proceso MySQL estaba detenido. Se inició `mysqld.exe` con el `my.ini` existente de XAMPP, en segundo plano. No se cambiaron usuarios, contraseñas ni configuración de acceso. MySQL queda disponible para el uso local.

Antes de editar pasaron **132 comprobaciones de trazabilidad** y **1.041 de seguridad**. Las suites crean bases `psicologia_test_*` o tablas temporales; no se insertaron pacientes, cuentas ni sesiones ficticias en la base real.

Dependencias verificadas: PHP 8.2/XAMPP, MariaDB y Chrome instalado. La falta de conexión de la revisión anterior no se clasificó como defecto del código.

## 3. Entrega de la fase 2: permisos y atribución

Cambios:

- `citas/datos.php` comprueba en servidor el permiso de cambiar estado al atender, cancelar o reprogramar. Reprogramar identifica el estado Reprogramada. Una cita atendida/cancelada conserva fecha, hora y estado.
- `citas/cancelar.php` centraliza cancelación y gestión de estado/horario con ese permiso y descarta observaciones u origen remitidos en un POST manipulado. Editar datos sigue requiriendo el permiso de edición. Se eliminó el endpoint separado de cambio de estado de citas.
- `docentes/datos.php`, `docentes/cambiar_estado.php`, listado/detalle, roles y permisos implementan baja/reactivación del perfil. Se muestran por separado perfil y cuenta.
- La autenticación exige un perfil activo y válido para un docente y revalida ese vínculo en la sesión.
- La migración conserva cuenta y nombres del autor de las derivaciones. La reasignación de un perfil no transfiere acceso a derivaciones de la cuenta anterior. Registro de derivación y reasignación bloquean el perfil durante sus escrituras.
- Se actualizaron las consultas de autoría en derivaciones, citas, historias, informes y vista SQL.

Tablas: `derivaciones`, `docentes`, `usuarios`, `permisos`, `auditoria`. Los cambios de estructura están en la migración 013. Pruebas: `tests/fases.php`, POST reales y matriz de seguridad, incluidas cuentas Administrador, Psicóloga, Docente y Director.

Limitación: la carga inicial conserva el autor que resulta de las relaciones existentes. No recupera identidad ya perdida en una reasignación anterior a la actualización.

## 4. Entrega de la fase 3: continuidad clínica

Cambios en `includes/trazabilidad_datos.php`, `includes/trazabilidad_vista.php`, `seguimientos/`, `citas/datos.php` y `historias_clinicas/`:

- Un seguimiento guarda su derivación de forma explícita; la cita aporta su episodio y se valida la coincidencia. Si no hay cita y existen varias derivaciones abiertas, el servidor exige elegir. Con una sola abierta puede resolverla; sin ninguna se permite la atención sin derivación, como el sistema anterior.
- Una cita Atendida no bloquea una nueva cita del episodio; Pendiente/Reprogramada sí representan una reserva vigente.
- «Fecha orientativa» no reserva. Para confirmar la próxima cita se requieren casilla de confirmación, fecha, hora y profesional correspondiente. Se valida disponibilidad y permiso de crear citas.
- Se reutiliza una reserva compatible del mismo estudiante, episodio, profesional y horario. Un conflicto revierte seguimiento, estados y reserva conjuntamente.
- **Decisión expresa del usuario:** cerrar con advertencia y motivo, conservando registros abiertos. La edición muestra los identificadores de citas/derivaciones pendientes y exige confirmar su conservación. El motivo se guarda y audita. No se cancelan ni cierran relaciones silenciosamente.
- Una historia Cerrada no admite sesiones hasta reabrirse. El detalle permite consultar el motivo del último cierre.

Tablas: `seguimientos.cod_derivacion`, `seguimientos.cod_proxima_cita`, `historias_clinicas.motivo_cierre`, citas y auditoría. Pruebas: episodios múltiples, sesiones sucesivas, atención con/sin cita, reserva repetida, conflictos, rollback, cierre sin motivo/sin confirmación y reapertura.

## 5. Entrega de la fase 4: informes y estadísticas

**Criterio único:** una atención es una fila de `seguimientos`. Una cita programada no es atención; una marca administrativa Atendida tampoco agrega una sesión clínica. La apertura de una historia no agrega una sesión automáticamente. Los seguimientos con cita cuentan una sola vez.

- `informes/datos.php` calcula en servidor las atenciones hasta la fecha del informe; ignora un conteo enviado por el formulario. También recalcula al editar.
- `informes/antecedentes.php` y el formulario actualizan las relaciones y los datos precargados al cambiar fecha o estudiante. No seleccionan sesiones posteriores.
- Para informes individuales se muestra el acumulado hasta su fecha; para mensuales y estadísticas del mismo rango se muestran sesiones del período. No comparar un acumulado individual con un subtotal mensual.
- Los informes antiguos conservan su número guardado. La consulta/listado calcula las atenciones con el criterio común; si difiere del valor histórico, el detalle muestra ambos y explica la conservación del original. No se reescribieron esos documentos durante la migración.
- `informes/mensual.php`, `guardar_mensual.php` y `ver_mensual.php` generan, guardan, consultan e imprimen resúmenes. Reutilizan `estadisticas_datos`, sus filtros de curso/estado y su numeración transaccional. Director consulta; no genera.
- `informe_resumen` conserva sesiones, filtros y totales al generar. El mes actual se corta en hoy. Una consulta actualizada después puede diferir de una instantánea guardada si hubo nuevos registros o correcciones.

**Fechas y límites históricos:** la fecha clínica `seguimientos.fecha` determina la inclusión, incluso para sesiones registradas después con fecha anterior. Historias y derivaciones no tienen versiones: sus textos no se precargan si la última actualización es posterior al corte. Esto no reconstruye cómo eran antes. Los estados y el contexto escolar se consultan como están al generar; el informe mensual lo indica y conserva su consulta. El usuario sigue siendo responsable del contenido que redacta manualmente.

Pruebas: mes vacío, primer/último día, corte del mes actual, registros posteriores, curso, permisos, numeración, guardado, rollback y correspondencia con estadísticas. El informe individual conserva su ficha y relaciones al editar.

## 6. Entrega de la fase 5: calendario y filtros

- `index.php`, sección **Agenda y calendario**: calendario mensual real, navegación entre meses, consulta diaria, estados escritos y profesional. Permite abrir la cita si se puede editar y programar desde una fecha válida si se puede crear. Estado/horario se gestiona en esa sección mediante `citas/cancelar.php`, respetando su permiso independiente de editar.
- Se retiró la pantalla separada de calendario y su entrada del menú. El listado enlaza al calendario del panel. Las consultas se reutilizan desde `citas/datos.php`; no se concedió acceso a Docente/Director. El listado diario incluye citas canceladas identificadas expresamente para coincidir con los conteos del mes.
- Informes individuales: filtro por fecha inicial/final, además de búsqueda, curso y estado existentes.
- Derivaciones: prioridad y rango de fechas, además de búsqueda, curso y estado existentes.
- Los filtros nuevos se validan/aplican en servidor; no aceptan fechas inválidas, rangos invertidos ni prioridades desconocidas. Los conteos de esos listados se basan en sus consultas filtradas. No se añadió una exportación inexistente.

Pruebas HTTP de inclusión/exclusión y errores, permisos, programación por fecha y calendario en Chrome a **320, 390, 768, 1024 y 1440 px** sin desbordamiento horizontal del documento. En pantallas pequeñas la agenda precede al seguimiento prioritario.

## 7. Entrega de la fase 6: operación local

Se conservaron las versiones ya usadas: Bootstrap 5.3.0, Bootstrap Icons 1.10.0, jQuery 3.7.1 y DataTables 1.13.8. Se descargaron CSS/JS/fuentes a `asset/vendor`, con las licencias MIT originales; se actualizaron `includes/header.php` y `includes/footer.php`. Procedencia y versiones: `asset/vendor/README.md`.

La revisión actual confirmó que **login ya utilizaba recursos propios locales**; la afirmación amplia del primer análisis sobre dependencias externas de login no se reprodujo. Las dependencias externas confirmadas estaban en la cabecera/pie comunes.

Se probó en Chrome con un perfil temporal nuevo y CSP `default-src 'self' 'unsafe-inline' data:; connect-src 'self'`, que bloquea recursos externos manteniendo el servidor local. Pasaron formularios del flujo clínico, informes, calendario, fuentes de iconos y bibliotecas locales. No se deshabilitó la conexión de todo Windows ni se implementó uso desconectado del servidor/sincronización.

## 8. Entrega de la fase 7: cotejo documental

Se revisó el texto y una hoja de contacto de las 40 imágenes del Word, además de ampliar las fotografías de los anexos de historia e informe. No se publican copias de las fotografías con información identificable.

| Diferencia | Resultado del cotejo | Acción |
|---|---|---|
| Varios colegios | El original de Descargas conserva menciones antiguas en conclusiones; el usuario excluyó expresamente esa parte | Ajustar/eliminar esas menciones del documento que se vaya a entregar. Ningún cambio de código para otros colegios. La copia corregida anterior desapareció junto con `docs` y no se restauró. |
| Docente y estudiantes | Diagrama general y CU de estudiantes le asignan operaciones más amplias que la identificación de actores y las restricciones actuales | Unificar matriz en el documento. No se habilitaron nuevos permisos estudiantiles. |
| Eliminación definitiva | Diagramas de paquetes representan eliminar usuario/docente/historia/informe, mientras los requisitos favorecen conservación | Ajustar diagramas/pruebas; no implementar borrado clínico para satisfacer dibujos antiguos. |
| Modelo de datos | Diagramas de clases muestran campos/identificadores y relaciones que no reflejan identidad en usuarios, escolaridad normalizada ni nuevos vínculos explícitos | Actualizar UML y diccionario con esquema vigente y migración 013. No se generaron diagramas sustitutos sin su fuente editable. |
| Anexos C/D de historia | Se reconocen datos generales, motivo, situación escolar, riesgos, conducta, estrategias, familia, resultados, acuerdos y evolución | Correspondencia conceptual con campos y seguimientos del sistema; no es certificación de igualdad gráfica de la impresión. |
| Anexo E de informe | Fotografía de ficha con atenciones, tipos, referente, motivo, aspectos cognitivos/afectivos, acuerdos y recomendaciones | Se corrigió la etiqueta «Anexo 4» por «Anexo E». La impresión usa HTML del sistema, no reproduce sellos o firmas del papel. |
| Pruebas declaradas aceptables | Hay operaciones declaradas probadas sin rutas equivalentes, además de conclusiones que mencionan evaluaciones pendientes | Sustituir afirmaciones por evidencias fechadas de esta entrega y decisiones pendientes de la tabla siguiente. |

No falta el Word ni sus anexos fotográficos. Sí faltan los archivos fuente editables de StarUML y los formularios originales editables para actualizar esos materiales y cotejar una reproducción gráfica exacta. No se modificó el Word original durante estas fases.

## 9. Estado de cada hallazgo del análisis anterior

«Corregido y verificado» se refiere al código y pruebas sintéticas de esta entrega. No afirma ensayos con pacientes reales ni certificación clínica. Los hallazgos adicionales que no forman parte de las correcciones ordenadas quedan visibles; no se presentan como resueltos.

| ID | Hallazgo | Estado | Resultado o decisión pendiente |
|---|---|---|---|
| H01 | Próxima sesión sin agenda | Corregido y verificado | Fecha orientativa separada de reserva confirmada transaccional. |
| H02 | Conteos distintos | Corregido y verificado | Seguimientos como atención; citas aparte; conteo en servidor y consulta histórica etiquetada. |
| H03 | Precarga posterior al informe | Corregido y verificado | Corte inclusivo y omisión de textos modificados después; sin reconstrucción de versiones. |
| H04 | Reatribución al reasignar cuenta | Corregido y verificado | Autor/copia de nombres conservados; acceso histórico no transferido. |
| H05 | Perfil inactivo con acceso inconsistente | Corregido y verificado | Perfil activo obligatorio; cuenta y perfil diferenciados. |
| H06 | Cita atendida sin registro clínico | Pendiente de decisión | Se mantiene marca administrativa independiente; no suma atenciones clínicas. Decidir si debe exigir sesión. |
| H07 | Apertura de historia distinta de primera sesión | Pendiente de decisión | No se inventa sesión al abrir expediente. Definir si la primera entrevista exige además seguimiento. |
| H08 | Cierre sin coordinación | Corregido y verificado | Advertencia, registros afectados, motivo y confirmación; conserva abiertos. |
| H09 | Cita atendida bloquea cita sucesiva | Corregido y verificado | Solo reserva Pendiente/Reprogramada bloquea otra cita del episodio. |
| H10 | Sesión sin cita hereda episodio antiguo | Corregido y verificado | Vínculo explícito y selección cuando hay varias derivaciones abiertas. |
| H11 | Roles personalizados frente a reglas profesionales | Pendiente de decisión | Se conserva la regla de programación por Administrador/Psicóloga. Definir capacidades profesionales antes de ampliar roles. |
| H12 | Editar cita permite cambiar estado | Corregido y verificado | Permiso independiente en servidor y acción de estado separada. |
| D01 | Calendario ausente | Corregido y verificado | Mes, día, citas reales y adaptación móvil. |
| D02 | Baja/reactivación docente ausente | Corregido y verificado | Endpoint POST/CSRF, control de permiso, auditoría y reactivación. |
| D03 | Filtro de fechas de informes | Corregido y verificado | Rango en servidor y validación. |
| D04 | Filtros de prioridad/fechas de derivaciones | Corregido y verificado | Aplicados en consulta, conservando propiedad docente. |
| D05 | Dependencia de Internet | Corregido y verificado | Recursos locales y prueba CSP con perfil nuevo. Login externo no reproducido. |
| D06 | Flujo de informe mensual ausente | Corregido y verificado | Generación, persistencia, consulta e impresión con filtros/totales comunes. |
| D07 | Modificar/rectificar seguimiento | Pendiente de decisión | No se añadió edición silenciosa. Definir corrección con historial o complemento. |
| D08 | Eliminar informe | Pendiente de decisión | No se añadió borrado definitivo. Definir anulación si se necesita; corregir prueba documental. |
| D09 | Finalizado sin bloqueo de edición | Pendiente de decisión | Se conserva comportamiento anterior. Definir cierre inmutable, reapertura o versiones. |
| D10 | Tipos/cantidad de evidencias | Pendiente de decisión | Se mantienen imágenes permitidas y último adjunto; no se amplían formatos por una redacción ambigua. |
| O01 | Apertura de gestión anual | Pendiente de decisión | Definir administración de gestiones/promociones; no incluida en las fases solicitadas. |
| O02 | Contexto escolar actual en informe histórico | Pendiente de decisión | Se advierte limitación; definir instantánea o escolaridad a la fecha antes de cambiar informes existentes. |
| O03 | Permiso de cierre de historia separado | Pendiente de decisión | Cerrar sigue siendo parte de editar historia, ahora con motivo/confirmación. No se creó un permiso no solicitado. |
| O04 | Posible rendimiento de listados completos | No reproducido | No se demostró lentitud con la revisión disponible; falta ensayo de carga representativo. |
| O05 | Visor/auditoría completa y respaldo integral | Pendiente de decisión | Se añadieron auditorías del cierre/perfil y respaldo SQL de migración; falta definir visor, retención y respaldo periódico de evidencias. |

## 10. Pruebas ejecutadas

| Suite final | Comprobaciones | Resultado |
|---|---:|---|
| `tests/seguridad.php` | 1.116 | Aprobada tras consolidar: 65 rutas, cuatro roles, métodos, CSRF, sesiones, propiedad y regresiones. |
| `tests/informes_actualizacion.php` | 31 | Aprobada: cabecera/detalle, relaciones, autor, auditoría y rollback. |
| `tests/migracion_identidad_academica.php` | 12 | Aprobada: migración previa de identidad/escolaridad. |
| `tests/esquema_informes_importacion.php --navegador` | 342 + 10 de Chrome | Aprobada: esquema/migración, CSV, estudiantes, docentes, concurrencia e interfaz. |
| `tests/historias_derivaciones.php --navegador` | 183 + 14 de Chrome | Aprobada: persistencia clínica, categorías, formularios y rollback. |
| `tests/trazabilidad.php --navegador` | 190 + 34 de Chrome | Aprobada: flujo, reservas concurrentes, episodios, permisos de estado, baja/reactivación, calendario integrado, navegación de mes/día, retorno al panel, filtros y reportes. |
| `tests/estadisticas.php --navegador` | 74 + 19 a 1440 px + 19 a 390 px | Aprobada: conteos, fechas, panel, filtros, privacidad y adaptación. |
| `tests/fases.php` | 44 | Aprobada: migración 013 con datos previos, calendario vacío/fechas inválidas, reservas/reutilización/rollback, episodios, cierre, períodos, cortes, manipulación de conteos y conservación de instantáneas. |
| Sintaxis PHP | `index.php`, `citas/cancelar.php` | Sin errores; comprobación complementaria de la consolidación, no sustituto de las pruebas anteriores. La revisión previa completa abarcó 123 archivos; después se retiraron dos. |
| Migración real | 9 operaciones; 0 pendientes posteriores | Aplicada con respaldo previo; 29 tablas de negocio preservan filas y columnas anteriores; 3 vistas consultables. |

Las suites antiguas se ajustaron únicamente donde cambió el contrato: estado del perfil docente, endpoints JSON, relación explícita de episodio y cálculo de atenciones. La prueba de fallo SQL del detalle de informe ahora fuerza un error en el texto de prueba, porque manipular el conteo ya no debe forzar escrituras inválidas. No se eliminaron verificaciones de rollback.

## 11. Comprobación local paso a paso

1. Iniciar Apache y MySQL en XAMPP. Abrir `http://localhost/proyecto_vercionII/login.php` con las cuentas habituales. No se crearon ni restablecieron credenciales reales.
2. En **Docentes**, revisar Perfil/Cuenta. Dar de baja un perfil: la cuenta mantiene su estado y no puede operar como docente. Reactivar y comprobar acceso. Realizar pruebas de reasignación solamente con cuentas/registros ficticios.
3. En **Roles y permisos**, retirar «Cambiar estado» de Citas a un rol de prueba que conserve Editar. Verificar que editar observaciones funciona y que cancelar/reprogramar se rechaza, también por POST. Con el permiso inverso, el calendario permite cambiar estado sin editar observaciones.
4. Abrir **Panel principal → Agenda y calendario**, cambiar mes, seleccionar un día y programar. Consultar Estado y Profesional en cada tarjeta. Cambiar estado/horario y comprobar el regreso al mismo día del panel. Repetir en celular.
5. En una historia de prueba con dos derivaciones abiertas, registrar seguimiento sin cita: seleccionar su episodio. Para la próxima sesión, probar primero solo fecha orientativa y después confirmar con hora/profesional. Repetir un horario ocupado: no debe guardarse la sesión parcial.
6. Atender una cita y programar otra del mismo episodio. Comprobar que los enlaces de seguimiento/derivación conservan el origen correcto.
7. Editar una historia con pendientes y elegir Cerrada. Sin motivo/confirmación debe rechazarse. Con ambos debe cerrar y conservar las citas/derivaciones. Reabrir para registrar nuevas sesiones.
8. En **Informes**, cambiar la fecha a un día anterior a las sesiones del estudiante: no deben precargarse esas sesiones ni recomendaciones. Comprobar el total con Estadísticas usando el mismo corte y definición.
9. En **Informes mensuales**, generar mes/curso/estado; abrir el enlace a estadísticas con los mismos filtros y comparar atenciones. Director debe poder consultar/imprimir, no generar.
10. Probar rangos de fechas/prioridad en listados. Desconectar Internet manteniendo la red local o bloquear recursos externos en el navegador: probar con caché vacía, sin desconectar el dispositivo del servidor.

Para repetir la verificación automática desde la raíz:

```powershell
& C:\xampp\php\php.exe tests\fases.php
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\informes_actualizacion.php
& C:\xampp\php\php.exe tests\migracion_identidad_academica.php
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php --navegador
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\estadisticas.php --navegador
& C:\xampp\php\php.exe database\migrar.php --base=psicologia_db --comprobar
```

Los detalles de aplicar/revertir la migración y la ubicación del respaldo están en [database/CONTINUIDAD_013.md](database/CONTINUIDAD_013.md). La validación manual con usuarios reales, la impresión física y una medición de rendimiento representativa siguen siendo tareas operativas; no se presentan como ensayos realizados.
