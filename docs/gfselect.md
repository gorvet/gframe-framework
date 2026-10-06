# GF Select

GFSelect presenta un `<select>` nativo como desplegable con búsqueda. El valor, el nombre del campo y la validación siguen perteneciendo al elemento nativo; los formularios se envían como siempre. Se instala como componente básico obligatorio en todos los perfiles, sin dependencia de jQuery. Su utilización en cada campo sigue siendo explícita.

## Instalación y carga

El instalador publica `gf-select` automáticamente. Para una publicación manual, use `php bin/modules.php publish /ruta/del/proyecto/public gf-select`. Los archivos quedan en `public/vendors/internal/gf-select/`. Incluya `gf-select.js` en el meta de la vista; el componente carga automáticamente `gf-select.css` desde la misma carpeta al crear la primera instancia. También puede incluir ambos archivos en el meta y usar `loadStyles: false` para evitar esa carga automática.

No se publican el README ni la demo incluidos en el código fuente del módulo.

El identificador canónico es `gf-select`, con el mismo prefijo que `gf-table`. `gfselect` se conserva como alias para comandos, dependencias y registros de instalación anteriores; ambos resuelven un único módulo. También se publican los archivos en `vendors/internal/gfselect/` para mantener las metas existentes. Los proyectos nuevos deben usar `vendors/internal/gf-select/`. La clase JavaScript `GFSelect` mantiene su nombre.

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

```html
<label for="sectors">Sectores</label>
<select id="sectors" name="sectors[]" class="form-select" multiple
        required data-max-selections="3">
  <option value="education">Educación</option>
  <option value="health">Salud</option>
  <option value="commerce">Comercio</option>
  <option value="technology">Tecnología</option>
</select>
```

```js
new GFSelect('#sectors', {
  placeholder: 'Seleccione sectores',
  summaryLimit: 2,
  onChange(values, labels) {
    console.log(values, labels);
  }
});
```

Al alcanzar el límite se deshabilitan las opciones pendientes, pero se pueden retirar selecciones. El límite regula la interacción del componente; compruebe también la cantidad y los valores permitidos en el backend. `FormData` conserva los valores repetidos de `sectors[]`.

## Cambiar valores y opciones

Modifique el elemento nativo. Emitir `change` actualiza la interfaz y avisa a los otros listeners del formulario.

```js
const select = document.querySelector('#country');
select.value = 'es';
select.dispatchEvent(new Event('change', { bubbles: true }));

const selectedCountry = GFSelect.getInstance(select).getValue();
```

`onChange` se ejecuta al elegir una opción mediante GF Select; un evento externo `change` sincroniza el control, pero no invoca ese callback. Use un listener nativo de `change` si necesita procesar ambos casos.

Para reemplazar opciones recibidas por AJAX, cree nodos con etiquetas de texto y conserve la opción vacía cuando el campo sea obligatorio.

```js
function replaceCountries(countries) {
  const select = document.querySelector('#country');
  select.replaceChildren(new Option('Seleccione un país', ''));
  countries.forEach(country => {
    select.add(new Option(country.label, country.value));
  });
  select.dispatchEvent(new Event('change', { bubbles: true }));
}
```

La búsqueda filtra las etiquetas cargadas mediante coincidencias parciales, sin distinguir mayúsculas. No consulta un endpoint ni normaliza tildes. Para catálogos remotos, la aplicación obtiene los datos y actualiza las opciones.

## Validación y fragmentos AJAX

El formulario conserva su flujo de validación y envío descrito en [Frontend core](frontend-core.md). `setInvalid()` cambia el estado visual, no la validez nativa ni las reglas del servidor.

```js
function validateCountry() {
  const select = document.querySelector('#country');
  const component = GFSelect.getInstance(select);
  const valid = select.checkValidity();
  component.setInvalid(!valid);
  if (!valid) component.focus();
  return valid;
}
```

Inicialice los controles después de insertar el fragmento. Antes de reemplazarlo, destruya sus instancias para desconectar observers y listeners.

```js
function mountSelects(fragment) {
  fragment.querySelectorAll('select[data-gf-select]').forEach(select => {
    new GFSelect(select);
  });
}

function unmountSelects(fragment) {
  fragment.querySelectorAll('select[data-gf-select]').forEach(select => {
    GFSelect.getInstance(select)?.destroy();
  });
}
```

`data-gf-select` es una convención del ejemplo, no un inicializador automático. Tras `form.reset()`, llame a `refresh()` para reflejar la selección restaurada. Cambiar una propiedad como `option.selected` también requiere `change` o `refresh()`; no todas las asignaciones de propiedades producen mutaciones observables.

## Contrato de integración

- La elección actualiza el `<select>` y emite un evento `change` con propagación. Las modificaciones externas deben actualizar el `<select>` y emitir `change`; `refresh()` sincroniza expresamente el componente después de reemplazar opciones o cambiar atributos. El componente también observa cambios de opciones y atributos relevantes.
- `getValue()` devuelve una cadena en selección simple y una lista de cadenas en selección múltiple.
- `setInvalid(true)` marca visualmente el control; `focus()` lleva el foco al botón visible. Se respetan `required`, `disabled`, `option[disabled]`, grupos y opciones ocultas.
- `destroy()` elimina la interfaz adicional y restaura el `<select>`. Llámelo antes de retirar mediante AJAX el fragmento que contiene el campo.
- `onReady(instance)` se ejecuta al montar la interfaz. La carga automática de CSS es asíncrona; no use `toggle`, `menu` ni otros nodos creados por el componente inmediatamente después del constructor.
- `prefixHtml` inserta HTML sin sanitizar: úselo solo con contenido confiable, nunca con texto del usuario.

Las opciones principales son `searchable`, `autoFocusSearch`, `searchLabel`, `emptyLabel`, `placeholder`, `maxHeight`, `multiple`, `maxSelections`, `summaryLimit`, `closeOnSelect`, `loadStyles` y `hideNative`. Las clases adicionales se asignan con `wrapperClass`, `toggleClass`, `menuClass`, `searchWrapperClass` y `searchInputClass`. Por defecto, el elemento nativo se oculta visualmente sin `display: none` para conservar su participación en la validación del navegador; `hideNative: true` fuerza `display: none` cuando la aplicación lo necesite.

| Opción | Valor predeterminado | Uso |
| --- | --- | --- |
| `searchable` / `autoFocusSearch` | `true` / `true` | Mostrar y enfocar la búsqueda al abrir |
| `maxHeight` | `250` | Altura máxima de la lista en píxeles; se adapta al espacio disponible |
| `multiple` | `null` | Conservar el atributo nativo; un booleano lo reemplaza |
| `maxSelections` | `null` | Leer `data-max-selections`; `0` permite cualquier cantidad |
| `summaryLimit` | `2` | Número de etiquetas visibles antes del contador adicional |
| `closeOnSelect` | `null` | Cerrar en selección simple y mantener abierto en múltiple |
| `loadStyles` / `hideNative` | `true` / `false` | Carga automática de CSS y ocultación del elemento nativo |

La instancia ofrece `openMenu()`, `closeMenu()` e `isOpen()` para controlar la apertura. Solo puede permanecer abierto un GF Select a la vez. Enter, espacio o flecha abajo abren el control; Escape cierra el menú y devuelve el foco al botón. Las opciones son botones recorribles con Tab.

## Personalización

El CSS del proyecto puede cambiar las clases `gf-select-*` y las variables de Bootstrap. La lógica no impone estilos del panel ni de una aplicación concreta. Si el proyecto aplica un tema propio, incluya su CSS después de `gf-select.css` y use `loadStyles: false`.

El puente visual utiliza las variables de GFrame/Bootstrap para tipografía, fondo del campo, bordes, selección, foco y estado deshabilitado. Respeta `data-bs-theme` sin otro selector de tema; las flechas y marcas de selección heredan el color del texto. La validación utiliza `--bs-danger` sin cambiar el grosor del borde. Al deshabilitar un campo abierto, su menú se cierra.

El módulo incluye controles redondeados, flecha giratoria, menú con espacios interiores y selección múltiple resaltada. Los estilos de los selects nativos se distribuyen en `public/css/common.css`, sin depender de una vista concreta. No hace falta añadir `wrapperClass: 'gf-select--app'` ni cargar otra hoja para aplicar el estilo predeterminado.

La API JavaScript conserva el nombre `GFSelect`. Para reutilizar una configuración del proyecto, cree una función que construya instancias con opciones comunes; no es necesario modificar el archivo publicado del módulo.


## Prueba visual aislada

El repositorio incluye `tests/fixtures/gfselect-preview.php` para comprobar el componente fuera de una pantalla de negocio. La fixture cubre selección simple y múltiple, validación, reemplazo de opciones y uso dentro de un modal.

Desde la raíz del repositorio puede abrirla con:

```bash
php -S 127.0.0.1:8767 -t . tests/fixtures/gfselect-preview.php
```

Utilice esta prueba al modificar CSS, eventos, inicialización o integración con modales. No sustituye las pruebas automatizadas: sirve para detectar regresiones visuales, foco, dimensiones, superposición y comportamiento interactivo que una aserción de DOM puede no mostrar.
