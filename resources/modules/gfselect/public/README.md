# GFSelect

**GFSelect** es un componente JavaScript personalizado para reemplazar y mejorar un `<select>` nativo con opciones de búsqueda, scroll optimizado y estilos avanzados.

Ideal para interfaces modernas que requieren mayor control visual y funcional sobre listas desplegables.

---

## 🚀 Instalación

Incluye el archivo JS y CSS en tu proyecto:

```html
<script src="gf-select.js"></script>
```

> Asegúrate de colocar el archivo CSS en la misma carpeta que el archivo JS.

---

## 🧠 Cómo usar

### HTML

```html
<select id="mySelect" class="form-select">
  <option value="1">Opción 1</option>
  <option value="2">Opción 2</option>
  <option value="3">Opción 3</option>
</select>
```

### JavaScript

```js
const myDropdown = new GFSelect('#mySelect', {
  searchable: true,
  maxHeight: 200,
  wrapperClass: 'gf-wrapper',
  toggleClass: 'gf-toggle',
  menuClass: 'gf-menu',
  searchLabel: 'Buscar...',
  onChange: (value, label) => {
    console.log('Seleccionado:', value, label);
  }
});
```

---

## ⚙️ Opciones disponibles

| Opción              | Tipo     | Descripción |
|---------------------|----------|-------------|
| `searchable`        | boolean  | Muestra un campo de búsqueda dentro del desplegable. |
| `autoFocusSearch`   | boolean  | Hace autofocues al campo de búsqueda |
| `maxHeight`         | number   | Altura máxima del menú (en píxeles). |
| `wrapperClass`      | string   | Clase para el contenedor del componente. |
| `toggleClass`       | string   | Clase para el botón visible. |
| `menuClass`         | string   | Clase para el menú desplegable. |
| `searchWrapperClass`| string   | Clase para el wrapper del buscador. |
| `searchInputClass`  | string   | Clase para el input de búsqueda. |
| `searchLabel`       | string   | Texto del placeholder del input. |
| `prefixHtml`        | string   | HTML que se inyecta antes del texto del botón. |
| `onReady`           | function | Callback que se ejecuta al terminar de crear el componente. |
| `onChange`          | function | Callback que se ejecuta al cambiar de opción. |
---

## ✅ Características

- Soporta búsqueda dinámica.
- Soporta scroll automático hasta el item seleccionado.
- Permite personalizar totalmente su aspecto con clases.
- Auto-carga de CSS si no se ha incluido manualmente.
- Adaptable a selectores con gran cantidad de elementos.

---

## 🧪 Recomendaciones

- Puedes eliminar el componente completamente usando destroy().

---

## 📄 Licencia

MIT License