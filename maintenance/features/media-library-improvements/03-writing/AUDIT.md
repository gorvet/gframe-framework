# Auditoría — etapa 03 Escritura

## Pasada 1 — 2026-10-15

**Contra:** TAREAS.md (principal)
**Baseline:** conciliación cerrada 2026-10-15; tareas del mismo día; HEAD `4f901dc` más etapas locales 01–03. Windows/PHP 8.3.35/SQLite/Node 24.13.0/Chrome. Auditoría separada tras implementación; se añadió una aserción adversarial sin reparar el código durante la pasada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Imagen en cursor, sustitución, galería/reordenación/reapertura, cancelar conserva documento | T1, T2 | cumple | E-01, E-03 | Backend real por CLI, transporte interceptado |
| CA-02 | Undo/redo, dos instancias y respuestas antiguas protegidas, pero insertar no debe dar artículo por guardado | T2 | no cumple | E-02 | `editor.save()` limpia isDirty antes del guardado del artículo |
| CA-03 | Selección estricta por ámbito/tipo/límite, servidor HTML, saneado y recarga segura | T1 | cumple | E-01, E-03 | Sin autorización HTTP de un CRUD real; la aplicación mantiene esa responsabilidad |
| CA-04 | Activación opcional, assets/override, campo sin biblioteca, teclado/móvil, docs y skills | T3 | cumple | E-01, E-03, E-04 | Regresiones HTTP completas de selector/gestión no reejecutadas por EACCES; sí picker integrado, regresiones sin sockets y PHP |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01, CA-02, CA-03, CA-04 | Comando JS de VERIFICACION.md, antes de nueva aserción | Flujos completos y regresiones pasan | 6/6 sin omisiones, exit 0; 56,858 s | Windows/Chrome; 2026-10-15T21:27–21:28-04:00 | Tests y capturas `.playwright-mcp/media-writing-*.png` | No cubría isDirty |
| E-02 | CA-02 | `node --test tests/js/media-writing.browser.test.cjs`, con aserción `isDirty() === true` después de insertar, antes de guardar artículo | Insertar conserva cambios pendientes | Falla, false !== true; exit 1; 8,803 s | Mismo entorno, 2026-10-15T21:28-04:00 | tests/js/media-writing.browser.test.cjs, línea 62 en este corte | Fallo reproducible del runtime, no del transporte |
| E-03 | CA-01, CA-03, CA-04 | Comando PHP de VERIFICACION.md, reejecutado | Ámbitos, límites, saneado, actualización y publicación | 56 pruebas/1246 aserciones, exit 0, sin omisiones; 56,439 s | PHP 8.3.35/SQLite; 2026-10-15T21:27–21:28-04:00 | Pruebas PHP indicadas en VERIFICACION.md | No demuestra MySQL ni middleware |
| E-04 | CA-04 | Lint de nueve PHP, `node --check` editor, `php bin/validate-skills.php`, `git diff --check`, validador de etapa y revisión visual | Sintaxis, enlaces, contratos y responsive | Exit 0; 14 skills/79 enlaces; cuatro avisos de tipo de CA del validador | Windows; 2026-10-15T21:27-04:00 | Fuentes y artefactos de etapa | El regex espera ambos tipos entre corchetes aunque el baseline declara uno; no se reescribe conciliación |

**Veredicto:** no coincide

### Verificación global

Trazabilidad: fragmentos/controlador y sanitizador → T1; editor/picker → T2; PHP opcional/meta/CSS/docs/skills → T3. Los comandos y configuración exactos de implementación constan en VERIFICACION.md. La nueva prueba negativa refuta CA-02 pese al conjunto inicialmente verde. La limitación de sockets se mantiene explícita y no certifica HTTP real; el método de esta etapa sí permite comprobar editor y backend con transporte simulado.

### Desviaciones de alcance

Ninguna. El defecto se cierra mediante delta contra el mismo baseline.

### Huecos

`TAREAS-delta-01.md`: conservar la marca de cambios al sincronizar el textarea tras insertar medios; reejecutar la aserción negativa y la regresión.

## Pasada 2 — 2026-10-15

**Contra:** TAREAS.md (principal), tras delta 01 implementado
**Baseline:** misma conciliación y origen de tareas; HEAD `4f901dc` más cambios locales. Diferencia respecto de pasada 1: sincronización con `set_dirty: false`, aserción adversarial permanente y fixture visual PNG sintética. Sin reparación de código durante esta pasada. Entorno idéntico; transporte de prueba interceptado a PHP CLI, sin sockets.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Imagen en cursor entre textos, sustitución sin duplicar, galería ordenada/reapertura, quitar y cancelar; HTML saneado y recargado conserva composición | T1, T2 | cumple | E-05, E-06 | Se usa la biblioteca real a través de PHP CLI; no es prueba de HTTP vivo |
| CA-02 | isDirty sigue verdadero tras insertar; undo/redo, múltiples instancias, respuesta tardía, destrucción/recreación, texto manual dentro de galería protegido | T2 | cumple | E-05, E-07 | La prueba roja E-02 demuestra sensibilidad; no se modificó saveAll ni el guardado del proyecto |
| CA-03 | Ámbitos global/user/tenant, IDs ajenos/cero/vacíos/límite, no imagen, sin resultado parcial; URL del navegador ignorada, marcadores acotados y XSS; aplicación mantiene autorización | T1 | cumple | E-05, E-06, E-07 | La fixture de guardado no certifica autorización de artículos; responsabilidad explícita en guía |
| CA-04 | Campo opcional y plain, setup/lifecycle anterior, assets/override, teclado, móvil, documento y foto de tamaño real en galería, saneado/formulario, docs/skills | T3 | cumple | E-05, E-06, E-07, E-08 | Sin certificación de lector de pantalla, contraste instrumental, MySQL o producción. Dos suites HTTP completas previas siguen sin reejecutarse; no se declaran aprobadas en esta pasada |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-05 | CA-01, CA-02, CA-03, CA-04 | `node --test --test-concurrency=1 tests/js/media-writing.browser.test.cjs tests/js/media-field-controls.browser.test.cjs tests/js/media-multiple-mounts.test.cjs tests/js/media-toast.browser.test.cjs tests/js/media-field.test.cjs tests/js/rich-text-documentation.test.cjs` | Incluida aserción isDirty y negativos, pasan flujos de escritura y regresiones proporcionadas | 6 pruebas, 6 pasan, 0 omitidas, exit 0; 66,750 s | Chrome/Node 24.13.0; 2026-10-15T21:32–21:33-04:00 | Tests y capturas media-writing-*.png | Playwright sirve peticiones mediante procesos PHP; no prueba sockets/HTTP ni subida nueva multipart en esta etapa |
| E-06 | CA-01, CA-03, CA-04 | `php packages/phpunit/phpunit/phpunit --filter 'MediaWriting\|HtmlSanitizer\|RichTextEditor\|MediaManagement\|MediaRuntime\|MediaLibrary\|MediaDocumentation\|MediaScopeResolver\|MediaMountIdentifiers\|ProjectUpdateService\|DocumentationLinks'` (sin escapes de barras al ejecutar) | PHP/SQLite, saneado, assets y documentación coherentes | 56 pruebas/1246 aserciones, exit 0, sin omisiones; 64,163 s | PHP 8.3.35/SQLite; 2026-10-15T21:32–21:33-04:00 | Fuentes de pruebas indicadas | SQLite no demuestra MySQL; contratos de permisos revisados estructuralmente |
| E-07 | CA-01, CA-02, CA-03, CA-04 | Diff y trazabilidad inversa; lint PHP/JS, skills y diff check; comparar con receta de editor y módulo multimedia | Solo cambios del principal/delta, fronteras MVC y copys/HTML PHP conservados | Correcto; exit 0; 14 skills/79 enlaces. No hay cambios de rutas, esquema, vendor TinyMCE ni dependencias obligatorias | Windows; 2026-10-15T21:32–21:33-04:00 | HtmlSanitizer.php, MediaController.php, _editor.php, richTextEditor.php/Media.meta.php, JS, CSS, tests/docs/skills | Avisos CRLF existentes; no release completa |
| E-08 | CA-04 | Revisar capturas escritorio 1440×1000 y móvil 390×844, tema claro/oscuro; ejecutar validador de etapa | Bootstrap/TinyMCE existentes, acciones visibles, imágenes en orden, sin overflow de página, estructura válida | Conforme; imagen sintética 360×240; validador exit 0 y mismos cuatro avisos no materiales | Chrome/Python; 2026-10-15T21:33-04:00 | `.playwright-mcp/media-writing-gallery.png`, `media-writing-desktop.png`, `media-writing-mobile.png` y artefactos de etapa | No incluye shell completo de producción; avisos de CA descritos en E-04 |

Entorno E-05: `NODE_PATH=C:/Users/Juank de Gorvet/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules`, `GFRAME_TEST_BROWSER=C:/Program Files/Google/Chrome/Application/chrome.exe`, `GFRAME_TEST_SCREENSHOTS=<repositorio>/.playwright-mcp`. PHP usado: `C:/xampp/php/php.exe`. Capturas ignoradas y regenerables con ese comando. El validador se ejecuta con `C:/Users/Juank de Gorvet/.codex/skills/feature-stages/scripts/validate_stage.py maintenance/features/media-library-improvements/03-writing`.

**Veredicto:** coincide

### Verificación global

Todos los métodos del principal se reejecutaron con el alcance explícito de transporte de esta etapa. T1 se verifica en E-06 y el round-trip de E-05; T2 en E-05, incluido el defecto de E-02; T3 en E-05–08. La prueba de escritura usa TinyMCE, archivos originales, controlador/servicios y SQLite reales; sus respuestas HTTP se transportan por procesos CLI controlados. No se hereda el verde anterior de las dos suites de servidor HTTP ni se usa como prueba de autorización. El campo, montajes, documentación y feedback anteriores sí se reejecutan aquí; la inserción no cambia su uploader ni sus rutas.

### Desviaciones de alcance

Informativa: se añadió una fixture visual mayor y un transporte de prueba sin sockets debido al bloqueo documentado. No cambian el runtime, autorización ni producto. Sin desviaciones pendientes. Edición visual y metadatos adicionales aplazados según prioridad del usuario; sin commit, etiqueta, publicación ni cambios a instalaciones.
