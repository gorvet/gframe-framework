# Tu primera página con GFrame

Crea una página pública con una ruta, un controlador, una vista y sus metadatos. El ejemplo utiliza el template `home` del proyecto inicial y no necesita base de datos.

## 1. Preparar el proyecto

Sigue [Instalación](instalacion.md), configura [Apache o Nginx](servidores-web.md) y completa el asistente con el perfil `static`. Comprueba que abre la portada. Para proyectos administrados puedes utilizar el mismo ejemplo, aplicando los middleware de acceso que necesites.

Los siguientes archivos se crean en el proyecto instalado, fuera de `packages/`:

```text
config/routes/routes_web.php
app/controllers/welcome/WelcomeController.php
app/views/welcome/welcomeIndex.php
app/views/welcome/welcomeIndex.meta.php
```

## 2. Declarar la ruta

Añade la declaración a `config/routes/routes_web.php`, conservando las rutas existentes. Si el archivo ya declara el alias `Route`, no repitas su importación.

```php
use RouteBuilder as Route;

Route::get('bienvenida', 'welcome/WelcomeController@index')
    ->template('home')
    ->view('welcomeIndex')
    ->registerFinal();
```

`GET /bienvenida` ejecuta la acción `index`. Los nombres explícitos seleccionan `welcomeIndex.php` y el template compartido `homeTemplate.php`. Consulta [Rutas](rutas.md) para parámetros y canales.

## 3. Crear el controlador

En `app/controllers/welcome/WelcomeController.php`:

```php
<?php

final class WelcomeController
{
    public function index(array $routeParams): array
    {
        return [
            'title' => 'Bienvenido a mi aplicación',
            'features' => ['Rutas declarativas', 'Vistas PHP', 'Módulos ampliables'],
        ];
    }
}
```

El controlador devuelve los datos de la página. Render los entrega a la vista como `$data`. Una página con persistencia puede obtenerlos desde un servicio o modelo; consulta [Arquitectura](arquitectura.md).

## 4. Crear la vista

En `app/views/welcome/welcomeIndex.php`:

```php
<?php
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="container py-4">
    <h1><?= $escape($data['title']) ?></h1>
    <ul>
        <?php foreach ($data['features'] as $feature): ?>
            <li><?= $escape($feature) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
```

El template y los elementos compartidos proporcionan la estructura de la página. La vista contiene únicamente su contenido; escapa los valores antes de incorporarlos al HTML. Consulta [Vistas](vistas.md) para partes reutilizables y templates.

## 5. Añadir metadatos

En `app/views/welcome/welcomeIndex.meta.php`:

```php
<?php

return [
    'metaTags' => [
        'title' => 'Bienvenida | Mi aplicación',
        'description' => 'Conoce las funcionalidades de nuestra aplicación.',
    ],
];
```

Los recursos comunes proceden de las metas globales. Añade CSS o JavaScript específico mediante este archivo cuando la página lo necesite. La decisión de indexación pertenece a la configuración SEO y a la ruta; las metas describen la página. Consulta [Metas](meta.md) y [SEO](seo.md).

## 6. Comprobar el resultado

Abre `/bienvenida` bajo la URL del proyecto. Debes ver el título y los tres elementos del listado. Comprueba el título del documento y la descripción en el HTML generado.

Si aparece una 404, revisa la declaración y `registerFinal()`, la ubicación del controlador y el nombre de la vista. Si faltan estilos, comprueba las metas globales y que el servidor entregue los recursos de `public/`.

Cuando esta página te resulte clara, continúa con [Tutorial completo: Productos](tutorial-productos.md). Allí se conectan base de datos, servicio, modelo/ORM, formulario AJAX, fragmentos de vista y permisos en un único recorrido. Después utiliza [Rutas](rutas.md), [ORM](orm.md) y [Frontend core](frontend-core.md) como referencia de cada pieza.


## 7. Añadir recursos propios

Cuando la página necesite estilos o comportamiento propios, manténgalos fuera de la vista:

```text
public/css/app/welcome/welcome.css
public/js/app/welcome/welcome.js
```

Amplíe `welcomeIndex.meta.php`:

```php
<?php
return [
    'metaTags' => [
        'title' => 'Bienvenida | Mi aplicación',
        'description' => 'Conoce las funcionalidades de nuestra aplicación.',
    ],
    'css' => ['public/css/app/welcome/welcome.css'],
    'js' => ['public/js/app/welcome/welcome.js'],
];
```

No copie Bootstrap, jQuery ni los estilos comunes en cada pantalla. Esos recursos pertenecen a capas compartidas y la meta de la vista solo añade lo específico de esta página.

## 8. Entender los parámetros de ruta

Una página real suele identificar un recurso en la URL. Antes de avanzar a persistencia, practique el recorrido con un parámetro siguiendo la sintaxis documentada en [Rutas](rutas.md). El controlador debe validar el valor recibido y no asumir que una cadena de la URL es segura o existe.

```text
URL -> Router -> routeParams -> controlador -> datos -> vista
```

La vista no debe leer directamente la URL para decidir qué consultar.

## 9. Convertir una parte en componente reutilizable

Si la lista de funcionalidades crece, extraiga cada elemento a `app/views/welcome/parts/featureItem.php` y reutilícelo desde el bucle. La parte hereda las variables disponibles en el punto del `include`; no ejecuta otra acción de controlador ni recibe datos automáticamente.

## 10. Comprobaciones antes de continuar

Verifique deliberadamente cada capa:

- la URL correcta responde y una URL inexistente conserva el 404;
- el controlador no imprime HTML;
- la vista no consulta la base de datos;
- los valores dinámicos se escapan;
- título y descripción aparecen en el documento;
- CSS y JavaScript específicos cargan desde la meta;
- no se añadieron recursos globales solo para esta pantalla.

## 11. Qué cambia al añadir datos

El siguiente paso no consiste en meter SQL dentro de este ejemplo. La arquitectura se amplía así:

```text
ruta -> controlador -> servicio -> modelo/ORM
                         |
                         +-> contrato de respuesta
                               |
                               +-> vista / AJAX
```

El [Tutorial completo: Productos](tutorial-productos.md) desarrolla ese recorrido con persistencia, validación, formularios AJAX, fragmentos, permisos y paginación. Esta primera página sirve como base mínima para entender dónde vive cada responsabilidad antes de añadir esas capas.
