# Verificación de implementación — 2026-10-19

Baseline: conciliación cerrada tras decisión de 10 revisiones configurables y vaciado de historial. Código base `ee23253` más cambios locales; roadmap previo conservado. Skills canónicas: orchestrator, framework-maintenance, backend y orm-models. No se modifican aplicaciones instaladas ni se ejecutan purgas de datos reales.

## Evidencia

- `php packages/phpunit/phpunit/phpunit --filter 'ContentRevisions|ContentEditing|ContentAutosave|ConfigurationDefaults|ModuleCatalog|ProjectUpdateService' --log-junit .phpunit.cache/content-history-regression.xml`: exit 0, 42 pruebas/1169 aserciones, dos omisiones MySQL de la suite ContentEditing por falta de DSN. Esa regresión corresponde al corte anterior a los últimos casos añadidos; los tests de historial finales se repiten abajo.
- `php packages/phpunit/phpunit/phpunit tests/ContentRevisionsTest.php --log-junit .phpunit.cache/content-history-final.xml`: exit 0, 2 pruebas/47 aserciones, sin omisiones. SQLite: carrera entre procesos y ciclo completo de historial.
- `GFRAME_HISTORY_TEST_ENGINE=mysql` con `php packages/phpunit/phpunit/phpunit tests/ContentRevisionsTest.php --log-junit .phpunit.cache/content-history-audit-mysql.xml`: exit 0, 2 pruebas/47 aserciones. Ciclo de historial/migración en MySQL local, esquema aleatorio eliminado por shutdown. La prueba de carrera de ese comando continúa siendo SQLite; no se atribuye concurrencia MySQL a esta ejecución.
- Sintaxis PHP correcta en los dos modelos y comparación PHP. Skills: 14 válidas, 79 enlaces. Enlaces de Markdown modificado: 158, cero ausentes. Validador estructural de etapa sin errores y advertencias históricas de formato de tipos de CA. No se ejecutó otra suite completa global por esta ampliación optativa.

## Sensibilidad

La primera prueba de retención falló: quedaron nueve entradas en vez de diez por la comparación textual de IDs numéricos en el ORM. Se cambió únicamente el predicado numérico de poda a useStrictComparison(false), manteniendo el ORM común y sin SQL específico dentro de modelos. El resultado rojo se conserva aquí; las pruebas posteriores acreditan límites 2, 10 y 12. El fallo de tabla de historial ausente verifica rollback de texto y token de revisión; el conflicto de revisión antigua no añade snapshots.

## Alcance y límites

El módulo aporta modelo optativo, esquema, comparación PHP escapada y recetas de integración. La aplicación mantiene sus rutas, auth/can/CSRF, controles de confirmación y autorización por registro. No se inventa una pantalla global de artículos que el framework no posee. Vaciado lógico no implica compactar físicamente la base. La operación es dueña de su transacción y rechaza anidación; la lectura previa se valida de nuevo mediante CAS antes de persistir, para evitar upgrades de snapshot SQLite en competencia.

Trazabilidad: modelos/snapshots/rollback/restauración a T1; metadatos/comparación/retención/vaciado a T2; manifiesto/migraciones/defaults/docs/skills y pruebas a T3. No se modifica el guardado sin historial existente: se elige explícitamente RevisionedContentModel y saveWithHistory.
