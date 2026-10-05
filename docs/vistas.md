# Vistas, templates y partes

Una pantalla web se compone de una vista con su contenido y un template que define su distribución. El header y el footer comunes completan el documento. Las partes permiten reutilizar fragmentos dentro de una vista o template.

## Archivos de una pantalla

```text
app/views/
├── catalogo/
│   ├── productIndex.php
│   ├── productIndex.meta.php
│   ├── catalogo.group.meta.php
│   ├── parts/
│   │   └── productCard.php
│   └── productIndex.footer.content.php
└── templates/
    ├── header.php
    ├── homeTemplate.php
    ├── home.meta.php
    ├── footer.php
    └── footer/
        ├── content.php
        ├── copyright.php
        └── credits.php
```

| Archivo | Responsabilidad |
| --- | --- |
| `productIndex.php` | Contenido propio de la pantalla |
| `productCard.php` | Fragmento incluido por la vista |
| `homeTemplate.php` | Layout compartido que coloca `$content` |
| `header.php` | Apertura del documento, etiquetas, CSS, scripts de cabecera y tokens comunes |
| `footer.php` | Áreas del pie, scripts finales y cierre del documento |
| Archivos `.meta.php` | Datos descriptivos y recursos de la pantalla o layout |

Los nombres `catalogo`, `productIndex` y `home` son del ejemplo. Las reglas de inferencia y las declaraciones explícitas se describen en [Rutas](rutas.md). No es necesario crear todos los archivos: las metas y los fragmentos se añaden cuando la pantalla los necesita.

## Vista y datos

La acción del controlador devuelve un array. La vista lo recibe en `$data`; los parámetros resueltos de la ruta están en `$routeParams`. No se extraen automáticamente claves como variables independientes.

Por ejemplo, para una acción que devuelve `['products' => [['name' => 'Cuaderno']]]`, `productIndex.php` puede contener:

```php
<h1>Productos</h1>
<div class="row g-3">
  <?php foreach (($data['products'] ?? []) as $product): ?>
    <div class="col-12 col-md-6">
      <?php include __DIR__ . '/parts/productCard.php'; ?>
    </div>
  <?php endforeach; ?>
</div>
```

En `parts/productCard.php`:

```php
<article class="card">
  <div class="card-body">
    <h2 class="h5"><?= htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
  </div>
</article>
```

La parte conserva las variables disponibles en el punto del `include`, como `$product`. No recibe automáticamente un nuevo contrato ni ejecuta otra acción. Las consultas y reglas de negocio corresponden al controlador, servicio o modelo; la vista presenta sus resultados.

Escapa texto y atributos al imprimirlos. El contenido enriquecido debe pasar por la política de [Sanitización de HTML](html-sanitizer.md). Nunca construyas un `include` con una ruta aportada por el usuario.

## Template

Render captura el HTML de la vista en `$content`. El template coloca ese contenido junto a las regiones compartidas de su layout:

```php
<main class="container py-4">
  <?= $content ?>
</main>
```

Este ejemplo corresponde a `app/views/templates/homeTemplate.php`. `$content` ya es HTML generado por la vista: no se escapa ni se vuelve a incluir la vista. Coloca el único `<main>` en el template o en la vista según el layout elegido, sin anidarlos.

## Header común

`app/views/templates/header.php` abre `<html>`, `<head>` y `<body>`. Imprime las etiquetas y los recursos registrados mediante metas. El esqueleto también incluye el formulario oculto `#tokens` para los flujos AJAX del framework.

El header común pertenece al proyecto y se carga directamente; no se resuelve como una vista de módulo. No copies la apertura del documento en cada pantalla. Para añadir un CSS o cambiar el título de una página, utiliza su [meta](meta.md), no edites el header para cada caso.

## Footer y sus áreas

`app/views/templates/footer.php` es el contenedor común. Sus tres áreas se resuelven independientemente:

| Área | Contenido habitual |
| --- | --- |
| `content` | Menús, contacto o enlaces del pie |
| `copyright` | Titularidad y año |
| `credits` | Autoría y reconocimientos |

Para `content` de `catalogo/productIndex`, se utiliza el primer archivo existente:

1. `app/views/catalogo/productIndex.footer.content.php`.
2. `app/views/catalogo/catalogo.footer.content.php`.
3. `app/views/templates/footer/content.php`.

La misma jerarquía se aplica a `copyright` y `credits`. Un archivo elegido sustituye el área, no se concatena con las alternativas. Si no existe ninguno, el área queda vacía. En grupos anidados se usa el nombre de la última carpeta.

Por ejemplo, `productIndex.footer.content.php` puede contener:

```php
<nav aria-label="Enlaces del catálogo">
  <a href="<?= htmlspecialchars(site_url . 'contacto', ENT_QUOTES, 'UTF-8') ?>">Consultar disponibilidad</a>
</nav>
```

Los fragmentos del footer reciben `$data` y `$routeParams`. El contenedor carga después los scripts de final de página, monta `#toastBox`, declara `site_url` e `is_protected` y cierra el documento. No elimines esas funciones al cambiar su presentación. El detalle de su API y estilos está en [Footer](footer.md).

## Vistas y partes de módulos

Para una ruta asociada a un módulo, la vista puede proceder de `app/views/<módulo>/` o del original incluido en el paquete. Una personalización sustituye el archivo completo. No necesita otra URL.

El `include __DIR__` del ejemplo inicial sirve para partes propias de la aplicación. Si una parte de módulo debe admitir respaldo al original, resuélvela mediante `ModuleRuntime::file()` antes de incluirla. El ejemplo comprobado y el orden de resolución están en [Render](render.md#partes-de-una-vista) y [Módulos del framework](modulos-runtime.md).

## Fragmentos AJAX y recursos

Una ruta web utiliza el montaje completo. Los listados y formularios AJAX actualizan fragmentos sin volver a imprimir header, template ni footer. El contrato de respuesta y la inicialización de componentes tras insertar HTML se explican en [Frontend core](frontend-core.md).

Registra CSS y JavaScript en la meta global, de template, grupo o vista según su alcance. Las metas no contienen el HTML del footer ni publican archivos; consulta [Metadatos y recursos](meta.md) para prioridad y ejemplos. El recorrido interno que prepara los datos y compone la respuesta corresponde a [Render](render.md).
