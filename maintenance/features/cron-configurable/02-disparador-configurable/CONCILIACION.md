# Etapa 02 — Disparador configurable

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md) § Requisitos R-01–R-04, R-08 y R-11.

## Resultado observable

La configuración `.env` selecciona cron real o virtual; en modo virtual las peticiones públicas y administrativas pueden lanzar una pasada independiente y acotada.

## Dependencias

- **DEP-01 — etapa 01:** excepción de secuencia autorizada; ver cierre y evidencia de implementación de la etapa 01.

## Entra

- Configuración por entorno y defaults internos validados.
- Integración en el ciclo HTTP normal, sin endpoint ejecutor ni JavaScript de polling.
- Reserva compartida de lanzamiento, intervalo global y límite de tareas.
- Lanzamiento mediante `Async` y preparación común del proyecto.
- Prevención de recursión, exclusión de canales persistentes y aislamiento de fallos respecto de la respuesta HTTP.

## No entra

- Instalar cron del sistema o desarrollar reglas de negocio.
- Modificar aplicaciones consumidoras o su JavaScript.
- Iniciar sesiones para poder activar cron.

## Aclaraciones

- **Baseline:** corte propuesto del 2026-10-15.
- 2026-10-15 — **Decisión del usuario:** el modo se configura desde `.env`; la implementación provisional de reservas queda descartada.
- Propuesta pendiente: `GFRAME_CRON_MODE=real|virtual`, intervalo 60 segundos y límite 10; modo inicial `real` para no introducir ejecución automática al actualizar.
- Modo real significa ausencia del disparador web; no significa que el framework instale un cron del servidor.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** configurar modo real y visitar páginas públicas/administrativas → no se lanza ningún worker; el CLI permanece operativo; evidencia `sistema`.
- **CA-02 — invariante acumulado:** configurar modo virtual con tareas vencidas y producir tráfico público/administrativo → se lanza el proceso independiente; la respuesta termina sin esperar al handler; evidencia `sistema`.
- **CA-03 — invariante acumulado:** peticiones concurrentes desde varias instancias dentro del intervalo → se respeta el intervalo compartido y no hay lanzamientos duplicados de la misma reserva; evidencia `sistema`.
- **CA-04 — invariante acumulado:** hay más tareas vencidas que el límite → cada pasada respeta el máximo; no hay un bucle HTTP que vacíe toda la cola; evidencia `sistema`.
- **CA-05 — invariante acumulado:** variar parámetros, cookies o cuerpo de la petición → no permite seleccionar código, handlers ni modificar tareas; workers/CLI no disparan workers recursivos; evidencia `sistema`.
- **CA-06 — invariante acumulado:** falla el lanzamiento o falta infraestructura → la petición principal conserva su resultado, queda diagnóstico y la reserva se recupera; evidencia `sistema`.
- **CA-07 — invariante acumulado:** usar valores inválidos o límites extremos en `.env` → la política validada es determinista, documentada y no provoca ejecución ilimitada; evidencia `conductual`.

## Riesgos y compatibilidad

- PHP CLI puede tener otro entorno o no estar disponible; `Async` ya tiene comprobaciones que deben conservarse.
- El lanzamiento tiene coste: declarar una medición de latencia y fijar su umbral antes de cerrar.
- Evitar actividad sobre sesiones y cambios de cabeceras causados por el disparador.

## Bloqueos

- Ninguno para continuar el diseño; implementación dependiente de 01.

## Preguntas abiertas

- Ninguna de producto. Las elecciones técnicas y limitaciones de validación se registran a continuación.

## Criterio para cerrar conciliación

Cerrar configuración, integración HTTP y criterios; dependencia 01 cerrada o excepción expresa justificada.

## Cierre y decisiones de implementación — 2026-10-15

El usuario autorizó expresamente implementar ambas features, actualizar documentación y crear el commit. Esta autorización cierra el alcance propuesto; no constituye evidencia de ejecución ni de auditoría.

Diseño: MySQL utiliza GET_LOCK por conexión de la base compartida; SQLite utiliza una transacción BEGIN IMMEDIATE en una base auxiliar junto al archivo de datos compartido. El bloqueo no caduca mientras vive su conexión. Los tickets de lanzamiento tienen propietario y caducan a los 120 segundos; cada worker valida el ticket bajo el bloqueo antes de ejecutar. Se verifica propiedad antes de persistir resultados. Un proceso que pierde conexión puede haber efectuado efectos externos: no se garantiza exactamente una vez. SQLite requiere almacenamiento local compatible con sus bloqueos; para instancias en hosts distintos se requiere MySQL compartido.

Configuración: GFRAME_CRON_MODE=real|virtual (real por defecto), GFRAME_CRON_MIN_INTERVAL=60 (1–86400) y GFRAME_CRON_BATCH_LIMIT=10 (1–100). Un valor inválido impide ejecutar y produce diagnóstico; no rompe la petición HTTP. Disparo al cierre de peticiones web/ajax, tras cerrar sesión y finalizar FastCGI cuando esté disponible. El handler de prueba de un segundo debe seguir independiente de la respuesta; la medición del coste de lanzamiento se registra sin prometer latencia nula.

Validación ambiental prevista: PHP CLI Windows/XAMPP local y Linux mediante CI o host disponible, dos procesos contra la misma base, diez competencias, cero ejecuciones duplicadas y recuperación después de terminar el propietario. Un entorno no disponible queda no verificado y pendiente, nunca aprobado por inferencia.

Dependencias: excepción explícita de secuencia autorizada para implementar el ciclo completo en esta petición; la etapa anterior debe tener sus verificaciones locales registradas antes de avanzar. La auditoría separada y los perfiles no disponibles siguen pendientes, sin declarar etapas cerradas.
