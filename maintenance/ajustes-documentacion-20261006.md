# Ajustes posteriores de documentación e identificadores

Fecha: 6 de octubre de 2026. Estos cambios responden a la revisión del usuario después de conciliar las ramas; no se atribuyen a GitHub ni a los commits locales originales.

## Documentación del framework

- `docs/arquitectura.md`: las tres capas explican responsabilidades; se distingue el nombre de una clase del concepto general.
- `docs/servidores-web.md`: Apache queda reunido en un apartado, seguido de Nginx, sin repetir Apache al final.
- `docs/index.md`: preparación del servidor antes de la instalación y acceso a vistas, header, footer y menús.
- `docs/configuracion.md`: el ejemplo explica cuándo se aplica el cambio de `IMPORT_MAX_ROWS` a peticiones y workers, en lugar de terminar con recomendaciones sin contexto.
- `docs/header.md`: ejemplo explícito de `hjs`, ámbitos global/template/vista y relación con el preload del panel.
- `docs/footer.md`: footer compartido de la aplicación, áreas independientes, recursos y cierre del documento.
- `docs/menus.md`: generador `MenuHelper::build()`, ampliación del menú administrativo y separación entre HTML y persistencia del sidebar. No existe una clase MenuBuilder.
- `docs/panel-administrativo.md`, `docs/estilos-comunes.md`: claves `gf-theme` y `gf-sidebar`, atributos HTML, preload, sincronización entre pestañas y responsabilidad de las variables CSS.
- `docs/aos.md`, `docs/coloris.md`, `docs/luxon.md`, `docs/dependencias-frontend.md`, `docs/paquetes-visuales.md`: versiones reales de las distribuciones. Manifiestos actualizados para AOS 3.0.0-beta.6, Coloris 0.25.0, Luxon 3.6.1 y jQuery UI 1.13.2. AOS coincide con los cuatro archivos JS/CSS del paquete oficial de npm; Coloris coincide con el JavaScript de la etiqueta v0.25.0, normalizando saltos de línea.

## Sitio local de documentación

Se trabaja únicamente en la aplicación `C:/xampp/htdocs/gframe-docs`, sin modificar su paquete instalado del framework:

- `app/views/docs/docsIndex.php` y su meta: introducción centrada en el valor del framework; eliminados el bloque redundante de módulos y la explicación de recargar para ver cambios locales.
- `app/views/docs/docsCredits.php`: producto desarrollado por Juank de Gorvet, con el LinkedIn indicado por el usuario.
- `app/views/templates/footer/credits.php`: «Un producto de Gorvet Estudios, desarrollado por Juank de Gorvet», con enlaces a Gorvet y LinkedIn.
- `app/services/docs/DocumentationRepository.php`: guías antes ausentes del catálogo, grupo Vistas y templates y Apache/Nginx antes de Instalación.
- `app/views/docs/docsModules.php`: versiones visibles en el listado, además de la ficha de cada biblioteca y los créditos.
- `config/routes/routes_web.php`: conserva el enlace antiguo del selector.
- `tests/check.php`: orden, descubribilidad y versiones verificadas.

## Identificador GF Select

La convención elegida es `gf-select` y `gf-table`. Se trasladó intacta la carpeta del selector a `resources/modules/gf-select/` y se ajustó su manifiesto. La clase JavaScript `GFSelect` no cambia.

`ModuleCatalog` normaliza el alias `gfselect`, las dependencias usan el nombre nuevo y `ModuleRuntime::isInstalled()` reconoce ambos. El publicador mantiene los dos destinos de assets para no romper metas existentes. `ProjectUpdateService` ya no pierde un destino cuando dos publicaciones comparten el mismo origen. Se actualizan las referencias de Campañas, documentación, fixtures, pruebas e instrucciones canónicas de mantenimiento.

## Verificación

- Suite completa tras los cambios iniciales: 410 pruebas, 4150 aserciones y 3 omitidas; sin fallos. Lint correcto y 9 skills válidos, con PHP 8.1.33 portátil.
- Tras corregir el caso de actualización con destinos duplicados: 29 pruebas de catálogo y actualizador, 966 aserciones; sin fallos. Incluye lectura del registro antiguo y publicación en ambas rutas.
- 6 pruebas JavaScript de GF Select correctas.
- Sitio de documentación: 151 comprobaciones correctas y sintaxis PHP válida en los archivos modificados.
- Respuestas HTTP 200 para inicio, header, menús, Apache/Nginx, AOS y los identificadores nuevo y antiguo de GF Select en la vista previa local. El inicio y la navegación también se comprobaron en el navegador.
- `git diff --check` correcto. Sin commit ni push.

La actualización de PHP en XAMPP sigue pendiente por el requisito de Opis 4. El sitio de documentación continúa utilizando su paquete instalado; no se ha actualizado ese paquete ni modificado el servidor.

## Preparación de la versión 1.1.0

Por solicitud del usuario, los cambios se guardan en commits y se prepara la etiqueta local `v1.1.0` sobre `main`. La subida a GitHub continúa pendiente; la etiqueta local no publica por sí sola una versión en Packagist.

Verificación final: `composer check` correcto con PHP 8.1.33 (lint, 410 pruebas, 4154 aserciones, 3 omitidas y 9 skills válidos); Composer válido, requisitos de plataforma correctos y auditoría sin avisos de seguridad. Las 6 pruebas JavaScript y las 151 comprobaciones del sitio también pasan. El sitio conserva su lock anterior hasta que se publique la versión y se pruebe la actualización del paquete.
