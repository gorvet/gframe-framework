# Multilenguaje

GFrame reconoce idiomas en la URL y entrega el idioma elegido al controlador y al renderizador. La aplicación utiliza ese valor para preparar textos, consultar contenido traducido y describir la página en sus metas.

## Configurar los idiomas

En el bloque `app` de `config/app.php`, configura una lista no vacía:

```php
return [
    'app' => [
        'language' => 'es',
        'supported_languages' => ['es', 'en'],
    ],
];
```

Integra estas claves en tu configuración existente; conserva el resto de sus opciones. El instalador genera inicialmente un solo idioma.

El Router utiliza **el primer elemento de `supported_languages`** como predeterminado. Mantén `language` alineado con ese valor: cambiar únicamente `language` no cambia la elección del Router. Los prefijos se comparan con los valores de la lista; utiliza los mismos códigos y mayúsculas en la configuración y los enlaces.

| URL de ejemplo | Idioma | Ruta que se busca |
| --- | --- | --- |
| `/about` | `es` | `about` |
| `/en/about` | `en` | `about` |
| `/es/about` | `es` | Redirección web 301 a `/about` |
| `/fr/about` | `es` | `fr/about`, porque `fr` no está configurado |

Un prefijo no reconocido permanece en la ruta y puede producir un 404. No se selecciona idioma por `Accept-Language`, geolocalización o preferencias de sesión.

## Declarar una ruta compartida

En `config/routes/routes_web.php` declara la ruta sin el prefijo:

```php
use RouteBuilder as Route;

Route::get('about', 'home/LanguageExampleController@index')
    ->template('home')
    ->view('homeAbout')
    ->registerFinal();
```

La misma declaración atiende `/about` y `/en/about`. El prefijo se retira antes de buscar la ruta y llega como `$routeParams['lang']`. `APP_LANG` también contiene el idioma de la petición.

La selección de idioma no cambia el controlador, la acción, el nombre de vista ni el template. Las traducciones del segmento, por ejemplo `acerca` y `about`, necesitan declaraciones de ruta y enlaces preparados por la aplicación; no se generan automáticamente.

## Preparar los textos en el controlador

En `app/controllers/home/LanguageExampleController.php`:

```php
<?php

class LanguageExampleController
{
    public function index(array $routeParams): array
    {
        $language = $routeParams['lang'] ?? 'es';
        $catalog = [
            'es' => [
                'title' => 'Acerca del proyecto',
                'description' => 'Conoce nuestro equipo y los servicios del proyecto.',
            ],
            'en' => [
                'title' => 'About the project',
                'description' => 'Meet our team and discover the project services.',
            ],
        ];

        return ['language' => $language, 'texts' => $catalog[$language] ?? $catalog['es']];
    }
}
```

El catálogo del ejemplo pertenece al proyecto. Si crece, puedes llevarlo a archivos o a un servicio propio; para contenido editorial, consulta desde el modelo la traducción del idioma solicitado. Define también qué hacer cuando falte una traducción, como recurrir al idioma principal o devolver una página no encontrada.

## Mostrar la misma vista con distintos textos

En `app/views/home/homeAbout.php`, el resultado de la acción está disponible en `$data`:

```php
<?php
$texts = $data['texts'];
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<h1><?= $escape($texts['title']) ?></h1>
<p><?= $escape($texts['description']) ?></p>
```

GFrame no añade sufijos como `.en.php` ni busca automáticamente una carpeta por idioma. Comparte la vista cuando solo cambien los textos. Si cambia el contenido o la composición, selecciona las partes desde la aplicación o declara otra vista mediante las convenciones de [Render](render.md).

## Metas y JSON-LD traducidos

En `app/views/home/homeAbout.meta.php` puedes utilizar los datos de la acción:

```php
<?php
return [
    'metaTags' => [
        'title' => $data['texts']['title'],
        'description' => $data['texts']['description'],
    ],
    'schema' => [
        'type' => 'WebPage',
        'title' => $data['texts']['title'],
        'description' => $data['texts']['description'],
        'lang' => $routeParams['lang'],
    ],
];
```

Render proporciona el idioma a `oglocale` y la URL solicitada a canonical y `ogurl`. El header inicial utiliza `oglocale` para el atributo HTML `lang`. SchemaComposer también puede tomar el idioma del contexto de ruta. Si sustituyes esos valores en una meta, comprueba que coincidan con el contenido mostrado.

El archivo meta global se carga antes de ejecutar la acción: no utilices allí `$data['texts']`. Reserva ese ejemplo para la meta de vista. La indexación sigue decidiéndose en la ruta y la configuración global, no en la traducción de la meta. Consulta [Metas](meta.md) y [SEO](seo.md).

## Enlaces para cambiar de idioma

Para la página del ejemplo, ambos enlaces apuntan a la misma ruta:

```php
<?php $base = rtrim((string)site_url, '/'); ?>
<a href="<?= htmlspecialchars($base . '/about', ENT_QUOTES, 'UTF-8') ?>" lang="es">Español</a>
<a href="<?= htmlspecialchars($base . '/en/about', ENT_QUOTES, 'UTF-8') ?>" lang="en">English</a>
```

`site_url` es la base del despliegue y no incorpora automáticamente el idioma actual. Construye el prefijo de los idiomas secundarios y deja sin prefijo el predeterminado. Los enlaces de navegación deben conservar esa elección. Para rutas con parámetros y filtros, conserva sus valores usando codificación URL, en lugar de reemplazar texto arbitrario en la URL completa.

## AJAX y otros canales

Los prefijos también se reconocen antes de determinar el canal: `/en/ajax/catalogo/list` busca `ajax/catalogo/list` y entrega `lang = en`. Lo mismo ocurre con API, webhook y SSE.

Estos canales no realizan la redirección de limpieza del prefijo predeterminado que se aplica a las páginas web. Sus controladores reciben el idioma como contexto, pero siguen necesitando preparar las traducciones de mensajes y fragmentos.

El JavaScript debe enviar la URL con el prefijo correspondiente si necesita conservar el idioma. Una solicitud a `site_url + 'ajax/...'`, sin prefijo, utiliza el idioma predeterminado aunque la pantalla actual esté en inglés.

## URLs traducidas en el SEO

El sitemap actual recorre las rutas registradas; no multiplica cada ruta por todos los idiomas configurados ni genera relaciones `hreflang` automáticamente. Activar `supported_languages` tampoco traduce el título o la descripción que lee llms.

Para un sitio público con varias versiones lingüísticas, prepara explícitamente las URLs y sus metadatos. Si necesitas enlaces `hreflang` en el header o alternancias en el sitemap, debes implementarlos en la aplicación mediante sus puntos de personalización y comprobar cada URL publicada. La generación automática de esas alternancias no forma parte del contrato actual.

## Comprobar una página traducida

1. Abre la URL predeterminada y la de un idioma secundario.
2. Comprueba los textos, el atributo HTML `lang`, canonical y JSON-LD.
3. Cambia de idioma y verifica que los enlaces y parámetros se conserven.
4. Ejecuta búsqueda, paginación o formularios AJAX y comprueba el idioma de los fragmentos y mensajes.
5. Revisa sitemap y llms: no supongas que incluyen todas las traducciones por haber configurado sus idiomas.
