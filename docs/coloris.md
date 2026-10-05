# Coloris

`coloris` es una librería externa opcional para seleccionar colores. GFrame la conserva sin modificar su código. Está registrada para publicarse en `public/vendors/external/coloris/`.

Para usarla, seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public coloris`. Incluya `coloris.min.css` y `coloris.min.js` desde esa carpeta en el meta de la vista que los necesita. La aplicación decide qué campos activan el selector y cómo guardar el color.

El manifiesto publica también `examples.html` como referencia de uso.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Campo e inicialización

Cargue `gframe-coloris.css` después del CSS original:

```html
<label for="brandColor">Color de marca</label>
<input id="brandColor" name="color" class="form-control" data-coloris value="#563dff">
```

```js
Coloris({ el: '[data-coloris]', format: 'hex' });
```

Guarde el valor del input y valide el formato en el servidor. El puente adapta el control al tema sin alterar los colores reales de muestras y gradientes. Dentro de un modal, configure `parent` con su contenedor para conservar el foco.

El manifiesto y la cabecera de los archivos no identifican una versión exacta; no se presupone que coincida con la versión actual del proveedor. [Documentación oficial](https://coloris.js.org/) y [código fuente](https://github.com/mdbassit/Coloris).


## Formatos y validación

El valor del input es la fuente que envía el formulario. Configure un formato coherente con el backend y no dependa de la muestra visual para interpretar el color. Si la aplicación admite únicamente HEX, valide una representación como `#RRGGBB` o la variante expresamente aceptada por su contrato; si admite alpha u otros formatos, documente y valide esos casos por separado.

No utilice un valor de color como autorización, identificador ni dato de seguridad. Es una preferencia visual y debe tratarse como entrada del usuario.

## Uso en formularios dinámicos

Cuando un fragmento AJAX introduce nuevos campos, inicialice Coloris después de insertar el HTML. Si reutiliza un selector global por atributo `data-coloris`, asegúrese de no registrar configuraciones incompatibles entre pantallas.

Dentro de modales, use `parent` para que el selector permanezca en la jerarquía correcta del overlay. Compruebe foco, teclado y cierre del modal; el puente visual no sustituye esas pruebas.

## Tema y personalización

`gframe-coloris.css` adapta superficies, bordes y controles a las variables comunes. Los colores seleccionados y gradientes deben conservar su valor real: no aplique filtros o sobrescrituras globales que alteren la muestra de color.

Para ajustes del proyecto, cargue su CSS después del puente en lugar de editar los archivos de `vendors/external/coloris`. Así el actualizador puede reemplazar la dependencia sin destruir la personalización.

## Diagnóstico

Si el selector no abre o aparece fuera de lugar, compruebe:

1. que CSS y JS originales cargaron;
2. que el puente se cargó después del CSS original;
3. que el selector configurado por `el` coincide con el input;
4. que no hay IDs duplicados;
5. que el `parent` corresponde al modal cuando existe;
6. que otro script no reemplazó el input después de inicializar.

Si la muestra funciona pero el valor guardado es incorrecto, revise el contenido real del input y la validación del backend.
