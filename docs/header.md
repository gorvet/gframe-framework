# Header y apertura del documento

`app/views/templates/header.php` abre el documento HTML compartido por las páginas web. Render lo incluye antes del template y de `footer.php`:

```text
app/views/templates/header.php
  -> template seleccionado y contenido de la vista
  -> app/views/templates/footer.php
```

El archivo original se distribuye en `resources/skeleton/app/views/templates/header.php`. En la aplicación creada se trabaja con el archivo de `app/`, no con el paquete instalado de Composer.

## Qué contiene

| Parte | Fuente |
| --- | --- |
| Idioma del documento | `Meta::getMetaTag('oglocale')`, con `es` como alternativa |
| Título y descripción | Metadatos combinados de la página |
| Robots | Política global y de la ruta; una meta de vista no puede cambiarla |
| Canonical | Meta `canonical` preparada por Render |
| Favicon y apple-touch-icon | Imágenes del proyecto bajo `public/img/` |
| Scripts de cabecera | `Meta::getHeaderJsScripts()` |
| Hojas de estilo | `Meta::getCssLinks()` |
| Datos estructurados | `Meta::renderSchema()` |
| Clases del body | `$bodyClass`, calculado por Render |
| Campos CSRF | Formulario oculto `#tokens`, con `csrfToken` y `csrfTimestamp` |

El header abre `<html>`, `<head>` y `<body>`; el footer cierra el documento. Las vistas y templates aportan el contenido intermedio. Evita repetir esas etiquetas en cada pantalla.

## Registrar recursos

Los recursos se declaran en [metadatos](meta.md). Por ejemplo, en `app/views/home/homeIndex.meta.php`:

```php
<?php
return [
    'metaTags' => [
        'title' => 'Inicio',
        'description' => 'Presentación de la aplicación.',
    ],
    'css' => ['public/css/home/home.css'],
    'hjs' => ['public/js/home/preload.js'],
    'js' => ['public/js/home/home.js'],
];
```

El ejemplo presupone que los tres archivos existen en el proyecto. `css` se imprime en el header y `js` en el footer. La clave `hjs` registra scripts en la cabecera cuando deben ejecutarse antes del contenido; la plantilla actual los imprime antes de las hojas de estilo y sin añadir `defer` automáticamente.

Por ejemplo, `preload.js` puede recuperar una preferencia visual antes de pintar la página. Para cargar un script en todas las páginas, declara `hjs` en `config/meta/global.meta.php`; si solo pertenece a un layout, decláralo en la meta de ese template. El panel ya utiliza este mecanismo para restaurar el [tema y el estado del menú](panel-administrativo.md#persistencia-del-tema-y-del-menú).

Los scripts de cabecera se ejecutan antes de que exista el contenido del body y antes de que el footer declare `site_url` e `is_protected` para JavaScript. Si necesitan esos datos o el DOM de la página, decláralos en `js` o coordina explícitamente su inicialización.

## Dónde colocar la navegación visible

Este archivo compartido abre el documento; no genera una barra de navegación por sí mismo. La navegación visible del home inicial está en su vista, y los layouts de administración organizan su propia cabecera y sidebar. Añade la navegación en el template o vista correspondiente cuando solo pertenezca a ese layout.

Para generar enlaces desde datos utiliza [Menús con MenuHelper](menus.md). Para el comportamiento móvil y las anclas de la navegación pública consulta [Navegación pública](navegacion-publica.md). El helper PHP y ese controlador JavaScript tienen responsabilidades distintas.

No existe una API `renderHeaderArea()` ni una jerarquía de parciales de cabecera equivalente a las [áreas del footer](footer.md). Render incluye directamente el header compartido del proyecto; no aplica el fallback de templates de módulos a este archivo.

## Personalización y actualización

Puedes ajustar estructura y contenido en el header de la aplicación, manteniendo las metas, los recursos, las clases del body y los campos CSRF que utilice el proyecto. Escapa cualquier dato añadido según su contexto de salida.

El header forma parte de los archivos gestionados por el actualizador del proyecto. Antes de actualizar revisa `composer gframe:update -- --dry-run` y las opciones de conservación descritas en [Actualizaciones](actualizaciones.md). Una personalización no debe darse por conservada sin revisar ese resultado.

Si faltan estilos, comprueba las metas y los archivos públicos. Si falla una operación AJAX con CSRF, comprueba que `#tokens` y sus campos sigan presentes. Si faltan título, canonical o JSON-LD, revisa [Render](render.md) y [SEO](seo.md) antes de duplicar las etiquetas en una vista.
