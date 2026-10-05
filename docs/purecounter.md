# PureCounter

Librería externa opcional para contadores animados. GFrame distribuye la versión 1.5.0 en `public/vendors/external/purecounter/`.

Seleccione `purecounter` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public purecounter`. Cargue `purecounter_vanilla.js` en el meta de la vista que lo utilice. La aplicación decide qué cifras mostrar y dónde activar el contador.

## Contador

```html
<span class="purecounter" data-purecounter-start="0" data-purecounter-end="120"
      data-purecounter-duration="1">120</span>
```

```js
new PureCounter();
```

El contenido HTML conserva la cifra final como alternativa si JavaScript no se ejecuta. La animación no consulta estadísticas ni persiste valores. No necesita puente de estilos: hereda la presentación del elemento. Evite animaciones continuas o innecesarias y respete las preferencias de movimiento del proyecto.

[Repositorio y opciones oficiales](https://github.com/srexi/purecounterjs). La copia distribuida es **1.5.0**; el repositorio puede documentar versiones posteriores.


## Datos y formato

El valor animado debe proceder de datos ya autorizados por el backend. PureCounter no consulta estadísticas ni decide qué cifra puede ver cada usuario. Renderice en el HTML el valor final correcto y utilice los atributos `data-purecounter-*` únicamente para la animación.

Para cifras monetarias, porcentajes o unidades, mantenga la semántica fuera de la animación cuando sea posible. El usuario debe poder entender el dato aunque JavaScript no se ejecute.

## Movimiento reducido

El contenido base debe mostrar inmediatamente la cifra final. Si el proyecto aplica `prefers-reduced-motion`, reduzca o suprima la animación desde la integración de la vista; no haga que el dato dependa de un recorrido animado para ser legible.

## Contenido dinámico

Si inserta nuevos contadores mediante AJAX, inicialice la biblioteca después de añadirlos al DOM. Evite recrear innecesariamente todos los contadores de la página cuando solo cambió un fragmento.

## Accesibilidad

No anuncie cada fotograma de la animación mediante regiones `aria-live`. Para lectores de pantalla, la cifra final debe ser el contenido significativo. Si el contador representa una métrica importante, acompáñelo con una etiqueta visible que explique qué mide.

## Diagnóstico

Si el número no anima, compruebe la clase `purecounter`, los atributos de inicio/fin y la carga de `purecounter_vanilla.js`. Si el valor correcto aparece antes de animar pero termina en otra cifra, revise los atributos renderizados por el backend.
