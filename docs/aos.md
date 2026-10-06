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


## Diseño y movimiento reducido

Use animaciones como mejora progresiva. El contenido debe ser visible, legible y navegable aunque AOS no cargue, JavaScript esté deshabilitado o el usuario solicite movimiento reducido. No oculte información crítica hasta que una animación se dispare.

`gframe-aos.css` respeta la preferencia `prefers-reduced-motion`. Si añade estilos propios, conserve esa política y evite transiciones largas o desplazamientos fuertes para quienes han pedido reducir movimiento.

## Contenido dinámico

AOS escanea los elementos animables durante su inicialización. Después de insertar contenido por AJAX, use `AOS.refreshHard()` para reconstruir la lista de elementos. No reinicialice toda la biblioteca repetidamente sin necesidad.

Si un fragmento se elimina, asegúrese de que los listeners propios de la vista no conserven referencias a elementos antiguos. AOS gestiona sus observaciones, pero la lógica adicional de la aplicación sigue siendo responsabilidad del módulo de la vista.

## Uso moderado

No aplique animación a cada bloque de una página. Priorice secciones de entrada, cambios de contexto o contenido secundario. Elementos funcionales como formularios, mensajes de error o controles principales deben aparecer sin retrasos que interfieran con la tarea.

## Diagnóstico

Si una animación no se ejecuta, compruebe CSS y JS, la llamada a `AOS.init()`, el atributo `data-aos` y que el elemento tenga dimensiones visibles. Si el contenido insertado dinámicamente no anima, ejecute `AOS.refreshHard()` después de insertarlo. Si aparecen saltos de layout, revise estilos propios y evite que el estado inicial cambie dimensiones estructurales.
