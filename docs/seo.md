# SEO

GFrame incluye infraestructura para:

- `sitemap.xml`;
- `robots.txt`;
- `llms.txt`;
- metadatos HTML y canonical;
- datos estructurados JSON-LD.

Estas piezas comparten configuración global, pero mantienen contratos específicos por ruta y por vista.

`GFrame\Seo\SeoPolicy` concentra la política moderna de indexación que utilizan Meta, Sitemap y LLMS. Una ruta protegida, privada o de error queda excluida de los índices y recibe `noindex,nofollow,noarchive` en HTML. Los controles individuales de los endpoints y el alcance de los contratos legacy se mantienen.

## Configuración

`config/defaults.php` define:

```php
'seo' => [
    'enabled' => true,
    'allow_indexing' => true,
    'sitemap' => true,
    'robots' => true,
    'llms' => true,
],
```

`LegacyConfigBridge` deriva:

```text
SEO_ENABLED
SEO_ALLOW_INDEXING
SEO_ENABLE_SITEMAP_XML
SEO_ENABLE_ROBOTS_TXT
SEO_ENABLE_LLMS_TXT
```

### Efecto de debug

Con `app.debug=true`:

- `SEO_ENABLED` queda en `false`;
- `SEO_ALLOW_INDEXING` queda en `false`;
- `SEO_ENABLE_SITEMAP_XML` queda en `false`;
- `SEO_ENABLE_LLMS_TXT` queda en `false`;
- `SEO_ENABLE_ROBOTS_TXT` conserva `seo.enabled && seo.robots`.

Así puede existir `robots.txt` en debug para bloquear rastreo, mientras sitemap, llms y JSON-LD quedan desactivados.

## Rutas del sistema

### Sitemap

`/sitemap.xml` se registra únicamente cuando:

```text
SEO_ALLOW_INDEXING = true
AND
SEO_ENABLE_SITEMAP_XML = true
```

### llms.txt

`/llms.txt` se registra únicamente cuando:

```text
SEO_ALLOW_INDEXING = true
AND
SEO_ENABLE_LLMS_TXT = true
```

### robots.txt

`/robots.txt` se registra cuando:

```text
SEO_ENABLE_ROBOTS_TXT = true
```

Si `SEO_ALLOW_INDEXING=false`, `Robots` responde:

```text
User-agent: *
Disallow: /
```

Cuando la indexación está permitida, publica sus reglas normales. La línea:

```text
Sitemap: <site_url>/sitemap.xml
```

solo se añade cuando `SEO_ENABLE_SITEMAP_XML` está habilitado. De esta forma `robots.txt` no anuncia una ruta de sitemap que no existe.

### SEO completamente desactivado

Con:

```php
'seo' => ['enabled' => false]
```

no se registran sitemap, llms ni robots. Además `Meta::renderSchema()` no genera JSON-LD.

## Perfil intranet

El perfil `intranet` genera SEO desactivado e indexación bloqueada. Aunque `seo_robots` pueda conservarse como valor del formulario de instalación, `LegacyConfigBridge` calcula `SEO_ENABLE_ROBOTS_TXT` como `seo.enabled && seo.robots`; con `seo.enabled=false` no se registra el endpoint.

La privacidad de una intranet depende de autenticación, autorización y configuración del servidor. SEO nunca es un control de acceso.

## Política de indexación de las páginas HTML

GFrame aplica una política de indexación con dos niveles:

1. el interruptor global `SEO_ALLOW_INDEXING`;
2. el contrato de la ruta `context.seo.indexable`.

Si cualquiera de los dos bloquea la indexación, `Meta::getMetaTag('robots')` devuelve:

```text
noindex,nofollow,noarchive
```

Una meta de template, grupo o vista no puede volver a habilitar una página bloqueada globalmente o por ruta. En el sentido contrario, una vista tampoco utiliza `metaTags.robots` para convertir arbitrariamente en `noindex` una ruta que el contrato considera indexable: el valor final de robots se decide por la política global + ruta.

Cuando la indexación global está desactivada, el `index.php` del esqueleto añade además:

```http
X-Robots-Tag: noindex, nofollow, noarchive
```

La cabecera y el meta robots son señales de indexación; no sustituyen autenticación ni autorización.

## Excluir una página de los índices

Para excluir coherentemente una ruta de su HTML, Sitemap y LLMS, utiliza el contrato moderno:

```php
Route::get('confirmacion', 'home/HomeController@confirmation')
    ->template('home')
    ->view('homeConfirmation')
    ->context([
        'seo' => ['indexable' => false],
    ])
    ->registerFinal();
```

`context.seo.indexable=false`:

- fuerza `noindex,nofollow,noarchive` en el HTML;
- excluye la ruta del Sitemap;
- excluye la ruta de `llms.txt`;
- no modifica la autorización de la ruta;
- no desactiva por sí solo JSON-LD cuando `SEO_ENABLED=true`.

Una ruta no puede usar `seo.indexable=true` para superar `SEO_ALLOW_INDEXING=false`.

### Compatibilidad con contratos anteriores

Durante la migración siguen siendo reconocidos:

```php
->context(['sitemap' => ['include' => false]])
```

para excluir Sitemap —y, por compatibilidad, también LLMS—, y:

```php
->context(['llms' => ['include' => false]])
```

para excluir únicamente `llms.txt`.

Estos contratos legacy no fuerzan el meta robots de la página. Para una exclusión coherente de HTML + Sitemap + LLMS, prefiera `context.seo.indexable=false`.

## Qué rutas entran al sitemap

`Sitemap` inspecciona rutas `GET` registradas cuyo `type` sea `web`.

Excluye:

- `context.seo.indexable=false`;
- `context.sitemap.include=false`;
- rutas con `permission` no vacío;
- rutas marcadas con `isProtected` o con `httpCode` de 400 en adelante;
- middleware `auth`, `admin`, nombres que comienzan por `auth`, `role:*` y `can:*`;
- los endpoints `sitemap.xml`, `robots.txt` y `llms.txt`;
- paths que comienzan por `admin`, `dashboard`, `api`, `ajax`, `webhook`, `auth`, `login`, `logout`, `core`, `app`, `storage`, `packages` o `vendor`.

`Llms` aplica la misma política moderna de `seo.indexable`, respeta los contratos legacy de sitemap/llms y excluye las mismas rutas protegidas e internas. Meta utiliza el patrón de `routeParams.uri` para reconocer esas áreas y conserva el alcance específico de las exclusiones legacy descritas arriba.

Si una política propia protege una ruta mediante un middleware que el núcleo no reconoce como privado, declara además `context.seo.indexable=false`.

Robots mantiene una política global de rastreo; no genera un `Disallow` individual por cada ruta marcada como no indexable.

## Rutas estáticas y `lastmod`

Una ruta web GET sin parámetros que supera los filtros entra en el sitemap.

Para `lastmod`, Sitemap intenta usar el `mtime` de:

```text
app/views/<grupo>/<vista>.php
app/views/<grupo>/<vista>.meta.php
```

y aplica esta precedencia:

```text
automático por mtime
  < sitemap.lastmod de la meta
  < context.lastmod de la ruta
```

También admite `changefreq` y `priority`.

El `mtime` de archivos no representa necesariamente la fecha de actualización del contenido de base de datos.

## Rutas dinámicas

Una ruta con placeholders necesita un contrato `sitemap.dynamic` en la meta de su vista.

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

`params` relaciona placeholders con columnas del dataset.

## SitemapDataProvider

El proveedor dinámico:

- valida tabla y columnas como identificadores seguros;
- limita consultas entre 1 y 50 000 filas;
- permite condiciones simples, `IN`, null/not null y operadores soportados;
- puede normalizar columnas de imagen y resolver IDs de media.

No ejecuta el controller, no aplica middleware y no añade automáticamente filtros de tenant o publicación. Define condiciones de negocio explícitas en cada dataset.

## Resolución de metas para Sitemap/Llms

Estos generadores consultan directamente:

```text
app/views/<relative>/<view>.meta.php
```

No montan necesariamente toda la misma combinación de template + grupo + vista que Render. Para una ruta dinámica de módulo, verifica dónde está disponible su meta en la aplicación.

## llms.txt

`Llms` produce un mapa Markdown del sitio público a partir de rutas GET y metadatos. Puede utilizar el mismo contrato `sitemap.dynamic` para expandir rutas dinámicas.

Añade referencias a sitemap/robots solo cuando esos endpoints están habilitados. `llms.txt` no concede acceso ni garantiza indexación por asistentes de IA.

## JSON-LD

El recorrido es:

```text
metas
  → Meta
  → SchemaComposer
  → JsonLD
  → <script type="application/ld+json">
```

`Meta::renderSchema()` devuelve `''` cuando `SEO_ENABLED=false`. Con SEO activo, `SchemaComposer` puede generar el grafo base `Organization → WebSite → WebPage` y añadir tipos específicos según los presets y bloques declarados.

### Presets

`SchemaComposer`:

- aplica `preset` o `presets`;
- resuelve recursivamente moldes compuestos;
- detecta ciclos de presets;
- normaliza bloques legacy;
- infiere tipo cuando falta;
- completa idioma, sitio, título, descripción e imagen desde metas.

No inventa una ruta `/buscar`. `SearchAction` solo aparece si la aplicación proporciona un `search.target` explícito con `{search_term_string}`.

El renderer conserva valores válidos `0` y `false`; elimina únicamente ausencia real (`null`, cadena vacía y arrays vacíos) en los nodos generados.

Consulta [Datos estructurados JSON-LD](json-ld.md) para el catálogo de tipos, moldes y ejemplos.

## Metas y canonical

Render establece `canonical` y `ogurl` desde `currentURL` antes de aplicar metas combinadas. Una meta posterior puede sustituir esos valores.

La jerarquía de template, grupo y vista está documentada en [Metadatos y recursos de vistas](meta.md).

## Separación de responsabilidades

```text
configuración global
  → activa SEO, indexación y endpoints del sistema

contexto de ruta
  → exclusiones de sitemap/llms y overrides soportados

meta de página
  → title, description, robots, canonical, schema, sitemap.dynamic

runtime SEO
  → genera sitemap, robots, llms y JSON-LD
```

No uses SEO como control de acceso. Una URL privada sigue necesitando middleware y filtrado de datos aunque esté ausente del sitemap y tenga `noindex`.

## Verificación

En un despliegue público comprueba:

1. existencia y código HTTP de `/robots.txt`, `/sitemap.xml` y `/llms.txt` según los switches;
2. que robots no anuncie un sitemap desactivado;
3. que sitemap/llms no incluyan rutas privadas;
4. que datasets dinámicos filtren contenido público y tenant correctamente;
5. `<meta name="robots">` y canonical;
6. JSON-LD cuando SEO está activo y su ausencia cuando está desactivado;
7. que `SearchAction` corresponda a una búsqueda real;
8. que un header/template personalizado conserve o elimine conscientemente `renderSchema()`.

La configuración SEO y la seguridad de acceso son problemas distintos.
