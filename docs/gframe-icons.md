# Iconos de GFrame

`gframe-icons` es la fuente de iconos propia del framework. Se instala con la base visual y se publica en `public/vendors/internal/gframe-icons/`. Para usarla, declare `style.css` en el meta de la vista o del grupo de vistas. Las fuentes se cargan mediante rutas relativas al CSS.

Un icono se representa con un elemento `<i>` cuya clase comienza por `gicon-`:

```html
<i class="gicon-user" aria-hidden="true"></i>
```

La fuente solo se aplica a elementos `<i>` con alguna clase `gicon-*`; no cambia el aspecto de los demás elementos `<i>` ni de otros sistemas de iconos. Si el icono es puramente decorativo, use `aria-hidden="true"`. Si un botón solo contiene un icono, dé al botón un nombre accesible con texto visible o `aria-label`.

## Elegir y ampliar iconos

Abra `demo.html` para consultar el catálogo visual y los nombres de clase disponibles. `selection.json` conserva la selección de IcoMoon y permite editar o ampliar la fuente. Para añadir un icono, importe esa selección, genere una fuente nueva y actualice juntos `style.css`, los archivos de `fonts/`, `selection.json` y la demo. Mantenga estables las clases y códigos de los iconos existentes para no romper vistas publicadas.

La demo y los archivos de IcoMoon quedan en el módulo como referencia. Las aplicaciones pueden crear sus propios iconos fuera del core; no necesitan modificar esta fuente salvo que quieran ampliar el catálogo común de GFrame.
