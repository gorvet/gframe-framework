# Verificación — Scroll propio

2026-10-15; HEAD `4f901dc` más cambios locales previos y ajuste de editor. Skills canónicas indicadas en TAREAS.md; sin modificaciones a instalaciones, aside del proyecto ni vendor TinyMCE.

## Sensibilidad y resultado

La nueva prueba se ejecutó antes del ajuste: con 180 párrafos, TinyMCE creció de 580 a 7342 px, fallando la condición de altura estable. Tras retirar el plugin autoresize predeterminado, fijar la altura inicial a la configuración ya existente y añadir overscroll containment al estilo base del iframe, la prueba pasa. No se añadieron opciones nuevas al componente.

La aserción inicial de teclado se corrigió para situar primero el cursor al principio y enviar Control+End al body del iframe; situarlo ya al final no demostraba desplazamiento por teclado. La prueba comprueba scroll interno creciente, página exterior en cero y botón del aside en viewport y pulsable; rueda al final no desplaza el formulario. Prueba también contenido corto, redimensionado manual real con el tirador, tamaño por instancia 420/320 y elección explícita de autoresize. La integración previa de biblioteca, galerías, saneado/recarga e isDirty sigue pasando.

## Comandos ejecutados

Con NODE_PATH del runtime y GFRAME_TEST_BROWSER apuntando a Chrome:

```powershell
node --test --test-concurrency=1 tests/js/rich-text-scroll.browser.test.cjs tests/js/media-writing.browser.test.cjs tests/js/rich-text-documentation.test.cjs
php packages/phpunit/phpunit/phpunit --filter 'RichTextEditor|DocumentationLinks|MediaDocumentation'
php bin/validate-skills.php
node --check resources/modules/rich-text-editor/public/rich-text-editor.js
git diff --check
```

Resultado: tres pruebas JS/navegador pasan, sin omisiones, 37,298 s; 12 PHP/182 aserciones, sin omisiones, 8,829 s; 14 skills/79 enlaces válidos; sin errores de sintaxis ni diff. Avisos CRLF previos conservados.

## Revisión visual y límites

Capturas `.playwright-mcp/rich-text-scroll-desktop.png` (1440×1000) y `rich-text-scroll-mobile.png` (390×844): toolbar fuera del contenido desplazable, editor/aside alineados en escritorio y apilados en móvil; sin overflow horizontal. Bootstrap/TinyMCE y sus estilos existentes; sin capa de diseño nueva. En móvil el aside queda después del editor según Bootstrap, sin prometer que sea fijo.

La prueba usa TinyMCE real con assets servidos por Playwright sin sockets. La regresión de biblioteca conserva backend PHP/SQLite real por procesos CLI y transporte interceptado; no certifica HTTP vivo ni aplicaciones instaladas. No se alteran estilos/configuraciones explícitas del proyecto, ni se certifican otros navegadores o lectores de pantalla. Un proyecto que reemplace content_style debe conservar el aislamiento si lo desea.

Lista para auditoría separada.
