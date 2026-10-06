# Owl Carousel

Librería externa opcional de carruseles basada en jQuery. GFrame distribuye la versión 2.3.4; el módulo declara `jquery` como dependencia.

Seleccione `owl-carousel` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public owl-carousel`. Los archivos quedan en `public/vendors/external/owl.carousel/`. Cargue `assets/owl.carousel.min.css`, el tema que elija desde `assets/` y `owl.carousel.min.js` después de jQuery. La aplicación define el contenido y la inicialización. `swiper` es una alternativa independiente.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Inicialización y ciclo de vida

Cargue `assets/gframe-owl-carousel.css` después de los estilos originales. El ejemplo requiere un contenedor `id="featured"` con clase `owl-carousel` y sus elementos hijos:

```js
$('#featured').owlCarousel({ items: 1, nav: true, dots: true, loop: false });
// Antes de sustituir el fragmento:
// $('#featured').trigger('destroy.owl.carousel');
```

No inicialice también Swiper sobre el mismo contenedor. El carrusel no obtiene contenidos ni imágenes; la vista debe renderizarlos y conservar sus textos alternativos.

[Proyecto y documentación oficial de 2.3.4](https://owlcarousel2.github.io/OwlCarousel2/).


## Contenido, responsive y navegación

El carrusel controla presentación, no contenido. Renderice en PHP los enlaces, imágenes, títulos y textos con el mismo escape y autorización que usaría fuera del carrusel. Las imágenes informativas necesitan texto alternativo y los enlaces deben conservar un nombre comprensible sin depender de su posición visual.

Configure el número de elementos y puntos de corte desde la vista según el contenido real. No fuerce muchas tarjetas en anchos pequeños solo para mantener la misma composición de escritorio.

## Accesibilidad

Los carruseles pueden dificultar navegación y lectura si cambian automáticamente. Evite autoplay para contenido que el usuario necesita leer, o proporcione controles claros para pausarlo. Revise teclado, foco y orden DOM; una diapositiva fuera de pantalla no debe provocar que el usuario pierda contexto al tabular.

Los botones de navegación necesitan nombres accesibles. Si reemplaza su contenido por iconos, conserve `aria-label` o texto equivalente.

## Fragmentos AJAX

Inicialice Owl después de insertar el contenedor. Antes de reemplazar o eliminar el fragmento, ejecute `destroy.owl.carousel`. Si vuelve a inicializar sin destruir, puede duplicar wrappers y eventos.

## Tema y personalización

Cargue `gframe-owl-carousel.css` después del CSS original. Para ajustes propios, añada una hoja de la vista después del puente; no edite los archivos del vendor. Compruebe claro/oscuro, estados de foco, controles deshabilitados y contraste de indicadores.

## Diagnóstico

Si aparecen elementos apilados sin carrusel, revise que jQuery cargó antes del plugin y que el CSS original está presente. Si la estructura se duplica tras una recarga AJAX, faltó destruir la instancia anterior. Si las imágenes cambian el alto durante la carga, defina dimensiones o proporción desde el layout.
