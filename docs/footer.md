# Footer por plantillas

El proyecto conserva `app/views/templates/footer.php` como contenedor general. Ahí se imprimen las áreas visibles y, después, se cargan los JavaScript y demás recursos del final de la página.

El esqueleto de GFrame incluye dos plantillas iniciales:

```text
app/views/templates/footer/
├── copyright.php
└── credits.php
```

Para añadir menús, contacto u otro contenido, cree `app/views/templates/footer/content.php`. El área es opcional. Puede utilizarse tanto en páginas públicas como administrativas; cada aplicación decide si corresponde mostrarla y qué contiene.

Las tres áreas son independientes:

- `content`: menús, contacto, enlaces o acciones globales.
- `copyright`: titularidad y año.
- `credits`: autoría, desarrollo u otros reconocimientos.

El contenido se escribe como PHP/HTML normal en esos archivos. No se declara HTML en los meta de la vista. Los estilos y scripts siguen registrándose en `css`, `js` o `hjs`, según corresponda.

## Personalización por grupo o vista

Para cada área, GFrame usa el primer archivo que encuentre en este orden:

1. `app/views/<grupo>/<vista>.footer.<area>.php`.
2. `app/views/<grupo>/<grupo>.footer.<area>.php`.
3. `app/views/templates/footer/<area>.php`.

Por ejemplo, `app/views/home/home.footer.content.php` establece el contenido del footer de todas las vistas de `home`, mientras que `app/views/home/homeIndex.footer.content.php` lo cambia solo para `homeIndex`. Las otras áreas siguen resolviéndose por separado. Si no hay archivo para un área, no se imprime.

En proyectos con rutas de vistas anidadas, `<grupo>` corresponde al último segmento de la carpeta de la vista. Por ejemplo, `app/views/admin/users/users.footer.content.php`.

## Personalización del contenedor

El proyecto puede editar `app/views/templates/footer.php` para definir las etiquetas y clases de cada zona. Este archivo sigue siendo responsable de cargar los scripts del final de la página. El método `Render::renderFooterArea($area, $routeParams, $data)` devuelve el HTML de `content`, `copyright` o `credits`; devuelve una cadena vacía si el área no tiene plantilla. Dentro de cada parcial están disponibles `$routeParams` y `$data`.

Una instalación existente que use la antigua clave meta `credits` debe trasladar ese HTML a la plantilla `credits.php` y separar el copyright en `copyright.php` durante su migración completa. GFrame no actualiza automáticamente los proyectos existentes.
