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

Cuando esta página te resulte clara, continúa con [Tu primera funcionalidad completa: Productos](primera-funcionalidad.md). Allí se conectan base de datos, servicio, modelo/ORM, formulario AJAX, fragmentos de vista y permisos en un único recorrido. Después utiliza [Rutas](rutas.md), [ORM](orm.md) y [Frontend core](frontend-core.md) como referencia de cada pieza.
