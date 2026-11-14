# Sistema de gestión de seguridad de la información (SGSI)

Versión 0.1 · 13/11/2026 · Estado: base inicial preparada, pendiente de adopción institucional.

Esta carpeta inicia la gestión de seguridad del sistema de seguimiento psicológico de la U.E. Cañada Pailita «B». Los nombres de responsables, aprobaciones, presupuestos y resultados operativos aún deben registrarse. Las funciones propuestas no equivalen a nombramientos. La documentación no acredita certificación ni conformidad completa con ISO/IEC 27001:2022.

## Documentos y uso

| Documento | Uso | Responsable propuesto |
|---|---|---|
| [Alcance y política](01_ALCANCE_POLITICA.md) | Límites, compromisos, responsabilidades y objetivos | Dirección |
| [Activos, riesgos y tratamiento](02_RIESGOS.md) | Valorar riesgos y ejecutar acciones con evidencia | Responsable SGSI y propietarios |
| [Aplicabilidad preliminar](03_APLICABILIDAD.md) | Relacionar controles, riesgos y evidencia | Responsable SGSI |
| [Procedimientos](04_PROCEDIMIENTOS.md) | Accesos, incidentes, copias, cambios y formación | Administración técnica y responsables funcionales |
| [Evaluación y mejora](05_EVALUACION.md) | Indicadores, auditoría interna y revisión directiva | Responsable SGSI y revisor independiente |
| [Registros reutilizables](06_REGISTROS.md) | Documentar autorizaciones, ejecución y decisiones | Responsable de cada actividad |
| [Evidencia inicial](07_EVIDENCIA_INICIAL.md) | Distinguir verificaciones reales de trabajo pendiente | Administración técnica |

## Primer ciclo de implantación

1. Dirección identifica personas titulares y suplentes, aprueba alcance, política, método de riesgos y recursos. Completar registro GOV-01. Registrar la fecha de aprobación como T0.
2. Cada propietario valida los activos y puntuaciones propuestas, acepta el plan y establece fechas calendario para T0+7, T0+15 y T0+30 días. No considerar aceptado ningún riesgo sin su decisión registrada.
3. Administración ejecuta el diagnóstico y las acciones priorizadas. Guardar evidencias operativas en un repositorio institucional restringido fuera de `htdocs`; usar aquí únicamente referencias sin datos personales.
4. Responsable SGSI completa la evaluación de todos los controles del Anexo A contra una copia autorizada de la norma, además de los controles adicionales que resulten necesarios. El documento 03 es una selección inicial, no una declaración de aplicabilidad terminada.
5. Realizar seguimiento mensual, auditoría interna inicial y revisión por Dirección. Comprobar eficacia y corregir desviaciones.

## Diagnóstico técnico local

```powershell
& C:\xampp\php\php.exe herramientas\diagnostico_sgsi.php
& C:\xampp\php\php.exe herramientas\diagnostico_sgsi.php --json
```

Consulta configuración del proceso PHP de terminal, conectividad y coherencia agregada de cuentas. No escribe en la base, muestra identidades ni revela contraseñas. No verifica HTTPS del servidor, permisos efectivos de BD, contenido clínico, copias, políticas ni cumplimiento ISO. Apache puede usar variables de entorno diferentes.

Códigos de salida: 0 = comprobaciones ejecutadas sin alertas automáticas; 1 = error de configuración, conexión o consulta; 2 = alertas que requieren revisión; 64 = argumentos inválidos. Un 0 no implica conformidad ISO. En el entorno XAMPP local se esperan alertas por cuenta root y clave vacía.

## Control documental

La versión 0.1 de los documentos 01–07 comparte fecha y estado de este índice. El responsable SGSI propone cambios mediante Git, registra motivo y versión; Dirección aprueba cambios de política y alcance, y los propietarios aprueban tratamientos y riesgos residuales. Conservar la versión aprobada y retirar copias obsoletas de circulación. Revisar anualmente y ante cambios relevantes o incidentes.

Clasificación de esta carpeta: uso interno. `.htaccess` bloquea su publicación mediante Apache 2.4 cuando `AllowOverride` lo permite. En otros servidores debe configurarse una regla equivalente o excluir esta carpeta del despliegue. Este bloqueo no protege otros documentos del proyecto ni sustituye los permisos del sistema de archivos. No guardar expedientes, secretos, informes de incidentes reales ni copias de BD en Git o dentro de `htdocs`.

Fuentes de referencia: [ISO/IEC 27001:2022](https://www.iso.org/standard/27001) y [Enmienda 1:2024](https://www.iso.org/standard/88435.html). El SGSI se orienta a gestionar riesgos de información en el contexto de la institución. Los métodos, plazos y metas de estos documentos son propuestas internas; no se presentan como valores obligatorios fijados por ISO.
