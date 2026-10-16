# Auditoría — Autoguardado privado en servidor

## Pasada 1 — 2026-10-15

Contra CONCILIACION.md cerrada y TAREAS.md principal implementada. HEAD 4f901dc, cambios locales anteriores conservados. JS auditado SHA-256 EB8EBE415E45C07186860C5990917EA1C9F0B4A42B730A8214E70A717E672BC9; servicio PHP 2CEB088B1F8DD707159351ECDB340FADAB73C0E809B06DB095DD44A2534C89A4. Pasada separada sin reparaciones de runtime ni pruebas durante la auditoría. Windows, PHP 8.3.35, Node 24 y Chrome.

| CA | Afirmaciones atómicas / tareas | Resultado | Evidencia |
|---|---|---|---|
| CA-01 | T1: copia privada saneada, actor/tenant/recurso del servidor, revisión actual; tabla de artículos intacta; scoping ajeno vacío | cumple | E-01, E-02; clave autorizada como argumento, sin ruta pública genérica ni tablas elegidas por POST |
| CA-02 | T1: dos procesos con versión común tienen un ganador; base pública antigua y descarte viejo rechazados; expiración y tombstone no resucitan contenido | cumple | E-01; afectados reales del UPDATE, no matched preliminar. MySQL condicionado al entorno, E-04 |
| CA-03 | T2: pausa/intervalo, tokens y una petición; carga explícita, recuperar/deshacer, confirmar/cancelar descarte; fallo conserva texto y reintenta; readonly y teardown detienen nuevos envíos | cumple | E-02; navegador/PHP/SQLite reales, fallo 503 inyectado; límite de tres intentos inspeccionado en fail/request |
| CA-04 | T3: respuesta antigua no reemplaza texto posterior; publicación durante autosave espera y limpia por versión, luego guarda texto posterior con revisión nueva | cumple | E-02; instantánea confirmada por el controlador del fixture; guardia de identidad anterior conservada y regresión E-03 |
| CA-05 | T4: módulo/migración, docs, permisos por registro y contratos explícitos; release preparado sin modificar sitios | cumple | E-01, E-03, E-04, E-05; commit/push/tag posteriores a esta auditoría autorizados por el usuario |

### Ledger de evidencia

| ID | Procedimiento | Esperado | Observado/exit | Entorno/fecha | Artefactos y límites |
|---|---|---|---|---|---|
| E-01 | `php packages/phpunit/phpunit/phpunit tests/ContentAutosaveTest.php` | Scope, sanitización, CAS, tombstone y migración válidos | 3/3, 40 aserciones, 2,587 s, exit 0 | PHP/SQLite, dos procesos; 2026-10-15 America/Havana | Test nuevo; no sustituye MySQL |
| E-02 | `node --test tests/js/content-autosave.browser.test.cjs` con GFRAME_TEST_BROWSER, NODE_PATH y capturas | Flujo remoto privado y negativos pasan | 1/1, 56,980 s, exit 0 | Chrome/Node/PHP CLI/SQLite; 2026-10-15 America/Havana | Fixture temporal y content-autosave-conflict.png; transporte interceptado, no middleware HTTP vivo |
| E-03 | Regresión del ciclo: media-field-controls, media-toast, media-writing, rich-text-protection y rich-text-scroll con Chrome; suite JS sin navegador | Sin regresiones | Chrome 5/5, 75,025 s; JS 80 pasan/94, 14 omisiones de navegador, exit 0 | Windows/Chrome; 2026-10-15 | No se certifican otros motores ni se reejecutan innecesariamente los checks globales ya verdes sobre las mismas fuentes |
| E-04 | Suite PHP completa; test MySQL nuevo aparte; módulos/documentación | Release sin fallos locales, límites explícitos | 504 tests/4906 aserciones, 12 omitidos; exit 0, 6:04,354. MySQL nuevo omitido por falta de DSN. Enfocadas 34/34/464 y 9/170 con una omisión MySQL | PHP 8.3.35; 2026-10-15 | Nuevo test MySQL incluido en la clase ejecutada por CI; no se afirma ejecución real local. La tabla y SQL SQLite se probaron realmente |
| E-05 | validate-skills.php, node --check, lint PHP, composer validate y diff --check; validador de etapa; revisión visual y de fuente | Contratos, distribución y guías válidos | 14 skills/79 enlaces; 451 PHP válidos; sintaxis/Composer/diff correctos; exit 0 | Windows; 2026-10-15 | Avisos CRLF y del regex de tipos CA heredados. Comprobación Python YAML no disponible por falta de PyYAML; no se cambió metadata YAML |

### Verificación global y alcance

Trazabilidad CA → tarea → persistencia/cliente → prueba/ejecución completa. No se añade un CMS ni se simula que un endpoint conoce el modelo de artículos. La aplicación debe autorizar su registro en una ruta auth/can/CSRF antes de delegar; el controlador común deriva usuario/tenant y no acepta resource/owner del navegador. Esta integración se documenta con el flujo real de módulos y revisiones. No hay despliegue a producción.

Caducidad de 30 días significa dejar de ofrecer contenido, conservando versión y tombstone; la política de borrado físico pertenece a la aplicación y está documentada. El autoguardado sigue siendo privado, cubre solo el texto y no confirma publicación. La protección local y los cambios sin publicar siguen activos.

La revisión visual muestra contenido de conflicto conservado y aviso legible junto a la copia local; recuperación es explícita. Sin desviaciones bloqueantes. MySQL disponible solo en CI/entorno configurado, HTTP vivo y políticas de negocio de consumidores quedan fuera de la evidencia local, según el baseline.

**Veredicto:** coincide
