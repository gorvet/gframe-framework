# Roles, permisos y membresías

GFrame guarda la plantilla de cada rol en `roles.permissions_json`. Las aplicaciones globales asignan un rol en `users.role_id`. Las aplicaciones multitenant usan una fila de `tenant_memberships` por pareja usuario–tenant. Esa fila guarda el rol, el estado activo y únicamente las excepciones de ese usuario en ese tenant.

## Alcance real del módulo

Auth registra cuentas y no crea tenants ni membresías. El módulo de permisos de GFrame instala el esquema, sincroniza plantillas, resuelve autorizaciones y permite gestionar membresías existentes. **No incluye una operación genérica que cree un tenant junto con su primera membresía**. Ese es el punto de integración del módulo que crea la entidad de negocio; el ejemplo de abajo muestra cómo implementarlo sin separar ambas escrituras. Tampoco existe un endpoint público de creación de dueños. `UserPermissionService::assignTenantRole()` no comprueba que el ID del tenant exista: el módulo que lo invoca debe comprobar la entidad y aplicar sus reglas de acceso antes de llamar al servicio.

## Elegir el modelo al instalar

| Perfil | Qué instala y quién recibe acceso |
| --- | --- |
| `static` | No instala Auth ni usuarios. |
| `managed` e `intranet` | Instalan Auth y permisos globales, sin tenants. El primer usuario es `superadministrator`; los registros posteriores reciben `registered`. Las capacidades se toman del rol global y sus excepciones en `users`. Una aplicación global corresponde a este modelo de autorización, aunque tenga suscripciones comerciales. |
| `saas` | Instala Auth, la tabla genérica `tenants` y `tenant_memberships`. Conserva el rol global `registered` al registrarse. No crea tenant ni membresía por registrar al usuario: cada módulo de negocio los crea cuando corresponde. Una aplicación de bots puede usar este modelo, con cada bot como tenant en su adaptación. |

Los esquemas base crean solo los roles `superadministrator` y `registered`. Si el proyecto necesita `owner`, `gestor`, `editor` u otros roles, los declara en `config/Permissions.php` antes de instalar o ejecuta `composer gframe:update` después de añadirlos. No se asignan solos a usuarios existentes. En una aplicación global, un administrador asigna el rol global mediante `RolePermissionService::assignRole()` o el flujo administrativo del proyecto. En una multitenant, la creación del tenant asigna su primera membresía; después el dueño gestiona las de sus gestores.

El rol `registered` no trae capacidades de negocio de forma predeterminada: permite identificar a la persona y entrar en rutas protegidas solo por `auth`. Una ruta con `can:...` exige que la plantilla global o la membresía del tenant conceda esa capacidad. `owner` tampoco es un bypass universal: sus capacidades proceden de su plantilla, mientras que `superadministrator` sí tiene bypass. En una aplicación sin tenants no se crea ninguna membresía; si un usuario necesita más acceso se le asigna un rol global o una excepción global.

## Definir plantillas

El proyecto puede declarar `config/Permissions.php`:

```php
return [
    'owner' => [
        'bot' => ['view' => true, 'edit' => true, 'delete' => true],
        'flows' => ['view' => true, 'edit' => true],
    ],
    'gestor' => [
        'bot' => ['view' => true, 'edit' => true, 'delete' => false],
        'flows' => ['view' => true, 'edit' => false],
    ],
];
```

Al instalar y al ejecutar `composer gframe:update`, GFrame crea los roles declarados si faltan, convierte las claves en `bot.view`, `bot.edit`, etcétera, y actualiza el JSON de cada rol. No recorre usuarios ni membresías. Los permisos ausentes del archivo conservan su valor almacenado. Un `true` concede y un `false` deniega. Las excepciones individuales permanecen intactas. La facultad de gestionar membresías `gestor` se comprueba actualmente por el rol `owner` activo en `UserPermissionService`; no la otorga por sí sola una clave de permisos JSON.

## Resolver permisos

Para una aplicación global, GFrame combina `roles.permissions_json` con `users.permission_overrides_json`. Para un tenant, combina la plantilla del rol asignado en `tenant_memberships` con `tenant_memberships.permission_overrides_json`. Una excepción `true` concede y una `false` deniega. El superadministrador conserva su bypass.

La membresía debe existir y estar activa. El dueño de un registro no recibe permisos automáticamente por esa sola condición. El proyecto debe crear su membresía cuando corresponda.

`AuthModel::registerAcount()` y la instalación inicial solo crean usuarios y su rol global; nunca crean membresías. El perfil SaaS instala una tabla `tenants` con `tenant_id`, `name`, `slug` y `status`, pero **no** una columna de dueño. GFrame tampoco deduce el dueño de una petición ni crea una membresía de reserva. La aplicación define qué entidad es su tenant y cómo acredita su propiedad.

### Crear un tenant y su primer dueño

En el caso habitual, la ruta exige `auth`. El controlador obtiene el ID de `$_SESSION['auth']['id']`, nunca de un campo `user_id` enviado por el cliente. El servicio de negocio crea el tenant y su membresía `owner` en la **misma transacción y conexión**. Así se comprueba la propiedad inicial: el tenant procede de esa operación autenticada, su ID se obtiene del `INSERT` recién efectuado y solo a ese usuario se le concede `owner`. Si cualquiera de las escrituras falla, se revierte todo.

Este ejemplo usa la tabla `tenants` del perfil SaaS. El proyecto adapta el `INSERT` si su entidad es otra, como `bots` en una aplicación de bots:

```php
use GFrame\Session\SessionRuntime;

// Precondición del controlador: ruta protegida con auth y CSRF según el transporte.
$ownerID = (int)($_SESSION['auth']['id'] ?? 0);
if ($ownerID <= 0) {
    return ['status' => 'unauthorized', 'code' => 'login_required'];
}

$db = DatabaseManager::connection(); // La misma conexión para todas las escrituras.
if (!$db instanceof PDO) {
    return ['status' => 'error', 'code' => 'database_unavailable'];
}
try {
    $db->beginTransaction();
    $role = $db->prepare('SELECT role_id FROM roles WHERE slug = ? LIMIT 1');
    $role->execute(['owner']);
    $ownerRoleID = (int)$role->fetchColumn();
    if ($ownerRoleID <= 0) {
        throw new RuntimeException('Falta el rol owner en config/Permissions.php.');
    }

    $create = $db->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)');
    $create->execute([$name, $slug]);
    $tenantID = (int)$db->lastInsertId();

    $membership = $db->prepare(
        'INSERT INTO tenant_memberships '
        . '(user_id, tenant_id, role_id, is_active, permission_overrides_json) '
        . 'VALUES (?, ?, ?, 1, ?)'
    );
    $membership->execute([$ownerID, $tenantID, $ownerRoleID, '{}']);

    $db->prepare('UPDATE users SET authorization_version = authorization_version + 1 WHERE user_id = ?')
        ->execute([$ownerID]);
    $version = $db->prepare('SELECT authorization_version FROM users WHERE user_id = ?');
    $version->execute([$ownerID]);
    $authorizationVersion = (int)$version->fetchColumn();
    $db->commit();
} catch (Exception $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Tenant creation] ' . $exception->getMessage());
    return ['status' => 'error', 'code' => 'tenant_creation_failed'];
}

// Solo después del commit: necesario para que las sesiones Redis detecten el cambio.
SessionRuntime::registry()?->publishUserAuthorizationVersion($ownerID, $authorizationVersion);
return ['status' => 'success', 'code' => 'tenant_created', 'tenant_id' => $tenantID];
```

El ejemplo es un flujo de aplicación, no un método automático de Auth. El proyecto debe validar `name` y `slug`, controlar duplicados y aplicar sus límites de plan. La publicación posterior al commit no forma parte de la transacción: si falla, el tenant ya existe y hace falta reintentar la sincronización de autorización; no se debe informar que la creación se revirtió. Con sesiones en base de datos, la versión se consulta desde la misma base. Con Redis, se publica mediante el registro de sesiones. Si el proceso que crea el tenant no inició `SessionRuntime` (por ejemplo, un trabajador CLI), debe resolver explícitamente el registro Redis o ejecutar una reconciliación antes de depender de la nueva autorización.

### Vincular un tenant existente

No basta con recibir `tenant_id` y `user_id` por POST: eso permitiría apropiarse de tenants ajenos. Antes de asignar `owner`, el proyecto debe comprobar la propiedad en una fuente independiente de la membresía que va a crear. En Bebots, esa fuente es `bots.user_id`: consultar `SELECT user_id FROM bots WHERE bot_id = ?` y exigir que coincida con el usuario autenticado. La lectura y la nueva membresía deben quedar en una transacción que impida cambios de dueño concurrentes. Si se usa MySQL, puede bloquearse la fila con `FOR UPDATE`; en SQLite se utiliza la estrategia de escritura/transacción correspondiente. La tabla genérica `tenants` no tiene dueño; para tenants preexistentes sin otra fuente fiable no se puede inferir la propiedad: hace falta una migración auditada o una columna/relación de propiedad del proyecto.

No se debe permitir crear `owner` desde una acción pública de asignación de roles. `UserPermissionService::assignTenantRole()` rechaza ese rol: está reservado al flujo de creación o transferencia de propiedad que implemente el proyecto.

El dueño activo de un tenant puede asignar o reactivar `gestor` y desactivar membresías de gestores. Un gestor puede desactivar su propia membresía para salir del tenant. Ninguna de esas operaciones permite sustituir o desactivar la membresía `owner`; una transferencia de propiedad exige un flujo específico del proyecto. El superadministrador conserva las operaciones administrativas, salvo la sustitución o desactivación accidental del dueño. Los límites de plan y las notificaciones son responsabilidad del módulo de la aplicación.

Los métodos actuales de `UserPermissionService` publican la versión de autorización inmediatamente después de sus escrituras. No los llames dentro de una transacción más amplia que aún podría revertirse, especialmente con sesiones Redis. Si la operación de negocio exige una transacción conjunta, haz las escrituras y el incremento de versión en ella y publica **después** del commit, como en el ejemplo anterior.

```php
$permissions->setOverride($actorID, $userID, 'bot.delete', 'allow', $tenantID);
$permissions->removeOverride($actorID, $userID, 'bot.delete', $tenantID);
$permissions->assignTenantRole($actorID, $userID, $tenantID, $roleID);
$permissions->deactivateTenantMembership($actorID, $userID, $tenantID);
```

Los servicios devuelven arreglos con `status` y `code`; el controlador decide si presenta una vista de error, `swalAlert` o `alertToast`.

## Sesión y coste de consultas

El inicio de sesión carga los permisos globales. El primer acceso a un tenant carga su membresía y plantilla. La sesión conserva solo el tenant activo; cambiar de tenant carga la nueva membresía. El middleware `can:*` comprueba el resultado guardado en sesión sin consultar la tabla de permisos en cada operación.

El almacenamiento de sesiones sí se lee en cada petición. El driver de base de datos comprueba en esa misma lectura las versiones del rol global, del rol del tenant activo y del usuario. Un cambio de plantilla, membresía o excepción hace que la siguiente operación recargue los permisos. El driver Redis usa claves de versión equivalentes. No hay una fila de permiso por usuario y acción.

## Estructura

- `roles`: una fila y un JSON por plantilla.
- `users`: rol global y excepciones globales.
- `tenant_memberships`: una fila por usuario y tenant, con rol y excepciones locales.

Las tablas de sesiones administradas son infraestructura de autenticación, no un catálogo de permisos. Las modificaciones SQL directas deben incrementar `roles.security_version` o `users.authorization_version`; los servicios de GFrame lo hacen al modificar esos datos.
