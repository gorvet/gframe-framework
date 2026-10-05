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

## Tema y menú móvil

El tema se controla con `data-bs-theme` en `html`. Se guarda en `gf-theme`, sigue el sistema sin preferencia explícita y se sincroniza entre pestañas.

Después de cargar `admin.js`:

```js
GFTheme.get();               // 'light' o 'dark'
GFTheme.set('dark');         // Aplica y guarda.
GFTheme.set('light', false); // Aplica solo en esta página.
GFTheme.resetToSystem();     // Vuelve a seguir al sistema.
```

El menú de escritorio guarda su estado en `gf-sidebar`. En móvil abre temporalmente desde la izquierda, con fondo de bloqueo y control de cierre; no reutiliza el estado colapsado de escritorio.

Para consultar los tokens y componentes, abre `public/css/colores.html` por HTTP desde el proyecto. El visor permite buscar y copiar valores, pero no modifica el CSS.
