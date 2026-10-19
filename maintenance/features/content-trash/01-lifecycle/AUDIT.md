# Auditoría — papelera

## Pasada 1 — 2026-10-19

**Contra:** principal TAREAS.md implementada, conciliación cerrada 2026-10-19.
**Snapshot:** base `b88b868` más implementación local. Windows/PHP 8.3.35, SQLite y MySQL local temporal. Roadmap previo conservado y actualizado para reflejar capacidades implementadas. Sin reparaciones de runtime en esta pasada.

| CA | Afirmaciones auditadas | Tarea | Resultado | Evidencia | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Marcado/restauración preservan texto y relaciones; estado/revisión/scope correctos; revisión rota; repetición y revisión anterior fallan | T1, T2 | cumple | E-01, E-02 | Adopción explícita por cada consumidor |
| CA-02 | Confirmación obligatoria, solo desde papelera; lock CAS, limpieza SQL y borrado atómicos; excepción revierte relaciones y revisión; registro ajeno conservado | T1, T2 | cumple | E-01, E-02 | Sin efectos externos dentro de la transacción |
| CA-03 | Listados activo/papelera separados, scope y conexión nombrada; OR pendiente rechazado; docs exigen auth/can/CSRF y todos los lectores/escritores | T1, T3 | cumple | E-01, E-03 | No activa filtros ORM globales ni rutas/UI universales |
| CA-04 | Lifecycle y rollback SQLite/MySQL; dos procesos SQLite con un ganador; historial y sintaxis/skills coherentes | T2, T3 | cumple | E-01–03 | Concurrencia entre procesos ejecutada en SQLite, lifecycle completo en ambos motores |

### Ledger de evidencia

| ID | Comando/procedimiento | Esperado | Observado/exit | Fecha y artefacto |
|---|---|---|---|---|
| E-01 | `php packages/phpunit/phpunit/phpunit --filter 'ContentTrash|ContentRevisions|ContentEditingTest' --log-junit .phpunit.cache/trash-audit.xml` | Sin regresión en lifecycle, historial y revisión; un ganador entre procesos | 9 pruebas, 187 aserciones, ninguna omisión; exit 0 | 2026-10-19; XML local |
| E-02 | `GFRAME_TRASH_TEST_ENGINE=mysql`, `php packages/phpunit/phpunit/phpunit --filter ContentTrash --log-junit .phpunit.cache/trash-mysql-audit.xml` | Igualdad exacta y rollback de contenido/relaciones en MySQL; competencia SQLite | 2 pruebas, 45 aserciones, ninguna omisión; exit 0 | 2026-10-19; XML local; esquema temporal eliminado |
| E-03 | Lint trait/test; `php bin/validate-skills.php`; validador de etapa; `git diff --check`; contraste docs/modelo/trait y referencias | Sintaxis, contratos y trazabilidad correctos | Skills 14/79, sin errores estructurales; exit 0 | 2026-10-19; salida CLI y fuentes |

**Veredicto:** coincide

Se intentó refutar el cierre con revisiones que difieren en mayúscula y espacio final, tenant ajeno, restauración desde activo, borrado sin confirmación, revisión obsoleta, OR pendiente, transacción anidada y fallo después de eliminar relaciones. El rollback preservó también la revisión original. No se exige mutación global porque estos casos ejercitan directamente las fronteras de seguridad y atomicidad del cambio.

La trazabilidad inversa relaciona trait/tests con T1–T2 y documentación/skill/changelog/roadmap con T3. No hay nuevas tablas, endpoints, publicaciones ni cambios en consumidores. La política de historial/autoguardado al borrar físicamente y la limpieza SQL concreta son responsabilidades documentadas del modelo consumidor. No se certifica integración visual ni despliegue.

El validador termina en 0; advierte por el formato histórico de tipos de CA aunque constan explícitos. Se normalizó únicamente el metadato de nivel de evidencia y el marcador Verificar antes de iniciar esta pasada; no se cambiaron las garantías funcionales. Sin desviaciones bloqueantes.
