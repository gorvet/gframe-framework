# Auditoría — Protección de escritura

## Pasada 1 — 2026-10-15

**Contra:** TAREAS.md principal y CONCILIACION.md cerrada 2026-10-15.
**Baseline auditado:** HEAD 4f901dc, cambios locales de etapas anteriores preservados; runtime JS SHA-256 `5BFFA9B220A839D9B9CDE6E7585E336E93EF76EE3155ECBC9F8325E5C75B9D10`; test de protección `1450561DD359E3B40C8199B9A81790F6ED76EE770F8E19C6ED92A135F9208986`. Auditoría posterior a la implementación, sin reparaciones de código/pruebas durante la pasada. Etapa 05 cerrada antes de implementar.

| CA | Afirmaciones atómicas y trazabilidad | Resultado | Evidencia |
|---|---|---|---|
| CA-01 | T1: edición provoca beforeunload real; cancelación conserva HTML; saveAll/submit prevenido no confirman; snapshot actual confirmado no avisa; opción false respetada | cumple | E-01, cancelación y recarga aceptada en Chrome; comparación separada de dirty inspeccionada |
| CA-02 | T2: copia tras pausa y al salir; servidor permanece hasta recuperar; undo/redo; conflicto visible; cuota y límite separados no bloquean escritura y se comunican | cumple | E-01; storage real y fallos inyectados, no simple simulación del callback |
| CA-03 | T2/T3: actor, documento, campo y pestaña aislados; dos copias distintas; descarte exige swalAlert, cancelar/falta de helper no borra; JSON inválido/caducidad y HTML hostil no ejecutan código | cumple | E-01, E-02; filtrado en TinyMCE real y comparación exacta antes de eliminar; backend sigue siendo obligatorio |
| CA-04 | T3: snapshot inmutable antes del envío; reconocerlo conserva escritura posterior; old instance, objeto inventado y ack anterior se rechazan; retirar editor conserva cambio sin esperar timer, sin escrituras tardías | cumple | E-01; prueba también consume copia recuperada editada antes de enviar; E-04 |
| CA-05 | T4: entrar/salir de concentración y esquema con teclado; cursor/scroll llegan al título sin cambiar HTML; móvil sin overflow; biblioteca visible y vuelve a fullscreen; setup/scroll/documentación/overrides conservados | cumple | E-01, E-02, E-03 |

### Ledger de evidencia

| ID | Procedimiento/comando | Esperado | Observado / exit | Entorno y timestamp | Artefacto y límites |
|---|---|---|---|---|---|
| E-01 | `node --test --test-concurrency=1 tests/js/rich-text-protection.browser.test.cjs tests/js/media-writing.browser.test.cjs tests/js/rich-text-scroll.browser.test.cjs tests/js/rich-text-documentation.test.cjs` | Positivos, negativos y regresiones pasan | 5/5; cero omisiones; exit 0; 98,954 s | Node 24/Chrome, Windows; 2026-10-15 22:59–23:01 America/Havana | Tests indicados; assets, browser storage y PHP CLI reales; transporte interceptado, sin middleware HTTP vivo |
| E-02 | `php packages/phpunit/phpunit/phpunit --filter 'RichTextEditor|DocumentationLinks|MediaDocumentation'`; `php bin/validate-skills.php`; node --check; git diff --check | Publicación, escape PHP, recursos opcionales, overrides y guías válidos | 13 tests/198 aserciones, 16,503 s; 14 skills/79 enlaces; exit 0 | PHP 8.3.35/Windows; 2026-10-15 22:59 America/Havana | Tests del módulo y documentación; avisos de normalización CRLF sin error; no suite de release |
| E-03 | Revisar capturas de recuperación, confirmación y móvil de E-01 | Controles legibles, Cancelar antes de descartar, ancho móvil sin desbordamiento | Conforme | Chrome 1440×1000 y 390×844; 2026-10-15 23:01 America/Havana | .playwright-mcp/rich-text-recovery.png, rich-text-recovery-mobile.png, rich-text-discard-confirm.png, rich-text-protection-mobile.png; capturas locales ignoradas, no lector de pantalla |
| E-04 | Revisión de fuente, snapshot y validador de etapa | Baseline/tareas/código/evidencia coherentes; alcance acotado | Conforme, validador estructural válido | Windows; 2026-10-15 23:01 America/Havana | Warnings heredados del regex de tipos CA, declarados correctamente; sin reescribir aceptación. Sin migración SQL necesaria |

E-01 utiliza NODE_PATH del runtime de Codex, GFRAME_TEST_BROWSER a Chrome instalado y GFRAME_TEST_SCREENSHOTS a .playwright-mcp. Los intentos negativos de cuota, contenido demasiado grande, HTML hostil, confirmación ausente y respuestas tardías se reejecutaron contra el snapshot auditado. La sensibilidad rojo/verde de consumo de copias está conservada en VERIFICACION.md; no hubo reparaciones dentro de esta auditoría.

### Verificación global

CA → tareas → fuentes PHP/JS → aserciones de navegador/documentación y PHP quedan trazados en ambas direcciones. Copy/HTML proceden de PHP; descarte usa swalAlert canónico, con meta opcional para plantillas que no cargan alerts. TinyMCE vendor, permisos, backend de negocio y sitios instalados no se modificaron. Los cambios de medios aquí se limitan a compatibilidad con concentración. Las copias no son guardado remoto y solo cubren el campo; ambos límites figuran en la guía.

### Desviaciones y pendientes

Ninguna desviación bloqueante. Chrome es el entorno acordado para estas pruebas; cierres forzados, middleware vivo, producción, políticas de logout de cada aplicación y otros navegadores están fuera de esta verificación, no se presentan como certificados. Los sitios deberán proporcionar draft_key y confirmar snapshots al actualizarse. Mover secciones y otras mejoras de enlaces siguen aplazados. Cambios locales sin commit, etiqueta ni publicación.

**Veredicto:** coincide
