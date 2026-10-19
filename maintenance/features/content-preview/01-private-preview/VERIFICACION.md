# Verificación de implementación

2026-10-19; Windows, PHP 8.3.35, SQLite y Chrome headless con Playwright.

- `php packages/phpunit/phpunit/phpunit --filter 'ContentPreview|ContentAutosave|RichText|ModuleCatalog|ProjectUpdateService' --log-junit .phpunit.cache/content-preview-regression.xml`: 44 pruebas, 1115 aserciones; exit 0. Una omisión: integración MySQL del autoguardado sin DSN configurado; no cambia su esquema en esta etapa.
- `node --test tests/js/*.test.cjs`: 97 pruebas, 81 aprobadas, 16 omitidas por requerir navegador; exit 0. Log `.phpunit.cache/content-preview-js.log`.
- Prueba específica con `GFRAME_TEST_BROWSER` apuntando a Chrome y `NODE_PATH` al runtime: botón real de TinyMCE abre pestaña privada; un cambio de versión provoca una recarga. Exit 0. El transporte del navegador está interceptado; PHP/SQLite y cabeceras HTTP tienen pruebas independientes.
- Durante la preparación del fixture se corrigieron doctype, ruta del idioma, anchura y charset de las respuestas. Los fallos previos fueron del documento simulado; la prueba final conserva las aserciones de apertura y recarga.
- `php bin/validate-skills.php`: 14 skills, 79 enlaces correctos. Lint PHP y JS focalizados y `git diff --check` correctos.

El framework entrega un módulo optativo. No se ha integrado ni probado una aplicación instalada, ni se ha creado una etiqueta o publicación. La aplicación debe autorizar cada lectura y renderizar su plantilla pública según `docs/content-preview.md`.
