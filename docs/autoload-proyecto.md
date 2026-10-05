# Autoload del proyecto y de módulos

GFrame utiliza **dos mecanismos distintos** para cargar clases de una aplicación:

1. el autoload ordinario de `app/`, registrado por `Bootstrap`;
2. el autoload de `ModuleRuntime` para originales y personalizaciones de módulos runtime.

No conviene mezclarlos como si fueran un único PSR-4 `App\`.

## 1. Composer carga el framework

El paquete `gorvet/gframe` declara:

```text
GFrame\ → src/GFrame/
```

mediante PSR-4.

También carga por classmap las clases globales históricas de áreas como:

```text
src/routing/
src/render/
src/database/
src/middleware/
src/async/
src/cron/
src/services/
src/utils/
```

Por eso una aplicación puede utilizar clases globales como:

```php
RouteBuilder
ORM
HttpClient
Async
UrlHelper
```

sin hacer `require` manual del archivo correspondiente.

## 2. El proyecto generado no declara PSR-4 para `App\`

El `composer.json` del esqueleto instala `gorvet/gframe`, pero no define actualmente:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

La carga ordinaria de clases propias se resuelve en `GFrame\Foundation\Bootstrap`.

## 3. Cómo funciona el autoload ordinario de `app/`

Al arrancar, `Bootstrap` recorre `app/` recursivamente y construye un mapa utilizando **el nombre del archivo PHP sin extensión**.

Ejemplo:

```text
app/controllers/productos/ProductController.php
```

registra el basename:

```text
ProductController
```

Por eso el patrón ordinario actual de una clase propia es:

```php
<?php

final class ProductController
{
}
```

Lo mismo puede aplicarse por convención a:

```text
app/models/productos/ProductModel.php
app/services/productos/ProductService.php
```

con clases globales:

```php
ProductModel
ProductService
```

## 4. La carpeta organiza; el basename identifica la clase ordinaria

Para el autoload ordinario actual:

```text
app/services/catalogo/ProductService.php
```

y:

```text
app/services/ventas/ProductService.php
```

compiten por el mismo basename:

```text
ProductService
```

Evita nombres de clase/archivo duplicados dentro de `app/` cuando dependan de este autoload.

La subcarpeta ayuda a organizar el proyecto, pero **no se convierte automáticamente en namespace** para el autoload ordinario.

Eso explica por qué la estructura recomendada puede ser:

```text
app/controllers/productos/
app/services/productos/
app/models/productos/
app/views/productos/
```

sin exigir namespaces `App\...` a cada clase propia.

## 5. Los módulos runtime sí tienen namespaces explícitos

Un módulo runtime declara en su manifiesto algo como:

```php
'runtime' => [
    'root' => 'application/app',
    'namespace' => 'GFrame\\Modules\\SelfAccount',
]
```

El original puede declarar:

```php
namespace GFrame\Modules\SelfAccount\Controllers;

class SelfAccountController
{
}
```

Ese namespace se resuelve mediante `ModuleRuntime`.

## 6. Personalizaciones de módulos: `App\...`

Para una personalización de runtime, GFrame sí define una convención namespaced específica.

Archivo:

```text
app/controllers/self-account/SelfAccountController.php
```

Clase:

```php
<?php
namespace App\Controllers\SelfAccount;

class SelfAccountController extends
    \GFrame\Modules\SelfAccount\Controllers\SelfAccountController
{
}
```

Para models y services se aplican equivalentes:

```text
App\Models\SelfAccount\...
App\Services\SelfAccount\...
```

`self-account` se transforma a `SelfAccount` para el namespace.

## 7. Por qué esa clase namespaced sí carga

`ModuleRuntime::autoload()` reconoce dos familias:

```text
<namespace nativo del módulo>\Controllers|Models|Services\...
```

 y:

```text
App\Controllers|Models|Services\<ModuloStudly>\...
```

Por eso una personalización namespaced de módulo no depende del classmap ordinario por basename.

## 8. Resolución de controller de módulo

Cuando una ruta pertenece a un módulo runtime, `ModuleRuntime::controller()`:

1. busca primero el archivo correspondiente en `app/controllers`;
2. si no existe, busca el original del módulo;
3. si el archivo es del proyecto, intenta la clase namespaced `App\Controllers\...`;
4. mantiene fallback al nombre global del controller del proyecto por compatibilidad;
5. para el original exige la clase derivada del namespace declarado por el módulo.

Eso permite migrar personalizaciones antiguas sin convertir el autoload ordinario completo en PSR-4.

## 9. Vistas no usan autoload de clases

Las vistas se resuelven como archivos.

Para una ruta de módulo:

```text
app/views/...                 ← prioridad
resources/modules/.../views  ← fallback
```

`ModuleRuntime::file()` admite actualmente:

```text
controllers
models
services
views
```

Los templates tienen además `ModuleRuntime::template()` y pueden ser aportados por módulos que los declaren.

Consulta [Módulos runtime](modulos-runtime.md).

## 10. Clases propias vs personalización de módulo

### Funcionalidad propia

```text
app/controllers/productos/ProductController.php
```

```php
final class ProductController
{
}
```

### Override de módulo

```text
app/controllers/notifications/NotificationController.php
```

```php
namespace App\Controllers\Notifications;

class NotificationController extends
    \GFrame\Modules\Notifications\Controllers\NotificationController
{
}
```

Son dos contratos distintos.

## 11. ¿Puedo añadir mi propio PSR-4 al proyecto?

Composer permite que una aplicación declare su propio autoload, pero eso sería una decisión explícita del proyecto y exige regenerar su autoloader.

No lo presupongas al escribir documentación o módulos de GFrame: **el proyecto generado actualmente no trae un PSR-4 general para `App\`.**

Si GFrame adopta oficialmente ese modelo en una versión futura, debe hacerse como un cambio deliberado del esqueleto, la documentación, los tests y las reglas de compatibilidad.

## 12. Regla práctica

```text
¿Es una clase normal propia de la aplicación?
  → clase global con basename único bajo app/

¿Es una personalización de un módulo runtime?
  → namespace App\Controllers|Models|Services\<ModuloStudly>

¿Es una clase original del módulo?
  → namespace GFrame\Modules\... declarado por su manifiesto

¿Es una clase del core moderno?
  → namespace GFrame\...

¿Es una clase histórica classmapped del core?
  → clase global
```

## Referencia

- [Arquitectura](arquitectura.md)
- [Desarrollar una aplicación con GFrame](guia-desarrollo.md)
- [Usar módulos en una aplicación](modulos-en-aplicacion.md)
- [Módulos runtime](modulos-runtime.md)
