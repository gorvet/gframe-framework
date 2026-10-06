# Iconos de GFrame

`gframe-icons` es la fuente de iconos propia del framework. Se instala con la base visual y se publica en `public/vendors/internal/gframe-icons/`. Para usarla, declare `style.css` en el meta de la vista o del grupo de vistas. Las fuentes se cargan mediante rutas relativas al CSS.

## Carga

```php
<?php

return [
    'css' => ['public/vendors/internal/gframe-icons/style.css'],
];
```

Declare el recurso en la [meta](meta.md) del template si lo utiliza todo el panel, o en el grupo o la vista cuando su uso sea puntual. No requiere JavaScript. Si publica manualmente el módulo, use `php bin/modules.php publish /ruta/del/proyecto/public gframe-icons`.

Conserve la carpeta `fonts/` junto a `style.css`; copiar solamente el CSS deja la fuente sin archivos que cargar. El manifiesto publica también el catálogo y los archivos de selección.

## Uso en vistas

Un icono se representa con un elemento `<i>` cuya clase comienza por `gicon-`:

```html
<i class="gicon-user" aria-hidden="true"></i>
```

La fuente solo se aplica a elementos `<i>` con alguna clase `gicon-*`; no cambia el aspecto de los demás elementos `<i>` ni de otros sistemas de iconos. Si el icono es puramente decorativo, use `aria-hidden="true"`. Si un botón solo contiene un icono, dé al botón un nombre accesible con texto visible o `aria-label`.

```html
<button type="button" class="btn btn-outline-primary">
  <i class="gicon-save" aria-hidden="true"></i> Guardar
</button>

<button type="button" class="btn btn-outline-secondary" aria-label="Editar usuario">
  <i class="gicon-edit" aria-hidden="true"></i>
</button>
```

El icono hereda el color y el tamaño del texto. Use las utilidades del framework o CSS de la vista para ajustar `font-size` y separación, sin reemplazar la familia tipográfica que define el módulo.

| Uso | Clases disponibles |
| --- | --- |
| Cuenta y navegación | `gicon-user`, `gicon-admin`, `gicon-menu`, `gicon-dashboard` |
| Acciones | `gicon-save`, `gicon-edit`, `gicon-trash`, `gicon-plus`, `gicon-close` |
| Información | `gicon-info`, `gicon-alert`, `gicon-help`, `gicon-check` |
| Comunicación | `gicon-bell`, `gicon-mail`, `gicon-chat`, `gicon-whatsapp` |
| Multimedia | `gicon-image`, `gicon-photo`, `gicon-video`, `gicon-download` |
| Búsqueda y organización | `gicon-search`, `gicon-folder`, `gicon-calendar`, `gicon-config` |

Los nombres son literales y distinguen mayúsculas cuando corresponda, por ejemplo `gicon-AI`. Consulte el catálogo para el resto de clases; no construya nombres a partir de una traducción del icono.

## Iconos multicolor

Algunos iconos se componen de varios glifos superpuestos. Necesitan los elementos `.pathN` indicados en la demo, no solo la clase exterior.

```html
<i class="gicon-gcolor" aria-hidden="true">
  <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
</i>
```

`gicon-gcolor` utiliza cuatro partes y `gicon-gorvet-color`, dos. Esas partes tienen colores propios en el CSS; a diferencia de los iconos simples, no todas heredan el color del texto.

## Elegir y ampliar iconos

Abra `public/vendors/internal/gframe-icons/demo.html` para consultar el catálogo visual y los nombres de clase disponibles. `selection.json` conserva la selección de IcoMoon y permite editar o ampliar la fuente. Para añadir un icono al catálogo común, importe esa selección en IcoMoon, genere una fuente nueva y actualice juntos `style.css`, los archivos de `fonts/`, `selection.json` y la demo en el módulo del framework. Mantenga estables las clases y códigos de los iconos existentes para no romper vistas publicadas.

La demo y los archivos de IcoMoon quedan en el módulo como referencia. Las aplicaciones pueden crear sus propios iconos fuera del core; no necesitan modificar esta fuente salvo que quieran ampliar el catálogo común de GFrame.

Para una colección del proyecto, publique sus fuentes y CSS en `public/`, use otro nombre de fuente y otro prefijo de clases, y cargue ese CSS mediante metas. No sobrescriba los archivos de `vendors/internal/gframe-icons`, porque son archivos administrados por el actualizador.

## Comprobar la carga

Si aparecen cuadrados o caracteres incorrectos, compruebe en la pestaña Red del navegador que `style.css` y sus fuentes responden correctamente. Verifique también la clase del icono, el elemento `<i>` y las partes de los iconos multicolor. El catálogo utiliza la misma fuente y permite distinguir un problema de carga de un error en el HTML de la vista.
