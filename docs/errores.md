# Gestión de errores

GFrame separa la gestión de errores en dos partes:

- el núcleo convierte fallos, excepciones y respuestas de error en contratos estables;
- el módulo predeterminado `error-pages` proporciona las vistas web, la plantilla y los estilos que recibe cada aplicación.

El módulo se instala automáticamente en todos los perfiles. No requiere rutas ni un controlador propio.

El router acepta también errores 403/404/500/503 originados por el servidor: `GFRAME_SERVER_ERROR` de FastCGI en Nginx y `REDIRECT_STATUS` en Apache. No interpreta `error_code` de la URL ni cabeceras HTTP como códigos de error del servidor. Las peticiones web usan estas mismas vistas; los otros canales conservan su contrato. Véase [configuración de servidores web](servidores-web.md).

## Errores web disponibles

| Código o alias | Vista | Estado HTTP |
| --- | --- | ---: |
| `permission` | `errorPermissions.php` | 403 |
| `403`, `forbidden` | `error403.php` | 403 |
| `404`, `not_found`, `badController`, `badMethod` | `error404.php` | 404 |
| `500`, `internal_error` | `error500.php` | 500 |
| `503`, `service_unavailable`, errores de conexión conocidos | `error503.php` | 503 |

Las vistas y la plantilla originales permanecen en `resources/modules/error-pages/application/app/views/`. El instalador crea `app/views/error-pages/` para personalizaciones, sin copiar originales. El template puede personalizarse en `app/views/templates/errorTemplate.php`; los estilos se publican en `public/css/404/404.css`. La marca utiliza el mismo archivo `public/img/logo.png` y tamaño que Auth.

El CSS específico depende de Bootstrap y de los estilos comunes cargados por `config/meta/global.meta.php`. `composer gframe:update` publica también ese metadato, el header/footer y los CSS compartidos del esqueleto, aplicando la misma política de archivos administrados y `--preserve-custom` que a los módulos. `composer update` por sí solo actualiza el paquete, no los archivos publicados de la aplicación.

Las páginas de error utilizan el mismo fondo que el login, `var(--bs-gray-100)`, sin imagen. El footer comparte ese fondo y la marca conserva sus colores originales.

La 404 predeterminada muestra el código, «Página no encontrada», «La dirección puede ser incorrecta o el contenido ya no está disponible.» y el botón de retorno. Los errores 404/500 no inventan un enlace de contacto: los textos y enlaces de ayuda solo aparecen cuando se proporcionan mediante el contrato. El espaciado del footer reside en `common.css` y se comparte con home y Auth.

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
- `http_code`: estado HTTP explícito para API; en webhook fija el estado mediante `http_response_code()` en la acción cuando corresponda.

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

Las vistas personalizadas pertenecen a la aplicación y se resuelven antes que los originales runtime, sin modificar el núcleo:

- crea `app/views/error-pages/_errorCard.php` para personalizar la estructura compartida;
- crea una vista concreta en `app/views/error-pages/` para cambiar textos o acciones por código;
- crea `app/views/templates/errorTemplate.php` para cambiar la envoltura visual;
- crea `app/views/error-pages/error-pages.group.meta.php` para personalizar metadatos y recursos;
- edita `public/css/404/404.css` para adaptar el diseño.

Las vistas runtime originales no se copian durante la instalación; las personalizaciones en `app/views/` se conservan. El CSS publicado es un archivo gestionado: revisa `composer gframe:update -- --dry-run` y utiliza `--preserve-custom` si debes conservar modificaciones directas. Preferiblemente añade tus ajustes en un CSS del proyecto referenciado por la meta personalizada.

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

La política SEO del núcleo bloquea la indexación de las páginas de error por su código HTTP; no necesitas repetir un interruptor de indexación en cada meta.

## Errores de negocio y feedback

Para una validación o una operación rechazada devuelve un código estable y un mensaje público. En AJAX, el frontend interpreta el contrato y muestra `swalAlert` o `alertToast` según la interacción; las [Alertas](alerts.md) no son el módulo de páginas HTTP ni la bandeja de notificaciones.

No uses un error 500 para representar todo rechazo funcional. En API declara un `http_code` apropiado cuando el código de negocio no tenga equivalencia en ErrorResponder. Un código desconocido no crea automáticamente una nueva vista: el resolver utiliza su categoría de error predeterminada.

`ErrorHandler` registra el tratamiento global de excepciones y fallos fatales durante el arranque. No convierte todos los warnings y notices en excepciones; en producción configura PHP para registrar errores sin imprimirlos en respuestas HTML o JSON. Un fallo antes del bootstrap o una respuesta servida directamente por Nginx/Apache requiere la configuración del servidor, no una ruta del controlador.

## Verificación

Las pruebas del módulo cubren:

- alias, vistas y estados HTTP;
- enlaces de retorno internos y bloqueo de referentes externos;
- mensajes y enlaces de ayuda;
- escape de contenido en las vistas;
- respuestas AJAX, API, webhook, SSE y sistema;
- resolución de las vistas y la plantilla originales, y publicación de sus estilos.

```bash
php packages/bin/phpunit --filter ErrorPagesTest
php packages/bin/phpunit --filter ErrorPagesPublish
```
