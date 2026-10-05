# Tutorial: construir Productos de extremo a extremo

Este tutorial aplica la arquitectura de GFrame a una funcionalidad real en una sola pantalla:

- `GET /productos` carga la página completa;
- el listado se obtiene con ORM;
- crear y editar usan AJAX;
- eliminar usa AJAX;
- el HTML del listado se genera en PHP y se reemplaza como fragmento;
- las rutas están protegidas por autenticación y permisos;
- la lógica de negocio vive en un service reutilizable.

El objetivo no es convertir este ejemplo en un generador de CRUD. Es mostrar **dónde va cada responsabilidad y cómo se conectan las piezas**.

Antes de continuar, lee [Desarrollar una aplicación con GFrame](guia-desarrollo.md).

## Resultado y archivos

La funcionalidad utiliza esta estructura:

```text
config/
  routes/
    routes_web.php
    routes_ajax.php

app/
  controllers/
    productos/
      ProductController.php
  services/
    productos/
      ProductService.php
  models/
    productos/
      ProductModel.php
  views/
    productos/
      productIndex.php
      _productList.php
      product.group.meta.php   # opcional
```

La tabla de ejemplo necesita, como mínimo:

```text
products
  product_id
  name
  price
  status
```

Adapta nombres, tipos, índices y esquema a tu aplicación. El tutorial se concentra en el flujo del framework, no en imponer una migración de negocio concreta.

## 1. Define el modelo

Crea `app/models/productos/ProductModel.php`:

```php
<?php

final class ProductModel extends ORM
{
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = ['name', 'price', 'status'];
    protected $casts = [
        'product_id' => 'int',
        'price' => 'float',
    ];
}
```

El modelo define cómo se relaciona esta clase con la tabla. No contiene HTML ni decide permisos de ruta.

El ORM actual permite, entre otras operaciones:

```php
ProductModel::find($id);
ProductModel::all();

(new ProductModel())
    ->where('status', '=', 'active')
    ->orderBy('name', 'ASC')
    ->get();
```

`get()` devuelve filas como arrays. `find()` y `first()` devuelven una instancia del modelo o `null`.

Consulta [ORM, modelos y dialectos](orm.md) para el contrato completo.

## 2. Crea el service

Crea `app/services/productos/ProductService.php`:

```php
<?php

final class ProductService
{
    public function list(): array
    {
        return (new ProductModel())
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function save(array $input): array
    {
        $id = max(0, (int)($input['product_id'] ?? 0));
        $name = trim((string)($input['name'] ?? ''));
        $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $status = (string)($input['status'] ?? 'active');

        if ($name === '') {
            return ['status' => 'error', 'code' => 'product_name_required'];
        }

        if ($price === false || $price < 0) {
            return ['status' => 'error', 'code' => 'product_price_invalid'];
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            return ['status' => 'error', 'code' => 'product_status_invalid'];
        }

        if ($id === 0) {
            $product = new ProductModel([
                'name' => $name,
                'price' => (float)$price,
                'status' => $status,
            ]);

            $productID = (int)$product->insert();

            return [
                'status' => 'success',
                'code' => 'product_created',
                'data' => ['product_id' => $productID],
            ];
        }

        $product = ProductModel::find($id);
        if ($product === null) {
            return ['status' => 'error', 'code' => 'product_not_found'];
        }

        $product->name = $name;
        $product->price = (float)$price;
        $product->status = $status;
        $write = $product->update();

        return [
            'status' => 'success',
            'code' => ($write['status'] ?? '') === 'no_change'
                ? 'product_unchanged'
                : 'product_updated',
            'data' => [
                'product_id' => $id,
                'write' => $write,
            ],
        ];
    }

    public function delete(int $id): array
    {
        if ($id <= 0) {
            return ['status' => 'error', 'code' => 'product_not_found'];
        }

        $product = ProductModel::find($id);
        if ($product === null) {
            return ['status' => 'error', 'code' => 'product_not_found'];
        }

        if (!$product->delete()) {
            return ['status' => 'error', 'code' => 'product_delete_failed'];
        }

        return ['status' => 'success', 'code' => 'product_deleted'];
    }
}
```

### Qué pertenece al service

Aquí viven:

- validación del dominio;
- normalización de datos;
- creación/edición/eliminación;
- reglas que podrían reutilizarse desde otro transporte.

En una aplicación real, este es también el lugar donde comprobarías reglas sobre el registro concreto: tenant, propietario, estado, límites del plan o relaciones obligatorias.

El middleware de ruta no sustituye esas comprobaciones.

## 3. Crea el controller

Crea `app/controllers/productos/ProductController.php`:

```php
<?php

final class ProductController
{
    public function index(): array
    {
        return [
            'title' => 'Productos',
            'products' => (new ProductService())->list(),
        ];
    }

    public function list(): array
    {
        $products = (new ProductService())->list();

        return [
            'status' => 'success',
            'code' => 'products_loaded',
            'html' => $this->renderList($products),
        ];
    }

    public function save(): array
    {
        $service = new ProductService();
        $result = $service->save($_POST);

        if (($result['status'] ?? '') === 'success') {
            $result['html'] = $this->renderList($service->list());
        }

        return $result;
    }

    public function delete(): array
    {
        $service = new ProductService();
        $result = $service->delete((int)($_POST['product_id'] ?? 0));

        if (($result['status'] ?? '') === 'success') {
            $result['html'] = $this->renderList($service->list());
        }

        return $result;
    }

    private function renderList(array $products): string
    {
        $data = ['products' => $products];
        $file = ABSPATH . 'app/views/productos/_productList.php';

        if (!is_file($file)) {
            throw new RuntimeException('No existe la vista parcial de productos.');
        }

        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
```

El controller hace tres cosas:

1. recibe la operación HTTP;
2. delega el negocio al service;
3. prepara el contrato de salida.

El mismo `ProductService` puede reutilizarse posteriormente desde una API, tarea programada o proceso interno sin simular una petición AJAX.

## 4. Declara la página web

En `config/routes/routes_web.php` añade:

```php
<?php
use RouteBuilder as Route;

Route::get('productos', 'productos/ProductController@index')
    ->template('admin')
    ->view('productIndex')
    ->middleware(['auth', 'can:products.view'])
    ->registerFinal();
```

Cuando el usuario visita `/productos`:

```text
GET /productos
  ↓
Router
  ↓
auth + can:products.view
  ↓
ProductController@index
  ↓
ProductService@list
  ↓
ProductModel / ORM
  ↓
productIndex.php
  ↓
adminTemplate.php
```

La primera carga sigue siendo una página web normal. No conviertas toda la aplicación en AJAX para poder actualizar un listado.

## 5. Declara las acciones AJAX

En `config/routes/routes_ajax.php` añade:

```php
<?php
use RouteBuilder as Route;

Route::post('ajax/productos/list', 'productos/ProductController@list')
    ->middleware(['auth', 'can:products.view'])
    ->registerFinal();

Route::post('ajax/productos/save', 'productos/ProductController@save')
    ->middleware(['auth', 'can:products.edit'])
    ->registerFinal();

Route::post('ajax/productos/delete', 'productos/ProductController@delete')
    ->middleware(['auth', 'can:products.delete'])
    ->registerFinal();
```

Las rutas AJAX pasan por los guardas automáticos de ese canal además de los middleware declarados. Conserva CSRF; no excluyas una operación privada solo para simplificar JavaScript.

En un proyecto con permisos más granulares puedes separar `products.create` y `products.edit` en dos endpoints. Aquí se usa `products.edit` para mantener el ejemplo compacto.

## 6. Crea el parcial del listado

Crea `app/views/productos/_productList.php`:

```php
<?php $products = $data['products'] ?? []; ?>

<?php if (empty($products)): ?>
    <p class="text-body-secondary mb-0">No hay productos todavía.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($product['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($product['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary js-product-edit"
                            data-id="<?= (int)($product['product_id'] ?? 0) ?>"
                            data-name="<?= htmlspecialchars((string)($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-price="<?= htmlspecialchars((string)($product['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-status="<?= htmlspecialchars((string)($product['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >Editar</button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger js-product-delete"
                            data-id="<?= (int)($product['product_id'] ?? 0) ?>"
                        >Eliminar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
```

La vista escapa los valores antes de incorporarlos al HTML. Los atributos `data-*` sirven para rellenar el formulario de edición; no constituyen autorización.

## 7. Crea la vista principal

Crea `app/views/productos/productIndex.php`:

```php
<?php
$products = $data['products'] ?? [];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Productos</h1>
    </div>

    <form id="product-form" class="row g-3 mb-4" novalidate>
        <input type="hidden" name="product_id" id="product_id" value="0">

        <div class="col-md-5">
            <label for="product_name" class="form-label">Nombre</label>
            <input type="text" class="form-control" name="name" id="product_name" required>
        </div>

        <div class="col-md-3">
            <label for="product_price" class="form-label">Precio</label>
            <input type="number" class="form-control" name="price" id="product_price" min="0" step="0.01" required>
        </div>

        <div class="col-md-2">
            <label for="product_status" class="form-label">Estado</label>
            <select class="form-select" name="status" id="product_status">
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </select>
        </div>

        <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <button type="button" class="btn btn-outline-secondary" id="product-reset">Nuevo</button>
        </div>
    </form>

    <div id="product-list">
        <?php
        $data = ['products' => $products];
        include ABSPATH . 'app/views/productos/_productList.php';
        ?>
    </div>
</div>
```

El listado inicial ya llega renderizado con la página. AJAX se usa después para las mutaciones y recargas del fragmento.

## 8. Añade el JavaScript de la vista

En una implementación real, coloca este comportamiento en un archivo JS de la aplicación y cárgalo mediante meta. Se muestra inline para que el flujo quede completo:

```html
<script>
(function ($) {
    'use strict';

    const $form = $('#product-form');
    const $list = $('#product-list');

    function csrfData() {
        return $('#tokens').serialize();
    }

    function replaceList(response) {
        if (response && response.html !== undefined) {
            $list.html(response.html);
        }
    }

    function resetForm() {
        $form[0].reset();
        $('#product_id').val('0');
    }

    $form.on('submit', function (event) {
        event.preventDefault();

        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        $.ajax({
            url: site_url + 'ajax/productos/save',
            type: 'POST',
            dataType: 'json',
            data: csrfData() + '&' + $form.serialize()
        }).done(function (response) {
            if (response.status === 'success') {
                replaceList(response);
                resetForm();
                return;
            }

            console.error(response.code || 'product_save_failed');
        });
    });

    $(document).on('click', '.js-product-edit', function () {
        const $button = $(this);
        $('#product_id').val($button.data('id'));
        $('#product_name').val($button.data('name'));
        $('#product_price').val($button.data('price'));
        $('#product_status').val($button.data('status'));
    });

    $(document).on('click', '.js-product-delete', function () {
        const productID = Number($(this).data('id') || 0);
        if (!productID || !window.confirm('¿Eliminar este producto?')) return;

        $.ajax({
            url: site_url + 'ajax/productos/delete',
            type: 'POST',
            dataType: 'json',
            data: csrfData() + '&product_id=' + encodeURIComponent(productID)
        }).done(function (response) {
            if (response.status === 'success') {
                replaceList(response);
                resetForm();
                return;
            }

            console.error(response.code || 'product_delete_failed');
        });
    });

    $('#product-reset').on('click', resetForm);
})(jQuery);
</script>
```

El ejemplo deliberadamente no impone el componente visual de feedback. En una aplicación GFrame puedes sustituir `console.error` y `confirm` por [Alertas](alerts.md), manteniendo los códigos que devuelve el backend.

Para formularios más complejos utiliza las utilidades de [Frontend core](frontend-core.md).

## 9. Define los permisos del proyecto

La ruta necesita permisos como:

```text
products.view
products.edit
products.delete
```

Decláralos en la plantilla del rol correspondiente mediante `config/Permissions.php`, siguiendo [Roles, permisos y membresías](permisos.md).

Por ejemplo, dentro de un rol propio:

```php
return [
    'editor' => [
        'products' => [
            'view' => true,
            'edit' => true,
            'delete' => false,
        ],
    ],
];
```

El superadministrador mantiene su bypass. Para otros usuarios, definir una capacidad no asigna automáticamente el rol ni crea membresías de tenant.

## 10. Qué ocurre al guardar

El recorrido completo de `Guardar` es:

```text
usuario envía formulario
  ↓
POST /ajax/productos/save
  ↓
Router detecta canal AJAX
  ↓
guardas AJAX + auth + can:products.edit
  ↓
ProductController@save
  ↓
ProductService@save
  ↓
ProductModel / ORM
  ↓
base de datos
  ↓
ProductService@list
  ↓
_productList.php
  ↓
JSON { status, code, data?, html }
  ↓
JavaScript reemplaza #product-list
```

Esa secuencia es el patrón que debes reconocer en otras funcionalidades de GFrame.

## 11. Cómo evoluciona esta funcionalidad

Una vez funciona el CRUD básico, no tienes que cambiar la arquitectura para añadir capacidades.

### Imágenes

El service puede relacionar imágenes mediante [Biblioteca multimedia](media-library.md). No guardes Base64 arbitrario o lógica de procesamiento de imágenes directamente en la vista.

### Notificaciones

Después de confirmar una operación importante:

```text
ProductService
  → operación confirmada
  → NotificationService
```

Consulta [Notificaciones](notificaciones.md).

### Correo

Si crear un producto debe generar correo, utiliza [Mail](mail.md). En una petición web, el envío asíncrono evita esperar al SMTP.

### Trabajo en segundo plano

Si la operación posterior es pesada pero inmediata, evalúa [Async](async.md). Si debe persistir, programarse o repetirse, utiliza [Cron](cron-runner.md).

### API externa

Si el producto debe sincronizarse con un proveedor, utiliza el cliente HTTP saliente del core. Eso es distinto de publicar una API de tu aplicación.

### Multitenant

Añade el tenant al modelo de datos y filtra cada operación por ese contexto. `can:products.edit` comprueba autorización; **no sustituye el filtro de los registros por tenant**.

## 12. Errores comunes que este patrón evita

- poner SQL en la vista;
- reconstruir HTML de negocio dos veces, una en PHP y otra en JavaScript;
- confiar en un botón oculto como autorización;
- aceptar `user_id`, rol, permiso o tenant del navegador sin validación del servidor;
- copiar lógica de negocio entre controlador web y controlador API;
- usar Async como cola persistente;
- convertir cada funcionalidad en un módulo runtime sin necesidad;
- editar `packages/gorvet/gframe/` para personalizar una aplicación.

## Referencia después del tutorial

Cuando ya entiendas este recorrido, profundiza según la necesidad:

- [Rutas](rutas.md)
- [Render](render.md)
- [Middleware](middleware.md)
- [ORM](orm.md)
- [Frontend core](frontend-core.md)
- [Permisos](permisos.md)
- [Módulos runtime](modulos-runtime.md)

La referencia explica los contratos y casos límite. Este tutorial explica cómo las piezas forman una funcionalidad completa.
