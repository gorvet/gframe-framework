# Swiper

Librería externa opcional para carruseles y galerías táctiles. GFrame distribuye la versión 11.2.10 en `public/vendors/external/swiper/`.

Seleccione `swiper` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public swiper`. Cargue `swiper-bundle.min.css` y `swiper-bundle.min.js` en el meta de la vista. La aplicación define las diapositivas y la inicialización. `owl-carousel` es una alternativa independiente.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Estructura e inicialización

Cargue `gframe-swiper.css` después del CSS original:

```html
<div class="swiper" id="featuredSlider">
  <div class="swiper-wrapper">
    <div class="swiper-slide">Primera diapositiva</div>
    <div class="swiper-slide">Segunda diapositiva</div>
  </div>
  <div class="swiper-pagination"></div>
</div>
```

```js
const slider = new Swiper('#featuredSlider', {
  slidesPerView: 1,
  pagination: { el: '#featuredSlider .swiper-pagination', clickable: true }
});
// Antes de retirar el contenedor:
// slider.destroy(true, true);
```

Los controles deben pertenecer a la instancia correspondiente cuando hay varios carruseles. Tras modificar diapositivas de una instancia existente, use `update()`. El puente adapta controles, no tamaños ni contenido de las diapositivas.

[Proyecto oficial](https://swiperjs.com/) y [API](https://swiperjs.com/swiper-api). La versión incluida es **11.2.10**; compruebe los ejemplos frente a la rama 11 si la web oficial muestra otra versión mayor.
