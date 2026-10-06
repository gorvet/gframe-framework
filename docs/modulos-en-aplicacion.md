# Usar módulos en una aplicación GFrame

En GFrame la palabra «módulo» puede referirse a cosas distintas. Esta guía empieza por esa diferencia y después explica qué debe hacer una aplicación.

## Cuatro conceptos que no son equivalentes

### 1. Capacidad instalable

Ejemplos:

```text
auth-ui
media-library
notifications
cron-runner
wordpress-headless
```

Tiene un manifiesto `module.php`, dependencias y, según el caso, assets, rutas, esquema, migraciones o runtime MVC.

### 2. Módulo runtime MVC

Conserva controller/model/service/view originales dentro del paquete y permite que el proyecto añada personalizaciones en `app/`.

Ejemplos: `auth-ui`, `self-account`, `media-library`, `notifications`.

### 3. Componente o librería publicable

Puede ser simplemente frontend y dependencias, sin MVC ni base de datos.

Ejemplo: `alerts` declara dependencias y assets, pero no un runtime MVC.

### 4. Funcionalidad propia del proyecto

Una carpeta como:

```text
app/controllers/productos
app/services/productos
app/models/productos
app/views/productos
```

**no necesita convertirse en módulo runtime** para estar bien organizada.

Convierte una funcionalidad en módulo solo cuando realmente necesita formar parte del catálogo reutilizable del framework o distribuir originales administrados con su propio contrato.

## El manifiesto explica qué instala un módulo

Cada módulo del framework vive en:

```text
resources/modules/<nombre>/module.php
```

El catálogo busca esos manifiestos y valida nombres tipo:

```text
auth-ui
media-library
gf-table
```

Los nombres usan minúsculas, números y guiones.

Ejemplo simplificado de un módulo funcional:

```php
return [
    'name' => 'auth-ui',
    'type' => 'backend-module',
    'dependencies' => [
        'alerts',
        'frontend-core',
        'heartbeat-client',
        'password-utils',
    ],
    'runtime' => [
        'root' => 'application/app',
        'namespace' => 'GFrame\\Modules\\AuthUi',
        'templates' => ['auth'],
    ],
    'assets' => [
        // recursos publicables
    ],
    'application' => [
        // rutas u otros archivos que deben copiarse al proyecto
    ],
];
```

Ejemplo de componente frontend:

```php
return [
    'name' => 'alerts',
    'type' => 'internal-ui',
    'default' => true,
    'dependencies' => [
        'bootstrap',
        'jquery',
        'sweetalert2',
        'gframe-icons',
    ],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/internal/gframe-alerts'],
    ],
];
```

La presencia de `module.php` no implica que tenga controllers o tablas.

## Dependencias

`ModuleCatalog` resuelve dependencias antes del módulo solicitado.

Conceptualmente:

```text
mi módulo
  ├─ dependencia A
  │   └─ dependencia C
  └─ dependencia B
```

se convierte en un orden donde C y A se resuelven antes que el módulo que las necesita.

El catálogo también detecta dependencias circulares y módulos inexistentes.

No mantengas manualmente una segunda lista paralela de dependencias dentro del proyecto.

## Módulos predeterminados

Un manifiesto puede declarar:

```php
'default' => true
```

Eso permite que el catálogo forme la base predeterminada que se publica automáticamente en los flujos correspondientes.

En la documentación actual, la base visual incluye capacidades como Bootstrap, jQuery, SweetAlert2, alertas, frontend-core, iconos y otros componentes definidos por el instalador/catálogo.

Consulta [Catálogo y dependencias](modulos-opcionales.md) para la lista actual.

## Instalar un módulo al crear el proyecto

El instalador resuelve los módulos correspondientes al perfil y las opciones elegidas.

Un módulo puede aportar:

- dependencias;
- esquemas MySQL/SQLite;
- migraciones;
- assets públicos;
- rutas;
- otros archivos de aplicación;
- runtime MVC original.

No todos los módulos aportan todas esas piezas.

Consulta [Instalación y perfiles](instalacion.md).

## Añadir un módulo a un proyecto ya instalado

No basta con publicar assets.

La vía documentada es actualizar la **lista completa** de módulos registrados:

```bash
composer gframe:update -- --modules=LISTA_COMPLETA --dry-run
composer gframe:update -- --modules=LISTA_COMPLETA
```

`LISTA_COMPLETA` contiene los módulos ya registrados más los nuevos.

Ese proceso puede:

- resolver dependencias;
- publicar archivos;
- crear carpetas de personalización;
- ejecutar migraciones;
- actualizar `storage/gframe-installed.json`.

`--no-database` evita las migraciones y, por tanto, puede dejar incompleto un módulo funcional que necesite tablas.

Quitar un nombre de `--modules` no constituye una desinstalación destructiva automática.

Consulta [Actualizaciones](actualizaciones.md).

## `bin/modules.php publish` no equivale a instalar una capacidad funcional

La publicación de módulos/recursos sirve para copiar assets declarados.

Pero una capacidad funcional puede necesitar además:

```text
registro de instalación
+ esquema/migraciones
+ rutas
+ archivos de aplicación
+ dependencias
```

Por eso, en un proyecto instalado utiliza el actualizador para añadir módulos funcionales.

Consulta [Catálogo y dependencias](modulos-opcionales.md).

## Qué significa runtime

Un módulo runtime declara algo parecido a:

```php
'runtime' => [
    'root' => 'application/app',
    'namespace' => 'GFrame\\Modules\\MediaLibrary',
]
```

Eso indica que sus originales MVC viven dentro del paquete.

La aplicación puede permanecer vacía hasta que necesite personalizar algo.

Ejemplo conceptual:

```text
packages/gorvet/gframe/resources/modules/media-library/
  application/app/
    controllers/...
    views/...

mi-proyecto/
  app/controllers/media-library/   ← solo personalizaciones
  app/views/media-library/         ← solo personalizaciones
```

El runtime busca primero la personalización del proyecto y después el original del módulo.

## Asociar una ruta al módulo

Ejemplo:

```php
Route::get('media', 'media-library/MediaController@index')
    ->module('media-library')
    ->middleware(['auth'])
    ->registerFinal();
```

`module()` conserva la procedencia necesaria para resolver controller, views, templates y otros recursos del runtime según el contrato actual.

Cuando la carpeta del controlador coincide con un módulo runtime instalado, existe inferencia en algunos casos, pero para rutas de módulo es preferible que la procedencia quede clara cuando la relación no sea obvia.

Consulta [Módulos runtime](modulos-runtime.md).

## Personalizar una vista

Si el módulo tiene una vista original:

```text
resources/modules/self-account/application/app/views/self-account/self-accountIndex.php
```

puedes crear:

```text
app/views/self-account/self-accountIndex.php
```

La aplicación tendrá prioridad.

Si no existe personalización, se utiliza el original del paquete, lo que permite recibir mejoras del módulo al actualizar GFrame.

Por esa razón, **no copies todas las vistas de un módulo al instalarlo** solo para tenerlas visibles en el proyecto.

## Personalizar un controller

El original puede ser:

```php
GFrame\Modules\SelfAccount\Controllers\SelfAccountController
```

La personalización del proyecto:

```text
app/controllers/self-account/SelfAccountController.php
```

usa el namespace correspondiente:

```php
<?php
namespace App\Controllers\SelfAccount;

class SelfAccountController extends
    \GFrame\Modules\SelfAccount\Controllers\SelfAccountController
{
    public function index(): array
    {
        $response = parent::index();

        // Personalización propia.

        return $response;
    }
}
```

En personalizaciones de módulo, el autoload y namespace siguen las reglas de `ModuleRuntime`, no el mecanismo ordinario por basename de una clase propia de la aplicación.

## Una subclase no reemplaza automáticamente todas las instancias

Este punto es importante.

Crear:

```php
class ProjectNotificationService extends NotificationService
```

no hace que cada:

```php
new NotificationService(...)
```

se convierta mágicamente en `ProjectNotificationService`.

Cuando una clase original construye una dependencia concreta, la personalización debe inyectar explícitamente su implementación cuando el contrato lo permita.

Herencia no equivale a contenedor de dependencias.

Consulta [Módulos runtime](modulos-runtime.md) y las guías específicas de cada módulo.

## `isInstalled()` y `has()` no preguntan exactamente lo mismo

Según el runtime actual:

- `ModuleRuntime::isInstalled('nombre')` comprueba el registro de módulos instalados, incluidos módulos que no tengan MVC runtime;
- `ModuleRuntime::has('nombre')` se utiliza para comprobar un módulo runtime activo.

Esto importa para componentes como `notifications-email`, que pueden estar instalados sin aportar controllers/views runtime propios.

## Assets administrados

Los módulos pueden declarar assets con `source` y `target`.

`ModuleAssetPublisher`:

- resuelve las dependencias;
- valida que el origen permanezca dentro del módulo;
- evita path traversal en destinos;
- crea carpetas necesarias;
- conserva archivos existentes salvo que se solicite overwrite.

En un proyecto instalado, los archivos publicados administrados pueden ser reemplazados por `composer gframe:update` según su política.

No conviertas un asset administrado por GFrame en tu lugar principal de personalización sin revisar [Actualizaciones](actualizaciones.md).

## Rutas y archivos de aplicación publicados

Un módulo puede declarar `application` para archivos que sí deben existir físicamente en el proyecto, por ejemplo:

```text
config/routes/routes_auth.php
app/views/admin-panel/parts/menu-items/media.php
```

Eso es distinto de los controllers/views runtime originales, que pueden permanecer dentro del paquete.

La regla práctica es:

```text
original runtime → permanece en el paquete
personalización runtime → vive en app/
ruta o integración que el proyecto debe cargar → puede publicarse
asset web → se publica en public/
```

## Crear una funcionalidad del proyecto vs crear un módulo del framework

Para una funcionalidad normal de negocio empieza con:

```text
app/controllers/catalogo
app/services/catalogo
app/models/catalogo
app/views/catalogo
```

No necesitas `resources/modules/catalogo/module.php`.

Crea un módulo del framework cuando quieras una capacidad reutilizable que necesite alguna combinación de:

- manifiesto y dependencias;
- instalación por perfiles/catálogo;
- esquema/migraciones administradas;
- assets publicables;
- originales runtime actualizables;
- integración reusable entre proyectos.

Ese cambio de categoría implica mantener también su contrato de actualización y documentación.

## Tipos de módulo observables en el catálogo

En la práctica conviene pensar en categorías de uso:

```text
runtime backend
  auth-ui, media-library, notifications...

capacidad backend sin necesidad de MVC propio
  cron-runner, notifications-email...

componente frontend interno
  alerts, frontend-core, gf-table...

biblioteca de terceros empaquetada
  bootstrap, jquery, swiper...
```

El campo `type` del manifiesto aporta clasificación, pero la arquitectura real se entiende mirando qué declara: `runtime`, `schemas`, `migrations`, `assets`, `application` y dependencias.

## Flujo de decisión

```text
¿Solo estoy creando una funcionalidad para esta aplicación?
  sí → app/controllers + services + models + views
  no ↓

¿Quiero reutilizar una capacidad administrada por GFrame entre proyectos?
  sí → evaluar módulo del catálogo

¿Solo necesito personalizar un módulo existente?
  → no lo copies entero
  → crea únicamente el override necesario en app/
```

## Referencia

- [Catálogo y dependencias](modulos-opcionales.md)
- [Módulos runtime](modulos-runtime.md)
- [Extensión por herencia y contratos](extensibilidad.md)
- [Actualizaciones](actualizaciones.md)
- [Arquitectura](arquitectura.md)
