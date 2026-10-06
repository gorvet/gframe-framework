# Auditoría inicial de compatibilidad

## Alcance

Se compararon aplicaciones con y sin tenant para identificar el núcleo común, las variaciones de implementación y las dependencias que deben permanecer opcionales.

## Resultados

- Entre los archivos divergentes están Router, RouteBuilder, Middleware, ORM, Render, Meta, Load, permisos y paginación.
- Los proyectos repiten Bootstrap, jQuery, SweetAlert, AOS, Venobox, `gfselect`, fuentes e iconos.
- `intlTelInput` aparece copiado con más de 500 archivos en varios proyectos.
- TinyMCE ocupa aproximadamente 11 MB en cada proyecto que lo utiliza.
- Los CSS comunes han divergido y no deben centralizarse sin una conciliación previa.

## Decisión

La extracción conserva únicamente las capacidades reutilizables. Ninguna aplicación concreta se considera fuente absoluta del framework.

## Cierre de paquetes frontend

Se compararon los directorios públicos de Base Confías, Bebots, Dane, RAG, Libros, AIPrint MVP y LiangApp Web. El catálogo de módulos cubre todos los componentes reutilizables encontrados:

- base obligatoria: Bootstrap, jQuery, SweetAlert2, `gframe-icons`, `alerts` y `frontend-core`;
- componentes propios seleccionables: `gfselect`, `password-utils`, `heartbeat-client` y `rich-text-editor`;
- componentes externos seleccionables: AOS, Chart.js, Coloris, Flatpickr, html2canvas, intl-tel-input, jQuery UI, Luxon, Owl Carousel, PureCounter, Swiper, TinyMCE y VenoBox;
- integración seleccionable: estilos para contenido WordPress.

Los nombres históricos de la fuente `bebots` se consolidaron como `gframe-icons`.

## Elementos que no se trasladan

- `qrcode`: retirado por decisión del proyecto.
- `opusConverter`: retirado por decisión del proyecto.
- `TextClassifier` y `wamania/php-stemmer`: permanecen en Bebots por ser parte de su algoritmo conversacional.
- PHPMailer, Opis Closure, PHP-SSE y PHPAsync: ya están cubiertos por Composer o por el núcleo de GFrame; no deben existir dentro de `public/vendors`.
- Las reglas, controladores y modelos particulares de cada negocio no forman parte del catálogo.

La biblioteca multimedia, las notificaciones, WordPress headless y la administración de usuarios ya están normalizadas como módulos opcionales. Sus esquemas, servicios y recursos se instalan desde el catálogo sin copiar reglas particulares de las aplicaciones auditadas.
