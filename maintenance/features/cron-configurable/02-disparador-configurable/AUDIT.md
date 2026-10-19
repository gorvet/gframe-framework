# Auditoría — etapa 02

## Pasada 1 — 2026-10-19

**Contra:** TAREAS.md (principal).
**Baseline:** conciliación cerrada y tareas del 2026-10-15; commit `c8ccd983202621c5c2be29dde810870c8f8873a4`. Windows, PHP 8.3.35, SQLite. Cambio previo ajeno conservado: `maintenance/roadmap.md`.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Real no lanza; CLI operativo | T1 | cumple | E-01, E-02 | Ninguna material |
| CA-02 | Virtual independiente y respuesta previa al fin | T2 | cumple | E-01 | Barrera real de archivos en VirtualCronHttpTest |
| CA-03 | Una reserva entre competidores e intervalo común | T3 | cumple | E-01 | Diez competencias SQLite |
| CA-04 | Máximo por pasada sin vaciado automático | T4 | cumple | E-01 | Ninguna material |
| CA-05 | Entrada HTTP no selecciona código; canales excluidos | T5 | parcial | E-02, E-03 | Falta intento adversarial del disparador con parámetros y cookies |
| CA-06 | Fallo conserva respuesta y recupera reserva | T6 | parcial | E-01, E-03 | Falta fallo real de PHP CLI y esquema desde HTTP |
| CA-07 | Límites deterministas | T7 | parcial | E-01, E-03 | Faltan extremos válidos del intervalo y lote |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01–CA-07 | `php packages/phpunit/phpunit/phpunit --filter 'CronConcurrency\|CronRunner\|VirtualCronHttp\|VisitorCsrf\|CronDocumentation\|MySqlMigrationIntegration\|ProjectUpdateService' --log-junit .phpunit.cache/cron-csrf-audit-20261019.xml` | Exclusión, recuperación, HTTP y actualización correctos | 39 pruebas, 913 aserciones, 7 omitidas; exit 0 | Windows/PHP 8.3.35; 2026-10-19 | `.phpunit.cache/cron-csrf-audit-20261019.xml` | MySQL no configurado localmente |
| E-02 | CA-01, CA-05 | `php packages/phpunit/phpunit/phpunit --filter 'CronHttpExecutor\|RouteSessionPolicy\|SessionManager\|MiddlewareDocumentation\|AuthUi\|InstallerHttp' --log-junit .phpunit.cache/cron-csrf-boundaries-20261019.xml` | Transporte autorizado, login y rutas seguros | 36 pruebas, 561 aserciones, sin omisiones; exit 0 | Mismo entorno; 2026-10-19 | `.phpunit.cache/cron-csrf-boundaries-20261019.xml` | El endpoint HTTP no prueba toda la entrada del disparador |
| E-03 | CA-05–CA-07 | Inspección de VirtualCron, CronRuntime, Router y aserciones de CronConcurrencyTest | Casos negativos ejecutables suficientes | Huecos de cobertura enumerados arriba | Mismo snapshot; 2026-10-19 | `src/GFrame/Cron/`, `tests/CronConcurrencyTest.php` | Inspección no sustituye ejecución |

**Veredicto:** no coincide

### Verificación global

El validador estructural termina en 0; advierte sobre el formato histórico del tipo de CA, aunque el texto sí declara sus tipos. No se modificó el baseline. Sin fallos funcionales reproducidos ni cambios de runtime durante esta pasada.

### Desviaciones de alcance

Ninguna. La ampliación HTTP de etapa 04 se conserva como capacidad posterior aprobada.

### Huecos

[Delta 01](TAREAS-delta-01.md): completar evidencia negativa contra el baseline principal.

## Pasada 2 — 2026-10-19

**Contra:** TAREAS.md (principal), mismo baseline y snapshot de runtime; delta 01 implementado. Reauditoría posterior separada, sin reparar fuentes ni pruebas en esta pasada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Real no registra en rutas web/AJAX; CLI operativo | T1 | cumple | E-10 | Rutas públicas y administrativas comparten canal web; fixture no pretende certificar permisos de negocio |
| CA-02 | Virtual entrega respuesta antes de liberar barrera del handler; worker termina independientemente | T2 | cumple | E-10 | Petición de prueba tiene timeout 8 s y barrera de handler de 15 s; no promete coste de lanzamiento nulo |
| CA-03 | Intervalo compartido, diez competencias, un ganador y exclusión del runner | T3 | cumple | E-10, E-11 | SQLite local y MySQL compartido, no producción distribuida |
| CA-04 | Lotes acotados y tareas restantes; ausencia de bucle para vaciar cola | T4 | cumple | E-10, E-11 | Se requieren nuevas oportunidades de ejecución |
| CA-05 | GET/cuerpo/cookie hostiles no seleccionan clase/payload/límite; solo ejecuta handler persistido; API/SSE/CLI no registran | T5 | cumple | E-10 | Canario de handler cliente ausente; payload servidor intacto y un intento |
| CA-06 | CLI inexistente y tabla ausente conservan respuesta; ticket liberado, intervalo respetado y log acotado | T6 | cumple | E-10 | Fallo de infraestructura real sobre proyecto temporal |
| CA-07 | Extremos válidos aceptados y exteriores/texto rechazados sin lanzar | T7 | cumple | E-10 | Rangos del baseline conservados |

### Ledger de evidencia

[CI-20261019.md](../CI-20261019.md): E-10, comando exacto y XML de reauditoría (83 pruebas/1676 aserciones); E-11, repetición Linux/MySQL sin omisiones; E-12, sintaxis, skills y validación de etapas. Incluye resultados negativos y corrección exclusiva del fixture durante implementación del delta.

**Veredicto:** coincide

### Verificación global

T1–T7 y delta 01 cubiertos contra criterios principales. Baseline intacto, runtime sin cambios; revisión de Router, VirtualCron, CronRuntime, Async y consumidores HTTP. Sin omisiones materiales; omisión MySQL de autosave fuera de este alcance. Validador sin errores y advertencias históricas documentadas.

### Desviaciones de alcance

Informativa: nueva suite compartida de frontera con CSRF y operación; cada aserción se traza a su CA. Ninguna desviación bloqueante. Etapa cerrada, sin trabajo activo.
