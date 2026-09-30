# GF Heartbeat

La documentación vigente de uso, extensión, sesión, eventos y seguridad se encuentra en [`docs/heartbeat.md`](../../docs/heartbeat.md).

Este directorio contiene el motor interno:

- `HeartbeatMaster.php`: programa y ejecuta los canales registrados.
- `HeartbeatChannelRegistry.php`: conserva las definiciones de canales.
- `HeartbeatChannelTrait.php`: normaliza parámetros y respuestas.

Los archivos que se publican en cada aplicación se encuentran en `resources/modules/heartbeat-client`.
