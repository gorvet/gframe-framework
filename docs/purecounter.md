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
