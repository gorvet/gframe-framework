# Auditoría — etapa 01 CSRF público

## Pasada 1 — 2026-10-19

**Contra:** TAREAS.md (principal).
**Baseline:** conciliación cerrada y tareas del 2026-10-15; commit `c8ccd983202621c5c2be29dde810870c8f8873a4`; Windows/PHP 8.3.35/SQLite. Cambio previo de roadmap conservado.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Visitante obtiene token y envía acción | T1 | cumple | E-01 | HTTP real |
| CA-02 | Ausente, manipulado, otra sesión y origen inválido rechazados | T2 | cumple | E-01 | Validación dirigida y HTTP real |
| CA-03 | Rotación, varias pestañas y sesión caducada recuperables | T3 | parcial | E-01 | Rotación probada; falta reapertura de sesión entre peticiones y pérdida real de sesión |
| CA-04 | Sin sesión no hay token/cookie; respuestas privadas | T4 | cumple | E-01 | Las cachés externas deben respetar las cabeceras |
| CA-05 | Originales y rutas coherentes | T5 | cumple | E-01, E-02 | Login de instalación real; revisión de originales |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01–CA-05 | Los dos comandos reproducibles de [cron 02](../../cron-configurable/02-disparador-configurable/AUDIT.md), E-01/E-02 | Tokens y rutas correctos | 39 pruebas/913 aserciones/7 omisiones MySQL y 36/561 sin omisiones; exit 0 | Windows/PHP 8.3.35; 2026-10-19 | `.phpunit.cache/cron-csrf-audit-20261019.xml`, `.phpunit.cache/cron-csrf-boundaries-20261019.xml` | Ninguna omisión de CSRF |
| E-02 | CA-03, CA-05 | Revisar VisitorCsrfTest, SessionRuntime, CsrfToken, rutas y cuatro vistas Auth, helper del header y docs/middleware.md | Cobertura completa de ciclo de vida | Falta prueba entre peticiones con pérdida de sesión | Mismo snapshot; 2026-10-19 | Fuentes del paquete | No sustituye ciclo HTTP real |

**Veredicto:** no coincide

### Verificación global

Validador estructural sin errores; advertencias históricas sobre tipos CA. No se reescribió conciliación ni se reparó código durante esta pasada.

### Desviaciones de alcance

Ninguna.

### Huecos

[Delta 01](TAREAS-delta-01.md): completar evidencia del ciclo de sesión.

## Pasada 2 — 2026-10-19

**Contra:** TAREAS.md (principal), mismo baseline y runtime; delta 01 implementado. Reejecución posterior separada sin reparar fuentes ni pruebas.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Sesión anónima genera token, helper lo envía y POST válido funciona sin login | T1 | cumple | E-10 | HTTP real y SessionRuntime/CsrfToken reales |
| CA-02 | Token ausente/manipulado/otra sesión y origen externo rechazados, sin requerir login al visitante | T2 | cumple | E-10 | Retorno unauthorized, fail_csrf/to_reload según timestamp |
| CA-03 | Misma cookie entre peticiones mantiene token; login rota; sesión destruida rechaza formulario antiguo y recarga recupera | T3 | cumple | E-10 | Dos peticiones representan pestañas; destrucción real del almacenamiento reproduce pérdida de sesión sin esperar al GC |
| CA-04 | Helper sin sesión no crea token/cookie; Router impide mezclas inseguras; respuesta con token privada/no-store | T4 | cumple | E-10 | Cachés externas deben respetar cabeceras, conforme al baseline |
| CA-05 | Login/registro/recuperación/reset incluyen helper y conservan validación; puente header y antispam compatibles | T5 | cumple | E-10, E-12 | Login de instalación real; originales/rutas y demás flujos cubiertos por AuthUiTest e inspección |

### Ledger de evidencia

E-10/E-12 de [CI-20261019.md](../../cron-configurable/CI-20261019.md), con comando exacto, snapshot, ventana, XML, resultados y sensibilidad de pruebas del delta. Suite posterior separada: 83 pruebas/1676 aserciones, exit 0; siete omisiones MySQL ajenas a CSRF. La rotación al autenticar se comprueba con SessionManager y VisitorCsrfTest; la continuidad y pérdida de sesión por HTTP con CronCsrfBoundaryTest.

**Veredicto:** coincide

### Verificación global

T1–T5 y método del delta cubiertos contra baseline principal. Sin errores estructurales; advertencias históricas de reconocimiento de tipo registradas. Revisión inversa de SessionRuntime, SessionManager, CsrfToken, Middleware, Router y originales distribuidos. No se cambiaron contratos ni runtime durante la pasada.

### Desviaciones de alcance

Informativa: fixture compartido con cron; no introduce dependencia entre features ni afecta aplicaciones reales. Ninguna desviación bloqueante. Etapa cerrada, sin trabajo activo.
