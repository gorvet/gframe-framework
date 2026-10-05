# Desarrollar una aplicación con GFrame

Esta guía explica GFrame desde el punto de vista de una aplicación. No intenta describir cada clase del framework: muestra primero **cómo se conectan las piezas** y después enlaza la referencia técnica cuando hace falta profundizar.

## El modelo mental

Una petición web típica recorre este camino:

```text
URL
  ↓
Route
  ↓
Middleware
  ↓
Controller
  ↓
Service        ← opcional, recomendado cuando existe lógica de negocio reutilizable
  ↓
Model / ORM    ← opcional, cuando hay persistencia
  ↓
Controller
  ↓
View
  ↓
Template
  ↓
HTML
```

Para AJAX, API, webhook y SSE el recorrido cambia al final:

```text
URL → Route → Middleware → Controller → Service/Model → respuesta del canal
```

Esos canales no pasan automáticamente por una vista y un template HTML.

No todas las funcionalidades necesitan todas las capas. Una página estática puede usar ruta + controller + view. Un proceso de negocio puede usar controller + service + varios modelos. La arquitectura sirve para separar responsabilidades, no para añadir archivos innecesarios.

## Qué pertenece al proyecto

Una aplicación instalada trabaja principalmente aquí:

```text
config/
  app.php
  routes/
    routes_web.php
    routes_ajax.php
    routes_api.php
    ...

app/
  controllers/
  services/
  models/
  views/
    templates/

public/
storage/
.env
```

Responsabilidades:

| Ubicación | Uso |
| --- | --- |
| `config/routes/` | Declara qué URL ejecuta qué acción y qué protecciones se aplican. |
| `app/controllers/` | Recibe la operación, coordina servicios/modelos y prepara la respuesta. |
| `app/services/` | Reglas y procesos de negocio reutilizables. |
| `app/models/` | Acceso a datos y modelos del proyecto. |
| `app/views/` | HTML y presentación. |
| `app/views/templates/` | Estructura exterior de páginas: header, contenido, footer. |
| `public/` | Recursos públicos. |
| `storage/` | Estado y archivos internos de ejecución. |
| `.env` | Secretos y valores dependientes del entorno. |

Estas carpetas expresan la organización recomendada del proyecto. El framework no debe modificarse dentro de `packages/gorvet/gframe/` para implementar reglas del negocio.

## 1. Empieza por la URL

Supongamos que quieres crear:

```text
/productos
```

La primera decisión no es el modelo. Es la ruta.

En `config/routes/routes_web.php`:

```php
<?php
use RouteBuilder as Route;

Route::get('productos', 'productos/ProductController@index')
    ->template('admin')
    ->view('productIndex')
    ->middleware(['auth'])
    ->registerFinal();
```

La ruta establece:

- método HTTP: `GET`;
- URL: `productos`;
- controlador: `ProductController` dentro del grupo `productos`;
- acción: `index`;
- template: `admin`;
- vista: `productIndex`;
- acceso: usuario autenticado.

`registerFinal()` forma parte del contrato actual de `RouteBuilder`; una declaración sin ese cierre no queda registrada como ruta final.

Consulta [Router y declaración de rutas](rutas.md) para parámetros, canales y modificadores.

## 2. El controller coordina

Crea:

```text
app/controllers/productos/ProductController.php
```

Una primera versión puede ser mínima:

```php
<?php

final class ProductController
{
    public function index(): array
    {
        return [
            'title' => 'Productos',
        ];
    }
}
```

En una ruta web, el array devuelto por la acción llega al render y queda disponible en la vista como `$data`.

El controller es un coordinador. Debe ocuparse de cuestiones como:

- obtener y validar entrada de la petición;
- comprobar contexto que no haya resuelto ya el middleware;
- invocar servicios o modelos;
- transformar el resultado en el contrato que necesita la respuesta.

Evita convertirlo en una clase que contenga todas las consultas SQL, reglas de negocio y HTML de la funcionalidad.

## 3. La vista presenta

Crea:

```text
app/views/productos/productIndex.php
```

Por ejemplo:

```php
<h1><?= htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
```

La vista recibe los datos preparados por la acción. No debería decidir permisos, procesar formularios ni ejecutar directamente operaciones del negocio.

El template seleccionado por la ruta envuelve el contenido. En una aplicación típica:

```text
header
  ↓
template
  ↓
$content de la vista
  ↓
footer
```

Consulta [Render, vistas y templates](render.md) y [Metadatos y recursos de vistas](meta.md).

## 4. Introduce un modelo cuando exista persistencia

Si `productos` vive en base de datos, crea por convención:

```text
app/models/productos/ProductModel.php
```

Ejemplo mínimo:

```php
<?php

final class ProductModel extends ORM
{
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = ['name', 'price', 'status'];
}
```

Puedes consultar desde una instancia nueva:

```php
$products = (new ProductModel())
    ->orderBy('name', 'ASC')
    ->get();
```

`get()` devuelve filas como arrays. `first()` y `find()` devuelven instancias del modelo cuando encuentran un registro.

Para crear:

```php
$product = new ProductModel([
    'name' => $name,
    'price' => $price,
    'status' => 'active',
]);

$productID = $product->insert();
```

`save()` también inserta cuando la instancia no tiene clave primaria y actualiza cuando sí la tiene.

Consulta [ORM, modelos y dialectos](orm.md) para filtros, paginación, relaciones, transacciones y operaciones avanzadas.

## 5. Introduce un service cuando exista lógica de negocio

No toda consulta necesita un service. Empieza a ser útil cuando la operación implica reglas que no pertenecen al transporte HTTP ni a un único modelo.

Ejemplos:

- crear un producto y relacionar imágenes;
- aplicar límites del plan;
- comprobar duplicados y reglas de estado;
- modificar varios modelos dentro de una transacción;
- enviar una notificación después de confirmar una operación;
- reutilizar la misma operación desde web, AJAX, cron o una API.

Convención recomendada:

```text
app/services/productos/ProductService.php
```

Ejemplo:

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

    public function create(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            return ['status' => 'error', 'code' => 'product_name_required'];
        }

        $product = new ProductModel([
            'name' => $name,
            'price' => (float)($input['price'] ?? 0),
            'status' => 'active',
        ]);

        $id = (int)$product->insert();

        return [
            'status' => 'success',
            'code' => 'product_created',
            'data' => ['product_id' => $id],
        ];
    }
}
```

Entonces el controller deja de contener la regla:

```php
final class ProductController
{
    public function index(): array
    {
        $service = new ProductService();

        return [
            'title' => 'Productos',
            'products' => $service->list(),
        ];
    }
}
```

La idea no es crear una capa por formalidad. La idea es que la lógica reutilizable tenga un lugar estable fuera de la ruta y de la vista.

## 6. Web y AJAX son dos entradas al mismo negocio

Una aplicación suele cargar la pantalla inicial por web y utilizar AJAX para filtros, formularios o acciones posteriores.

El patrón recomendado es:

```text
GET /productos
  → ProductController@index
  → página completa

POST /ajax/productos/create
  → ProductController@create
  → ProductService@create
  → JSON
```

Declara la acción AJAX en `config/routes/routes_ajax.php`:

```php
<?php
use RouteBuilder as Route;

Route::post('ajax/productos/create', 'productos/ProductController@create')
    ->middleware(['auth', 'can:products.create'])
    ->registerFinal();
```

El canal AJAX aplica además sus protecciones automáticas, incluido CSRF salvo las exclusiones expresamente definidas por una ruta o flujo compatible.

El controller puede reutilizar el mismo service:

```php
public function create(): array
{
    return (new ProductService())->create($_POST);
}
```

En código real valida y normaliza cada campo según el dominio; no pases datos del cliente a persistencia sin una política explícita.

Para actualizar fragmentos de la pantalla, el patrón actual de GFrame es renderizar el parcial en PHP y devolverlo dentro del JSON, en vez de duplicar la plantilla HTML en JavaScript.

Consulta [Frontend core](frontend-core.md) para el patrón de listados y fragmentos AJAX.

## 7. Autenticación y autorización son problemas distintos

`auth` responde:

> ¿hay un usuario autenticado?

`can:*`, `role:*` y `admin` responden:

> ¿puede este usuario ejecutar esta operación?

Ejemplo:

```php
Route::post('ajax/productos/delete', 'productos/ProductController@delete')
    ->middleware(['auth', 'can:products.delete'])
    ->registerFinal();
```

La ruta protege la capacidad general. El service todavía debe comprobar las reglas sobre el registro concreto, especialmente en aplicaciones multitenant.

Tener permiso para `products.delete` dentro del tenant A no debe permitir eliminar un producto perteneciente al tenant B.

Consulta [Autenticación](autenticacion.md), [Middleware](middleware.md), [Roles y permisos](permisos.md) y [Sesiones](sesiones.md).

## 8. Añade capacidades cuando el caso lo exija

GFrame ya incorpora capacidades que no hace falta reconstruir dentro de cada aplicación.

| Necesidad | Capacidad |
| --- | --- |
| subir, procesar y relacionar archivos | [Biblioteca multimedia](media-library.md) |
| enviar correo | [Mail](mail.md) |
| avisar dentro de la aplicación | [Notificaciones](notificaciones.md) |
| ejecutar algo fuera de la petición | [Async](async.md) |
| ejecutar algo después o recurrentemente | [Cron](cron-runner.md) |
| consumir una API externa | cliente HTTP del core; guía específica en reconstrucción |
| sanear HTML permitido | [HtmlSanitizer](html-sanitizer.md) |
| cifrar datos de aplicación | `GFrame\Security\Encryption`; guía específica en reconstrucción |
| SEO técnico | [SEO](seo.md) y [JSON-LD](json-ld.md) |
| integrar WordPress como backend | [WordPress headless](wordpress-headless.md) |

La guía principal debe ayudarte a descubrir estas opciones. Las páginas especializadas explican después sus contratos y límites.

## 9. Async y Cron no son lo mismo

Usa **Async** cuando quieres sacar una operación de la petición actual y puedes aceptar que no exista cola persistente, seguimiento ni reintento automático.

Usa **Cron** cuando el trabajo debe quedar registrado, ejecutarse en una fecha futura, repetirse o recuperarse tras una interrupción.

Ejemplos:

```text
generar una miniatura fuera del request → Async
sincronizar datos esta noche → Cron
limpiar registros cada hora → Cron
enviar un correo simple sin bloquear la página → Mail async / Async
campaña de miles de destinatarios → cola + Cron, no miles de procesos Async
```

Consulta [Async](async.md) y [Cron runner](cron-runner.md).

## 10. Módulos: primero entiende qué problema resuelven

En GFrame hay varias cosas que pueden llamarse «módulo» en conversación, pero no son equivalentes:

1. **capacidad instalable**: por ejemplo `notifications`, `media-library` o `cron-runner`;
2. **módulo runtime MVC**: conserva originales dentro del paquete y permite que el proyecto sobrescriba controller/view/model/service según su contrato;
3. **componente frontend o librería publicada**: por ejemplo Bootstrap, Swiper o una utilidad JS;
4. **módulo propio de la aplicación**: una funcionalidad que tú organizas y que puede o no convertirse en un paquete reutilizable.

No necesitas convertir cada carpeta de negocio en un módulo runtime.

Consulta [Catálogo y dependencias](modulos-opcionales.md) y [Módulos runtime](modulos-runtime.md).

## 11. Diferencia entre API entrante y HTTP saliente

Estas dos operaciones suelen confundirse:

```text
otro sistema → tu GFrame
```

es una **API entrante** y utiliza routing, middleware y credenciales de rutas.

```text
tu GFrame → otro sistema
```

es una **petición HTTP saliente** y utiliza el cliente HTTP del core.

Son contratos distintos aunque ambos hablen HTTP.

Consulta [Acceso API](api-access.md) para la primera. La referencia específica del cliente HTTP se está incorporando como parte de la reconstrucción documental.

## Siguiente paso

Continúa con [Tutorial: construir Productos de extremo a extremo](tutorial-productos.md), que aplica este mapa a una funcionalidad completa.

Después utiliza las páginas especializadas como referencia, no como requisito previo para empezar a desarrollar.
