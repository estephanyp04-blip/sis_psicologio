# Selección inicial de controles

Documento SGSI-03 · Versión y estado: [índice](README.md).

Este es el inicio de la declaración de aplicabilidad (SoA). La selección responde a riesgos del proyecto; los resúmenes son propios. Falta contrastar todos los controles del Anexo A de ISO/IEC 27001:2022 y los adicionales necesarios. No existen exclusiones aprobadas. Una fila ausente no significa «no aplicable». Los requisitos de gestión también deben evaluarse.

| Referencia de control | Motivo de inclusión / riesgos | Implementación y evidencia actual | Acción pendiente |
|---|---|---|---|
| A.5.1 Políticas | Orientar decisiones / todos | Política propuesta en SGSI-01 | Aprobación y comunicación |
| A.5.2 Responsabilidades | Evitar tareas sin propietario / todos | Funciones propuestas | Nombramientos y recursos |
| A.5.9 Inventario | Identificar lo que debe protegerse / todos | Inventario SGSI-02 | Validación física y nominal |
| A.5.12 Clasificación | Tratamiento proporcional / R06,R10 | Clasificación propuesta | Aplicación y formación |
| A.5.15–A.5.18 Accesos e identidades | Limitar acceso y gestionar credenciales / R01,R08 | config/permisos.php e includes/autenticacion.php; suite de seguridad | Revisión periódica y autorizaciones |
| A.5.24–A.5.28 Incidentes y evidencia | Responder y aprender / R05,R08 | Procedimiento y plantilla preparados | Canal, equipo designado y simulacro |
| A.5.29–A.5.30 Continuidad | Mantener servicio / R02,R09 | Procedimiento propuesto | Objetivos y recuperación demostrada |
| A.5.31 Requisitos aplicables | Obligaciones identificadas / R06 | Plantilla LEG-01 | Evaluación competente y seguimiento |
| A.5.34 Privacidad | Datos de salud y menores / R06 | Roles y restricciones técnicas | Finalidades, derechos y conservación |
| A.6.3 Concienciación | Uso seguro / R06,R08 | Plan de formación SGSI-04 | Asistencia y comprensión |
| A.7.2 Acceso físico | Custodia de equipos / R09 | No evaluado | Evaluar sede y personas autorizadas |
| A.8.2 Privilegios | Reducir alcance de compromiso / R01,R03 | Configuración local y roles | Usuario BD restringido; revisión de administradores |
| A.8.8 Vulnerabilidades | Software y dependencias / R07 | Versiones parcialmente documentadas | Inventario, avisos y remediación verificada |
| A.8.13 Copias | Recuperación / R02 | No demostrado | Copia protegida y restauración |
| A.8.15–A.8.16 Registros y seguimiento | Detección y trazabilidad / R05 | auditoria y flujo_auditar; cobertura parcial | Consultas, protección y revisión |
| A.8.24 Criptografía | Tránsito y copias / R04,R02 | Hash de claves y soporte de cookie Secure | TLS, cifrado de copias y custodia de claves |
| A.8.25 Desarrollo seguro | Cambios fiables / R07 | Git, migraciones y suites | Revisión, aceptación y evidencia por cambio |
| A.8.32 Cambios | Prevenir errores / R07 | Procedimiento SGSI-04 | Registro de cambios operativos y reversión |

Para completar la SoA, desglosar las filas agrupadas en un registro por control. Usar: identificador; necesario sí/no; justificación de inclusión o exclusión; riesgo/requisito; estado real; responsable; evidencia; fecha de revisión. Registrar controles adicionales fuera del Anexo A cuando el tratamiento los requiera. Dirección y propietarios revisarán las decisiones; no atribuir a ISO los plazos o soluciones técnicas elegidos por la institución.
