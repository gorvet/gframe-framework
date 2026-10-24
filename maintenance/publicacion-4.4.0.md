# Publicación 4.4.0

Alcance autorizado: publicar el framework integrado en main y actualizar el sitio documental local. Base: fd0277f, PR de seguridad/calidad/coordinación integradas y diez jobs de CI aprobados. Skills canónicas aplicadas: gframe-orchestrator y gframe-framework-maintenance, con sus referencias de repositorio y documentación.

Después de integrar las notas y comprobar CI se publica la etiqueta anotada `v4.4.0` sobre el SHA verificado de main y la release correspondiente, con las notas del changelog. Nunca se mueve una etiqueta existente a otro commit. La versión del paquete se obtiene de Git: no se introduce un campo version manual en composer.json. Se comprueba aparte la indexación de Packagist.

Comprobaciones: Composer estricto, PHP/JS/skills en CI y revisión de las notas. Los 68 diagnósticos heredados de PHPStan permanecen como deuda, no se presentan como resueltos. Revisar APP_URL y listas blancas de columnas/operadores ORM antes de actualizar aplicaciones. Sin migraciones nuevas en esta entrega.

El sitio gframe-docs usa APP_ENV=local: consume Markdown y recursos desde DOCS_REPOSITORY, manteniendo independiente el runtime instalado por Composer. La fuente local debe apuntar al main verificado. No se actualizan otros consumidores ni se despliega el sitio público.
