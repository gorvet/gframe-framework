# Auditoría — vista previa privada

## Pasada 1 — 2026-10-19

**Contra:** principal TAREAS.md implementada; conciliación cerrada el 2026-10-19.
**Snapshot:** base `cf1e830` más cambios locales de preview; roadmap previo conservado. Windows, PHP 8.3.35, SQLite, Chrome headless. Pasada sin reparaciones de implementación.

| CA | Afirmaciones auditadas | Tarea | Resultado | Evidencia | Limitación |
|---|---|---|---|---|---|
| CA-01 | Copia del actor/recurso, base compatible, fallback actual, cambio de versión y copia antigua conservada; lectura sin escritura | T1 | cumple | E-01, E-04 | Modelo y autorización del registro pertenecen a la aplicación |
| CA-02 | Identidad ausente y tenant contradictorio rechazados; HTTP privado/no-store/noindex; estado sin contenido | T1 | cumple | E-01, E-04 | Las dos rutas deben reautorizar; no existe endpoint universal |
| CA-03 | Versión repetida conserva página, nueva recarga una vez; pausa oculta, destrucción y error detienen consultas | T2 | cumple | E-02 | Transporte del navegador interceptado, PHP probado aparte |
| CA-04 | Botón real abre pestaña propia; URL externa rechazada; sin URL no se añade; preview nativa retirada; manifest, meta y docs coherentes | T2, T3 | cumple | E-02, E-03, E-04 | Activación optativa por aplicación |

### Ledger de evidencia

| ID | Procedimiento reproducible | Esperado | Observado y salida | Fecha / artefacto |
|---|---|---|---|---|
| E-01 | `php packages/phpunit/phpunit/phpunit --filter ContentPreview --log-junit .phpunit.cache/preview-audit.xml` | Scopes, fallback, headers HTTP y estado privado correctos | 2 pruebas, 17 aserciones; exit 0 | 2026-10-19; XML local |
| E-02 | `node --test tests/js/content-preview.browser.test.cjs tests/js/content-preview.test.cjs`, GFRAME_TEST_BROWSER Chrome, NODE_PATH runtime | Botón abre pestaña; nueva versión recarga; fallos y lifecycle protegidos | 2 aprobadas, ninguna omisión; exit 0 | 2026-10-19; `.phpunit.cache/preview-browser-audit.log` |
| E-03 | `php bin/validate-skills.php`, `node --check` para ambos JS y lint del controller; validador de etapa y `git diff --check` | Contratos estructurales y sintaxis correctos | Skills 14/79, sin errores; exit 0 | 2026-10-19; salida CLI |
| E-04 | Inspección de controller, ContentDraftService, manifest/meta/vista, defaults y setup de TinyMCE, docs y principal | Sin publicación, sin rutas de negocio inventadas ni barra pública; todas las modificaciones trazables a T1–T3 | Conforme; inspección acredita estructura | 2026-10-19; archivos del snapshot |

**Veredicto:** coincide

Las pruebas negativas de identidad, recurso, versión obsoleta, URL externa y pérdida de autorización intentan refutar las garantías. No se modifica persistencia ni esquema: la regresión focalizada y global JS están registradas en VERIFICACION.md, incluida una omisión MySQL ajena al nuevo lector. No se exige mutación global. El validador avisa por el formato histórico de los tipos de CA, expresados en la conciliación; no presenta errores estructurales.

Sin desviaciones del alcance. No certifica despliegues ni aplicaciones instaladas, ni equivale a publicar una versión de Composer. La aplicación conserva integración y autorización según la receta documentada.
