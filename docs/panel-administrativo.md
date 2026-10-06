# Panel administrativo

`admin-panel` proporciona la plantilla `admin`, el escritorio `/admin`, la barra superior, el menú lateral y el cambio de tema. Se incluye en los perfiles `managed`, `intranet` y `saas`.

## Crear una pantalla administrativa

Añade una ruta a `config/routes/routes_web.php`:

```php
Route::get('admin/reportes', 'reportes/ReportController@index')
    ->template('admin')
    ->view('reportIndex')
    ->middleware(['auth', 'can:reports.view'])
    ->registerFinal();
```

Crea el controlador `app/controllers/reportes/ReportController.php` y la vista `app/views/reportes/reportIndex.php`. Por ejemplo:

```php
<div class="col-12">
    <div class="pagetitle"><h1>Reportes</h1></div>
    <div class="card">
        <div class="card-body pt-3">Contenido del reporte.</div>
    </div>
</div>
```

La plantilla ya contiene header, sidebar y contenedor principal. No los repitas en la vista. Concede `reports.view` a los roles autorizados, según [roles y permisos](permisos.md).

Los recursos específicos se declaran en `reportIndex.meta.php`. La plantilla carga sus propios CSS y JS automáticamente. Consulta [metadatos](meta.md).

## Personalizar el escritorio

Crea `app/controllers/admin-panel/AdminController.php`:

```php
<?php
namespace App\Controllers\AdminPanel;

class AdminController extends \GFrame\Modules\AdminPanel\Controllers\AdminController
{
    public function index(): array
    {
        return ['data' => ['welcome' => 'Bienvenido al panel']];
    }
}
```

Crea `app/views/admin-panel/adminIndex.php` para mostrar esos datos:

```php
<div class="col-12">
    <div class="pagetitle"><h1>Escritorio</h1></div>
    <p><?= htmlspecialchars($data['data']['welcome'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</div>
```

El arreglo devuelto por la acción llega completo a `$data`; por eso el ejemplo accede a `$data['data']['welcome']`. El nombre explícito de la vista es `adminIndex`. Las personalizaciones tienen prioridad sobre los originales del módulo. Consulta [estructura runtime](modulos-runtime.md).

## Añadir enlaces al menú

Crea un fragmento propio, por ejemplo `app/views/admin-panel/parts/menu-items/reportes.php`:

```php
<?php
$actorID = (int)($_SESSION['auth']['id'] ?? 0);
$access = (new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel()))
    ->authorize($actorID, 'reports.view');
if (($access['status'] ?? '') !== 'success') return;
$url = rtrim((string)site_url, '/') . '/admin/reportes';
?>
<li class="nav-item">
    <a class="nav-link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
        <span>Reportes</span>
    </a>
</li>
```

El panel incluye esos fragmentos en Administración. Para situar un enlace antes de esa sección, declara `$menuSection = 'user';` en el fragmento.

La comprobación del menú solo controla visibilidad; la ruta también debe exigir el permiso. Usa nombres propios para no colisionar con fragmentos publicados por otros módulos.

## Sustituir partes del panel

| Archivo del proyecto | Personalización |
| --- | --- |
| `app/views/admin-panel/parts/menu.php` | Enlaces iniciales, incluido Escritorio. |
| `app/views/admin-panel/parts/navbar.php` | Identidad y estructura de la barra superior. |
| `app/views/admin-panel/parts/aside.php` | Estructura completa del menú lateral. |
| `app/views/templates/adminTemplate.php` | Envoltura completa del panel. |

Copia el original correspondiente si necesitas partir de su estructura. Sin personalización, se utiliza el del paquete. El [footer](footer.md) se configura por separado.

Los módulos pueden aportar acciones en `parts/header-actions/` y sus recursos en `app/views/templates/meta/admin/`. La campana de Notificaciones aparece solo cuando se instala ese módulo.

## Estilos y botones

Personaliza colores, tipografía, bordes y sombras en `public/css/variables.css`. Registra el CSS administrativo propio después de los recursos compartidos. No edites los originales dentro de Composer.

Los botones de acciones de listados usan:

```html
<button type="button" class="btn btn-outline-secondary btn-list-actions btn-sm">
    Acciones
</button>
```

Añade `dropdown-toggle` y los atributos de Bootstrap cuando abran un desplegable. En formularios, coloca Cancelar antes de la acción principal y alinea las acciones a la derecha. Los botones de SweetAlert están centrados.

## Persistencia del tema y del menú

El panel conserva estas preferencias en `localStorage`, dentro del navegador y del origen del sitio. No se guardan en la sesión PHP ni se sincronizan entre dispositivos.

| Preferencia | Clave | Valores | Aplicación |
| --- | --- | --- | --- |
| Tema | `gf-theme` | `light` o `dark` | Atributo `data-bs-theme` de `<html>` |
| Barra lateral de escritorio | `gf-sidebar` | `collapsed` o `expanded` | Atributo `data-gf-sidebar` de `<html>` |

`public/js/modules/admin-panel/preload.js`, declarado en `hjs` de `admin.meta.php`, restaura ambos atributos desde el header antes de que se dibuje la página. Después, `admin.js` atiende los controles y guarda sus cambios. El tema sigue la preferencia del sistema cuando no hay una elección explícita; los cambios se sincronizan entre pestañas del mismo sitio mediante el evento `storage`.

Después de cargar `admin.js`:

```js
GFTheme.get();               // 'light' o 'dark'
GFTheme.set('dark');         // Aplica y guarda.
GFTheme.set('light', false); // Aplica solo en esta página.
GFTheme.resetToSystem();     // Vuelve a seguir al sistema.
```

El menú de escritorio guarda su estado en `gf-sidebar`. En móvil abre temporalmente desde la izquierda, con fondo de bloqueo y control de cierre; no reutiliza el estado colapsado de escritorio.

Si el navegador impide usar `localStorage`, el panel mantiene sus controles en la página actual, pero no puede conservar la elección para la siguiente carga. El preload aplica el tema del sistema y deja la barra de escritorio desplegada cuando no hay preferencias válidas.

Para consultar los tokens y componentes, abre `public/css/colores.html` por HTTP desde el proyecto. El visor permite buscar y copiar valores, pero no modifica el CSS.


## Composición real del template

La plantilla administrativa no es una página completa duplicada en cada módulo. Su estructura runtime es:

```text
adminTemplate.php
├── <aside id="sidebar">
│   └── admin-panel/parts/aside.php
│       ├── admin-panel/parts/menu.php
│       └── app/views/admin-panel/parts/menu-items/*.php
└── <main id="main">
    ├── admin-panel/parts/navbar.php
    └── $content
```

Las piezas se resuelven mediante `ModuleRuntime::file()`: una personalización en `app/views/admin-panel/...` tiene prioridad y, si no existe, se utiliza el original del módulo. Sustituir una pieza significa reemplazar ese archivo completo; no existe mezcla automática entre bloques del original y la copia del proyecto.

El template ya incluye un único `<main>`, el contenedor de navegación y `#toastBox`. Las vistas administrativas deben entregar solo el contenido de la pantalla y no volver a crear esos elementos.

## Recursos de la plantilla

`admin.meta.php` carga la base visual del panel en este orden conceptual:

```text
Bootstrap
-> SweetAlert2
-> variables.css
-> compatibilidad de botones
-> common.css
-> puente SweetAlert2
-> GFrame Icons
-> Alerts
-> admin.css
-> JavaScript del panel
```

El preload del panel se carga en cabecera; jQuery, Bootstrap, SweetAlert2, Alerts y `admin.js` se cargan al final. Los módulos pueden aportar recursos adicionales mediante `app/views/templates/meta/admin/*.meta.php`.

No duplique esas dependencias en cada pantalla. Declare en la meta de la vista únicamente lo que esa pantalla necesita.

## Contrato de extensiones del menú

El panel descubre fragmentos PHP en:

```text
app/views/admin-panel/parts/menu-items/*.php
```

Cada fragmento se renderiza de forma aislada y su resultado se agrega al menú. Por defecto se coloca en la sección de administración. Para colocarlo antes de esa sección, el fragmento puede establecer `$menuSection = 'user';`.

Los únicos grupos interpretados actualmente son `user` y `admin`. Cualquier otro valor termina en administración.

El fragmento debe decidir si imprime o no su enlace según la autorización del usuario. Aun así, esa comprobación es solo de presentación: la ruta correspondiente debe conservar `auth`, `can:*` u otros middleware necesarios.

Evite efectos secundarios dentro de un fragmento de menú. Su trabajo es decidir visibilidad y producir marcado de navegación; no debe modificar datos ni ejecutar procesos de negocio.

## Sidebar de escritorio y drawer móvil

En escritorio, el botón de colapso cambia `data-gf-sidebar` entre `expanded` y `collapsed`. La preferencia se guarda en `localStorage` bajo `gf-sidebar` y se sincroniza entre pestañas mediante el evento `storage`. El botón actualiza `aria-expanded`, `aria-label` e icono según el estado.

Por debajo de 991.98 px el sidebar funciona como drawer. Al abrirlo se agrega un overlay, se bloquea el scroll del body, se conserva el elemento que tenía el foco y el foco pasa a un control del drawer. Tab y Shift+Tab permanecen dentro del menú, Escape lo cierra y al cerrar se devuelve el foco al control anterior.

Al volver a escritorio, un drawer abierto se cierra automáticamente. El estado móvil no sobrescribe la preferencia de colapso del escritorio.

## Contrato del tema

`GFTheme` es la API pública del controlador visual del panel:

| Método | Efecto |
| --- | --- |
| `GFTheme.get()` | Devuelve el tema aplicado |
| `GFTheme.set('light'|'dark')` | Aplica y persiste |
| `GFTheme.set(theme, false)` | Aplica sin persistir |
| `GFTheme.resetToSystem()` | Elimina la preferencia y vuelve al sistema |

La preferencia persistida vive en `gf-theme`. Sin preferencia explícita, el panel sigue `prefers-color-scheme`. Los cambios persistidos se sincronizan entre pestañas.

El tema se expresa mediante `data-bs-theme` y `color-scheme` en el elemento raíz. Los módulos deben integrarse con ese contrato en lugar de crear otro selector de tema.

## Crear una sección administrativa completa

Para una capacidad nueva del proyecto, el recorrido recomendado es:

```text
permiso
-> ruta protegida
-> controlador
-> vista
-> meta de vista
-> fragmento de menú
```

Ejemplo de archivos:

```text
config/Permissions.php
config/routes/routes_web.php
app/controllers/reportes/ReportController.php
app/views/reportes/reportIndex.php
app/views/reportes/reportIndex.meta.php
app/views/admin-panel/parts/menu-items/reportes.php
public/css/app/reportes/report-index.css
public/js/app/reportes/report-index.js
```

El enlace del sidebar es el último paso, no el mecanismo de seguridad. Pruebe también que un usuario sin permiso reciba el rechazo correcto aunque conozca directamente la URL.
