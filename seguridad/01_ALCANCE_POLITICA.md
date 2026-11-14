# Alcance, política y responsabilidades

Documento SGSI-01 · Versión y estado: [índice](README.md).

## Alcance propuesto

Gestión, uso, mantenimiento y recuperación de la información tratada por la aplicación de seguimiento psicológico estudiantil: estudiantes, docentes, usuarios, roles, derivaciones, citas, historias clínicas, seguimientos, informes y estadísticas. Comprende PHP, MariaDB, Apache/XAMPP, configuración, repositorio, equipos autorizados, sesiones, registros, exportaciones y respaldos asociados, así como las personas que los administran y utilizan.

Ubicación técnica observada: `C:\xampp\htdocs\proyecto_vercionII`. Dirección debe identificar sede física, equipos, redes, ubicación de copias y eventual alojamiento remoto en GOV-01. El despliegue de producción no se ha identificado. La dirección local del proyecto no delimita por sí sola el alcance organizativo.

Interacciones: Dirección define necesidades y autorizaciones; psicología custodia el contenido clínico; docentes originan derivaciones; administración mantiene cuentas y plataforma. Estudiantes y tutores aportan información. Registrar proveedores y accesos de soporte antes de habilitarlos.

Otros sistemas escolares quedan fuera del alcance propuesto, pero sus intercambios de información con esta aplicación deben registrarse y evaluarse. Los documentos impresos o exportados del sistema y sus custodios permanecen dentro del tratamiento de riesgos.

## Contexto y partes interesadas

| Parte / condición | Necesidad que debe validarse | Evidencia requerida |
|---|---|---|
| Estudiantes y tutores | Confidencialidad, exactitud y uso justificado de información | Información comunicada y procedimiento de solicitudes |
| Psicología | Acceso oportuno, integridad de historia y continuidad | Matriz funcional y plan de recuperación |
| Docentes | Derivaciones según autorización y propiedad | Pruebas de permisos y formación |
| Dirección | Informes necesarios y rendición de cuentas | Revisión de contenido compartido y decisiones |
| Administración y proveedores | Operación controlada y accesos trazables | Responsabilidades, acuerdos y revisiones |
| Obligaciones legales, contractuales y profesionales | Determinar requisitos aplicables a datos de salud y menores | Registro LEG-01 validado por responsable competente; no asumir jurisdicción o plazos solo por la configuración horaria |
| Energía, temperatura, inundación y otros eventos físicos | Evaluar interrupción y pérdida de equipos/copias | Dirección determina y documenta si el cambio climático es relevante para el contexto y si existen requisitos de partes interesadas relacionados; consideración vinculada a la [enmienda de 2024](https://www.iso.org/standard/88435.html) |

## Política propuesta

La institución protege confidencialidad, integridad y disponibilidad de la información; cumple los requisitos aplicables identificados; proporciona recursos y mejora continuamente el SGSI. Autoriza accesos por necesidad de la función, con cuentas individuales, revisión periódica y retirada al finalizar la relación. Prohíbe compartir claves y usar datos clínicos reales en pruebas sin un tratamiento específicamente autorizado.

Clasifica expedientes psicológicos, datos familiares, credenciales y copias como restringidos. Limita su consulta, envío y reproducción a destinatarios autorizados mediante canales aprobados. Conservación y eliminación se deciden según requisitos y riesgos documentados, sin fijar borrado automático antes de esa evaluación.

Los cambios se revisan y prueban antes de su publicación. Los incidentes se comunican por el canal institucional designado; su análisis protege a las personas afectadas y conserva evidencia mínima. Los riesgos residuales se aceptan por sus propietarios y conforme al método aprobado. La política se comunica al personal y se revisa al menos anualmente o ante cambios sustanciales.

## Responsabilidades propuestas

| Función | Responsabilidad | Designación |
|---|---|---|
| Dirección | Aprobar política, alcance, recursos y decisiones de riesgo alto; revisar resultados | Persona y suplente pendientes |
| Responsable SGSI | Mantener riesgos, evidencias, indicadores y coordinación de incidentes | Persona y suplente pendientes |
| Propietario clínico | Autorizar acceso clínico, validar integridad y recuperación del expediente | Profesional designado pendiente |
| Administración técnica | Ejecutar cambios, gestionar cuentas, copias y diagnóstico | Persona y suplente pendientes |
| Personal usuario | Custodiar acceso, respetar finalidad y comunicar incidentes | Lista de formación pendiente |
| Auditor interno | Evaluar con objetividad e independencia de las actividades revisadas | Designación pendiente; puede ser externo |

Estas funciones organizativas no crean roles nuevos ni amplían permisos en la aplicación. Si una persona acumula tareas, Dirección documentará la revisión compensatoria por otra persona.

## Objetivos iniciales propuestos

| Objetivo | Medición y meta | Responsable / plazo |
|---|---|---|
| Accesos justificados | Cuentas activas revisadas / cuentas activas: 100%; bajas comunicadas ejecutadas antes del fin de la relación o inmediatamente al conocerse una urgencia | Administración y propietario / T0+7 y mensual |
| Recuperación verificable | Primera restauración aislada con integridad comprobada y tiempo registrado; después una trimestral | Administración y psicología / T0+15 |
| Tratamiento de riesgos | 100% de riesgos altos con propietario, fecha y decisión; seguimiento de acciones vencidas | SGSI / T0+7 y mensual |
| Formación | Personal autorizado con formación y comprensión verificadas / personal autorizado: 100% | SGSI / T0+30 y nuevas altas |
| Mejora | Primera auditoría interna y revisión por Dirección con decisiones registradas | Dirección / T0+30; frecuencia posterior según riesgos |

T0 y metas requieren aprobación en GOV-01. No hay resultados medidos para estos objetivos todavía.
