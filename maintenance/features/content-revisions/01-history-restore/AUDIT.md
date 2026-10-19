# Auditoría — historial de revisiones

## Pasada 1 — 2026-10-19

**Contra:** TAREAS.md (principal), marcada implementada antes de esta pasada.
**Baseline:** conciliación cerrada 2026-10-19; `ee23253` más cambios locales de historial. Windows/PHP 8.3.35, SQLite y MySQL local de pruebas. Sin reparaciones de código durante esta pasada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Guardado y snapshots juntos; tabla ausente revierte texto/token; revisión obsoleta no guarda; dos procesos tienen un ganador y dos entradas iniciales | T1 | cumple | E-01, E-02 | Carrera de procesos SQLite; no se certifica concurrencia MySQL nueva |
| CA-02 | Metadatos sin snapshots; tenant/recurso ajenos no revelan entradas; comparación presenta etiquetas como texto | T2 | cumple | E-01, E-02, E-03 | La ruta propia sigue siendo responsable de auth/can y autorización por registro |
| CA-03 | Restaurar conserva estado seleccionado con token nuevo; rechaza entrada ajena y revisión antigua; añade historial | T1 | cumple | E-01, E-02 | Historia de campos incompatibles se rechaza explícitamente |
| CA-04 | Default 10; límites 2/12, cero rechazado; vaciado sin confirmación rechazado; vaciado completo no cambia contenido/token ni entradas ajenas | T2 | cumple | E-01, E-02 | La reducción del límite se aplica al guardar; no ejecuta compactación física |
| CA-05 | Migraciones repetibles en ambos motores; ORM/herencia sin rutas universales; defaults/manifest/docs/skills coherentes | T3 | cumple | E-01–E-04 | Integración optativa de cada aplicación, sin despliegue real |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01–05 | `php packages/phpunit/phpunit/phpunit tests/ContentRevisionsTest.php --log-junit .phpunit.cache/content-history-audit.xml` | Atomicidad, límites, restauración, aislamiento y vaciado correctos | 2 pruebas/47 aserciones, sin omisiones, exit 0 | Windows/PHP 8.3.35/SQLite, 2026-10-19; posterior a implementar | XML local ignorado | Una carrera de dos procesos; no estrés de producción |
| E-02 | CA-01–05 | `GFRAME_HISTORY_TEST_ENGINE=mysql`, mismo test con XML `.phpunit.cache/content-history-audit-mysql.xml` | Ciclo y migraciones compatibles con MySQL | 2 pruebas/47 aserciones, sin omisiones, exit 0 | Mismo host, MySQL temporal, 2026-10-19 | XML local ignorado | La carrera interna sigue siendo SQLite; el ciclo funcional sí usa MySQL |
| E-03 | CA-02, CA-05 | Inspección de modelos, comparación PHP, manifiesto, defaults, docs y skill; `php bin/validate-skills.php`; validador de etapa | Capas, permisos y salida seguros | Modelos ORM, campos permitidos, mismo PDO, comparación escapada; 14 skills/79 enlaces; validador sin errores | Snapshot sin cambios en esta pasada, 2026-10-19 | Fuentes y documentos | Inspección estructural no sustituye pruebas E-01/E-02 |
| E-04 | CA-05 | Regresión focalizada y enlaces descritos en VERIFICACION.md | Sin regresiones pertinentes | 42 pruebas/1169 aserciones, dos omisiones de MySQL ajenas al historial; 158 enlaces sin ausentes | Windows, 2026-10-19 | `.phpunit.cache/content-history-regression.xml` | Corte previo, complementado por reejecución final E-01/E-02; no se atribuye suite global nueva |

**Veredicto:** coincide

### Verificación global

CA y tareas principales cubiertos; revisión inversa de cambios sin funcionalidad ajena. Se preserva la decisión de 10 configurable, vaciado por contenido y baseline cerrado. Se ejecutó prueba negativa de retención, fallo de persistencia, revisión antigua y entrada ajena; el rojo de poda y su corrección anterior se conservan en VERIFICACION.md. Sin mutación global por existir fallos y negativos directos de las fronteras relevantes. Validador estructural en 0; advertencias históricas de reconocimiento de tipos, declarados en texto.

### Desviaciones de alcance

Informativa: primer estado capturado con autor null, ya previsto para no inventar autor retrospectivo; fecha de captura documentada. No se implementan rutas ni tablas de artículos universales. Sin desviaciones bloqueantes. No certifica UI o despliegue de una aplicación consumidora; no se publican commits, etiquetas ni nuevas versiones por esta auditoría.
