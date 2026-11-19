# Análisis preliminar de aplicabilidad y brechas ISO

Fecha: 13 de noviembre de 2026. Sistema: seguimiento psicológico estudiantil.

## Dictamen

Actualización de implantación: se preparó la [base documental y operativa inicial del SGSI](seguridad/README.md), con riesgos, tratamientos, procedimientos y diagnóstico técnico. Consultar [la evidencia de esta etapa](seguridad/07_EVIDENCIA_INICIAL.md). Las referencias siguientes a políticas o procedimientos pendientes deben leerse ahora como pendientes de aprobación, ejecución y validación; la preparación documental no cierra por sí sola las brechas.

El repositorio contiene controles técnicos alineados con objetivos de seguridad, integridad y calidad. **No hay evidencia suficiente para declarar conformidad completa con ninguna de las normas consideradas ni para afirmar que el sistema está certificado.** Esto no equivale a demostrar que incumple todos sus requisitos: existen aspectos implementados y otros pendientes de evaluación.

No corresponde exigir todas las ISO existentes. La selección depende de la actividad, los datos tratados, el despliegue y el alcance institucional. Las normas de gestión evalúan procesos de la organización; las de modelos de calidad o guías de controles no son, por sí mismas, certificados del programa.

## Método y límites

Se revisaron README.md, la revisión preliminar del 10 de noviembre de 2026 (conservada en el historial de Git), tests/README.md, autenticación, configuración de conexión, permisos, trazabilidad, formulario y persistencia de historias clínicas y estructura SQL. Se consultaron catálogos oficiales para identificar normas y ediciones. No se realizó una auditoría cláusula por cláusula contra los textos normativos completos.

La revisión cubre el estado de trabajo local, con modificaciones preexistentes. No se modificó código de aplicación. No se inspeccionaron el servidor de producción, datos reales, contratos, políticas externas, certificados ni prácticas del personal. La ausencia de evidencia en el repositorio no demuestra ausencia en la institución.

El primer intento de `C:\xampp\php\php.exe tests\seguridad.php` terminó con código 1 al rechazar MySQL la conexión a 127.0.0.1:3306. El servidor de pruebas devolvió HTTP 500 durante la preparación. En una comprobación posterior del mismo día, MariaDB ya estaba activo; su registro muestra un arranque posterior al intento fallido. PHP conectó correctamente a psicologia_db y verificó acceso a usuarios, roles, permisos e historias_clinicas. Por HTTP, login.php respondió 200 e index.php sin sesión respondió 303 hacia login.php.

**La repetición de tests/seguridad.php terminó correctamente: 1116 comprobaciones aprobadas**, con tablas TEMPORARY de conexión. Incluyó matriz de 65 rutas, cuatro roles, métodos, CSRF, sesiones, archivos internos, propiedad de derivaciones, cancelación de citas, retiro/reactivación, rollback y formularios. No fue necesario modificar la configuración de conexión. Las demás suites siguen siendo antecedentes documentales y no fueron ejecutadas en esta comprobación. Este resultado aporta evidencia técnica y no cambia por sí solo el dictamen de conformidad ISO.

## Normas prioritarias

En esta tabla «evidencia parcial» describe implementación observada; no significa cumplimiento parcial cuantificado de la norma.

| Norma y aplicación | Evidencia observada | Brechas o evidencia necesaria | Dictamen |
|---|---|---|---|
| ISO/IEC 27001:2022, gestión de seguridad de la información | Autenticación, permisos, sesiones y trazabilidad | Alcance del SGSI, inventario, evaluación y tratamiento de riesgos, declaración de aplicabilidad, responsables, incidentes, auditoría interna y revisión directiva | Controles técnicos parciales; conformidad del SGSI no demostrada |
| ISO/IEC 27002:2022, guía de controles de seguridad | CSRF, consultas preparadas en flujos revisados, hash, cookies y cabeceras | Selección de controles según riesgos, protección operativa, privilegios de BD, registros protegidos, revisión de accesos y recuperación | Alineación técnica parcial; guía, no certificación autónoma del software |
| ISO/IEC 27701:2025, gestión de privacidad | Restricción de acceso a información personal | Inventario de tratamientos, finalidades y fundamento aplicable, información a estudiantes/tutores, minimización, conservación, derechos, proveedores y responsabilidades | Conformidad no demostrada |
| ISO 27799:2025, controles de seguridad para información de salud | Historias y seguimientos restringidos; registro de ciertos cambios | Confidencialidad de expedientes psicológicos, acceso por necesidad profesional, trazabilidad de consultas, divulgaciones, contingencias y protección de copias | Muy pertinente por el tipo de información; alineación parcial |
| ISO/IEC 25010:2023, modelo de calidad del producto | Módulos funcionales, validaciones y suites documentadas | Requisitos y mediciones de adecuación funcional, eficiencia de desempeño, compatibilidad, capacidad de interacción, fiabilidad, seguridad, mantenibilidad, flexibilidad y seguridad operacional | Calidad no evaluada de forma completa y medible |
| ISO/IEC 25012:2008, modelo de calidad de datos | Validación de fechas/textos, relaciones SQL y transacciones | Indicadores de exactitud, completitud, consistencia y actualidad; revisión autorizada de datos reales, responsables y corrección de errores | Evidencia estructural parcial; calidad real no verificada |
| ISO 22301:2019, continuidad del negocio | Transacciones y rollback protegen algunas escrituras | Análisis de impacto, continuidad del servicio, objetivos de recuperación, copias externas protegidas y simulacros de restauración | Continuidad no demostrada; rollback no sustituye respaldos |
| ISO 9241-210:2019, diseño centrado en las personas | Formularios con etiquetas; pruebas de interfaz documentadas | Usuarios y contexto de uso definidos, evaluación con psicóloga/docentes, tareas y criterios de éxito, iteraciones y accesibilidad | Proceso de diseño no demostrado; no se auditó visualmente la interfaz en esta revisión |
| ISO/IEC/IEEE 12207:2026, procesos del ciclo de vida del software | Git, documentación, migraciones y pruebas | Requisitos trazables, aceptación, gestión de cambios, mantenimiento, configuración y retiro definidos | Evidencia de ingeniería parcial; procesos completos no demostrados |
| ISO/IEC 20000-1:2018, gestión de servicios TI | Instrucciones locales de instalación y operación | Responsables de soporte, niveles de servicio, incidentes, problemas, cambios y seguimiento operativo | Pertinente si se presta como servicio institucional; no demostrado |
| ISO 9001:2026, gestión de calidad institucional | Documentación técnica y correcciones registradas | Alcance organizativo, procesos, objetivos, satisfacción, gestión de no conformidades, auditorías y mejora | Aplicabilidad organizativa; conformidad no demostrada por el código |

Considerar las enmiendas aplicables al fijar el alcance formal, incluidas las de 2024 en normas de gestión cuando correspondan. Las ediciones se identifican para esta fecha; las transiciones de certificados existentes deben comprobarse por separado.

## Hallazgos concretos

| Prioridad interna | Evidencia y conclusión | Acción y forma de comprobarla |
|---|---|---|
| Alta | config/login.php admite variables de entorno, pero usa root y clave vacía como valores locales predeterminados. No se verificó la cuenta efectiva de producción | Crear cuenta de ejecución con permisos mínimos; verificar permisos efectivos y separación respecto de la cuenta de migraciones |
| Alta | includes/autenticacion.php configura HttpOnly, SameSite=Lax, sesiones estrictas, regeneración y no-store. Secure se activa por configuración o detección HTTPS; no se comprobó TLS real | Verificar HTTPS y cookies en el despliegue; documentar terminación TLS y configuración del servidor |
| Alta | No se encontró evidencia operativa de copias cifradas, restauración ni tiempos de recuperación en el material revisado | Aprobar objetivos de recuperación y restaurar una copia en entorno aislado, registrando integridad, tiempo y responsable |
| Alta | Se almacenan datos psicológicos y familiares. No se encontró en los archivos revisados un procedimiento completo de finalidad, información, conservación y eliminación | Acordar política institucional y procedimiento aplicable a menores; determinar cuándo corresponde consentimiento u otra justificación, sin asumir que una casilla resuelve la privacidad |
| Alta | config/permisos.php e includes/autenticacion.php controlan rutas y acciones. El administrador conserva acceso amplio en la resolución de permisos | Validar necesidad del acceso administrativo al contenido clínico; ensayar URLs e identificadores manipulados y roles personalizados contra una matriz aprobada |
| Media | historias_clinicas/datos.php registra guardados en auditoria; incluye transacciones, bloqueo y rollback. La lectura en historias_clinicas/ver.php exige permiso, pero no se identificó allí un evento de consulta | Definir eventos auditables, verificar lecturas y exportaciones, proteger registros contra cambios y asignar revisión periódica |
| Media | El cierre de historia incorpora motivoCierre en el detalle de auditoría | Revisar sensibilidad de ese texto y quién puede consultar auditoria; limitar detalles clínicos en registros técnicos |
| Media | El formulario usa CSRF, escape mediante login_html y etiquetas; datos.php valida en servidor y usa consultas preparadas en el flujo revisado | Ejecutar pruebas funcionales y de entradas maliciosas; no extender esta observación a todas las consultas o salidas del sistema sin revisarlas |
| Media | Autenticación usa password_verify, rehash, límite por cuenta y revocación de sesiones por cambios relevantes | Probar concurrencia y bloqueo; evaluar controles adicionales según riesgos. La norma no se satisface únicamente con una longitud de contraseña ni exige universalmente el mismo mecanismo a todos los sistemas |
| Media | Los datos de recuperación del formulario se conservan temporalmente en sesión y el selector incluye atributos personales de estudiantes | Evaluar minimización de datos enviados al navegador y protección/limpieza del almacenamiento de sesiones en el servidor |

Las prioridades anteriores son recomendaciones de esta revisión, no categorías de no conformidad emitidas por un auditor.

## Normas condicionadas al alcance

- **ISO/IEC 27017:2026 e ISO/IEC 27018:2025:** pertinentes si se contratan servicios de nube y, para 27018, se tratan datos personales como encargado en nube pública. No se identificó ese despliegue en esta revisión. Catálogo oficial: https://www.iso.org/committee/45306/x/catalogue/
- **Familia ISO/IEC/IEEE 29119:** puede organizar procesos, documentación y técnicas de pruebas; disponer de tests PHP no demuestra conformidad con toda la familia. Deben seleccionarse partes y ediciones según el plan de evaluación.
- **ISO 13485 e ISO 14971:** analizar si la finalidad prevista y clasificación aplicable convierten el producto en dispositivo médico. Gestionar expedientes psicológicos no basta para atribuir esa condición. No se evaluó clasificación regulatoria.
- **ISO/IEC 42001:** analizar si se incorpora un sistema de inteligencia artificial; no se identificó esa función en el alcance revisado.
- Normas ambientales, energía o seguridad laboral pueden corresponder a la institución, pero no se pueden evaluar mediante este repositorio.

Esta selección cubre las áreas pertinentes identificadas; no pretende inventariar todas las normas ISO ni afirmar aplicabilidad universal.

## Orden de trabajo recomendado

1. Definir alcance, institución responsable, usuarios, datos y entorno real. Priorizar 27001/27002, 27701, 27799 y 25010.
2. Verificar cuenta de BD restringida, HTTPS, acceso a expedientes y copias; aprobar privacidad y conservación de información.
3. Seguridad ya se repitió satisfactoriamente tras comprobar MySQL; continuar con historias_derivaciones y trazabilidad en bases sintéticas. Registrar versión del código, entorno, resultados y fallos sin exponer datos clínicos.
4. Probar restauración y completar registros de lectura, cambios, divulgaciones e incidentes según el alcance acordado.
5. Crear matriz requisito → control/procedimiento → evidencia → responsable → resultado. Para calidad, fijar umbrales de rendimiento, tareas de usuario y criterios de aceptación antes de medir.
6. Realizar evaluación contra las normas completas y auditoría institucional si se requiere certificación. No calcular porcentajes sin criterios y denominadores definidos.

## Fuentes oficiales

Catálogos y resúmenes de alcance consultados; no sustituyen los textos completos:

- ISO/IEC 27001: https://www.iso.org/standard/27001
- ISO/IEC 27002:2022: https://www.iso.org/standard/75652.html
- ISO/IEC 27701:2025: https://www.iso.org/standard/27701
- ISO 27799:2025: https://www.iso.org/standard/84647.html
- ISO/IEC 25010:2023: https://www.iso.org/standard/78176.html
- ISO/IEC 25012:2008: https://www.iso.org/standard/35736.html
- ISO 22301:2019: https://www.iso.org/standard/75106.html
- ISO 9241-210:2019: https://www.iso.org/standard/77520.html
- ISO/IEC/IEEE 12207:2026, ficha IEEE: https://standards.ieee.org/ieee/12207/11416/
- ISO/IEC 20000-1:2018: https://www.iso.org/standard/70636.html
- ISO 9001:2026: https://www.iso.org/standard/9001

Conclusión utilizable: el sistema dispone de medidas técnicas que apoyan objetivos ISO de seguridad y calidad. La conformidad normativa completa permanece sin demostrar y requiere evidencias organizativas, operativas y de evaluación adicionales.
