# Procedimientos operativos propuestos

Documento SGSI-04 · Versión y estado: [índice](README.md). Entrada en vigor pendiente de GOV-01.

## Accesos y cuentas

1. Responsable funcional solicita alta o modificación en ACC-01: función, módulos, acciones, justificación, duración y aprobador. La autorización de contenido clínico corresponde a su propietario.
2. Administración verifica identidad, crea una cuenta individual y asigna solo permisos aprobados. Verifica también las restricciones de rutas y propiedad; no presume que el nombre del rol define todos los permisos efectivos.
3. Entrega acceso por canal institucional acordado, comprueba la operación con el usuario y registra fecha sin copiar claves. Cambios de clave utilizan hash y deben evitar exposición en registros, capturas o terminales compartidas.
4. En baja o cambio de función, desactivar/reducir permisos y comprobar que la sesión anterior queda revocada. Revisar accesos al servidor, repositorio y copias además de la aplicación. Conservar registros según política; no eliminar expedientes para dar de baja una cuenta.
5. Revisar mensualmente cuentas activas, administradores, permisos dinámicos y vínculos docentes. Propietario confirma o solicita corrección. Administración no se autoriza a sí misma un acceso clínico excepcional: otra persona designada revisa la decisión.

## Incidentes

Canal y contacto: pendientes de designación en GOV-01. El personal comunica sospechas al responsable SGSI por el canal institucional aprobado, sin enviar historias clínicas, contraseñas ni archivos sensibles por mensajería no autorizada.

1. Abrir INC-01 con fecha/hora y zona, quién recibe, síntomas, sistemas y clasificación inicial. Una alerta no equivale todavía a incidente confirmado.
2. Clasificar: crítico = exposición clínica activa, pérdida significativa o interrupción de atención; alto = acceso sospechoso o integridad incierta; ordinario = evento acotado sin impacto confirmado. Como meta interna propuesta, escalar críticos inmediatamente y altos durante la jornada de detección. Registrar tiempos reales y suplencias.
3. Responsable coordina contención proporcional: revocar cuenta/sesión, restringir acceso o aislar equipo afectado cuando proceda. Registrar autorización y efecto en atención. Preservar registros antes de reiniciar, borrar o reinstalar cuando sea viable; no improvisar reparaciones destructivas.
4. Conservar evidencia mínima en almacenamiento restringido: identificador, origen, recolector, fecha, hash del archivo, ubicación, accesos y transferencias. No adjuntar datos clínicos al repositorio. Trabajar sobre copias cuando sea posible.
5. Determinar causa y alcance con personal competente. Dirección evalúa comunicaciones y obligaciones aplicables con el responsable designado; documenta destinatarios, fundamento y plazos. Este procedimiento no establece plazos legales universales.
6. Recuperar desde estado verificado, comprobar integridad y permisos con propietario funcional; registrar quién autoriza retorno y seguimiento.
7. Cerrar tras verificar eficacia; actualizar riesgos, registrar causa, corrección y prevención en NC-01. Realizar un simulacro de cuenta comprometida con datos sintéticos durante el primer ciclo.

## Copias y recuperación

Dirección y psicología deben acordar pérdida máxima tolerable de datos (RPO) y tiempo máximo de recuperación (RTO) antes de definir frecuencia. Administración registra alcance: BD, configuración necesaria, archivos asociados y versión de aplicación; verifica consistencia de la copia y dependencias de restauración.

Guardar copias cifradas fuera del servidor web y al menos una fuera del equipo de operación, con acceso y claves separados. Aprobar ubicación, custodios, retención y verificación de eliminación. No copiar producción a pruebas rutinarias. Registrar ejecución y fallos en BAK-01; alertar al responsable ante copia fallida.

Restaurar primero en un entorno aislado autorizado. Validar esquema, integridad referencial y flujos de prueba; comparar con los criterios acordados y medir tiempo y punto de recuperación. El propietario valida el resultado. Una copia creada sin ensayo de restauración no cierra R02. Ensayo inicial en T0+15 y después trimestral como propuesta.

## Cambios y vulnerabilidades

Registrar CHG-01: motivo, riesgo, archivos/versiones, responsable, aprobación según impacto, pruebas y reversión. Revisar migraciones antes de ejecutarlas; usar cuenta separada para administración de BD. No ejecutar el SQL de instalación sobre una base con información existente.

Utilizar datos sintéticos y suites apropiadas al cambio. Registrar resultado real, versión del código y entorno. Para publicación, comprobar copia recuperable, ventana y verificación funcional posterior. Si falla, seguir reversión documentada y evaluar incidente. Reversión de código no equivale a reversión de una migración de datos.

Mensualmente inventariar versiones y consultar avisos oficiales del fabricante; priorizar según exposición, gravedad y riesgo clínico, asignar plazo y comprobar corrección. Un producto actualizado no elimina la necesidad de validar su configuración.

## Formación y comunicación

Antes de otorgar acceso y al menos anualmente, cubrir confidencialidad clínica, cuentas individuales, phishing, custodia de equipos, impresiones/exportaciones y reporte de incidentes. Registrar asistencia, versión de material y una comprobación de comprensión en FOR-01. Repetir contenidos tras incidentes o cambios que afecten al personal. Comunicar cambios de política a todas las personas afectadas y registrar recepción.
