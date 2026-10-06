# Identidad, autenticación, permisos y sesiones

GFrame separa cuatro conceptos que suelen mezclarse:

1. **autenticación**: comprobar quién es el usuario;
2. **sesión**: mantener esa identidad entre peticiones;
3. **autorización**: decidir qué puede hacer;
4. **reglas sobre el recurso**: comprobar sobre qué registro concreto puede hacerlo.

Entender esa separación evita gran parte de los errores de acceso en una aplicación.

## Mapa completo

```text
credenciales
  ↓
AuthModel
  ↓
identidad verificada
  ↓
SessionManager::login()
  ↓
$_SESSION['auth']
  ↓
Middleware
  ├─ auth
  ├─ admin
  ├─ role:...
  └─ can:...
  ↓
Controller
  ↓
Service
  ↓
regla sobre el recurso / tenant
  ↓
Model / ORM
```

La autenticación ocurre antes de que exista una identidad confiable de sesión. La autorización utiliza esa identidad, pero no sustituye las comprobaciones del recurso concreto.

## 1. Autenticación: comprobar credenciales

`GFrame\Auth\AuthModel` contiene las operaciones de cuenta y credenciales.

Ejemplo:

```php
use GFrame\Auth\AuthModel;

$result = (new AuthModel())->login($email, $password);
```

Si el resultado es correcto, el modelo ha comprobado credenciales y estado de la cuenta. **Eso no crea por sí solo una sesión autenticada.**

El flujo estándar de `auth-ui` añade las operaciones necesarias alrededor del modelo: formularios, recuperación, verificación, correo y creación de sesión.

Consulta [Autenticación](autenticacion.md) y [Interfaz de autenticación](auth-ui.md).

## 2. Sesión: mantener una identidad confiable

Después de autenticar, el flujo estándar utiliza:

```php
use GFrame\Auth\SessionManager;

(new SessionManager())->login($identity);
```

`SessionManager::login()` regenera el ID de sesión, normaliza la identidad y registra la sesión autenticada en el driver correspondiente.

La aplicación consulta después:

```php
$identity = $_SESSION['auth'] ?? [];
$userID = (int)($identity['id'] ?? 0);
```

La identidad normalizada puede contener, entre otros:

```text
id
email
name
role_id
role
permissions
role_version
authorization_version
bypass
```

Esos valores se generan desde el servidor. No aceptes `role`, `permissions`, `bypass` ni identidad desde un formulario como si fueran autorización.

Consulta [Sesiones](sesiones.md).

## 3. Middleware: proteger la entrada

La ruta expresa el requisito de acceso antes de ejecutar la acción.

### Solo autenticación

```php
Route::get('cuenta', 'account/AccountController@index')
    ->middleware(['auth'])
    ->registerFinal();
```

### Capacidad concreta

```php
Route::post('ajax/products/delete', 'products/ProductController@delete')
    ->middleware(['auth', 'can:products.delete'])
    ->registerFinal();
```

### Rol

```php
->middleware(['auth', 'role:editor'])
```

### Área administrativa

```php
->middleware(['auth', 'admin'])
```

El middleware responde a la pregunta:

> ¿este usuario puede entrar a esta operación general?

Consulta [Middleware](middleware.md).

## 4. Permisos: resolver capacidades

Las capacidades utilizan nombres como:

```text
products.view
products.edit
products.delete
admin.access
```

Los roles almacenan su plantilla de permisos y pueden existir excepciones por usuario.

En una aplicación global:

```text
usuario
  → rol global
  → permissions_json del rol
  + overrides del usuario
  → capacidades efectivas
```

En una aplicación multitenant:

```text
usuario + tenant
  → tenant_membership
  → rol dentro de ese tenant
  → plantilla del rol
  + overrides de la membresía
  → capacidades efectivas
```

Consulta [Roles, permisos y membresías](permisos.md).

## 5. Permiso de ruta no equivale a permiso sobre cualquier registro

Esta distinción es crítica.

Una ruta:

```php
->middleware(['auth', 'can:products.edit'])
```

puede confirmar que el usuario tiene la capacidad de editar productos en el contexto autorizado.

Pero el service todavía debe comprobar que **el producto concreto** pertenece al ámbito que ese usuario puede modificar.

Ejemplo conceptual:

```php
final class ProductService
{
    public function update(int $productID, int $tenantID, array $input): array
    {
        $product = (new ProductModel())
            ->where('product_id', '=', $productID)
            ->where('tenant_id', '=', $tenantID)
            ->first();

        if ($product === null) {
            return ['status' => 'error', 'code' => 'product_not_found'];
        }

        // Aplicar regla y guardar.
    }
}
```

No hagas:

```php
$product = ProductModel::find($_POST['product_id']);
$product->delete();
```

solo porque la ruta tenía `can:products.delete`, si el recurso pertenece a un tenant o propietario concreto.

## 6. De dónde sale el tenant

En una aplicación SaaS, el framework puede resolver autorización por tenant, pero la aplicación sigue siendo responsable de definir qué entidad representa ese tenant y cómo se relacionan los registros.

No confíes simplemente en:

```php
$tenantID = (int)($_POST['tenant_id'] ?? 0);
```

como prueba de pertenencia.

El tenant debe derivarse de un contexto autorizado, una membresía o el recurso que se está operando, según el diseño de la aplicación.

La guía [Roles, permisos y membresías](permisos.md) explica el modelo de `tenant_memberships` y las responsabilidades que permanecen en la aplicación.

## 7. Cambios de permisos y sesiones existentes

GFrame no necesita consultar toda la autorización desde cero en cada operación.

La identidad de sesión mantiene versiones como:

```text
role_version
authorization_version
```

Cuando cambian plantillas o excepciones, esas versiones permiten detectar que una sesión tiene autorización antigua y refrescarla.

Conceptualmente:

```text
cambia permiso
  ↓
aumenta versión
  ↓
siguiente petición del usuario
  ↓
se detecta diferencia
  ↓
se recargan capacidades
```

Eso es distinto de un cambio de identidad suficientemente fuerte como para revocar sesiones.

Consulta [Autenticación](autenticacion.md), [Permisos](permisos.md) y [Sesiones](sesiones.md) para los casos exactos.

## 8. Suspender una cuenta y revocar sesiones

Cambiar un estado en `users` no debería tratarse como una simple modificación visual.

Los servicios de administración existentes coordinan estado y revocación de sesiones cuando corresponde.

Con drivers administrados (`database` o `redis`) existe un registro capaz de revocar sesiones por usuario.

No implementes un «suspendido = sí» propio y dejes activas sesiones que el resto del framework considera válidas.

Consulta [Administración de usuarios](user-admin.md) y [Sesiones](sesiones.md).

## 9. Drivers de sesión

`SessionRuntime` soporta actualmente:

| Driver | Uso |
| --- | --- |
| `database` | sesión administrada y revocable, adecuada para MySQL/SQLite y hosting común |
| `redis` | aplicaciones distribuidas o con mayor concurrencia |
| `native` | manejador PHP; menor integración con revocación administrada de GFrame |

El controller no debe cambiar porque cambies de driver.

Eso es precisamente la responsabilidad de `SessionRuntime`.

Consulta [Sesiones](sesiones.md) para lifetime, idle timeout, Redis, base de datos y revocación.

## 10. CSRF no es autenticación

En mutaciones web/AJAX normalmente necesitas varias capas:

```text
authenticación
+ autorización
+ CSRF
+ validación de entrada
+ regla sobre el recurso
```

CSRF comprueba que una petición mutadora procede del contexto de sesión esperado. No concede permisos.

Del mismo modo, estar autenticado no significa poder ejecutar cualquier mutación.

El canal AJAX de GFrame aplica sus guardas automáticos. Mantén las exclusiones solo para los flujos que realmente deban ser públicos.

Consulta [Router](rutas.md), [Middleware](middleware.md) y [Frontend core](frontend-core.md).

## 11. Registro no equivale a administrador

El registro normal crea una cuenta de acuerdo con el flujo estándar y no convierte al usuario en superadministrador.

La instalación inicial crea el primer usuario protegido con el rol `superadministrator`.

Ese rol tiene tratamiento especial dentro del framework y no debe utilizarse como sustituto de diseñar correctamente permisos de negocio para usuarios normales.

## 12. Roles globales y roles por tenant

Usa autorización global cuando el rol aplica a toda la aplicación:

```text
administrador global
editor global
operador global
```

Usa membresías por tenant cuando una misma cuenta puede tener una relación diferente con cada entidad:

```text
usuario
  ├─ owner en tenant A
  ├─ gestor en tenant B
  └─ sin acceso en tenant C
```

No crees `tenant_memberships` artificiales en una aplicación que realmente es global solo para poder usar los mismos nombres de rol.

## 13. Comprobación dentro de un service

Hay operaciones cuyo acceso necesita verificarse fuera del middleware, por ejemplo un proceso interno o una regla adicional.

```php
use GFrame\Auth\RoleModel;
use GFrame\Auth\RolePermissionService;

$permissions = new RolePermissionService(new RoleModel());
$result = $permissions->authorize(
    $userID,
    'products.edit',
    $tenantID
);
```

Comprueba el contrato devuelto; no interpretes la presencia de datos como autorización si el estado no indica éxito.

## 14. Flujo recomendado para una mutación privada

```text
POST AJAX
  ↓
Router detecta canal
  ↓
CSRF / guardas del canal
  ↓
auth
  ↓
can:resource.action
  ↓
Controller lee entrada
  ↓
Service valida dominio
  ↓
Service filtra recurso por tenant/propiedad
  ↓
Model / ORM modifica
  ↓
respuesta
```

Cada capa responde una pregunta diferente.

## 15. Qué guía leer según el problema

| Pregunta | Guía |
| --- | --- |
| ¿cómo inicia sesión un usuario? | [Autenticación](autenticacion.md) |
| ¿qué pantallas/endpoints estándar existen? | [Auth UI](auth-ui.md) |
| ¿cómo protejo una ruta? | [Middleware](middleware.md) |
| ¿cómo defino y resuelvo capacidades? | [Permisos](permisos.md) |
| ¿cómo persiste/revoca la sesión? | [Sesiones](sesiones.md) |
| ¿cómo edita el usuario su propia cuenta? | [Cuenta y seguridad](self-account.md) |
| ¿cómo administra un operador otras cuentas? | [Administración de usuarios](user-admin.md) |

La idea de esta página es dar el **mapa mental**. Las guías especializadas siguen siendo la referencia de comportamiento exacto.
