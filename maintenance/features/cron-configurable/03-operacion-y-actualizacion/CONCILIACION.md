# Etapa 03 — Operación y actualización

**Estado:** cerrada

**Seguimiento conciliado — 2026-10-25:** Etapa cerrada; [AUDIT.md](AUDIT.md), pasada 2, y [CI-20261019.md](../CI-20261019.md) cierran T4 y el bloqueo B-01 de Linux. Las notas de implementación anteriores no son tareas activas.
**Spec:** [SPEC.md](../SPEC.md) § Requisitos R-09–R-12.

## Resultado observable

Una instalación nueva o actualizada permite diagnosticar ambos modos y dispone de evidencia de operación y documentación reproducible.

## Dependencias

- **DEP-01 — etapa 01:** cerrada; [auditoría](../01-ejecucion-coordinada/AUDIT.md) con veredicto coincide. La excepción de secuencia fue necesaria durante la implementación.
- **DEP-02 — etapa 02:** cerrada; [auditoría](../02-disparador-configurable/AUDIT.md), pasada 2 coincide. La excepción de secuencia fue necesaria durante la implementación.

## Entra

- Diagnóstico por CLI, logs y estados de lanzamiento, ejecución, bloqueo y recuperación.
- Guía de ambos modos, variables `.env`, migraciones y actualización segura del CLI publicado.
- Comprobación de módulo/esquema ausente y fallos sin inundación de logs.
- Verificación integrada de instalación y actualización, MySQL/SQLite y procesos reales en Windows/Linux.
- Actualización de las skills canónicas afectadas y documentación de limitaciones.

## No entra

- Panel administrativo nuevo, instalación del cron del servidor, releases o actualización de aplicaciones.
- Garantizar ejecución puntual sin tráfico o efectos externos exactamente una vez.

## Aclaraciones

- **Baseline:** corte propuesto del 2026-10-15.
- No utilizar EnConfianza2 como entorno de pruebas ni incorporar su migración a este ciclo.
- Las pruebas automatizadas de procesos no sustituyen una validación de host declarada.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** un lanzamiento y una pasada satisfactoria o fallida → el diagnóstico distingue intentos, inicio, fin, contadores y fallo sin mostrar datos internos al público; evidencia `sistema`.
- **CA-02 — invariante acumulado:** instalar y actualizar con tareas existentes → ambos modos pueden operar sin pérdida de tareas y sin sobrescritura silenciosa de un CLI personalizado; evidencia `sistema`.
- **CA-03 — invariante acumulado:** consultar guía, ejemplo `.env` y skills → coinciden con los nombres y comportamiento implementados e incluyen caché, falta de tráfico e idempotencia externa; evidencia `estructural`.
- **CA-04 — condición de salida:** ejecutar la matriz de procesos y fallos en hosts declarados → cron real/virtual funcionan con el límite y recuperación acordados; evidencia `ambiental`. Antes del cierre se fijarán host/perfil Windows y Linux, ventana, umbrales y archivos de evidencia.
- **CA-05 — invariante acumulado:** sitio sin módulo/esquema o sin PHP CLI utilizable → la petición sigue funcionando y el diagnóstico identifica la causa con logs acotados; evidencia `sistema`.

## Riesgos y compatibilidad

- Registrar como no verificado cualquier entorno no ejercitado; no presentar pruebas simuladas como operación real.
- La caché que evita PHP elimina oportunidades de disparo virtual.

## Bloqueos

- **B-01 — resuelto el 2026-10-19:** Linux no estaba disponible en la sesión de implementación. La repetición Linux/MySQL del job 118176309692 completó la evidencia requerida; [ledger E-11](../CI-20261019.md). Se conserva la causa histórica; no hay bloqueo activo.

## Preguntas abiertas

- Ninguna de producto. Las elecciones técnicas y limitaciones de validación se registran a continuación.

## Criterio para cerrar conciliación

Acordar los perfiles y la evidencia, cerrar dependencias y fijar el contrato del diagnóstico.

## Cierre y decisiones de implementación — 2026-10-15

El usuario autorizó expresamente implementar ambas features, actualizar documentación y crear el commit. Esta autorización cierra el alcance propuesto; no constituye evidencia de ejecución ni de auditoría.

Diseño: MySQL utiliza GET_LOCK por conexión de la base compartida; SQLite utiliza una transacción BEGIN IMMEDIATE en una base auxiliar junto al archivo de datos compartido. El bloqueo no caduca mientras vive su conexión. Los tickets de lanzamiento tienen propietario y caducan a los 120 segundos; cada worker valida el ticket bajo el bloqueo antes de ejecutar. Se verifica propiedad antes de persistir resultados. Un proceso que pierde conexión puede haber efectuado efectos externos: no se garantiza exactamente una vez. SQLite requiere almacenamiento local compatible con sus bloqueos; para instancias en hosts distintos se requiere MySQL compartido.

Configuración: GFRAME_CRON_MODE=real|virtual (real por defecto), GFRAME_CRON_MIN_INTERVAL=60 (1–86400) y GFRAME_CRON_BATCH_LIMIT=10 (1–100). Un valor inválido impide ejecutar y produce diagnóstico; no rompe la petición HTTP. Disparo al cierre de peticiones web/ajax, tras cerrar sesión y finalizar FastCGI cuando esté disponible. El handler de prueba de un segundo debe seguir independiente de la respuesta; la medición del coste de lanzamiento se registra sin prometer latencia nula.

Validación ambiental prevista: PHP CLI Windows/XAMPP local y Linux mediante CI o host disponible, dos procesos contra la misma base, diez competencias, cero ejecuciones duplicadas y recuperación después de terminar el propietario. Un entorno no disponible queda no verificado y pendiente, nunca aprobado por inferencia.

Dependencias: excepción explícita de secuencia autorizada para implementar el ciclo completo en esta petición; la etapa anterior debe tener sus verificaciones locales registradas antes de avanzar. La auditoría separada y los perfiles no disponibles siguen pendientes, sin declarar etapas cerradas.
