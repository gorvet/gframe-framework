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
