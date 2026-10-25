# Etapa 04 — Ejecutor HTTP

**Estado:** cerrada

**Seguimiento conciliado — 2026-10-25:** Etapa cerrada; [AUDIT.md](AUDIT.md), pase 4, incluye los deltas 01 y 02 y la comprobación [HTTPS/PHP-FPM](HTTPS-FPM.md). Las auditorías de las etapas 01–03 también están cerradas.
**Spec:** [SPEC.md](../SPEC.md); ampliación aprobada en el chat el 2026-10-15.

## Resultado observable

Cron real y virtual pueden solicitar ejecución mediante CLI o HTTP protegido, conservando el mismo bloqueo.

## Dependencias

Etapas anteriores: cerradas por sus auditorías separadas, registradas en el [índice de cron](../README.md). Al iniciar esta ampliación se autorizó una excepción de secuencia con implementación publicada en v4.0.0 y CI aprobado.

## Entra

- GFRAME_CRON_MODE=real|virtual y GFRAME_CRON_EXECUTOR=cli|http; real no registra callbacks por visitas. CLI manual continúa disponible para operación y recuperación.
- Endpoint POST publicado por cron-runner, sin sesión ni Router; bearer secreto configurado en servidor, mínimo 32 caracteres. Sin secreto o executor distinto de http, responde 404.
- Virtual HTTP envía ticket interno y credencial al APP_URL configurado; no acepta URL del visitante. Transporte sin exec, TLS verificado y sin redirecciones; espera acotada de conexión/escritura, sin esperar handlers.
- Real HTTP ejecuta una pasada ante petición autenticada del programador externo; virtual HTTP exige ticket vigente, impidiendo convertirlo en disparador real accidental.
- Pruebas, actualización y documentación; sin despliegue ni publicación de otra versión en esta etapa.

## No entra

Optimización de cola vacía, reintentos, supervisor, historial ni cambios en aplicaciones consumidoras.

## Aclaraciones

El usuario aprobó expresamente los cuatro caminos y solicitó implementarlos. La vía del cron real se configura en el programador externo; EXECUTOR habilita el endpoint HTTP o selecciona el lanzador virtual, no instala tareas del sistema. Un endpoint HTTP ocupa recursos PHP web mientras ejecuta, incluso tras finalizar la respuesta.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** modo real → no callback virtual ni acceso al almacenamiento por visitas; evidencia `sistema`.
- **CA-02 — invariante acumulado:** virtual CLI/HTTP → lanzamiento independiente sin esperar handlers, mismo ticket/límite/bloqueo; evidencia `sistema`.
- **CA-03 — invariante acumulado:** real HTTP autenticado → pasada compartida; credencial errónea, método incorrecto, endpoint deshabilitado o ticket inválido → sin handlers; evidencia `sistema`.
- **CA-04 — invariante acumulado:** URL externa o cabeceras manipuladas por visitante → no seleccionan destino, handler ni límite; secretos no aparecen en diagnóstico, URL ni HTML; evidencia `conductual`.
- **CA-05 — invariante acumulado:** actualizar cron-runner → publica endpoint y preserva personalizaciones; docs y skills describen las cuatro combinaciones y requisitos; evidencia `sistema`.

## Riesgos y compatibilidad

HTTP requiere conexión interna y procesos web disponibles; PHP CLI requiere exec desde web solo en modo virtual CLI. Mantener HTTPS en producción; localhost HTTP se permite para pruebas. El despliegue debe preservar Authorization. Las restricciones anteriores que impedían cualquier endpoint público quedan sustituidas por este endpoint autenticado sin selección de código.

## Bloqueos

Ninguno para implementación local. TLS/FPM y proxy reales se documentan como validación ambiental no realizada.

## Preguntas abiertas

Ninguna de producto.

## Criterio para cerrar conciliación

Alcance autorizado por la solicitud de implementación; auditoría y cierre de etapa separados.
