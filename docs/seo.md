# SEO

GFrame incluye infraestructura dinámica para:

- `sitemap.xml`;
- `robots.txt`;
- `llms.txt`;
- metadatos HTML;
- canonical;
- datos estructurados JSON-LD.

Estas piezas están relacionadas, pero **no son un único interruptor** en el runtime actual. La configuración global, las rutas del sistema, las metas de una vista y los contratos de sitemap/llms intervienen en lugares distintos.

## Configuración actual

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

Los cinco valores siguen activos en la implementación actual.

`LegacyConfigBridge` los convierte en constantes que utiliza el núcleo:

```text
SEO_ENABLED
SEO_ALLOW_INDEXING
SEO_ENABLE_SITEMAP_XML
SEO_ENABLE_ROBOTS_TXT
SEO_ENABLE_LLMS_TXT
```

### Efecto de debug

Con `app.debug=true`:

- `SEO_ALLOW_INDEXING` queda en `false`;
- `SEO_ENABLE_SITEMAP_XML` queda en `false`;
- `SEO_ENABLE_LLMS_TXT` queda en `false`;
- `SEO_ENABLE_ROBOTS_TXT` conserva el valor de `seo.enabled && seo.robots`.

Por eso un proyecto con SEO habilitado y debug activo puede seguir publicando `robots.txt`, pero no sitemap ni llms.

## Qué rutas se registran

`config/routes/routes_system.php` aplica estas reglas:

### Sitemap

Se registra `/sitemap.xml` únicamente cuando:

```text
SEO_ALLOW_INDEXING = true
AND
SEO_ENABLE_SITEMAP_XML = true
```

### llms.txt

Se registra `/llms.txt` únicamente cuando:

```text
SEO_ALLOW_INDEXING = true
AND
SEO_ENABLE_LLMS_TXT = true
```

### robots.txt

Se registra `/robots.txt` cuando:

```text
SEO_ENABLE_ROBOTS_TXT = true
```

`Robots` comprueba después `SEO_ALLOW_INDEXING`: si la indexación global está bloqueada, responde:

```text
User-agent: *
Disallow: /
```

### SEO completamente desactivado

Con:

```php
'seo' => [
    'enabled' => false,
]
```

el código actual no registra sitemap, llms **ni robots**, porque los tres interruptores derivados quedan desactivados.

No documentes `seo.enabled=false` como «robots sigue publicado con Disallow» porque esa no es la ejecución actual.

## Perfil intranet

El instalador fuerza en `intranet`:

```text
seo_enabled = false
seo_allow_indexing = false
seo_sitemap = false
seo_robots = true
seo_llms = false
```

Sin embargo, `LegacyConfigBridge` calcula `SEO_ENABLE_ROBOTS_TXT` como `seo.enabled && seo.robots`. Por tanto, con la configuración generada actual de intranet, `seo.enabled=false` impide registrar también `robots.txt`.

La protección de una intranet sigue dependiendo de autenticación/middleware y configuración del servidor; SEO nunca es un mecanismo de acceso.

## Metatag robots de las páginas HTML

`Meta::initializeConfig()` crea un valor predeterminado:

```text
SEO_ALLOW_INDEXING=true  → index,follow
SEO_ALLOW_INDEXING=false → noindex,nofollow,noarchive
```

El header del esqueleto imprime:

```html
<meta name="robots" content="...">
```

### Las metas pueden sobrescribirlo

Después de inicializar ese valor, Render combina metas globales, de template/grupo y de vista. Un `metaTags.robots` posterior **sí puede sobrescribir** el valor predeterminado.

Ejemplo:

```php
return [
    'metaTags' => [
        'robots' => 'noindex,nofollow,noarchive',
    ],
];
```

No existe actualmente un bloqueo dentro de `Meta::setMetaTags()` que impida declarar `index,follow` cuando la indexación global está desactivada. Por tanto, evita contradicciones manuales en tus metas.

## No existe actualmente `context.seo.indexable`

La implementación auditada no utiliza un contrato de ruta:

```php
->context(['seo' => ['indexable' => false]])
```

para excluir páginas del sitemap, llms o modificar `<meta name="robots">`.

No dependas de ese contrato mientras el código no lo implemente.

La exclusión actual utiliza contratos separados.

## Excluir una ruta del sitemap

Usa:

```php
Route::get('confirmacion', 'home/HomeController@confirmation')
    ->template('home')
    ->view('homeConfirmation')
    ->context([
        'sitemap' => ['include' => false],
    ])
    ->registerFinal();
```

`Sitemap` comprueba directamente:

```text
context.sitemap.include === false
```

Ese valor **no cambia automáticamente el robots meta del HTML**.

Si también quieres `noindex`, decláralo en la meta que Render aplica a esa página:

```php
return [
    'metaTags' => [
        'robots' => 'noindex,nofollow,noarchive',
    ],
];
```

## Excluir una ruta de llms.txt

`Llms` reconoce:

```php
->context([
    'llms' => ['include' => false],
])
```

También excluye una ruta cuando:

```text
context.sitemap.include === false
```

Por tanto:

```php
'context' => [
    'sitemap' => ['include' => false],
]
```

es el bloqueo común actual para sitemap + llms.

Si quieres excluir **solo** llms y conservar sitemap, usa `llms.include=false`.

## Qué rutas entran automáticamente al sitemap

`Sitemap` inspecciona las rutas `GET` registradas y aplica filtros internos.

Solo considera rutas cuyo `type` sea `web`.

Excluye actualmente:

- `context.sitemap.include=false`;
- rutas con el campo `permission` no vacío;
- middleware `auth`;
- middleware cuyo nombre comience por `auth`;
- el propio `sitemap.xml`;
- paths que comiencen por `admin`, `dashboard`, `api`, `ajax`, `webhook`, `auth`, `login` o `logout`.

### Importante: el filtro no interpreta todo middleware

El código de Sitemap **no clasifica genéricamente cualquier middleware como privado**. Por ejemplo, no analiza por nombre todos los posibles `role:*`, `can:*`, middleware propios o políticas de negocio.

Por eso, si una ruta no debe aparecer públicamente, declara la exclusión expresamente:

```php
->context(['sitemap' => ['include' => false]])
```

No confíes únicamente en que un middleware no relacionado con `auth` vaya a ser detectado por el generador SEO.

`Llms` utiliza una heurística parecida y añade algunos prefijos internos como `core`, `app`, `storage` y `vendor`.

## Rutas estáticas

Una ruta web GET sin parámetros que supera los filtros se incorpora al sitemap.

La URL se construye desde `site_url` y el path de la ruta.

Para `lastmod`, Sitemap intenta obtener el `mtime` de:

```text
app/views/<grupo>/<vista>.php
app/views/<grupo>/<vista>.meta.php
```

Después aplica precedencia:

```text
automático por mtime
  < sitemap.lastmod de la meta
  < context.lastmod de la ruta
```

También admite `changefreq` y `priority` desde meta/contexto.

Ese `mtime` representa cambios de archivos, no necesariamente cambios del contenido de base de datos.

## Rutas dinámicas

Una ruta con placeholders no se publica como plantilla literal. Necesita un contrato `sitemap.dynamic` en la meta de su vista.

Ejemplo:

```php
return [
    'sitemap' => [
        'dynamic' => [
            'params' => [
                'slug' => 'slug',
            ],
            'dataset' => [
                'table' => 'articles',
                'conditions' => [
                    'is_public' => 1,
                ],
                'limit' => 1000,
            ],
            'columns' => [
                'slug',
                'updated_at',
            ],
            'lastmod' => 'updated_at',
        ],
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ],
];
```

`params` relaciona cada placeholder de URL con una columna del dataset.

## SitemapDataProvider

El proveedor dinámico:

- valida nombres de tabla/columnas con identificadores alfanuméricos + `_`;
- limita la consulta entre 1 y 50 000 filas;
- permite condiciones simples;
- permite `IN` cuando el valor es array;
- permite null / not null;
- soporta `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=` y `LIKE`;
- normaliza ciertas columnas de imagen;
- puede resolver IDs de media contra la tabla `medias`.

### El aislamiento de negocio sigue siendo responsabilidad del contrato

El proveedor **no ejecuta el controller**, no aplica middleware y no añade automáticamente filtros de tenant o publicación.

Por tanto, un dataset como:

```php
'dataset' => [
    'table' => 'articles',
]
```

puede exponer al sitemap filas que tu aplicación no considere públicas.

Define siempre condiciones explícitas adecuadas al contenido.

## Dónde busca las metas Sitemap/Llms

La implementación actual resuelve directamente:

```text
app/views/<relative>/<view>.meta.php
```

para los contratos especiales de Sitemap/Llms.

No utiliza `ModuleRuntime::file()` en ese recorrido y no monta toda la combinación de template + group + view que hace Render.

Por tanto, si una ruta dinámica de módulo necesita participar en Sitemap/Llms, comprueba expresamente dónde está disponible su meta en el proyecto actual.

## llms.txt

`Llms` produce un mapa Markdown del sitio público.

Utiliza:

- nombre del sitio;
- descripción global si existe;
- rutas GET públicas según sus filtros;
- título y descripción de `metaTags` de la vista;
- el mismo contrato `sitemap.dynamic` para expandir rutas dinámicas;
- campos opcionales `dynamic.title`, `title_fallback` y `description` para obtener textos desde el dataset.

Añade enlaces opcionales a sitemap/robots únicamente cuando los interruptores correspondientes están activos.

`llms.txt` no concede acceso ni garantiza indexación por asistentes de IA.

## robots.txt

Cuando la indexación está permitida, `Robots` genera bloques para varios user-agents y bloquea paths internos como:

```text
/admin/
/ajax/
/api/
/auth/
/core/
/app/
/storage/
/packages/
/vendor/
```

Además incluye:

```text
Sitemap: <site_url>/sitemap.xml
```

cuando llega a ese modo de render.

Cuando `SEO_ALLOW_INDEXING=false`, devuelve únicamente bloqueo global.

Ten en cuenta que robots.txt controla rastreo, no autenticación.

## JSON-LD: comportamiento actual

Render combina metas y llama a `Meta::renderSchema()` desde el header del esqueleto.

El recorrido es:

```text
metas
  → Meta
  → SchemaComposer
  → JsonLD
  → <script type="application/ld+json">
```

### No está condicionado actualmente por `SEO_ENABLED`

En el código auditado, `Meta::renderSchema()`, `SchemaComposer` y `JsonLD::renderSchema()` no comprueban `SEO_ENABLED` para omitir el JSON-LD.

Además, `SchemaComposer` infiere `WebPage` cuando no se declara `type`, por lo que el header puede seguir generando un grafo base incluso sin un bloque `schema` específico.

Por tanto, no documentes actualmente «`seo.enabled=false` omite JSON-LD» como una garantía del runtime.

Si el otro trabajo de desarrollo cambia este comportamiento, esta sección deberá reconciliarse contra el código integrado.

Consulta [Datos estructurados JSON-LD](json-ld.md) para presets y entidades.

## Presets y SchemaComposer

`SchemaComposer`:

- aplica `preset` o `presets`;
- normaliza bloques legacy;
- infiere tipo cuando falta;
- completa idioma, sitio, título, descripción e imagen desde metas;
- prepara un `SearchAction` por defecto con `/buscar?q={search_term_string}` cuando falta target;
- utiliza `src/seo/schema.presets.php` como catálogo incorporado.

Los campos específicos y tipos generados se detallan en [JSON-LD](json-ld.md).

## Metas y canonical

Render establece `canonical` y `ogurl` desde `currentURL` de la ruta antes de aplicar las metas combinadas. Una meta posterior puede sustituir esos valores.

La jerarquía general de template, grupo y vista está documentada en [Metadatos y recursos de vistas](meta.md).

## Separación de responsabilidades

Piensa SEO en cuatro capas distintas:

```text
configuración global
  → habilita/indexa y registra endpoints del sistema

contexto de ruta
  → exclusiones de sitemap/llms y overrides específicos soportados

meta de página
  → title, description, robots, canonical, schema, sitemap.dynamic

runtime SEO
  → genera sitemap, robots, llms y JSON-LD
```

No uses SEO como control de acceso. Una URL privada sigue necesitando middleware y filtrado de datos aunque esté ausente del sitemap y tenga `noindex`.

## Verificación

En un despliegue público comprueba realmente:

1. si existen `/robots.txt`, `/sitemap.xml` y `/llms.txt` con tu combinación de switches;
2. sus códigos HTTP y Content-Type;
3. que sitemap/llms no incluyan rutas privadas;
4. que los datasets dinámicos filtren contenido publicado/tenant correctamente;
5. `<meta name="robots">` del HTML;
6. canonical;
7. JSON-LD generado en el HTML;
8. que un header/template personalizado conserve o elimine conscientemente `renderSchema()`.

La configuración SEO y la seguridad de acceso son problemas diferentes.
