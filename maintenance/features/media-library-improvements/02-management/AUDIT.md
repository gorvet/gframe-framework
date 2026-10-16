# Auditoría — etapa 02 Gestión

## Pasada 1 — 2026-10-15

**Contra:** TAREAS.md (principal)
**Baseline:** conciliación cerrada 2026-10-15; tareas del mismo día; HEAD `4f901dc` más cambios locales de selector/gestión y corrección previa del toast. Entorno Windows, PHP 8.3.35, SQLite real, Node 24.13.0 y Chrome local. Reejecución separada tras finalizar implementación; no se reparó código durante esta pasada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Cuadrícula/lista PHP; seis órdenes; fallback inválido e inyección; empate estable; páginas y filtros; aislamiento global/user/tenant; URL al recargar y Atrás | T1 | cumple | E-01, E-02, E-04 | Orden alfabético depende de la colación del motor; integración ejecutada con SQLite |
| CA-02 | Anterior/siguiente cruza páginas en ambos sentidos; extremos desactivados; edición/guardado bloquean salida; descartar; error 500 mantiene archivo anterior; 403 no guarda; detalle tardío no contamina reapertura; foco vuelve al listado | T2 | cumple | E-01, E-02 | 403 sintético verifica UX; las rutas conservan middleware sin modificarlo |
| CA-03 | Nombre y alt saneados por controlador; reproducción real de MP3/WebM; sin autoplay; `src` liberado al cambiar/cerrar; enlace de archivo | T2, T3 | cumple | E-01, E-02, E-04 | No certifica todos los códecs ni servidores remotos; no se promete proxy/descarga privada |
| CA-04 | Selector y campos conservados; teclado sin doble activación; vacío/una/múltiples páginas; tabla horizontal y footer móvil; tema oscuro; documentación y skills; esquema/rutas sin cambios | T4 | cumple | E-01, E-02, E-03, E-04, E-05 | Sin auditoría formal de lector de pantalla, contraste instrumental, zoom ni producción; fuera del método acordado |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01, CA-02, CA-03, CA-04 | `C:/xampp/php/php.exe packages/phpunit/phpunit/phpunit --filter 'MediaManagement\|MediaRuntime\|MediaLibrary\|MediaDocumentation\|MediaScopeResolver\|MediaMountIdentifiers\|ProjectUpdateService\|DocumentationLinks'` (sin escapes de barra en PowerShell) | Órdenes, ámbitos, backend, publicación y documentación coherentes | 36 pruebas / 1136 aserciones; exit 0, sin omisiones; 43,941 s | Windows/PHP 8.3.35; 2026-10-15T20:29–20:30-04:00 | Salida de terminal; tests/MediaManagementTest.php y tests/MediaRuntimeTest.php | SQLite; no equivalencia inferida con ejecución MySQL |
| E-02 | CA-01, CA-02, CA-03, CA-04 | Comando y entorno de navegador debajo | Flujos de gestión y regresiones de selector/campos pasan sin errores JS | 6 pruebas, 6 pasan, 0 omitidas; exit 0; 34,823 s | Chrome local/Node 24.13.0/PHP temporal; 2026-10-15T20:29-04:00 | tests/js/media-management.browser.test.cjs y regresiones indicadas | Fixture con backend real y sesión sintética; no bootstrap de aplicación instalada |
| E-03 | CA-04 | Abrir capturas generadas por E-02, comparar con lista notification-campaigns y modal existente | Tabla legible, controles accesibles, footer visible, sin desbordamiento de página ni decoración ajena | Revisión visual: escritorio 1440×1000, móvil 390×844, oscuro 768×1024; conforme. IDs únicos y footer dentro del viewport comprobados en navegador | Mismo entorno; 2026-10-15T20:29-04:00 | `.playwright-mcp/media-management-desktop.png`, `media-management-mobile.png`, `media-management-mobile-list.png`, `media-management-dark-tablet.png` | Capturas locales ignoradas; se regeneran con E-02. La fixture no incluye todo el shell de una aplicación |
| E-04 | CA-01, CA-03, CA-04 | Revisión de diff y trazabilidad; `php -l` de nueve fuentes/tests; `node --check` de media-admin.js y media-library.js; `php bin/validate-skills.php`; `git diff --check` | MVC, SQL permitido, PHP HTML/copy, assets/meta y compatibilidad; sintaxis y enlaces correctos | Exit 0; 14 skills y 79 enlaces; sin errores de diff. Sin cambios de rutas, esquema ni manifest | Windows; 2026-10-15T20:29–20:30-04:00 | Fuentes descritas en VERIFICACION.md y docs/media-library.md | Avisos CRLF no son errores. Contratos de otros módulos no se reauditan |
| E-05 | CA-04 | Validador de etapa antes y después de la auditoría | Artefactos estructuralmente válidos y todos los CA vinculados | Exit 0; cuatro avisos de tipo de CA | Python del runtime; 2026-10-15T20:30-04:00 | CONCILIACION.md, TAREAS.md y este archivo | El regex del validador exige literalmente ambos tipos entre corchetes; la conciliación sí declara un tipo por CA. Se conservan avisos sin cambiar el baseline |

Reproducción de E-02 desde el repositorio en PowerShell:

```powershell
$env:NODE_PATH='C:/Users/Juank de Gorvet/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
$env:GFRAME_TEST_BROWSER='C:/Program Files/Google/Chrome/Application/chrome.exe'
$env:GFRAME_TEST_SCREENSHOTS=(Join-Path (Get-Location) '.playwright-mcp')
& 'C:/Program Files/nodejs/node.exe' --test tests/js/media-management.browser.test.cjs tests/js/media-picker-workflow.browser.test.cjs tests/js/media-field-controls.browser.test.cjs tests/js/media-multiple-mounts.test.cjs tests/js/media-toast.browser.test.cjs tests/js/media-field.test.cjs
```

Validador E-05:

```powershell
& 'C:/Users/Juank de Gorvet/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe' 'C:/Users/Juank de Gorvet/.codex/skills/feature-stages/scripts/validate_stage.py' maintenance/features/media-library-improvements/02-management
```

**Veredicto:** coincide

### Verificación global

T1 → modelo/servicio/controlador, `_mlist.php`, `_table.php` y estado del listado → órdenes/ámbitos en SQLite y UI. T2 → media-admin.js y modal → navegación, fallos inyectados, protección y foco. T3 → reproductores del modal y fixtures sintéticas → reproducción y liberación observables. T4 → CSS, docs, skills y regresiones → E-01–05. Todas las verificaciones principales ejecutadas. La regresión inicial de foco y fixture WAV rechazada permanecen en VERIFICACION.md como evidencia de implementación, sin presentarlas como pases de auditoría.

### Desviaciones de alcance

Informativa: restauración del foco y footer persistente concretan CA-04, sin añadir otro flujo. Sin cambios a la conciliación ni desviaciones pendientes. Los archivos previos del selector y la corrección del toast se conservan; no son nueva funcionalidad de esta etapa. Sin commit, etiqueta, publicación ni cambios en producción.
