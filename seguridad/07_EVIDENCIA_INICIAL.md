# Evidencia de la implementación inicial

Documento SGSI-07 · Fecha de verificación: 13/11/2026, America/La_Paz. Estado: registro técnico local, sin aprobación institucional.

## Cambios realizados

- Documentos SGSI-01 a SGSI-06 y guía de adopción: preparados en versión 0.1. Se distinguen propuestas, pendientes y evidencias; no se asignaron personas ni aprobaciones ficticias.
- Herramienta `herramientas/diagnostico_sgsi.php`: solo terminal, consultas de lectura, salida de texto o JSON y códigos diferenciados.
- Carpeta `seguridad/`: acceso HTTP bloqueado por `.htaccess` en el Apache local. Los permisos de archivos y otros servidores requieren revisión propia.

## Comprobaciones realizadas en esta etapa

| Comprobación | Resultado observado | Límite |
|---|---|---|
| PHP lint de la herramienta | Sin errores de sintaxis | No evalúa todo el sistema |
| Ejecución real con `--json` | JSON válido; código real 2 y código declarado 2 | Alertas esperadas por configuración local |
| Configuración CLI de BD | Cuenta root y clave vacía: alertas | No se alteraron cuentas; producción no evaluada |
| Conexión y consulta | Correctas | Disponibilidad puntual |
| Cuentas activas con rol ausente/inactivo | No detectadas | No demuestra necesidad de los permisos |
| Administrador activo | Existe | No constituye autorización institucional |
| Columnas de auditoría | Accesibles | No mide cobertura ni protección ante alteraciones |
| Argumento inválido | Código 64 | Rechazo sin ejecutar diagnóstico |
| Puerto inaccesible, mediante DB_PORT=1 solo en proceso de prueba | JSON válido, error controlado de conexión y código 1 | Se restauró la variable al terminar; no se modificó configuración persistente |
| GET `/seguridad/README.md` y `/seguridad/02_RIESGOS.md` | HTTP 403 | Apache local con AllowOverride habilitado |
| GET `/herramientas/diagnostico_sgsi.php` | HTTP 404 | Herramienta no disponible por HTTP |
| GET `/login.php` | HTTP 200 | Página disponible; no prueba autenticación completa |

Anteriormente, durante la comprobación de conexión del mismo día, `tests/seguridad.php` aprobó 1116 comprobaciones. Ese resultado se conserva como antecedente en [el análisis ISO](../ANALISIS_ISO_2026-11-13.md); no se volvió a ejecutar en esta etapa documental y de diagnóstico. No se alteraron rutas de autenticación ni funciones clínicas.

## Resultado y pendientes

R10 dispone de evidencia de bloqueo local de esta carpeta; su riesgo residual sigue sin aceptación del propietario, y debe comprobarse la custodia de registros fuera de la web. R01–R09 permanecen abiertos. La existencia de documentos preparados mejora la trazabilidad de la implantación, pero aún faltan adopción, ejecución, mediciones, auditoría y revisión.

Siguiente acción técnica prioritaria: T03, cuenta de BD con privilegios mínimos y separación de migraciones. Antes de aplicarla hay que identificar las operaciones que necesita la aplicación, preparar la cuenta, verificar flujos y coordinar su configuración en Apache y terminal. Este registro no afirma que dicha acción esté implementada.
