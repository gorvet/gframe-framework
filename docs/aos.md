# AOS

`aos` es una librería externa para animaciones al desplazarse. GFrame la distribuye como componente visual opcional; no modifica su código ni impone animaciones a las vistas.

Seleccione `aos` en el instalador o publique sus archivos con `php bin/modules.php publish /ruta/del/proyecto/public aos`. Se copian a `public/vendors/external/aos/`.

Para usarla, declare `aos.css` y `aos.js` en el meta de la vista o del grupo de vistas. La aplicación decide dónde inicializar `AOS` y qué elementos llevan atributos `data-aos`. GFrame no hace esa inicialización automáticamente.


La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Inicialización y puente

Cargue `gframe-aos.css` después de `aos.css`. El puente respeta la preferencia de movimiento reducido.

```html
<section data-aos="fade-up">Contenido de la sección</section>
```

```js
AOS.init({ once: true });
// Después de insertar elementos con data-aos mediante AJAX:
// AOS.refreshHard();
```

El manifiesto no declara una versión y el archivo distribuido no aporta una identificación inequívoca. No se atribuye una versión hasta verificar la procedencia del archivo. Mantenga accesible el contenido aunque las animaciones estén desactivadas.

[Proyecto y ejemplos oficiales](https://michalsnik.github.io/aos/) y [repositorio](https://github.com/michalsnik/aos).
