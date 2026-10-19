# Auditoría — etapa 01

## Pasada 1 — 2026-10-19

**Contra:** TAREAS.md (principal).
**Baseline:** conciliación cerrada y tareas del 2026-10-15; commit `c8ccd983202621c5c2be29dde810870c8f8873a4`. Windows/PHP 8.3.35/SQLite y perfil Linux/MySQL de E-11. Cambio previo del roadmap conservado.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Diez competencias con un ganador; CLI y virtual comparten bloqueo; real invalida ticket virtual | T1 | cumple | E-10, E-11 | Mismo primario MySQL; no certifica particiones de red |
| CA-02 | Caída exit 23 recuperada; ticket vencido/obsoleto rechazado; antiguo propietario no finaliza ni libera sucesor | T2 | cumple | E-10, E-11 | Pérdida de propiedad inyectada por ExecutionLock; caída real en ambos motores |
| CA-03 | Handler vivo con locked_at antiguo no se recupera; contendiente ocupado; pérdida de propiedad bloquea finalización | T3 | cumple | E-10, E-11 | Se envejece locked_at deliberadamente, sin esperar 900 segundos |
| CA-04 | Bootstrap/registradores reales en HTTP/CLI; recurrencia y límite; Exception/TypeError registran fallo | T4 | cumple | E-10 | VirtualCronHttpTest, CronRunnerTest y CronDocumentationTest |
| CA-05 | Instalación nueva, esquema manual e historial previo; migraciones repetibles sin pérdida MySQL/SQLite | T5 | cumple | E-10, E-11 | La prueba MySQL de autoguardado es ajena y no se atribuye al cierre |

### Ledger de evidencia

E-10, E-11 y E-12 se documentan con comandos, resultados, timestamps, perfiles y artefactos en [CI-20261019.md](../CI-20261019.md). Se ejecutaron independientemente del registro de implementación. E-10: 83 pruebas/1676 aserciones, siete omisiones MySQL; E-11: 15/126, sin omisiones; E-12: controles estructurales.

**Veredicto:** coincide

### Verificación global

Métodos de T1–T5 cubiertos por pruebas y aserciones identificadas; inspección inversa de CronScheduler, CronRuntime, locks, migraciones, CLI y tests sin capacidades ajenas. Validación estructural sin errores; advertencias históricas de tipo descritas en E-12. No se modificó runtime durante la pasada. Ninguna omisión material queda sin evidencia.

### Desviaciones de alcance

Ninguna. El endpoint de etapa 04 es una ampliación aprobada posterior y conserva la coordinación. Sin mutación global: caída de proceso, envejecimiento de reserva, ticket obsoleto y pérdida de propiedad ejercitan los fallos de mayor riesgo.

Etapa cerrada; sin trabajo activo. No implica despliegue ni publicación.
