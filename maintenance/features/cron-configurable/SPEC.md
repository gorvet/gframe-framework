# Cron real o virtual configurable

**Estado:** alcance aprobado para implementación el 2026-10-15; auditoría separada pendiente.
**Fecha:** 2026-10-15.

## Decisiones confirmadas

- GFrame permite operar con cron real del servidor o cron virtual, seleccionable desde `.env`.
- Es una capacidad general del framework que reutiliza `Async`, `CronTaskService` y `CronScheduler`.
- La actividad pública y administrativa puede activar el modo virtual, sin esperar la ejecución de los handlers.
- No se toma `ReservationHeartbeat` como base de implementación. No existe dependencia de reservas ni de EnConfianza2; no se modifica ninguna aplicación consumidora.
- Ambos mecanismos comparten protección contra concurrencia, incluso si coinciden accidentalmente durante un cambio de configuración.
- Se requieren intervalo mínimo, límite por pasada, recuperación tras fallos, diagnóstico, pruebas, documentación y migraciones.

## Situación comprobada

- `src/async/Async.php` lanza un proceso PHP CLI independiente; no proporciona persistencia ni bloqueo.
- `src/cron/CronScheduler.php` ejecuta tareas secuencialmente, recupera bloqueos con antigüedad de 900 segundos y captura `Exception`.
- `src/cron/CronDataProvider.php` reserva mediante actualización condicional; la finalización utiliza solo el identificador de tarea, sin token de propietario.
- `resources/modules/cron-runner/application/bin/gframe-cron.php` carga `core/Load.php`, después `config/cron/*.php`, y ejecuta el scheduler.
- El almacenamiento y el ejecutor publicados pertenecen al módulo `cron-runner`, con esquemas MySQL y SQLite.

## Requisitos

- **R-01 — Modos:** `.env` selecciona cron real o virtual. En modo real el tráfico HTTP no lanza workers; el administrador configura el cron del servidor por separado. Deshabilitar el disparador virtual no elimina tareas ni desactiva el CLI.
- **R-02 — Activación:** peticiones públicas y administrativas atendidas por GFrame pueden provocar una pasada virtual. No depende de polling, formularios, sesión ni un endpoint público de ejecución. Excluir procesos CLI, workers y canales persistentes del disparador para evitar recursión y alterar respuestas de streaming.
- **R-03 — Segundo plano:** usar `Async` para ejecutar handlers fuera de la petición. La comprobación y el lanzamiento tienen un coste acotado; no se promete coste HTTP nulo. El fallo del cron no convierte en error la operación HTTP principal.
- **R-04 — Configuración:** intervalo mínimo y máximo de tareas por pasada configurables desde `.env`, con validación, valores internos por defecto y documentación. Las variables, valores y límites definitivos se fijan en la etapa 02.
- **R-05 — Concurrencia:** coordinación atómica compartida por instancias de una misma instalación y por ambos mecanismos. Un archivo local o una variable PHP no satisfacen este requisito. Separar la reserva de lanzamiento de la propiedad de ejecución.
- **R-06 — Recuperación:** recuperar lanzamientos abandonados y procesos fallidos; un trabajador antiguo no puede liberar el bloqueo ni finalizar la reserva de un sucesor. No recuperar una ejecución viva únicamente porque su handler superó un tiempo fijo. Definir cómo se comprueba la vitalidad durante handlers largos y qué ocurre cuando se pierde la propiedad.
- **R-07 — Preparación común:** CLI y worker cargan el mismo bootstrap, registradores y handlers del proyecto mediante una preparación compartida. Registro de tareas y ejecución siguen siendo operaciones distintas.
- **R-08 — Seguridad:** las peticiones no seleccionan handlers, clases, rutas de archivos, payloads ejecutables ni modifican tareas. Todo objetivo de ejecución procede de configuración y registros internos del servidor.
- **R-09 — Diagnóstico:** registrar fallos de lanzamiento y ejecución y permitir consultar actividad, bloqueo, última pasada y contadores mediante CLI o una superficie administrativa autorizada. No publicar detalles internos a visitantes. Una pasada con handlers fallidos debe poder distinguirse de una pasada satisfactoria.
- **R-10 — Compatibilidad:** conservar las tareas y recurrencias existentes; actualizar instalación nueva y migraciones MySQL/SQLite. Describir actualización del ejecutor publicado sin sobrescribir personalizaciones silenciosamente. Si falta el módulo o el esquema, el sitio sigue respondiendo y el diagnóstico explica la situación sin inundar logs.
- **R-11 — Pruebas:** procesos concurrentes contra almacenamiento compartido; competencia real/virtual; intervalo; límites; recuperación de caída antes y después del inicio; trabajador obsoleto; handler largo; fallo de lanzamiento; errores PHP; ausencia de recursión; controles de seguridad.
- **R-12 — Operación:** documentar que sin tráfico que ejecute PHP no se garantiza puntualidad, que una caché externa puede impedir el disparo y que reintentos o recuperación no garantizan efectos externos exactamente una vez. El modo real es la opción para horarios independientes del tráfico.

## Diseño técnico adoptado

- Coordinación en la base de datos compartida por las instancias de la instalación; no añadir Redis como requisito.
- Configuración: `GFRAME_CRON_MODE=real|virtual`, `GFRAME_CRON_MIN_INTERVAL=60` y `GFRAME_CRON_BATCH_LIMIT=10`. Se mantiene `real` como valor por defecto para conservar el comportamiento sin lanzamientos web automáticos; no implica instalar un cron del sistema.
- Servicio común de preparación y ejecución para CLI y worker. El disparador HTTP delega en él y no carga registradores en cada visita.
- Bloqueo ligado a conexión: GET_LOCK en el primario MySQL compartido; transacción SQLite en base auxiliar local junto al archivo compartido. Sin caducidad de ejecución viva; ticket de lanzamiento con propietario y caducidad de 120 segundos. Comprobar propiedad antes de finalizar. La pérdida de conexión no revierte efectos externos.

## Fuera de alcance

- Modificar aplicaciones consumidoras, reservas o colas de correo de negocio.
- Copiar la implementación de WordPress o añadir su modelo de hooks.
- Instalar cron del sistema, publicar una versión o actualizar Composer en aplicaciones.
- Reintentos automáticos generales de tareas en estado de error, paralelismo de handlers y garantías de efectos externos exactamente una vez.
- CSRF de visitantes, tratado en [su feature independiente](../csrf-visitantes/SPEC.md).

## Referencias

- [Cron actual](../../../docs/cron-runner.md).
- [Async](../../../docs/async.md).
- [WP-Cron, referencia conceptual](https://developer.wordpress.org/plugins/cron/): la actividad del sitio ofrece oportunidades de ejecución; no constituye un reloj del sistema.
