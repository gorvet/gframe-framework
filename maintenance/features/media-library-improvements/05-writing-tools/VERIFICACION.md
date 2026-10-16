# Verificación de implementación

2026-10-15. Implementación terminada; pendiente de auditoría separada. Fuentes originales de rich-text-editor, sin modificar TinyMCE vendor ni proyectos instalados.

| Tarea | Evidencia |
|---|---|
| T1 | media-writing.browser.test.cjs abre «Más herramientas» por teclado; rich-text-scroll.browser.test.cjs conserva modo sliding, altura y móvil sin overflow |
| T2 | media-writing.browser.test.cjs cambia imagen, reedita galería, quita y deshace; comprueba medios existentes y dirty |
| T3 | media-writing.browser.test.cjs comprueba validación, Ctrl+S y botón, disabled/lectura, destino ajeno y bloqueo entre instancias durante medios pendientes |
| T4 | rich-text-documentation.test.cjs conserva callbacks; 12 pruebas PHP/184 aserciones; validate-skills.php: 14 skills y 79 enlaces; lint PHP, node --check y diff --check pasan |
| T5 | rich-text-scroll.browser.test.cjs usa teclado real para filtrar /titulo y /lista, crear h2/lista, deshacer/rehacer, Escape sin pérdida y slash dentro de frase sin menú. Barra contextual nativa visible con cita y contador con palabras |

Comandos: Node `--test --test-concurrency=1` sobre media-writing.browser.test.cjs, rich-text-scroll.browser.test.cjs y rich-text-documentation.test.cjs. La ejecución conjunta pasó medios y documentación; el test de texto se corrigió y reejecutó al añadir casos: último resultado 1/1, 8,304 s, sin omisiones. NODE_PATH del runtime de Codex y GFRAME_TEST_BROWSER apuntando a Chrome instalado. PHP: `packages/phpunit/phpunit/phpunit --filter 'RichTextEditor|DocumentationLinks|MediaDocumentation'`: exit 0, 12/184, 17,783 s.

Negativos conservados: TinyMCE serializa la tilde como entidad (aserción cambiada a texto DOM); deshacer después de escribir revierte primero esa escritura (se comprueba la transformación antes de escribir); etiqueta nativa de cita es «Cita en bloque». El submit automático nativo limpiaba dirty; el puente optativo usa sincronización propia con set_dirty:false y se comprobó el estado tras submit prevenido. El botón nativo deshabilitado usa aria-disabled.

Revisión visual: .playwright-mcp/rich-text-selection.png muestra formatos junto al texto y contador, con aside accesible. Capturas de scroll escritorio/móvil revisadas en la etapa anterior y regeneradas en esta ejecución. Capturas locales ignoradas por Git.

Límites: Chrome con assets reales y PHP por CLI; transporte interceptado por Playwright debido al bloqueo de sockets locales. No certifica middleware HTTP vivo, producción ni todos los navegadores. Sin commit ni etiqueta. Auditoría separada pendiente; este documento no emite veredicto.
