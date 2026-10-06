# Middleware y control de acceso

Un middleware comprueba la petición antes de ejecutar la acción del controlador. Puede permitirla, rechazarla o aportar contexto, como la identidad de un consumidor API.

Router ejecuta `Middleware::handle()` con los datos de la ruta. No necesitas invocarlo desde cada controlador. Consulta [Rutas](rutas.md) para declarar canales y [Permisos](permisos.md) para configurar roles y autorizaciones.

## Proteger una página

En `config/routes/routes_web.php`:

```php
use RouteBuilder as Route;

Route::get('admin/catalogo', 'catalogo/ProductController@index')
    ->template('admin')
    ->view('productIndex')
    ->middleware(['auth', 'admin', 'can:catalogo.read'])
    ->registerFinal();
```

La petición necesita una sesión autenticada, acceso administrativo y permiso `catalogo.read`. El controlador solo se ejecuta si los controles permiten continuar. Tener una vista administrativa o usar `template('admin')` no protege una ruta.

`middleware()` recibe la lista completa: llamarlo dos veces sustituye la primera lista. Los nombres se comparan sin distinguir mayúsculas y minúsculas.

## Orden real de ejecución

1. Router añade los guardas automáticos del canal que no estén declarados ni excluidos.
2. Middleware comprueba el estado de sesión cuando la lista utiliza autenticación, invitado, administración, rol o permisos.
3. Ejecuta la lista en orden, empezando por los guardas añadidos.
4. Se detiene ante el primer resultado cuyo `status` no sea `success`.
5. Router interpreta el resultado y decide si continúa, responde a un preflight o produce la respuesta de error del canal.

Un rechazo no ejecuta los middleware posteriores. Los resultados satisfactorios pueden acumular cabeceras, estado HTTP, información CORS y contexto. El contexto acumulado se incorpora a la ruta después de finalizar `handle()`; no se reinyecta automáticamente en los parámetros que recibe cada middleware posterior dentro de la misma cadena.

## Controles disponibles

| Nombre | Comprobación |
| --- | --- |
| `auth` | Existe una identidad con ID positivo en `$_SESSION['auth']` |
| `guest` | No existe una identidad autenticada |
| `admin` | El usuario tiene acceso efectivo `admin.access` |
| `role:<rol>` | El rol resuelto coincide con el solicitado |
| `can:<módulo>.<acción>` | El usuario tiene el permiso efectivo indicado |
| `CSRF` | Token y timestamp enviados coinciden con los de sesión en métodos de escritura |
| `honeypot` | El campo `middle_name` de `$_POST` está vacío |
| `block_external_ajax` | Rechaza OPTIONS y orígenes AJAX externos cuando hay Origin o Referer |
| `allow_cors_with_token` | Autentica al consumidor API y comprueba los orígenes permitidos |
| `webhook_guard` | Comprueba las condiciones de entrada del webhook |
| `sse_guard` | Comprueba GET y las condiciones de entrada al stream |

Un nombre no reconocido devuelve `unknown_middleware`. No se convierte en una llamada a una clase del proyecto ni se ignora silenciosamente.

### Identidad, rol y permiso no son equivalentes

`auth` no concede acceso administrativo. `admin` se basa en permisos, no en comparar un nombre de rol con «administrador». Para restringir una operación suele ser más adecuado `can:*` que exigir un rol concreto.

El superadministrador tiene el bypass definido por el servicio de permisos para `admin` y `can:*`. No lo interpretes como un bypass de CSRF, autenticación o cualquier guarda del canal. `role:*` compara el rol exacto.

Usa permisos completos, como `can:catalogo.read`. La forma corta `can:read` depende del valor histórico `module` de la ruta, inferido del controlador; no es lo mismo que `sourceModule`, que identifica el módulo runtime.

Declarar `permission()` en RouteBuilder no añade automáticamente `can:*`. Los permisos deben existir y estar configurados en el sistema de roles del proyecto.

## Sesión e inactividad

La identidad normalizada está en `$_SESSION['auth']`; Middleware comprueba su campo `id`, no variables alternativas inventadas por el controlador. El estado de sesión se prepara durante el arranque de la aplicación.

Las rutas con `auth`, `guest`, `admin`, `role:*` o `can:*` comprueban inactividad cuando hay identidad. El límite se lee de `session.idle_timeout`, con un mínimo efectivo de 60 segundos y un valor predeterminado de 1800. La actividad se registra en `$_SESSION['lastActivity']`.

Para una petición automática que no deba mantener viva la sesión:

```php
use RouteBuilder as Route;

Route::get('ajax/estado', 'estado/StatusController@index')
    ->middleware(['auth'])
    ->noRefreshSession()
    ->registerFinal();
```

`noRefreshSession()` evita actualizar la última actividad; no desactiva expiración ni autentica al visitante. Sin identidad, una ruta marcada así puede devolver `expired` antes de los controles de la lista. En rutas sin controles ligados a sesión, el manejador no conserva esa marca como política de inactividad.

Consulta [Sesiones](sesiones.md) para almacenamiento, inicio y cierre de sesión.

## Protección CSRF y honeypot

Router añade CSRF automáticamente en AJAX. Para una ruta web de escritura, debes declararlo cuando uses el contrato de sesión:

```php
use RouteBuilder as Route;

Route::post('perfil/guardar', 'perfil/ProfileController@save')
    ->middleware(['auth', 'CSRF', 'honeypot'])
    ->template('home')
    ->view('profileSaved')
    ->registerFinal();
```

CSRF comprueba POST, PUT, PATCH y DELETE. Que el guarda reconozca PATCH no implica que RouteBuilder disponga de `patch()`. En GET y otros métodos no realiza esa comprobación de token.

El contrato actual lee `csrfToken` y `csrfTimestamp` de `$_POST` y los compara con la sesión. No extrae esos campos de una cabecera ni de un cuerpo JSON automáticamente. Si hay Origin o Referer, también comprueba el origen del sitio. Un timestamp anterior al de sesión puede producir `to_reload`, por ejemplo tras iniciar otra sesión en una pestaña.

Utiliza las utilidades de [Frontend core](frontend-core.md) y el puente de tokens del header; no sustituyas este contrato con una validación propia en JavaScript. En PUT o DELETE, comprueba cómo entrega el cliente los campos: PHP no llena `$_POST` de todos los cuerpos automáticamente.

El honeypot estándar espera `middle_name`. Un formulario con un campo antispam distinto no queda cubierto por ese middleware. El honeypot no sustituye validación, límites de frecuencia ni autorización.

## Guardas automáticos por canal

| Canal | Middleware añadido |
| --- | --- |
| web | Ninguno por el mero hecho de ser web |
| ajax | `block_external_ajax`, `CSRF` |
| api | `allow_cors_with_token` |
| webhook | `webhook_guard` |
| sse | `sse_guard` |

Si declaras el guarda en tu lista, Router no añade otra copia y conserva su posición declarada. `excludeMiddleware()` impide una adición automática; no elimina un elemento ya escrito en `middleware()`.

Cada guarda responde a un contrato distinto. CORS no sustituye credenciales; CSRF no sustituye permisos; SSE no sustituye el middleware `auth` cuando el stream es privado. La declaración y protección de API, webhook y SSE se explican en [Rutas](rutas.md).

No excluyas un guarda para hacer pasar una petición mal formada. Corrige primero credenciales, token, origen y tipo de cuerpo.

## Contexto disponible en el controlador

Tras una autorización satisfactoria, Router mezcla el contexto aportado con el declarado en la ruta. Las claves aportadas por middleware pueden sustituir claves de igual nombre.

Entre los datos que pueden aparecer están `role`, `permission_module`, `permission_action`, `api_consumer`, `api_tenant_id` y `api_scopes`, según el control ejecutado.

```php
public function index(array $routeParams): array
{
    $context = $routeParams['context'] ?? [];
    return ['consumer' => $context['api_consumer'] ?? null];
}
```

Los scopes se entregan como contexto; no convierten automáticamente cualquier acción en una operación autorizada. Comprueba los alcances necesarios en el servicio del proyecto. Tampoco uses un tenant enviado por el cliente como prueba de pertenencia.

## Permisos globales y por tenant

Sin configuración de tenancy, `can:*` comprueba permisos globales. Con `TENANT` y `TENANT_TABLE` definidos conjuntamente, comprueba el tenant solicitado. Definir solo uno es una configuración inválida.

La búsqueda del identificador revisa parámetros de ruta, POST y REQUEST, usando la clave configurada y después `tenant_id`. Encontrar un ID no autoriza al usuario: el servicio de permisos debe verificar la membresía y el permiso efectivos.

La aplicación debe conservar el aislamiento en sus consultas y políticas. El middleware no añade filtros de tenant automáticamente al ORM. Consulta [Permisos](permisos.md) para configuración y membresías.

## Cómo ampliar los controles

El manejador actual reconoce los nombres de su lista interna. No ofrece un registro público de middleware arbitrarios, closures o clases del proyecto para añadirlos mediante `middleware(['mi_control'])`.

Router instancia `new Middleware()` directamente. Heredar la clase en el proyecto no cambia esa instancia automáticamente. Tampoco existe una opción de ruta para sustituirla por tu subclase.

El constructor admite inyectar `RolePermissionService` y un `ApiCredentialProvider` cuando creas el manejador explícitamente. Ese punto facilita composición y pruebas, pero no equivale a configurar esa inyección desde una ruta del Router estándar.

Para una regla de negocio adicional, implementa una política o servicio del proyecto y ejecútalo desde la acción, después de los controles estándar y antes de acceder o modificar los datos. No copies el middleware del núcleo ni declares como disponibles mecanismos de ampliación que no existen.

## Diagnóstico de un rechazo

Comprueba el código del resultado y el canal de respuesta. `login_required`, `expired`, `forbidden`, `fail_csrf`, `to_reload`, `is_bot` y `unknown_middleware` representan situaciones distintas.

Para diagnosticar, revisa lista y orden, identidad normalizada, actividad de sesión, tokens y contexto. No registres contraseñas, tokens API ni secretos de webhook. El feedback al visitante debe utilizar el contrato de [Errores](errores.md) y del frontend, no mensajes de depuración del servidor.
