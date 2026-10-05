# SEO

GFrame incluye generación dinámica de `sitemap.xml`, `robots.txt`, `llms.txt` y datos estructurados JSON-LD. No requiere un módulo opcional.

## Rutas iniciales

Los proyectos nuevos reciben tres rutas del sistema:

- `/sitemap.xml`
- `/robots.txt`
- `/llms.txt`

La configuración global controla la publicación de los índices. La ruta decide si una página permite indexación; las metas describen su contenido y JSON-LD.

## Configuración

```php
'seo' => [
    'enabled' => true,
    'allow_indexing' => true,
],
```

En modo debug se bloquea la indexación y se omiten sitemap, llms y JSON-LD. Robots permanece para comunicar el bloqueo.

| Opción global | Qué controla |
| --- | --- |
| `enabled` | Activar el soporte SEO |
| `allow_indexing` | Permitir la indexación del sitio |

`enabled` controla el soporte SEO y JSON-LD. `allow_indexing` permite publicar los índices del sitio; solo tiene efecto con SEO activo. Robots se publica siempre para comunicar la política de rastreo. Los títulos, recursos CSS/JS y demás datos necesarios para mostrar la página siguen disponibles aunque desactives SEO.

### Comportamiento global

| Configuración | Sitemap y llms | Robots |
| --- | --- | --- |
| SEO activo, indexación permitida, fuera de debug | Publicados | Publicado con la política de rastreo del sitio |
| SEO activo, indexación bloqueada | No publicados | Publicado con `Disallow: /` |
| SEO desactivado | No publicados | Publicado con `Disallow: /` |
| SEO activo en debug | No publicados | Publicado con `Disallow: /` |

El punto de entrada inicial emite además `X-Robots-Tag` para impedir indexación cuando no está permitida. El header imprime `noindex,nofollow,noarchive` en ese caso. `Disallow` limita el rastreo; `noindex` comunica que la página no debe indexarse y requiere que el buscador pueda leer esa instrucción. Estos mecanismos no garantizan eliminar inmediatamente páginas ya indexadas.

Sitemap y llms se activan conjuntamente con la indexación. No hay interruptores independientes para estos recursos. Las opciones controlan la generación del framework, no archivos físicos o reglas añadidos directamente al servidor.

## Excluir una página

Para excluir una página de sitemap y llms y emitir `noindex` en su HTML, declara en la ruta:

```php
use RouteBuilder as Route;

Route::get('confirmacion', 'home/HomeController@confirmation')
    ->template('home')
    ->view('homeConfirmation')
    ->context(['seo' => ['indexable' => false]])
    ->registerFinal();
```

Usa `indexable => true` o deja el valor sin declarar para páginas públicas. Una ruta nunca puede habilitar indexación bloqueada globalmente ni convertir en pública una ruta protegida. La exclusión no reemplaza los middleware de acceso. Robots tiene una política global; no se construye una regla individual por cada ruta.

### Ruta y vista: responsabilidades diferentes

| Dónde se declara | Qué modifica |
| --- | --- |
| Contexto de la ruta: `seo.indexable` | Inclusión en sitemap/llms y robots de la página HTML |
| Meta de la vista: `metaTags` y `schema` | Título, descripción, otras etiquetas y datos estructurados JSON-LD |
| Meta de la vista: `sitemap.dynamic` | Fuente de valores para las URLs parametrizadas |

Las metas no controlan indexación: `metaTags.robots` se ignora. Dos rutas que comparten una vista pueden tener distinta política de indexación sin duplicar el archivo meta. Los datos para expandir URLs dinámicas permanecen en `sitemap.dynamic`; describen su fuente de contenido, no habilitan indexación.

## Crecimiento automático del sitemap

El sitemap inspecciona las rutas `GET` públicas registradas. Una nueva ruta pública y estática se incorpora automáticamente; no hace falta crear otra ruta SEO.

La política compartida excluye canales distintos de `web`, rutas con permisos, middleware `auth`, `admin`, `role:*`, `can:*` y prefijos internos. Para políticas de acceso propias o páginas públicas que quieras mantener fuera de los índices, declara explícitamente:

```php
->context(['seo' => ['indexable' => false]])
```

## Rutas dinámicas

Una ruta con parámetros necesita indicar cómo obtener sus valores en el archivo meta de su vista:

```php
return [
    'sitemap' => [
        'dynamic' => [
            'params' => ['slug' => 'slug'],
            'dataset' => [
                'table' => 'articles',
                'conditions' => ['is_public' => 1],
                'limit' => 1000,
            ],
            'columns' => ['slug', 'updated_at'],
            'lastmod' => 'updated_at',
        ],
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ],
];
```

El proveedor consulta solamente la tabla y las columnas declaradas. La aplicación es responsable de definir una fuente pública y segura.

Guarda este contrato en la meta de la vista de una ruta como `articulos/{slug}`. `params` relaciona el placeholder con la columna de la tabla. Los campos `slug`, `updated_at` e `is_public` deben existir en tu esquema.

El proveedor admite condiciones simples, listas IN y comparaciones. El límite efectivo queda entre 1 y 50 000 registros. Usa un filtro explícito de publicación; el proveedor no ejecuta la política del controlador ni añade aislamiento de tenant automáticamente.

La generación consulta la meta de vista situada en `app/views/`, sin pasar por el montaje completo de Render. No combina automáticamente metas globales, de template y de grupo ni busca ese contrato en el original runtime del módulo. Mantén los datos de sitemap independientes de `$data` de una acción.

## Títulos, etiquetas y datos estructurados

Organiza título, descripción, canonical y assets según [Metas](meta.md). La meta de una pantalla puede declarar los datos estructurados que describe:

```php
<?php

return [
    'metaTags' => [
        'title' => 'Servicios del proyecto',
        'description' => 'Consulta los servicios disponibles.',
    ],
    'schema' => [
        'type' => 'WebPage',
        'title' => 'Servicios del proyecto',
        'description' => 'Consulta los servicios disponibles.',
    ],
];
```

SchemaComposer combina configuración, contexto de ruta y presets; JsonLD genera el bloque que imprime el header. Comprueba el HTML final al personalizar el header, porque registrar una clave de meta no garantiza que este imprima una etiqueta nueva.

JSON-LD forma parte del soporte SEO: describe páginas, artículos, organizaciones o productos mediante datos estructurados. `Meta::renderSchema()` devuelve una cadena vacía con SEO desactivado; con SEO activo puede describir una página aunque su indexación esté bloqueada. Conserva esa llamada al personalizar el header.

Consulta [Datos estructurados JSON-LD](json-ld.md) para el catálogo de presets, los campos de cada tipo, ejemplos de artículos, productos y SaaS, y entidades personalizadas.

## Qué se genera automáticamente

| Recurso | Fuente |
| --- | --- |
| Canonical e idioma de la página | Contexto de la ruta, con posibilidad de sustitución por metas |
| JSON-LD | Schema y metadatos de la página |
| Sitemap de páginas sin parámetros | Registro de rutas GET que supera los filtros de publicación |
| Sitemap de páginas parametrizadas | Contrato `sitemap.dynamic` de la meta de vista |
| robots.txt | Política de indexación y lista interna de áreas |
| llms.txt | Rutas públicas y datos que obtiene de sus metas |

Son respuestas dinámicas del núcleo. No necesitas crear archivos físicos ni ejecutar un cron para actualizar el registro de rutas. Un archivo físico servido por el servidor puede impedir que la petición llegue al generador.

Para páginas sin parámetros, `lastmod` puede obtenerse de la fecha del archivo de vista y su meta en la aplicación. La meta puede sustituirla; el contexto de ruta tiene prioridad sobre ambos. Esa fecha de archivo no equivale necesariamente a la última modificación del contenido en base de datos.

## Robots y llms

`robots.txt` permite el contenido público y bloquea las áreas internas habituales. `llms.txt` crea un índice legible de las páginas públicas utilizando el título y la descripción de sus archivos meta.

La lista de áreas internas de robots es fija. Cuando permite indexación, imprime el enlace al sitemap generado. Llms es una guía de contenido para asistentes de IA; publicarlo no garantiza indexación ni controla permisos de acceso.

Los prefijos bloqueados se escriben desde la raíz del dominio. En instalaciones bajo subcarpetas debes comprobar el resultado y la política robots del dominio completo.

## Actualizar declaraciones anteriores

Conserva únicamente `enabled` y `allow_indexing` en la configuración SEO. Las opciones anteriores `sitemap`, `robots` y `llms` ya no controlan recursos por separado.

Traslada cualquier `metaTags.robots` usado para bloquear indexación a las rutas que utilicen esa vista, declarando `context.seo.indexable = false`. Las metas antiguas no pueden sobreescribir la política de la ruta. Revisa también las metas globales y de grupo.

Las exclusiones anteriores `context.sitemap.include = false` y `context.llms.include = false` se aceptan temporalmente como bloqueo común de indexación, incluido el HTML. Sustitúyelas por `context.seo.indexable = false`. El contrato `sitemap.dynamic` sigue vigente para describir URLs dinámicas.

Actualiza el paquete y los archivos gestionados del proyecto para recibir las rutas del sistema actuales. Una ruta redefinida por el proyecto o un archivo físico robots/sitemap requiere revisión propia.

## Verificación

1. Configura la URL real y desactiva debug en producción.
2. Abre `/robots.txt`, `/sitemap.xml` y `/llms.txt` bajo la URL de despliegue.
3. Comprueba cabeceras y contenido, además del estado HTTP.
4. Verifica que el sitemap solo incluya contenido público y que sus URLs respondan.
5. Inspecciona título, descripción, canonical, idioma y JSON-LD en el HTML de una página.

La desactivación global de indexación establece robots bloqueante y una cabecera `X-Robots-Tag` desde el punto de entrada. Ninguna ruta ni meta puede habilitar indexación por encima de ese bloqueo.

Los bloqueos robots y las exclusiones del sitemap no protegen archivos ni operaciones. La protección depende del servidor y de los middleware. Para problemas de acceso a los endpoints, consulta [Servidores web](servidores-web.md).
