# Panel administrativo

El módulo `admin-panel` proporciona el escritorio, la navegación lateral, la barra superior y el tema claro/oscuro para aplicaciones administradas. Los perfiles `managed`, `intranet` y `saas` lo incluyen; `static` no lo instala.

El módulo usa runtime: originales en `resources/modules/admin-panel/application/app/`, personalizaciones en `app/controllers/admin-panel/` y `app/views/admin-panel/`. El instalador crea esas carpetas sin copiar el controlador, las vistas ni las partes originales. Para personalizar el escritorio, herede `GFrame\Modules\AdminPanel\Controllers\AdminController` con namespace `App\Controllers\AdminPanel`. `adminIndex` sigue siendo el nombre explícito de vista. El template compartido `admin` se resuelve desde el módulo salvo que exista una personalización en `app/views/templates/adminTemplate.php`. Las rutas, CSS, JS y aportaciones de menú siguen publicándose.

## Implementación en GFrame

El módulo `admin-panel` instala la ruta `/admin`, un escritorio inicial, `adminTemplate.php`, `admin.meta.php`, `navbar.php`, `aside.php`, `menu.php`, CSS y JavaScript propios. Los perfiles `managed`, `intranet` y `saas` lo incluyen. `user-admin` lo requiere y usa `->template('admin')`. El perfil `static` no lo instala.

La plantilla mantiene la disposición actual: sidebar izquierdo, área principal y navbar en la parte superior de esa área. El footer sigue siendo independiente. La ruta `/admin` muestra el escritorio inicial; cada aplicación puede ampliar esa vista con sus datos propios.

El meta de la plantilla carga los recursos del panel incluso en vistas anidadas. La prioridad es global → plantilla → extensiones de módulos → grupo → vista. Los recursos repetidos se deduplican. El módulo `notifications` publica una acción de header y su meta de CSS/JS; si no se instala, el panel funciona sin ese control.

La barra lateral usa `gf-sidebar` para guardar el estado de escritorio. En móvil funciona como cajón temporal y no hereda el estado colapsado. El tema usa `gf-theme`, sigue el sistema cuando no hay elección explícita y sincroniza cambios entre pestañas. Un único script temprano aplica ambos valores antes de cargar los estilos.

## Uso y personalización

Declare una ruta protegida con `->template('admin')` y una vista propia de la aplicación. Por ejemplo:

```php
Route::get('admin/reportes', 'admin/reportes/ReportController@index')
    ->template('admin')
    ->view('reportIndex')
    ->middleware(['auth', 'can:reports.view'])
    ->registerFinal();
```

Cree `app/views/admin-panel/parts/menu.php` para personalizar las secciones y enlaces propios del proyecto; si no existe, se usa el original. La plantilla imprime ese archivo dentro de `#sidebar-nav`: primero «Escritorio», fuera de cualquier sección, y después el encabezado «Administración». Los módulos pueden publicar archivos en `app/views/admin-panel/parts/menu-items/`; cada archivo comprueba el permiso antes de mostrar su enlace. El módulo `user-admin` aporta «Gestión de usuarios» dentro de Administración. `aside.php` imprime «Mi cuenta» después de todas las aportaciones de módulos, siempre al final.

Biblioteca multimedia (`admin/media`) y Campañas (`admin/notifications/campaigns`) aportan enlaces al menú cuando el usuario tiene, respectivamente, `media.view` y `notifications.campaigns.view`. Notificaciones (`notifications`) aporta un enlace para usuarios autenticados y conserva la campana de la barra superior. Las tres pantallas usan la plantilla `admin`; sus URLs y permisos no cambian. Estos fragmentos se publican tanto al instalar como al actualizar los módulos.

Cree `app/views/admin-panel/parts/navbar.php` para personalizar el logo, el enlace de inicio o acciones propias; si no existe, se usa el original. Las acciones aportadas por módulos viven en `app/views/admin-panel/parts/header-actions/`. Los archivos meta de esas acciones se colocan en `app/views/templates/meta/admin/`. La identidad del usuario se lee de `$_SESSION['auth']` y se escapa antes de imprimirla.

Los estilos del proyecto van en su CSS administrativo; los de una vista, en su hoja específica. Regístrelos en los meta correspondientes. Las vistas son responsables de su contenido y no duplican el header ni el sidebar. El footer conserva las áreas opcionales de `content`, `copyright` y `credits` descritas en `docs/footer.md`.

Los botones de acciones de los listados usan `btn btn-outline-secondary btn-list-actions btn-sm`, añadiendo `dropdown-toggle` si despliegan un menú. La clase compartida de `admin.css` mantiene un fondo claro, contorno neutro y estados de interacción suaves; respeta el tema oscuro. Reutiliza esta clase en nuevos listados, sin botones secundarios de relleno oscuro ni estilos duplicados por vista.

## Tema Bootstrap y personalización

El selector único del tema es `data-bs-theme` en `<html>`. Bootstrap aporta los estilos de sus componentes y GFrame conserva el controlador propio: `gf-theme` en `localStorage`, preferencia del sistema, sincronización entre pestañas y aplicación temprana mediante `preload.js`. No se necesita otro selector `data-gf-theme` ni otro controlador de Bootstrap.

`public/css/variables.css` se carga después de Bootstrap y reúne la personalización de variables, incluidas las superficies oscuras. No se añade un archivo de tema separado. Usa `:root` para valores comunes y `:root[data-bs-theme="light"]` o `:root[data-bs-theme="dark"]` para diferencias por modo. La personalización específica de botones permanece en el archivo de botones del proyecto; no requiere otra capa de tema. No dupliques las reglas de componentes que Bootstrap ya resuelve. El esqueleto actual incluye los valores oscuros adaptados, no una copia completa de los estilos de los proyectos de origen.

```css
:root[data-bs-theme="light"] {
    --bs-body-bg: #ffffff;
    --bs-body-bg-rgb: 255, 255, 255;
}
:root[data-bs-theme="dark"] {
    --bs-body-bg: #051321;
    --bs-body-bg-rgb: 5, 19, 33;
}
```

Mantén sincronizadas las variables de color y sus variantes `-rgb`. Cambiar `--bs-primary` no redefine automáticamente `--bs-primary-rgb`, las variables locales `--bs-btn-*` de los botones ni los colores compilados de todos los componentes. Personaliza esos casos concretos en tu hoja o compila Bootstrap con Sass si necesitas reconstruir toda la paleta. Las variables propias, como sombras adicionales, siguen siendo válidas si nuestro CSS las consume.

API disponible tras cargar `admin.js`:

```js
GFTheme.get();                  // 'light' o 'dark'
GFTheme.set('dark');            // Aplica y guarda la elección.
GFTheme.set('light', false);    // Aplica sin cambiar la preferencia guardada.
GFTheme.resetToSystem();        // Borra la elección y sigue al sistema.
```

La persistencia es local al navegador, no un ajuste guardado en la cuenta. Si el almacenamiento está bloqueado, el cambio funciona en la página pero no se garantiza entre recargas. El panel sigue el sistema cuando no existe una elección guardada; el modo público no incorpora automáticamente este controlador.

### Visor de variables

El esqueleto incluye `public/css/colores.html` junto a `variables.css`. Permite consultar las variables globales, tipografía, sombras, bordes, radios y componentes de Bootstrap; alternar claro/oscuro; buscar por nombre o valor y copiar los valores. Ábrelo por HTTP desde una instalación con Bootstrap publicado.

El catálogo presenta los valores calculados de las variables globales; las variables locales de componentes no se enumeran en ese catálogo. Los componentes reales muestran su resultado visual. Al añadir variables globales en `variables.css`, el visor las detecta sin mantener una lista manual. No edita CSS ni modifica la preferencia de tema del panel. Los archivos de personalización de botones adicionales deben incluirse después de `variables.css` para que la muestra los refleje. Las variables `--ui-*` pertenecen únicamente al visor y se excluyen del catálogo.
