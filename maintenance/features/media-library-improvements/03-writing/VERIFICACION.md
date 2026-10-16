# Verificación de implementación — Escritura

Fecha: 2026-10-15. Baseline: HEAD `4f901dc` más cambios locales de etapas 01–03 y corrección previa del toast. Windows, PHP 8.3.35, SQLite, Node 24.13.0, Chrome. Sin cambios a aplicaciones instaladas.

## Resultados

- PHP: `php packages/phpunit/phpunit/phpunit --filter 'MediaWriting|HtmlSanitizer|RichTextEditor|MediaManagement|MediaRuntime|MediaLibrary|MediaDocumentation|MediaScopeResolver|MediaMountIdentifiers|ProjectUpdateService|DocumentationLinks'`: 56 pruebas, 1246 aserciones; exit 0, sin omisiones, 45,214 s.
- JS/navegador: `node --test --test-concurrency=1 tests/js/media-writing.browser.test.cjs tests/js/media-field-controls.browser.test.cjs tests/js/media-multiple-mounts.test.cjs tests/js/media-toast.browser.test.cjs tests/js/media-field.test.cjs tests/js/rich-text-documentation.test.cjs`: seis pasan, ninguna omitida, exit 0, 46,991 s.
- Skills: 14 y 79 enlaces válidos. PHP lint de nueve archivos y JS check del editor; diff sin errores.

## Evidencia por tarea

T1/CA-01/03: MediaWritingTest ejecuta controlador, servicio y SQLite reales, con global/user/tenant, orden exacto, duplicados normalizados, mezcla ajena, no imagen, cero IDs, vacío, 51 IDs y URL del navegador ignorada. No devuelve HTML parcial. HtmlSanitizerTest conserva figuras/IDs seguros y elimina eventos, clases, marcadores inválidos y JavaScript; sanitizado idempotente. RichTextEditorTest prueba publicación de CSS/JS y original/override.

T2/CA-01/02: TinyMCE real en Chrome, con PHP/SQLite reales por procesos CLI, verifica botón mediante Enter, cursor entre texto anterior/posterior, sustitución sin duplicar, undo/redo, galería y reapertura ordenada, quitar, cancelar, saneado PHP y recarga, dos instancias, error 403 simulado, respuesta tardía con documento modificado y destrucción/recreación. Se protege texto añadido manualmente a la figura: no se recompone ni se elimina.

T3/CA-04: editor sin activar conserva controles/configuración; documentación de registro sigue ejecutándose; pruebas anteriores de campo, montajes y aviso pasan. Picker real, sus fragmentos y Bootstrap se ejercitan dentro de escritura. Capturas `.playwright-mcp/media-writing-{gallery,desktop,mobile}.png`; revisión de escritorio/móvil. CSS de galerías separado de administración; meta opcional resuelto mediante ModuleRuntime. Sin cambios de rutas, dependencias obligatorias ni esquema.

## Fallos y límites conservados

El servidor PHP temporal arrancó, pero las conexiones fueron denegadas por el entorno (`connect EACCES 127.0.0.1`; Chrome `ERR_NETWORK_ACCESS_DENIED`). No se alteró firewall ni configuración de red. La prueba nueva sirve cada petición interceptada por Playwright mediante PHP CLI y SQLite real, sin sockets. Esto prueba editor, fragmentos y persistencia/sanitización; no prueba HTTP vivo ni middleware. Las dos pruebas anteriores que requieren servidor HTTP (gestión y selector completo con subidas) no se reejecutan bajo esta limitación; su evidencia anterior permanece en etapas 01/02. Las rutas y el uploader no cambian en esta etapa.

Fallos iniciales de aserción: TinyMCE codifica tildes como entidades; se corrigió la comparación sin cambiar esa política. La recarga debe esperar a todas las instancias, no solo a la última; la prueba se corrigió tras una ejecución concurrente que observó una instancia todavía vacía. Las esperas de sustitución ahora verifican el ID nuevo, no una cuenta de imágenes que ya era cierta. No se presentan esos intentos fallidos como resultados finales.

La fixture guarda únicamente HTML saneado como demostración; no es un controlador de artículos ni certifica autorización HTTP. La aplicación receptora debe validar IDs y URLs bajo su ámbito además de sanear. No se certifican lector de pantalla, contraste instrumental, MySQL, aplicaciones instaladas ni red de producción.

Implementación lista para auditoría separada contra TAREAS.md.
