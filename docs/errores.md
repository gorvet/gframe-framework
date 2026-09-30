# Gestión de errores

GFrame separa la gestión de errores en dos partes:

- el núcleo convierte fallos, excepciones y respuestas de error en contratos estables;
- el módulo predeterminado `error-pages` proporciona las vistas web, la plantilla y los estilos que recibe cada aplicación.

El módulo se instala automáticamente en todos los perfiles. No requiere rutas ni un controlador propio.

## Errores web disponibles

| Código o alias | Vista | Estado HTTP |
| --- | --- | ---: |
| `permission` | `errorPermissions.php` | 403 |
| `403`, `forbidden` | `error403.php` | 403 |
| `404`, `not_found`, `badController`, `badMethod` | `error404.php` | 404 |
| `500`, `internal_error` | `error500.php` | 500 |
| `503`, `service_unavailable`, errores de conexión conocidos | `error503.php` | 503 |

Las vistas se publican en `app/views/error/`, la plantilla en `app/views/templates/errorTemplate.php` y los estilos en `public/css/modules/error-pages/error-pages.css`.

## Devolver un error desde un controlador

Un controlador puede devolver el contrato estándar:

```php
return [
    'status' => 'error',
    'code' => 'permission',
    'message' => 'No puedes modificar este registro.',
    'helpMsg' => 'Solicita acceso al administrador.',
    'helpUrl' => site_url . '/ayuda/permisos',
    'helpLabel' => 'Consultar ayuda',
];
```

En una ruta web, `Render` transforma la respuesta en la página correspondiente. El mensaje técnico solo se muestra cuando el modo de depuración está activo. Los campos de ayuda pueden mostrarse también en producción.

Los contratos admiten:

- `status`: `error` o `unauthorized`;
- `code`: alias reconocido por `ErrorResponder`;
- `message`: detalle del error;
- `helpMsg`: explicación adicional opcional;
- `helpUrl` y `helpLabel`: enlace de ayuda opcional;
- `helpEnabled`: permite ocultar explícitamente la ayuda;
- `http_code`: estado HTTP explícito para API o webhook.

## Generar parámetros de error

Cuando una integración necesita construir la respuesta web sin ejecutar una acción de controlador:

```php
$routeParams = (new ErrorResponder())->buildRouteParams('not_found', [
    'tolink' => site_url . '/admin',
    'infoMsg' => 'El registro solicitado no existe.',
    'helpMsg' => 'Comprueba que el enlace sea correcto.',
]);
```

El resultado está preparado para el flujo de renderizado e incluye plantilla, vista, código HTTP, contexto y `skipAction`.

## Canales no web

El framework adapta automáticamente los errores al transporte:

- AJAX y sistema devuelven JSON con estado HTTP 200 para conservar el contrato histórico del frontend;
- API devuelve JSON y un estado HTTP apropiado;
- webhook devuelve texto plano;
- SSE emite un evento `error`.

Fuera del modo de depuración, las excepciones internas no exponen mensajes, rutas de archivos ni trazas.

## Personalización

Los archivos publicados pertenecen a la aplicación y pueden personalizarse sin modificar el núcleo:

- edita `app/views/error/_errorCard.php` para cambiar la estructura compartida;
- edita una vista concreta para cambiar textos o acciones por código;
- edita `app/views/templates/errorTemplate.php` para cambiar la envoltura visual;
- edita `app/views/error/error.group.meta.php` para cambiar metadatos y recursos;
- edita `public/css/modules/error-pages/error-pages.css` para adaptar el diseño.

La publicación de módulos conserva archivos existentes salvo que se solicite sobrescribirlos. Por eso, una actualización del framework no debe reemplazar automáticamente las personalizaciones de la aplicación.

## Extender el módulo

Para añadir información reutilizable a una página existente, incorpora opciones al contrato y consúmelas desde la vista de la aplicación. La lógica específica del negocio debe permanecer en la aplicación.

Si se necesita una categoría de error nueva con su propio código HTTP y vista, debe añadirse como una capacidad genérica de `ErrorResponder`, acompañada por:

1. el alias y el estado HTTP;
2. la resolución hacia una vista web;
3. la vista publicada por `error-pages`;
4. pruebas del contrato y del renderizado;
5. documentación del nuevo código.

No es necesario crear un `ErrorController` vacío. `ErrorResponder` genera una ruta interna con `skipAction`, y `Render` carga directamente la vista correspondiente.

## Depuración y producción

Con el modo de depuración activo, las excepciones web muestran una página técnica con tipo, mensaje, archivo, línea y traza. En producción se muestra la página 500 y se ocultan los detalles internos.

Las páginas de error declaran `noindex, nofollow` para impedir que los buscadores las indexen.

## Verificación

Las pruebas del módulo cubren:

- alias, vistas y estados HTTP;
- enlaces de retorno internos y bloqueo de referentes externos;
- mensajes y enlaces de ayuda;
- escape de contenido en las vistas;
- respuestas AJAX, API, webhook, SSE y sistema;
- publicación de todas las vistas, la plantilla y los estilos.

```bash
php packages/bin/phpunit --filter ErrorPagesTest
php packages/bin/phpunit --filter ErrorPagesPublish
```
