# Flujo de atención y permisos

Verificado el 5 de octubre de 2026. [config/permisos.php](config/permisos.php) declara las 52 rutas. Cada POST exige sesión, rol, método y CSRF. Los enlaces clínicos se muestran únicamente a administrador y psicóloga.

## Recorrido

1. **Derivación → cita.** «Programar cita» preselecciona estudiante y derivación. El responsable es el usuario autenticado; el profesional solicitado en la derivación sigue siendo una referencia independiente. La cita nace pendiente y el servidor comprueba la pertenencia al estudiante.
2. **Cita → historia.** «Abrir historia desde esta cita» conserva cita, estudiante y derivación. La apertura no puede ser anterior a la cita. Si ya existe expediente, se continúa con un seguimiento. La historia es única por estudiante y conserva autor y vínculos originales.
3. **Historia → seguimiento.** Se guardan descripción, técnicas, acuerdos, recomendaciones y próxima sesión, con el autor autenticado. Se admite atención sin cita. La cita seleccionada debe pertenecer al estudiante, estar vigente, coincidir con la fecha de atención y no tener otro seguimiento. La fecha no puede ser futura ni anterior a la apertura; la próxima sesión debe ser posterior.
4. **Efectos anunciados al guardar.** Seguimiento, cita atendida, historia en seguimiento, derivación en seguimiento y auditoría se guardan en una transacción. Un fallo revierte todo. La próxima sesión no reserva una cita automáticamente. Las notas se consultan desde la historia y su detalle; no se ofrece su modificación ni eliminación.
5. **Seguimiento → informe.** «Crear informe desde este seguimiento» conserva exactamente ese origen. El alta general propone el último seguimiento del expediente. Si su cita proviene de una nueva derivación, el informe conserva esa nueva derivación; la historia mantiene la original. Las atenciones posteriores no cambian el vínculo del informe. Se mantiene el alta sin historia o sin seguimiento.
6. **Cierre explícito.** Iniciar seguimiento requiere una historia del estudiante. Cerrar como Atendido requiere un seguimiento de la derivación; se permite reabrirla, pero no regresar a Pendiente. Emitir un informe no cierra la derivación ni el expediente. Una historia cerrada debe reabrirse desde su edición para recibir seguimientos.

Una nueva derivación del mismo estudiante se atiende mediante una nueva cita y seguimiento, sin reemplazar el origen del expediente. Un seguimiento sin cita pertenece a la derivación original de la historia; para atender otra derivación, seleccione una cita de ella.

## Agenda y conservación

- Agenda única compartida: un turno vigente por fecha/hora, independientemente del responsable, cada 30 minutos entre 07:00 y 18:00. La base impide reservas simultáneas del mismo turno.
- Cancelar libera el turno. Las citas atendidas o canceladas conservan fecha, hora y estado; se pueden corregir sus observaciones. Una cita con historia o seguimiento tampoco se puede cancelar o mover. No se permite reprogramar hacia el pasado ni atender citas futuras.
- La edición conserva responsable, estudiante y derivación. Una derivación pendiente con citas, historia o informes no puede eliminarse.
- `auditoria` registra actor, fecha y referencias de los cambios. Los informes conservan el autor y auditan al editor.
- Las relaciones nuevas de registros antiguos quedan en NULL. No se inventan responsables o vínculos históricos por cercanía de fecha o por ser el registro más reciente.

## Matriz por acción

| Acción | Administrador | Psicóloga | Docente | Director |
|---|---|---|---|---|
| Panel y estudiantes | Sí | Sí | No | No |
| Usuarios y docentes | Sí | No | No | No |
| Ver derivaciones | Todas | Todas | Propias | No |
| Crear/editar derivaciones | Sí; editar pendientes | No | Propias; editar pendientes | No |
| Eliminar derivaciones | No | No | Propias, pendientes y sin vínculos | No |
| Iniciar/cerrar/reabrir derivación | Sí | Sí | No | No |
| Citas y sus cambios de estado | Sí | Sí | No | No |
| Historias clínicas | Sí | Sí | No | No |
| Registrar/ver seguimientos | Sí | Sí | No | No |
| Crear/editar informes | Sí | Sí | No | No |
| Listar/ver informes | Sí | Sí | No | Sí |
| Navegar por vínculos clínicos | Sí | Sí | No | No |

El docente recibe el estado de sus derivaciones, sin notas ni enlaces clínicos. El director consulta informes sin acceso a historias, citas o seguimientos. Los límites se verifican también mediante URL y POST directos.

## Pruebas

```powershell
& C:\xampp\php\php.exe tests\trazabilidad.php --navegador
& C:\xampp\php\php.exe tests\seguridad.php
& C:\xampp\php\php.exe tests\historias_derivaciones.php --navegador
& C:\xampp\php\php.exe tests\esquema_informes_importacion.php
```

Se usan bases aleatorias y copias temporales. Se comprueban relaciones ajenas, origen y autor, transiciones, rollback, reservas concurrentes, permisos HTTP y formularios reales en Chrome. Las pruebas no escriben registros en la base real.
