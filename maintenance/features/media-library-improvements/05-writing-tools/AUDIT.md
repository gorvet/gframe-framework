# Auditoría — Herramientas de escritura

## Pasada 1 — 2026-10-15

Contra TAREAS.md principal y conciliación cerrada, incluida aclaración de texto. HEAD 4f901dc, cambios locales de etapas 01–05. Pasada separada, sin reparaciones de runtime durante auditoría. Chrome/Node 24/PHP 8.3 en Windows.

| CA | Afirmaciones y tareas | Resultado | Evidencia |
|---|---|---|---|
| CA-01 | T1: grupo por teclado, herramientas conservadas, override sliding y móvil | cumple | E-01 |
| CA-02 | T2: cambiar/editar contexto, quitar solo del texto, undo y dirty | cumple | E-01; detalles de biblioteca siguen accesibles tras quitar |
| CA-03 | T3: puente explícito, validación, disabled/lectura, target ajeno, pendiente entre instancias, sin éxito ni limpieza dirty | cumple | E-01; intentos negativos de guardado y submit prevenido |
| CA-04 | T4: editor plano, personalización/setup, scroll, documentación y contratos sin backend nuevo | cumple | E-01, E-02, E-03; diff acotado del módulo |
| CA-05 | T5: menú filtrado/teclado, transformación de título/lista, undo/redo, Escape, slash en frase, contexto y palabras sin medios | cumple | E-01 |

### Ledger de evidencia

| ID | Procedimiento | Esperado | Observado/exit | Entorno y fecha | Artefactos y límites |
|---|---|---|---|---|---|
| E-01 | `node --test --test-concurrency=1 tests/js/media-writing.browser.test.cjs tests/js/rich-text-scroll.browser.test.cjs tests/js/rich-text-documentation.test.cjs` | Casos positivos y negativos pasan | 3/3, 0 omitidos, exit 0; 38,821 s | Node/Chrome, 2026-10-15 | Tests indicados; HTTP interceptado, PHP/SQLite reales. No certifica middleware vivo ni producción |
| E-02 | `php packages/phpunit/phpunit/phpunit --filter 'RichTextEditor|DocumentationLinks|MediaDocumentation'`; `php bin/validate-skills.php` | Componentes/documentación/skills válidos | 12 tests/184 aserciones; 14 skills/79 enlaces; exit 0 | PHP 8.3.35, 2026-10-15 | Tests y guías del módulo; no suite de release |
| E-03 | Revisión de JS, PHP, docs y evidencias visuales de implementación; validador de etapa; `git diff --check` | Trazabilidad y alcance coherentes | Cumple; validador estructural válido | Windows, 2026-10-15 | Avisos heredados por regex de tipo CA; tipos declarados, sin cambiar baseline. Capturas locales de selección/contextos/scroll |

Verificación global: cada tarea tiene pruebas y cada cambio responde al baseline; copias y publicación ajenas quedan intactas. Sin desvíos bloqueantes. No se infiere guardado remoto del clic ni se equipara transporte interceptado a HTTP real.

**Veredicto:** coincide
