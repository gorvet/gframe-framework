# SEO

GFrame incluye generación dinámica de `sitemap.xml`, `robots.txt`, `llms.txt` y datos estructurados JSON-LD. No requiere un módulo opcional.

## Rutas iniciales

Los proyectos nuevos reciben tres rutas del sistema:

- `/sitemap.xml`
- `/robots.txt`
- `/llms.txt`

`sitemap.xml` y `llms.txt` solo se registran cuando la indexación y cada recurso están habilitados. `robots.txt` se registra cuando su opción está habilitada, incluso si la indexación está desactivada; en ese caso responde con `Disallow: /`.

## Configuración

```php
'seo' => [
    'enabled' => true,
    'allow_indexing' => true,
    'sitemap' => true,
    'robots' => true,
    'llms' => true,
],
```

En modo debug se impiden la indexación, el sitemap y `llms.txt`. `robots.txt` puede permanecer activo para comunicar el bloqueo a los rastreadores.

## Crecimiento automático del sitemap

El sitemap inspecciona las rutas `GET` públicas registradas. Una nueva ruta pública y estática se incorpora automáticamente; no hace falta crear otra ruta SEO.

No se incluyen rutas protegidas, administrativas, API, AJAX, webhook, autenticación ni rutas con permisos. También puede excluirse una ruta expresamente:

```php
->context(['sitemap' => ['include' => false]])
```

## Rutas dinámicas

Una ruta con parámetros necesita indicar cómo obtener sus valores en el archivo meta de su vista:

```php
return [
    'sitemap' => [
        'dynamic' => [
            'params' => ['slug' => 'slug'],
            'dataset' => ['table' => 'articles'],
            'columns' => ['slug', 'updated_at'],
            'lastmod' => 'updated_at',
        ],
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ],
];
```

El proveedor consulta solamente la tabla y las columnas declaradas. La aplicación es responsable de definir una fuente pública y segura.

## Robots y llms

`robots.txt` permite el contenido público y bloquea las áreas internas habituales. `llms.txt` crea un índice legible de las páginas públicas utilizando el título y la descripción de sus archivos meta.
