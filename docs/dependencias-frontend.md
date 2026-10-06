# Dependencias frontend distribuidas

Los archivos distribuidos con los módulos conservan sus avisos originales. Esta tabla documenta su procedencia y evita confundirlos con paquetes propios de GFrame.

| Módulo | Versión incluida | Fuente de versión | Licencia | Proyecto |
|---|---:|---|---|---|
| AOS | Sin versión exacta identificada | Distribución vendorizada sin versión fiable en el manifiesto | MIT | [Proyecto](https://michalsnik.github.io/aos/) |
| Bootstrap | 5.3.8 | Manifiesto del módulo | MIT | https://getbootstrap.com/ |
| Chart.js | 4.4.2 | Manifiesto del módulo | MIT | https://www.chartjs.org/ |
| Coloris | Sin versión exacta identificada | Distribución vendorizada sin versión fiable en el manifiesto | MIT | [Proyecto](https://github.com/mdbassit/Coloris) |
| Flatpickr | 4.6.13 | Manifiesto del módulo | MIT | https://flatpickr.js.org/ |
| html2canvas | 1.4.0 | Manifiesto del módulo | MIT | https://html2canvas.hertzen.com/ |
| intl-tel-input | 24.7.0 | Manifiesto del módulo | MIT | https://github.com/jackocnr/intl-tel-input |
| jQuery | 3.5.1 | Manifiesto del módulo | MIT | https://jquery.com/ |
| jQuery UI | 1.13.2 | Cabecera de la distribución vendorizada | MIT | https://jqueryui.com/ |
| Luxon | 3.6.1 | `VERSION` de la distribución vendorizada | MIT | https://moment.github.io/luxon/ |
| Owl Carousel | 2.3.4 | Manifiesto del módulo | MIT | https://owlcarousel2.github.io/OwlCarousel2/ |
| PureCounter | 1.5.0 | Manifiesto del módulo | MIT | https://github.com/srexi/purecounterjs |
| SweetAlert2 | 11.10.0 | Manifiesto del módulo | MIT | https://sweetalert2.github.io/ |
| Swiper | 11.2.10 | Manifiesto del módulo | MIT | https://swiperjs.com/ |
| TinyMCE | 8.6.0 | Manifiesto del módulo | GPL-2.0-or-later | https://www.tiny.cloud/ |
| VenoBox | 2.0.4 | Manifiesto del módulo | MIT | https://veno.es/venobox/ |

`gfselect`, `password-utils`, `gframe-icons`, `alerts`, `frontend-core`, `heartbeat-client` y la integración `rich-text-editor` son componentes propios.

`gf-table` también es un componente propio. Los números indican la copia distribuida, no la versión actual de la web del proveedor. Consulta las guías enlazadas desde [Instalación de módulos](modulos-opcionales.md#integraciones-y-componentes-externos) para carga, ejemplos y dependencias; [Puentes visuales](paquetes-visuales.md) reúne su adaptación al tema.

## Qué verifica automáticamente el repositorio

`ModuleCatalogTest` valida para los módulos externos, entre otros contratos:

- que sus manifiestos sean válidos y sus dependencias se puedan resolver;
- que cada origen declarado en `assets` exista dentro del paquete;
- que los destinos públicos no colisionen;
- que los módulos `external-ui` se puedan publicar y generen los destinos declarados.

Eso permite tratar **existencia, resolución y publicación** como contratos comprobados por CI. La versión se considera contractual cuando figura en el manifiesto; cuando no figura, la tabla identifica la copia vendorizada mediante los metadatos disponibles en sus archivos.

La licencia no se infiere ni valida automáticamente desde el manifiesto actual: esta columna es metadato curado a partir de los avisos distribuidos y del proyecto de origen. Debe revisarse cuando se sustituya una distribución externa. Los archivos originales mantienen sus avisos de licencia. TinyMCE distribuye condiciones GPL-2.0-or-later o licencia comercial del proveedor; revisa el archivo `license.md` incluido para la modalidad aplicable a tu proyecto. La licencia MIT de GFrame no sustituye las condiciones de sus dependencias.

## Qué sigue siendo una comprobación de integración

La suite no demuestra por sí sola que una biblioteca externa se vea o se comporte correctamente en todos los navegadores, temas y combinaciones de módulos. Tras actualizar una distribución deben comprobarse en navegador sus componentes principales, carga de CSS/JS, interacción móvil y convivencia con el tema del proyecto. Esa verificación manual no debe confundirse con un fallo abierto del core mientras los contratos de catálogo y publicación sigan pasando.
