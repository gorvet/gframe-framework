# Footer por plantillas

El proyecto conserva `app/views/templates/footer.php` como contenedor general. Ahí se imprimen las áreas visibles y, después, se cargan los JavaScript y demás recursos del final de la página.

El espaciado del footer se define una sola vez en `public/css/common.css`, bajo `#footer.gframe-footer`, para home, Auth y errores. Ese CSS controla el padding del contenedor, copyright y créditos, evitando el padding vertical duplicado de los fragmentos. Las hojas de cada vista no repiten esos ajustes; pueden definir el fondo o el comportamiento de su layout.

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

En administración, `admin.css` alinea copyright y créditos a la izquierda, con el mismo padding que el contenido. El footer respeta la barra lateral: 260 px desplegada, 60 px contraída y sin desplazamiento en móvil. Estas reglas pertenecen a la plantilla administrativa; no alteran el centrado de las vistas públicas, Auth o errores.

El proyecto puede editar `app/views/templates/footer.php` para definir las etiquetas y clases de cada zona. Este archivo sigue siendo responsable de cargar los scripts del final de la página. El método `Render::renderFooterArea($area, $routeParams, $data)` devuelve el HTML de `content`, `copyright` o `credits`; devuelve una cadena vacía si el área no tiene plantilla. Dentro de cada parcial están disponibles `$routeParams` y `$data`.

Una instalación existente que use la antigua clave meta `credits` debe trasladar ese HTML a la plantilla `credits.php` y separar el copyright en `copyright.php` durante su migración completa. GFrame no actualiza automáticamente los proyectos existentes.

## Funciones compartidas

El contenedor conserva `id="footer"`, `footer-credits`, copyright y créditos, la carga ordenada de los JS declarados, el punto de montaje `#toastBox`, el aviso entre pestañas `session_expired_<scope>` y la carga opcional de Metricool. `site_url` e `is_protected` se declaran en el footer antes de cargar los scripts. Los scripts del header se reservan para precarga y no deben depender de esas variables. Heartbeat y sesión se publican mediante `heartbeat-client` y se declaran en `config/meta/global.meta.php`, después de sus dependencias. El footer solo imprime la lista recibida; no añade estos archivos por su cuenta.

El contenido público específico del proyecto se coloca en `content.php`. Los créditos configurados en metadatos antiguos deben trasladarse completos a las áreas del footer; el texto predeterminado del esqueleto no sustituye los créditos del proyecto.

La lista final de JS mantiene el orden declarado en los metadatos y elimina duplicados. Cada archivo se imprime una sola vez. El metadato global solo incluye los JS publicados por los módulos instalados; un proyecto estático sin `heartbeat-client` no solicita sus archivos.


## Seguridad y semántica

Los parciales del footer reciben datos de la ruta y del controlador, por lo que deben aplicar las mismas reglas de escape que una vista normal. Escape texto y atributos; valide destinos antes de imprimir enlaces dinámicos. No coloque secretos, tokens ni información de sesión dentro de atributos o scripts del footer.

Utilice `<footer>` para la región general y `<nav aria-label="...">` cuando el contenido sea navegación. Los créditos y copyright no deben convertirse en encabezados solo para conseguir tamaño visual; utilice CSS y estructura semántica apropiada.

## Recursos y orden de scripts

El footer imprime la lista final de JavaScript en el orden resultante de las metas. Si un script depende de otro, declare ambos en el orden correcto en la capa que corresponda; no dependa de que el footer «adivine» dependencias.

Los scripts globales deben vivir en meta global y los de una pantalla en su meta de vista o grupo. Evite añadir un script directamente a `footer.php` solo porque varias páginas lo utilizan: eso impide que perfiles o módulos sin esa capacidad mantengan una salida mínima.

## Personalizaciones y actualizaciones

Una personalización de `footer.php` pertenece al proyecto y no recibe automáticamente mejoras de la plantilla original. Después de actualizar GFrame, compare responsabilidades nuevas del footer base —variables, puntos de montaje o recursos— antes de conservar una copia antigua sin cambios.

Los parciales `content`, `copyright` y `credits` son una alternativa más estable cuando solo necesita cambiar contenido. Prefiera personalizar el contenedor completo únicamente cuando cambie su estructura.

## Diagnóstico

Si un área no aparece, compruebe el nombre exacto del grupo, vista y sufijo `.footer.<area>.php`, y recuerde que la resolución utiliza el primer archivo existente. Si un JavaScript no encuentra `site_url`, confirme que se carga al final y no en `hjs`. Si una personalización rompe toast o sesión, compare su `footer.php` con la versión actual del esqueleto y restaure los puntos de montaje y variables compartidas que todavía necesite.
