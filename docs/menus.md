# Menús con MenuHelper

`MenuHelper::build(array $menuItems, string $currentURL = '', string $context = 'nav'): void` genera los elementos HTML de un menú desde un arreglo. Está disponible en el núcleo; no necesita un módulo adicional. Imprime directamente el resultado y no devuelve una cadena.

El proyecto crea el contenedor, elige los enlaces y filtra los elementos visibles. El helper genera los `<li>` y submenús; no registra rutas ni decide permisos.

Este es el generador de menús del framework: su API es `MenuHelper::build()`, no una clase llamada `MenuBuilder`. El [panel administrativo](panel-administrativo.md#añadir-enlaces-al-menú) también admite fragmentos PHP para ampliar su navegación. El generador construye el HTML; el JavaScript del panel controla la apertura y la persistencia de la barra lateral.

## Ejemplo en una vista o template

Con las rutas `productos`, `categorias` y `contacto` registradas en el proyecto:

```php
<?php
$baseUrl = rtrim((string)site_url, '/');
$items = [
    ['item', $baseUrl . '/productos', '', 'Productos'],
    ['dropdown', '#', '', 'Catálogo', [
        ['item', $baseUrl . '/categorias', '', 'Categorías'],
        ['item', $baseUrl . '/contacto', '', 'Contacto'],
    ]],
];
?>
<nav aria-label="Navegación principal">
    <ul class="navbar-nav">
        <?php MenuHelper::build($items, $_SERVER['REQUEST_URI'] ?? ''); ?>
    </ul>
</nav>
```

El resultado contiene un enlace y un dropdown. Sus clases y atributos esperan los recursos Bootstrap publicados por el proyecto. `MenuHelper` no carga CSS ni JavaScript automáticamente; decláralos en las [metas](meta.md) cuando el layout los necesite.

## Estructura de cada elemento

El arreglo es posicional:

| Posición | Contenido |
| --- | --- |
| `0` | Tipo: `heading`, `divider`, `item`, `menu` o `dropdown` |
| `1` | URL del enlace o identificador usado para un submenú |
| `2` | Clases del icono; cadena vacía si no hay icono |
| `3` | Texto visible |
| `4` | Arreglo de hijos, o ID del enlace cuando es un valor escalar |

`heading` genera un título de grupo, `divider` un separador e `item` un enlace. `dropdown` usa Bootstrap dropdown y marca también al padre si algún descendiente es el enlace actual. `menu` usa Bootstrap collapse, genera un ID a partir de la posición `1` y utiliza `data-bs-parent="#sidebar-nav"`; está orientado al sidebar con ese contenedor. Utiliza identificadores distintos para evitar colisiones entre submenús.

El contexto predeterminado `nav` genera `nav-item` y `nav-link`; `dropdown` genera `li-dd` y `dropdown-item` para sus hijos. No es un motor genérico de layouts ni ofrece un catálogo configurable de plantillas HTML.

## Enlace actual y límites

La comparación utiliza únicamente el path, sin query string y sin la barra final. Los enlaces con fragmento no se marcan como actuales. Un `item` activo recibe `current disabled`; esas clases no añaden por sí mismas atributos ARIA ni una política de navegación deshabilitada. La comparación tampoco distingue hosts, por lo que debes revisar enlaces externos cuyo path coincida con una página local.

El helper escapa títulos, URLs, clases e IDs al imprimirlos, pero no valida los protocolos de las URLs. Usa destinos conocidos o previamente validados. Escapar un atributo no convierte una URL arbitraria en un destino permitido.

Filtra los elementos según los permisos antes de llamar al helper y protege sus rutas con middleware. Ocultar un enlace no autoriza ni bloquea el acceso a su destino.

La función global `buildMenu()` es un wrapper de compatibilidad que delega en `MenuHelper::build()` cuando el arranque ha cargado esa capa. Para código nuevo utiliza la clase. La referencia general permanece en [Helpers PHP del core](helpers-php.md#menuhelper).

Para la apertura móvil, anclas y estado de navegación pública consulta [Navegación pública](navegacion-publica.md); para situar el menú en el documento consulta [Header](header.md) y [Footer](footer.md).
