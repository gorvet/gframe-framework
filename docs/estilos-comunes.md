# Variables, estilos comunes y botones

La base visual utiliza Bootstrap y tres hojas propias: `variables.css` define los valores del tema, `bootstrap-buttons-compat.css` conecta esos valores con los botones y `common.css` establece la presentación común de los elementos. El CSS de cada vista añade solo lo específico de esa pantalla.

## Responsabilidades

| Archivo | Qué contiene |
| --- | --- |
| `public/css/variables.css` | Paleta, tipografía, colores semánticos, superficies, bordes y valores para claro/oscuro |
| `public/css/bootstrap-buttons-compat.css` | Variables internas de botones Bootstrap enlazadas con la paleta del framework |
| `public/css/common.css` | Reglas compartidas de enlaces, secciones, formularios, selects, footer, tablas y otros elementos comunes |
| CSS de template, grupo o vista | Distribución y componentes propios de ese ámbito |

## Orden de carga

La meta global del esqueleto declara Bootstrap y SweetAlert2 originales antes de las hojas de GFrame. El orden de las tres hojas comunes es:

```php
<?php

return [
    'css' => [
        'public/css/variables.css',
        'public/css/bootstrap-buttons-compat.css',
        'public/css/common.css',
    ],
];
```

El bloque muestra el orden, no una meta global completa: conserva también las dependencias originales. No vuelvas a declarar estos recursos en todas las vistas. Las [metas](meta.md) acumulan después los recursos de template, grupo y vista.

## Variables del tema

Utiliza las variables disponibles según la intención del elemento:

| Intención | Variables habituales |
| --- | --- |
| Texto y superficie | `--bs-body-color`, `--bs-body-bg` |
| Marca y selección suave | `--bs-primary`, `--bs-primary-bg-subtle` |
| Bordes | `--bs-border-color`, `--bs-primary-border-subtle` |
| Estado de formulario | `--bs-form-control-bg`, `--bs-form-control-disabled-bg` |
| Feedback | `--bs-success`, `--bs-warning`, `--bs-danger` y sus fondos `*-bg-subtle` |
| Tipografía | `--bs-font-sans-serif`, `--bs-font-monospace`, `--bs-body-font-size` |

Por ejemplo, un componente de la vista puede utilizar:

```css
.catalog-summary {
  color: var(--bs-body-color);
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
}

.catalog-summary.is-selected {
  background: var(--bs-primary-bg-subtle);
}
```

La selección cambia el fondo, no añade otro borde que desplace el contenido. Reutiliza las variables semánticas para colores ya definidos; evita crear una variable nueva por cada navbar, tarjeta o pantalla. Una variable propia se justifica cuando representa una decisión del componente que todavía no cubre la base común.

## Claro y oscuro

El tema se identifica mediante `data-bs-theme` en el elemento raíz. `variables.css` define valores comunes y sobrescrituras para `light` y `dark`, además de `color-scheme`. En el panel, el controlador de tema existente modifica ese atributo y conserva la preferencia.

No crees un segundo atributo de tema para una vista o biblioteca. Los componentes que utilizan colores semánticos heredan el cambio; los [puentes de bibliotecas](paquetes-visuales.md) conectan los controles externos con esa misma fuente.

Comprueba texto, superficies, estados de foco, hover, selección y deshabilitado en ambos modos. Mantén dimensiones y grosor de borde constantes. Las variables no garantizan por sí solas contraste ni foco visible: revisa esos estados en el componente concreto.

## Puente de botones

Bootstrap define valores `--bs-btn-*` dentro de sus variantes. Cambiar únicamente `--bs-primary` no reemplaza todas esas declaraciones compiladas; `bootstrap-buttons-compat.css` vuelve a enlazar las variantes incluidas con la paleta y sus estados.

El puente cubre botones rellenos `primary`, `secondary`, `success`, `info`, `warning` y `danger`, además de `outline-primary` y `outline-danger`. No presupongas que todas las variantes Bootstrap reciben el mismo remapeo. `common.css` puede añadir reglas compartidas posteriores.

```html
<button type="submit" class="btn btn-primary">Guardar</button>
<button type="button" class="btn btn-outline-primary">Enviar ahora</button>
```

Selecciona la variante según la acción y las convenciones del proyecto. No añadas listeners para cambiar manualmente el color durante hover. Si necesitas una variante propia, define sus estados mediante CSS y las variables comunes, sin editar Bootstrap original.

Cambiar un color de marca no actualiza automáticamente todas las variables RGB ni todas las utilidades compiladas de Bootstrap. Revisa también las utilidades y los estados que use tu proyecto; consulta [Bootstrap](bootstrap.md) para la integración distribuida.

## Personalización del proyecto

Las hojas comunes del esqueleto son archivos administrados por el actualizador. Para ajustes propios, crea una hoja del proyecto y cárgala después de la base mediante la meta adecuada. Puedes sobrescribir valores existentes o reglas de un componente sin modificar los archivos de dependencias.

Si modificas directamente una hoja administrada, revisa `composer gframe:update -- --dry-run` y la opción `--preserve-custom` antes de actualizar. Consulta [Actualizaciones](actualizaciones.md) para la política de reemplazo. No copies todas las reglas de `common.css` a cada vista: limita los ajustes al ámbito que los necesita.
