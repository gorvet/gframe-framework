# Auditoría — Scroll propio del editor

## Pasada 1 — 2026-10-15

**Contra:** TAREAS.md (principal)
**Baseline:** conciliación cerrada y tareas de 2026-10-15; HEAD `4f901dc` con cambios locales anteriores y ajuste de scroll. Windows/PHP 8.3.35/Node 24.13.0/Chrome. Reejecución separada después de implementación; sin reparaciones durante esta pasada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | 180 párrafos no expanden altura; scroll interno con rueda/Control+End, extremos no mueven página; aside visible y pulsable; barra fuera del texto desplazable | T1 | cumple | E-01, E-03 | Chrome, formulario Bootstrap temporal con aside; no shell de una aplicación instalada |
| CA-02 | Documento corto, tirador manual real, altura 420/320 personalizada, autoresize explícito, móvil sin overflow horizontal; biblioteca, undo/redo, isDirty, guardado y docs conservados | T2 | cumple | E-01, E-02, E-03, E-04 | No certifica todos los navegadores ni modifica overrides del proyecto |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01, CA-02 | `node --test --test-concurrency=1 tests/js/rich-text-scroll.browser.test.cjs tests/js/media-writing.browser.test.cjs tests/js/rich-text-documentation.test.cjs` | Flujos de scroll y regresión pasan | 3/3 sin omisiones, exit 0; 44,843 s | Node 24.13.0/Chrome; 2026-10-15T21:47-04:00 | Pruebas indicadas; capturas de E-03 | HTTP interceptado, sin sockets; escritura usa PHP real por CLI como etapa 03 |
| E-02 | CA-02 | `php packages/phpunit/phpunit/phpunit --filter 'RichTextEditor\|DocumentationLinks\|MediaDocumentation'` (sin escapes de barras al ejecutar) | Assets, componentes y documentación compatibles | 12 pruebas/182 aserciones, exit 0, sin omisiones; 15,221 s | PHP 8.3.35; 2026-10-15T21:47-04:00 | Tests originales del editor y enlaces | No es un release completo |
| E-03 | CA-01, CA-02 | Revisar capturas de 1440×1000 y 390×844; aserciones de altura/scroll/botón y overflow de E-01 | Layout coherente y acciones accesibles | Conforme; toolbar visible con párrafo 180 y aside en escritorio; apilado móvil y redimensionado manual | Chrome; 2026-10-15T21:46–21:47-04:00 | `.playwright-mcp/rich-text-scroll-desktop.png`, `rich-text-scroll-mobile.png` | Capturas locales ignoradas; no auditoría de lector de pantalla ni aside de producción |
| E-04 | CA-02 | `php bin/validate-skills.php`, `node --check resources/modules/rich-text-editor/public/rich-text-editor.js`, `git diff --check`; validador de etapa antes/después y revisión del diff | Sintaxis, enlaces, convenciones y alcance conservados | Exit 0; 14 skills/79 enlaces. Cambios de runtime acotados a opciones base/estilo del iframe; no nuevas opciones PHP ni dependencias | Windows; 2026-10-15T21:47-04:00 | JS y docs/rich-text-editor.md; skill content-editor-search | Avisos CRLF y dos avisos del regex de tipo de CA; baseline declara tipo, no se reescribe |

E-01 usa NODE_PATH del runtime, `GFRAME_TEST_BROWSER=C:/Program Files/Google/Chrome/Application/chrome.exe`, `GFRAME_TEST_SCREENSHOTS=<repositorio>/.playwright-mcp`. El test de scroll sirve assets locales y componente PHP original en Playwright; su documento exterior puede desplazarse, por lo que la comprobación de aislamiento no se explica por ausencia de scroll exterior. El rojo inicial (580 → 7342 px) se conserva en VERIFICACION.md y demuestra sensibilidad.

**Veredicto:** coincide

### Verificación global

T1 → opciones base sin autoresize, altura explícita y containment → prueba larga/rueda/teclado/aside. T2 → personalización y documentación → gestos de redimensionado, altura por instancia y autoresize explícito, regresión de biblioteca y pruebas PHP/enlaces. Se conservan el contenido y los ciclos de las etapas anteriores. No se ha inferido HTTP real del transporte interceptado. Validación de etapa: `python <skills-root>/feature-stages/scripts/validate_stage.py maintenance/features/media-library-improvements/04-editor-scroll`.

### Desviaciones de alcance

Ninguna. Otros ajustes de UX quedan pendientes de decisión; no se modifica el aside ni el layout de una aplicación instalada. Cambios locales, sin commit, etiqueta ni publicación.
