# Verificación de GFrame 3.0.0

Fecha: 14 de octubre de 2026. Entorno: Windows, PHP 8.3.35 y Node 24.13.0. Trabajo realizado en el framework; no se actualizaron proyectos consumidores.

## Alcance y contratos

- Retirada de `media_relations` de los esquemas nuevos y de la API `attach()`, `detach()` y `related()` del servicio, modelo y contrato de repositorio.
- Migraciones ordenadas MySQL y SQLite registradas en el manifiesto para eliminar la tabla auxiliar al actualizar.
- Cada CRUD conserva su ID de imagen o JSON ordenado de galería. El selector, los archivos y las variantes mantienen sus contratos.
- Documentación de medios, actualización, índice, changelog y transición a 3.0.0 actualizados; skills canónicas de medios y routing revisadas.
- Versión mayor por retirada de métodos públicos. Composer obtiene la versión de la etiqueta Git; no se añade `version` al manifiesto.
- Se conservan fuera del release los cambios previos del aviso de subida y su prueba de navegador.

Skills aplicadas: `skills/gframe-orchestrator/SKILL.md`, `skills/gframe-framework-maintenance/SKILL.md`, `skills/gframe-media-module/SKILL.md` y `skills/gframe-orm-models/SKILL.md`. Revisión editorial mediante la skill española local.

## Evidencia

- Lint completo: 419 archivos PHP válidos.
- Suite PHP completa con `COMPOSER_PROCESS_TIMEOUT=1800`: 474 pruebas, 4545 aserciones, 10 omitidas y ningún fallo. El primer `composer check` agotó el timeout de 300 segundos durante PHPUnit; se completaron sus verificaciones por separado.
- Pruebas enfocadas de medios, documentación e instalación: 34 pruebas, 306 aserciones y una omitida.
- La prueba de migración SQLite comprueba retirada de la tabla, conservación del registro de archivo y de los campos del contenido, y ejecución única del actualizador.
- JavaScript: 87 pruebas, 79 aprobadas, 8 de navegador omitidas y ningún fallo. Incluye la prueba pendiente preexistente; esa prueba queda fuera del release.
- `composer validate --strict` y `composer audit`: aprobados, sin avisos de vulnerabilidades.
- Skills: 14 válidas y 79 enlaces locales comprobados.
- Revisión editorial de la guía de transición: sin errores críticos ni advertencias. Guía de medios: sin errores críticos; advertencias del script revisadas en el contexto de ejemplos de código y textos existentes.
- `git diff --check`: aprobado.

## Límites

No se ejecutó la migración sobre MySQL real ni se probaron navegadores en esta tarea. No se verificó la metadata YAML mediante Python porque falta PyYAML en el runtime disponible. La publicación de la etiqueta y del release requiere acceso a GitHub; Git HTTPS y el navegador integrado fallaron por conectividad en este entorno. El conector de GitHub permite publicar el commit, pero no ofrece creación de etiquetas o releases.
