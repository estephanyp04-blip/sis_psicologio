# Revisión inicial de seguridad y calidad

Fecha: 2026-11-10. Estado: evaluación parcial; cumplimiento no demostrado.

## Alcance provisional

Aplicación PHP de seguimiento psicológico estudiantil. Confirmar con la institución la norma, edición, alcance y criterios exigidos antes de declarar conformidad.

- ISO/IEC 27001:2022 establece requisitos para un sistema de gestión de seguridad de la información de la organización. El código es solo una parte de la evidencia.
- ISO/IEC 25010:2023 define un modelo de calidad del producto. Es necesario acordar requisitos medibles y evaluar el producto en su contexto de uso.

Este documento es una revisión técnica preliminar, no una auditoría completa, declaración de aplicabilidad ni certificación. Los pendientes siguientes son propuestas internas, no una transcripción de requisitos normativos.

## Evidencias observadas y trabajo pendiente

| Área | Evidencia en el repositorio | Estado y siguiente acción |
|---|---|---|
| Acceso | `includes/autenticacion.php`, `config/permisos.php`: permisos en servidor, CSRF, comprobación de cuenta y rol | Implementación observada; validar matriz con responsables y probar aislamiento de expedientes |
| Contraseñas | Verificación mediante `password_verify`, actualización del hash y límite de intentos por cuenta | Implementación observada; definir política institucional y evaluar protección ante intentos distribuidos |
| Sesiones | Cookies HttpOnly/SameSite, regeneración de identificador, inactividad de 30 minutos y máximo de 8 horas | Implementación observada; comprobar HTTPS y cookies Secure en el despliegue real |
| Navegador | Cabeceras contra interpretación incorrecta de contenido, inclusión en marcos, envío de referente y uso de sensores | Añadidas al inicio de sesión; comprobar también respuestas estáticas y errores del servidor |
| Trazabilidad | `includes/trazabilidad_datos.php` escribe eventos en `auditoria` | Cobertura y resistencia a alteración no evaluadas; revisar accesos de lectura, exportaciones y eventos de autenticación sin registrar notas clínicas ni claves |
| Base de datos | `config/login.php` permite variables de entorno, con valores locales root y contraseña vacía | Antes de producción, usar cuenta dedicada de privilegios mínimos y proteger credenciales; no se verificó configuración efectiva |
| Recuperación | No se verificó un procedimiento operativo de respaldo y restauración | Definir responsables, frecuencia, cifrado, ubicación externa y objetivos de recuperación; conservar evidencia de restauración aislada |
| Privacidad | El sistema trata expedientes psicológicos de estudiantes | Definir clasificación, acceso por función, conservación, eliminación y responsabilidades con la institución |
| Calidad | Suites existentes en `tests/` | Ejecutar pruebas apropiadas; acordar escenarios funcionales, accesibilidad, tiempos de respuesta y carga; una suite aprobada no demuestra conformidad ISO |
| Gestión | Documentación técnica y migraciones existentes | Definir alcance organizativo, riesgos, responsables, tratamiento, incidentes y revisión periódica; comprobar evidencias fuera del repositorio |

## Criterios propuestos para la siguiente evaluación

1. Ningún rol accede a expedientes fuera de su autorización, incluso mediante URL o identificador manipulado.
2. Las operaciones de escritura rechazan solicitudes sin sesión, permiso o token CSRF válido.
3. El despliegue de producción usa HTTPS, cookies Secure, una cuenta de base restringida y errores sin detalles internos para el usuario.
4. Se restaura un respaldo en un entorno aislado y se documentan integridad, duración y responsable. La institución debe aprobar los objetivos de pérdida de datos y tiempo de recuperación.
5. Cada requisito de calidad tiene escenario, resultado esperado, evidencia, responsable y fecha. Definir umbrales de rendimiento y accesibilidad antes de evaluarlos.
6. Se revisa la cobertura de auditoría y su protección; no incluir contraseñas, tokens ni contenido clínico en registros técnicos.

## Fuentes oficiales

- https://www.iso.org/standard/27001
- https://www.iso.org/standard/78176.html

Consultar las normas completas y las condiciones exigidas por la institución para una evaluación formal.
