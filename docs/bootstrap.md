# Bootstrap

Bootstrap es una biblioteca de terceros para maquetación adaptable, formularios y componentes interactivos. Es una dependencia visual predeterminada de GFrame; no pertenece a GFrame.

**Versión distribuida: 5.3.8.** Está declarada en `resources/modules/bootstrap/module.php` y en la cabecera del bundle. La documentación oficial enlazada corresponde a la rama 5.3; puede incorporar correcciones posteriores a la copia incluida en el framework.

## Integración

Los archivos se publican en `public/vendors/external/bootstrap/`. Los metadatos globales cargan su CSS antes de `variables.css` y `common.css`, y su JavaScript desde el footer. Usa `container`, `row`, `col-*` y sus componentes sin duplicar su comportamiento.

GFrame personaliza los componentes mediante variables y `bootstrap-buttons-compat.css`. El tema se identifica con `data-bs-theme`; no añadas un segundo selector de tema.

## Archivos y orden de carga

| Recurso | Ruta publicada |
| --- | --- |
| CSS | `public/vendors/external/bootstrap/css/bootstrap.min.css` |
| JavaScript | `public/vendors/external/bootstrap/js/bootstrap.bundle.min.js` |
| Variables del proyecto | `public/css/variables.css` |
| Puente de botones | `public/css/bootstrap-buttons-compat.css` |
| Componentes comunes | `public/css/common.css` |

El bundle incluye Popper, utilizado por desplegables y tooltips. Bootstrap 5 no requiere jQuery; otras utilidades de GFrame sí lo utilizan. No añadas otro Bootstrap desde un CDN cuando ya está cargado por la meta global.

El CSS original se conserva intacto. Los colores de marca y los estados claro/oscuro se definen en `variables.css`; el puente adapta las variables internas `--bs-btn-*` para que los botones sigan esa paleta. Los ajustes específicos de una pantalla pertenecen a su CSS de vista, cargado después de los recursos comunes.

## Maquetación de una vista

```html
<div class="container py-4">
  <h1 class="mb-4">Productos</h1>
  <div class="row g-3">
    <div class="col-12 col-md-6">
      <label for="productName" class="form-label">Nombre</label>
      <input id="productName" name="name" class="form-control" required minlength="3">
      <div id="productNameFeedback" class="invalid-feedback"></div>
    </div>
    <div class="col-12 col-md-6">
      <label for="productPrice" class="form-label">Precio</label>
      <input id="productPrice" name="price" class="form-control" type="number" min="0" step="0.01" required>
      <div id="productPriceFeedback" class="invalid-feedback"></div>
    </div>
  </div>
</div>
```

El ejemplo define la estructura y las restricciones HTML. El formulario completo debe conservar la validación, el envío AJAX y el feedback descritos en [Frontend core](frontend-core.md). Bootstrap aporta la presentación de `.is-invalid` y `.invalid-feedback`; no valida las reglas del backend ni envía el formulario.

## Componentes interactivos

Usa los atributos `data-bs-*` o la API pública de Bootstrap, sin duplicar listeners para un mismo comportamiento. Por ejemplo, un modal puede abrirse desde un botón:

```html
<button type="button" class="btn btn-outline-primary"
        data-bs-toggle="modal" data-bs-target="#productHelp">
  Ver ayuda
</button>
<div class="modal fade" id="productHelp" tabindex="-1" aria-labelledby="productHelpTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="productHelpTitle">Ayuda del producto</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">Completa el nombre y el precio.</div>
    </div>
  </div>
</div>
```

Para controlarlo por código después de cargar el bundle:

```js
const element = document.querySelector('#productHelp');
const modal = bootstrap.Modal.getOrCreateInstance(element);
modal.show();
element.addEventListener('hidden.bs.modal', function () {
  // Aquí puede retirarse un fragmento temporal.
});
```

No retires el nodo mientras el modal sigue abierto. Si lo reemplazas por AJAX, espera `hidden.bs.modal` y libera la instancia con `dispose()` antes de retirarlo. Los tooltips y popovers requieren inicialización explícita; los nodos nuevos no heredan instancias de fragmentos anteriores.

## Tema y ampliación

`data-bs-theme="light"` y `data-bs-theme="dark"` identifican los temas. En el panel, utiliza el controlador de tema existente y sus preferencias; no crees otro estado paralelo para una biblioteca. Los [puentes visuales](paquetes-visuales.md) conectan los componentes externos con estas mismas variables.

Para crear un componente del proyecto, combina clases Bootstrap con un selector propio y variables ya disponibles, como `--bs-body-color`, `--bs-body-bg`, `--bs-border-color` y `--bs-primary`. Mantén el mismo grosor de borde entre temas y estados para evitar desplazamientos. Las variantes de acciones del listado deben seguir las convenciones del proyecto, no introducir un estilo distinto en cada módulo.

## Fuente oficial

[Proyecto oficial](https://getbootstrap.com/), [documentación 5.3](https://getbootstrap.com/docs/5.3/getting-started/introduction/), [modales](https://getbootstrap.com/docs/5.3/components/modal/) y [temas de color](https://getbootstrap.com/docs/5.3/customize/color-modes/). Las licencias de sus autores acompañan los archivos originales. Para actualizar la biblioteca, revisa también la compatibilidad de los puentes y de los componentes de GFrame que la utilizan.
