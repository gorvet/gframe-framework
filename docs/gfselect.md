# GFSelect

GFSelect presenta un `<select>` nativo como desplegable con búsqueda. El valor, el nombre del campo y la validación siguen perteneciendo al elemento nativo; los formularios se envían como siempre. Es un componente visual opcional, sin dependencia de jQuery.

## Instalación y carga

Seleccione `gfselect` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public gfselect`. Los archivos quedan en `public/vendors/internal/gfselect/`. Incluya `gf-select.js` en el meta de la vista; el componente carga automáticamente `gf-select.css` desde la misma carpeta al crear la primera instancia. También puede incluir ambos archivos en el meta y usar `loadStyles: false` para evitar esa carga automática.

No se publican el README ni la demo incluidos en el código fuente del módulo.

## Uso

```html
<label for="country">País</label>
<select id="country" name="country" class="form-select" required>
  <option value="">Seleccione un país</option>
  <option value="cu">Cuba</option>
  <option value="es">España</option>
</select>
```

```js
const country = new GFSelect('#country', {
  searchable: true,
  searchLabel: 'Buscar país',
  onChange(value, label) {
    // Reaccionar a la selección; el <select> ya tiene el valor actualizado.
  }
});
```

También acepta el elemento `HTMLSelectElement` directamente. Crear otra instancia para el mismo elemento devuelve la existente. `GFSelect.getInstance(select)` permite recuperarla.

Para selección múltiple, conserve `name="sectors[]"` y `multiple` en el HTML. Puede establecer el límite mediante `data-max-selections="3"` o la opción `maxSelections: 3`. En modo múltiple, `onChange` recibe dos listas: valores y etiquetas.

## Contrato de integración

- La elección actualiza el `<select>` y emite un evento `change` con propagación. Las modificaciones externas deben actualizar el `<select>` y emitir `change`; `refresh()` sincroniza expresamente el componente después de reemplazar opciones o cambiar atributos. El componente también observa cambios de opciones y atributos relevantes.
- `getValue()` devuelve una cadena en selección simple y una lista de cadenas en selección múltiple.
- `setInvalid(true)` marca visualmente el control; `focus()` lleva el foco al botón visible. Se respetan `required`, `disabled`, `option[disabled]`, grupos y opciones ocultas.
- `destroy()` elimina la interfaz adicional y restaura el `<select>`. Llámelo antes de retirar mediante AJAX el fragmento que contiene el campo.
- `onReady(instance)` se ejecuta al montar la interfaz. La carga automática de CSS es asíncrona; no use `toggle`, `menu` ni otros nodos creados por el componente inmediatamente después del constructor.
- `prefixHtml` inserta HTML sin sanitizar: úselo solo con contenido confiable, nunca con texto del usuario.

Las opciones principales son `searchable`, `autoFocusSearch`, `searchLabel`, `emptyLabel`, `placeholder`, `maxHeight`, `multiple`, `maxSelections`, `summaryLimit`, `closeOnSelect`, `loadStyles` y `hideNative`. Las clases adicionales se asignan con `wrapperClass`, `toggleClass`, `menuClass`, `searchWrapperClass` y `searchInputClass`. Por defecto, el elemento nativo se oculta visualmente sin `display: none` para conservar su participación en la validación del navegador; `hideNative: true` fuerza `display: none` cuando la aplicación lo necesite.

## Personalización

El CSS del proyecto puede cambiar las clases `gf-select-*` y las variables de Bootstrap. La lógica no impone estilos del panel ni de una aplicación concreta. Si el proyecto aplica un tema propio, incluya su CSS después de `gf-select.css` y use `loadStyles: false`.

La integración de Dane contiene ejemplos de selección múltiple y refresco tras reemplazo de fragmentos; Bebots contiene ejemplos de selección simple. Esos proyectos son referencias y no se modifican al publicar el módulo.
