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


## Responsive, contenido y accesibilidad

El carrusel controla la presentación; la vista sigue siendo responsable del contenido, URLs, textos alternativos, permisos y orden semántico. Mantenga las diapositivas en un orden DOM comprensible incluso cuando la composición visual muestre varias a la vez.

Use `breakpoints` cuando el número de diapositivas visibles cambie por ancho. No duplique el HTML para móvil y escritorio salvo que el contenido sea realmente distinto.

Los controles de navegación y paginación necesitan nombres y estados comprensibles. Si añade autoplay, proporcione una forma de detenerlo cuando el contenido requiera lectura. Revise teclado y foco; una transición no debe mover el foco inesperadamente.

## Fragmentos dinámicos

Después de añadir o retirar diapositivas de una instancia existente, llame a `update()`. Si el contenedor completo será reemplazado por AJAX, destruya primero la instancia con `destroy(true, true)` y cree una nueva después de insertar el nuevo HTML.

Mantenga una referencia distinta por carrusel. Selectores globales de controles pueden conectar accidentalmente una paginación con otra instancia cuando hay varios sliders en la página.

## Tema y personalización

`gframe-swiper.css` adapta controles e indicadores a las variables del framework. Los tamaños, relación de aspecto y espaciado pertenecen al layout de la vista. Cargue el CSS propio después del puente y no modifique directamente los archivos del vendor.

## Diagnóstico

Si las diapositivas aparecen apiladas, compruebe que `swiper-bundle.min.css` cargó. Si los controles no responden, revise sus selectores y que pertenezcan a la instancia correcta. Si el carrusel se duplica después de AJAX, destruya la instancia antes de reinicializar. Si cambia el número de elementos sin reflejarse visualmente, ejecute `update()`.
