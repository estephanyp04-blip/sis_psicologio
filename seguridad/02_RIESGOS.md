# Inventario, valoración y tratamiento de riesgos

Documento SGSI-02 · Versión y estado: [índice](README.md).

## Inventario inicial

| ID | Activo / ubicación lógica | Clasificación | Propietario propuesto | Dependencias |
|---|---|---|---|---|
| A01 | Historias, versiones y anexos históricos / MariaDB | Restringido clínico | Psicología | A03, A04, A05, A06 |
| A02 | Identidades, familiares, derivaciones e informes / MariaDB y salidas | Restringido personal | Dirección y psicología | A03–A06 |
| A03 | Cuentas, hashes, permisos y sesiones / BD, PHP y servidor | Restringido seguridad | Administración | A04, A05 |
| A04 | Código, configuración, migraciones y dependencias / proyecto y Git | Interno; secretos restringidos | Administración | A05 |
| A05 | Servidor XAMPP, equipo, red y energía / ubicación física pendiente | Interno | Dirección | Proveedores y custodia física pendientes |
| A06 | Copias, almacenamiento y claves de recuperación / ubicación pendiente | Restringido | Administración | Repositorio externo autorizado pendiente |
| A07 | Auditoría y evidencia SGSI / BD y repositorio restringido pendiente | Restringido | Responsable SGSI | A03–A06 |
| A08 | Estaciones, navegador, impresiones y exportaciones | Restringido cuando contienen expedientes | Responsable de cada puesto | Autorizaciones y custodia |

Pendiente de completar: identificadores de equipos y responsables nominales, ubicación física, proveedores, accesos, retención y dependencias externas. A06 representa un activo necesario cuya existencia operativa no se ha comprobado.

## Método reproducible propuesto

Para cada escenario, registrar amenaza, debilidad, activos y consecuencias. Valorar probabilidad P: 1 = poco probable con barreras verificadas; 2 = plausible o evidencia insuficiente; 3 = exposición frecuente o antecedentes confirmados. Valorar impacto por confidencialidad, integridad y disponibilidad: 1 = limitado y reversible; 2 = afecta operación o información personal; 3 = exposición clínica, daño grave o pérdida prolongada. I es el mayor de los tres impactos. Nivel = P × I: 1–2 bajo; 3–4 medio; 6–9 alto.

La incertidumbre se registra y no reduce por sí sola el riesgo. Las valoraciones iniciales siguientes son hipótesis conservadoras para validación de propietarios, no mediciones de incidentes. Revalorar mensualmente y al cambiar funciones, infraestructura, proveedores o tras incidentes.

Bajo: propietario puede proponer aceptación documentada con fecha de revisión. Medio: tratar o justificar aceptación temporal con propietario y responsable SGSI. Alto: requiere tratamiento y decisión de Dirección antes de ampliar exposición; toda excepción necesita razón, vencimiento y medidas compensatorias. Aceptar riesgo no permite omitir obligaciones aplicables.

Riesgo residual: recalcular P e I después de ejecutar y verificar medidas. No dar por reducido un riesgo por escribir una política. Propietario aprueba el tratamiento y acepta explícitamente el residual en RSK-01. Ninguno está aceptado en esta versión.

## Registro inicial y plan

| ID / activo | Escenario y evidencia actual | P / I / nivel | Tratamiento propuesto y evidencia de cierre | Responsable propuesto / fecha objetivo | Residual / estado |
|---|---|---|---|---|---|
| R01 / A01,A02,A03 | Permisos excesivos exponen expedientes; permisos dinámicos y administrador amplio | 2 / 3 / 6 alto | T01: revisar cuentas y matriz efectiva; justificar accesos administrativos y probar roles contra autorización firmada | Psicología + administración / T0+7 | No evaluado / abierto |
| R02 / A01,A02,A05,A06 | Pérdida de datos por fallo; restauraciones no demostradas | 2 / 3 / 6 alto | T02: definir RPO/RTO, copia protegida fuera del equipo y restauración aislada con acta | Administración + Dirección / T0+15 | No evaluado / abierto |
| R03 / A01,A03,A05 | Compromiso de aplicación alcanza BD con privilegios amplios; valores locales root/clave vacía | 2 / 3 / 6 alto | T03: separar usuario de ejecución y migración; verificar privilegios efectivos y funcionamiento antes de cambiar configuración | Administración / T0+7 | No evaluado / abierto |
| R04 / A01,A03,A08 | Interceptación de sesión o expediente al usar red sin TLS; HTTPS de producción no verificado | 2 / 3 / 6 alto | T04: verificar TLS y cookies reales; ensayar acceso y renovación de certificado en entorno de despliegue | Administración / antes de acceso por red no confiable | No evaluado / abierto |
| R05 / A07 | Acceso indebido no detectado o registro alterado; auditoría parcial en BD | 2 / 3 / 6 alto | T05: completar eventos de consulta/cambio y proteger registro; demostrar revisión y detección con casos sintéticos | Administración + SGSI / T0+15 | No evaluado / abierto |
| R06 / A02,A08 | Divulgación por informes, impresiones o uso no justificado; procedimientos no validados | 2 / 3 / 6 alto | T06: validar destinatarios, finalidades, retención y canales; registrar formación y muestra autorizada | Dirección + psicología / T0+15 | No evaluado / abierto |
| R07 / A04,A05 | Cambio defectuoso o dependencia vulnerable interrumpe servicio o altera datos | 2 / 3 / 6 alto | T07: inventariar versiones, revisar avisos oficiales y aplicar cambio probado con reversión | Administración / T0+15; seguimiento mensual | No evaluado / abierto |
| R08 / A03 | Robo de credenciales; hash, límite de intentos y vencimiento existentes | 2 / 3 / 6 alto | T08: revisar altas/bajas, formación, privilegios y necesidad de autenticación adicional según exposición | SGSI + administración / T0+15 | No evaluado / abierto |
| R09 / A05,A06 | Fallo eléctrico, pérdida física o eventos ambientales afectan servicio y copias | 2 / 3 / 6 alto | T09: evaluar sede, UPS, acceso físico, ubicación alternativa y relevancia climática; ensayar contingencia | Dirección / T0+30 | No evaluado / abierto |
| R10 / A07 | Documentación de seguridad publicada desde htdocs | 2 / 2 / 4 medio | T10: bloquear carpeta seguridad por HTTP y conservar registros operativos fuera del servidor web; verificar rutas | Administración / implementación inicial | No evaluado / protección local a verificar |

Cada tratamiento necesita responsable nominal, fecha calendario, recursos y aceptación en RSK-01. Los plazos son propuestas a partir de T0; ningún riesgo se cierra automáticamente al vencerlos. Documentar decisiones de evitar, reducir, compartir o aceptar, y sus consecuencias.
