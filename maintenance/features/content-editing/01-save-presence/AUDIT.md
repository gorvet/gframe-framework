# Auditoría — guardado por revisión y presencia

## Pasada 1 — 2026-10-19

**Contra:** TAREAS.md (principal), implementada antes de esta pasada.
**Baseline:** conciliación cerrada 2026-10-19; commit base `939b5c6` más implementación local. Windows/PHP 8.3.35, SQLite, MySQL local de pruebas y Chrome headless. Roadmap previo conservado. Sin reparaciones durante esta pasada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Diez competencias por motor; un ganador; perdedor no sobrescribe; revisión exacta y rotación aun sin cambios de texto | T1 | cumple | E-01, E-02 | Garantía requiere adopción por todos los escritores |
| CA-02 | Scope ajeno no modifica; conexión nombrada preservada; rollback restaura contenido/revisión; OR pendiente rechazado; decisión por affected | T1 | cumple | E-01, inspección de saveRevision y updateRaw | La aplicación conserva transacción y autorización |
| CA-03 | Dos sesiones se detectan; tenant/recurso separados; caducidad y retorno; no hay bloqueo exclusivo | T2 | cumple | E-01, E-03 | Caducidad inyectada con reloj de prueba; no exige esperar 90 s reales |
| CA-04 | Identidad ausente y tenant contradictorio rechazados; tokens malformados rechazados; identidades POST ignoradas; SQL no expuesto | T2 | cumple | E-01, E-03, E-04 | Auth/can y autorización por registro siguen en la ruta propia, sin endpoint genérico |
| CA-05 | Tokens, petición única, pausa/retorno, destrucción sin nuevos pings en intervalo completo; respuesta/instancia antigua no modifica la nueva; conflicto conserva texto/revisión | T3 | cumple | E-03 | Navegador real con transporte interceptado a procesos PHP/SQLite reales; no valida proxy de producción |
| CA-06 | Migraciones repetibles por motor; manifest publica assets y conserva originales; ejemplos PHP ejecutables; regresiones, lint, skills y enlaces | T4 | cumple | E-01, E-04, E-05 | Cinco omisiones ambientales de suite global ajenas a esta capacidad |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01–04, CA-06 | `php packages/phpunit/phpunit/phpunit --filter ContentEditing --log-junit .phpunit.cache/content-editing-audit.xml`, DSN MySQL de pruebas | Contratos e instalación correctos | 6 pruebas/107 aserciones, sin omisiones; exit 0 | Windows/PHP 8.3.35/SQLite/MySQL; 2026-10-19, auditoría posterior a implementación | XML local ignorado | MySQL usa esquemas temporales y los retira |
| E-02 | CA-01 | `GFRAME_CONTENT_TEST_ENGINE=mysql`, `GFRAME_TEST_MYSQL_DSN=mysql:host=127.0.0.1;port=3306`, usuario local de prueba; `php packages/phpunit/phpunit/phpunit --filter 'ContentEditingTest::testTenCompetitions' --log-junit .phpunit.cache/content-editing-audit-mysql.xml` | Un ganador por revisión en diez rondas | 1 prueba/50 aserciones, sin omisiones; exit 0 | Mismo host; MySQL; 2026-10-19 | XML local ignorado | Dos procesos por ronda, sin producción |
| E-03 | CA-03–05 | `node --test tests/js/content-editing.browser.test.cjs`, GFRAME_TEST_BROWSER Chrome y NODE_PATH runtime | Dos formularios, conflicto y lifecycle conservados | 1 prueba, sin omisiones; exit 0; 42,059 s | Chrome/Playwright con PHP/SQLite reales; 2026-10-19 | Salida CLI | Espera real de 31 s prueba que destroy cancela el siguiente ping; relojes virtuales de presencia se prueban en E-01 |
| E-04 | CA-04, CA-06 | Contraste de controller/service/model, ORM/dialectos, manifest, originales PHP/meta/JS, docs/content-editing.md y skills canónicas | Capas y contratos GFrame preservados | Sin acceso a tablas de negocio desde presencia ni repositorios/hooks duplicados; autorización explícita documentada | Snapshot de esta pasada; 2026-10-19 | Archivos de implementación y documentación | Inspección acredita estructura, no despliegue |
| E-05 | CA-06 | Suite completa, lint, JS y enlaces descritos en VERIFICACION.md; repetición `php bin/validate-skills.php` y validador de etapa | Sin regresiones materiales | Suite 522/5226, exit 0; lint 465; skills 14/79; enlaces 163/0 ausentes; JS 80 aprobadas, 15 omitidas de navegador; nueva prueba real en E-03 | Windows; 2026-10-19 | `.phpunit.cache/content-editing-full-final.xml`, `.phpunit.cache/content-editing-js.log` | Cinco omisiones globales identificadas en VERIFICACION.md, ninguna de content-editing |

**Veredicto:** coincide

### Verificación global

T1–T4 y CA-01–CA-06 cubiertos contra el principal. Las ejecuciones focalizadas de esta pasada son posteriores e independientes del registro de implementación. Se revisó la trazabilidad inversa: cada cambio responde a guardado, presencia, lifecycle o instalación/documentación. La sensibilidad se acredita con el fallo MySQL de mayúsculas conservado en VERIFICACION.md y los casos negativos posteriores de mayúsculas/espacio final, scope, OR, rollback y callbacks antiguos. No se exige una mutación global.

El validador termina en 0; sus advertencias de reconocimiento del formato histórico de tipos no contradicen los tipos explícitos de cada CA. No se reescribió la conciliación para acomodar la implementación. Los cambios previos de roadmap no forman parte de la prueba de cierre.

### Desviaciones de alcance

Informativa: la regresión completa detectó una carrera del fixture de VirtualCronHttpTest. Se corrigió su sincronización durante implementación y se conservó la aserción original, añadiendo ausencia de ticket pendiente. El fallo y la reejecución están registrados; no cambia runtime ni cierre funcional del cron. La operación ORM exacta tiene SQL por dialecto y compatibilidad de interfaz comprobada; no introduce otra API de persistencia.

Sin desviaciones bloqueantes ni entornos materiales pendientes. No certifica una aplicación instalada, no publica una versión y no activa automáticamente la protección en consumidores. Etapa cerrada.
