# Etapa 01 — Ejecución coordinada

**Estado:** cerrada

**Seguimiento conciliado — 2026-10-25:** Etapa cerrada; [AUDIT.md](AUDIT.md), pasada 1, y [CI-20261019.md](../CI-20261019.md) completan la auditoría y los perfiles Windows/Linux. Las notas de implementación del 15 de octubre conservan su estado histórico.
**Spec:** [SPEC.md](../SPEC.md) § Requisitos R-05, R-06, R-07 y R-11.

## Resultado observable

El scheduler y el CLI coordinan la ejecución entre procesos e instancias y recuperan procesos abandonados sin aceptar finalizaciones de propietarios obsoletos.

## Dependencias

- Ninguna etapa previa. Se reutilizan las tareas persistentes y el módulo existentes.

## Entra

- Preparación común del proyecto y registradores; integración en scheduler y CLI.
- Almacenamiento compartido, propiedad, adquisición atómica y recuperación.
- Política explícita para handlers largos, pérdida de propiedad y errores `Throwable`.
- Esquemas de instalación y migraciones MySQL/SQLite necesarios para esta coordinación.
- Pruebas con procesos independientes y almacenamiento compartido.

## No entra

- Disparador HTTP y selección de modo, asignados a 02.
- Diagnóstico operativo completo y validación de despliegue, asignados a 03.
- Cambios de negocio o reintentos automáticos generales.

## Aclaraciones

- **Baseline:** corte propuesto del 2026-10-15; criterios técnicos todavía no conciliados.
- 2026-10-15 — **Decisión del usuario:** cron general real o virtual desde `.env`; no utilizar `ReservationHeartbeat` ni trabajar en EnConfianza2.
- La exclusión mutua de base de datos no promete efectos externos exactamente una vez.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** dos procesos compiten por la misma ejecución → solo el propietario autorizado ejecuta; CLI y futuro worker usan la misma coordinación; evidencia `sistema`.
- **CA-02 — invariante acumulado:** el propietario cae antes o durante una pasada → se recupera según la política acordada; un propietario antiguo no libera ni finaliza la reserva del sucesor; evidencia `sistema`.
- **CA-03 — invariante acumulado:** un handler supera el umbral de recuperación anterior → no se considera muerto solo por su antigüedad; se demuestra la política para vitalidad y pérdida de propiedad; evidencia `sistema`.
- **CA-04 — invariante acumulado:** CLI y ejecución común preparan un proyecto de prueba → cargan los mismos registradores y handlers, conservan límite y recurrencia y registran fallos ordinarios y errores PHP sin dejar propiedad inválida; evidencia `sistema`.
- **CA-05 — invariante acumulado:** instalar o migrar MySQL/SQLite con tareas previas → existe coordinación utilizable y se conservan tareas y programación; evidencia `sistema`.

## Riesgos y compatibilidad

- Un TTL no prueba muerte del proceso; un token de propiedad no revierte un correo ya enviado.
- Revisar compatibilidad de implementaciones externas de `CronTaskRepository` antes de extender contratos.
- No reutilizar sin revisión la recuperación actual de 900 segundos.

## Bloqueos

- Ninguno para continuar el diseño.

## Preguntas abiertas

- Ninguna de producto. Las elecciones técnicas y limitaciones de validación se registran a continuación.

## Criterio para cerrar conciliación

Acordar el diseño de recuperación, los criterios y sus pruebas antes de generar tareas o implementar.

## Cierre y decisiones de implementación — 2026-10-15

El usuario autorizó expresamente implementar ambas features, actualizar documentación y crear el commit. Esta autorización cierra el alcance propuesto; no constituye evidencia de ejecución ni de auditoría.

Diseño: MySQL utiliza GET_LOCK por conexión de la base compartida; SQLite utiliza una transacción BEGIN IMMEDIATE en una base auxiliar junto al archivo de datos compartido. El bloqueo no caduca mientras vive su conexión. Los tickets de lanzamiento tienen propietario y caducan a los 120 segundos; cada worker valida el ticket bajo el bloqueo antes de ejecutar. Se verifica propiedad antes de persistir resultados. Un proceso que pierde conexión puede haber efectuado efectos externos: no se garantiza exactamente una vez. SQLite requiere almacenamiento local compatible con sus bloqueos; para instancias en hosts distintos se requiere MySQL compartido.

Configuración: GFRAME_CRON_MODE=real|virtual (real por defecto), GFRAME_CRON_MIN_INTERVAL=60 (1–86400) y GFRAME_CRON_BATCH_LIMIT=10 (1–100). Un valor inválido impide ejecutar y produce diagnóstico; no rompe la petición HTTP. Disparo al cierre de peticiones web/ajax, tras cerrar sesión y finalizar FastCGI cuando esté disponible. El handler de prueba de un segundo debe seguir independiente de la respuesta; la medición del coste de lanzamiento se registra sin prometer latencia nula.

Validación ambiental prevista: PHP CLI Windows/XAMPP local y Linux mediante CI o host disponible, dos procesos contra la misma base, diez competencias, cero ejecuciones duplicadas y recuperación después de terminar el propietario. Un entorno no disponible queda no verificado y pendiente, nunca aprobado por inferencia.

Dependencias: excepción explícita de secuencia autorizada para implementar el ciclo completo en esta petición; la etapa anterior debe tener sus verificaciones locales registradas antes de avanzar. La auditoría separada y los perfiles no disponibles siguen pendientes, sin declarar etapas cerradas.
