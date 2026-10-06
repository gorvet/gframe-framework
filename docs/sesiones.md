# Sesiones

Una sesión mantiene la identidad del usuario entre peticiones. Después de iniciar sesión, el navegador conserva una cookie con un identificador; el servidor utiliza ese identificador para recuperar el usuario autenticado y sus permisos. Por eso puede abrir otra página o solicitar un listado por AJAX sin volver a introducir su contraseña.

GFrame usa `SessionRuntime` para seleccionar dónde guarda esa información. El driver es el almacenamiento de la sesión: base de datos, Redis o el manejador nativo de PHP. Cambiarlo no requiere reescribir los controladores ni los middleware.

## Uso en la aplicación

Las rutas privadas declaran el middleware `auth`; este comprueba la sesión antes de ejecutar la acción. El módulo de autenticación se encarga del inicio y cierre de sesión. Los módulos del proyecto consultan la identidad mediante los contratos de autenticación, en lugar de crear otro sistema de cookies o guardar manualmente un usuario en la sesión. Consulta [Autenticación](autenticacion.md) y [Middleware](middleware.md).

Una cookie identifica la sesión; no contiene por sí misma una autorización válida. El servidor puede revocarla, por ejemplo al suspender la cuenta. Una misma sesión se comparte entre pestañas del navegador, mientras que otro dispositivo utiliza una sesión independiente.

### Consultar al usuario actual

En una acción protegida, consulta la identidad normalizada:

```php
<?php
$identity = $_SESSION['auth'] ?? [];
$userID = (int)($identity['id'] ?? 0);
$email = (string)($identity['email'] ?? '');
```

| Campo de `auth` | Contenido |
| --- | --- |
| `id`, `email`, `name` | Identidad; el nombre puede proceder del perfil del proyecto |
| `role_id`, `role` | Identificador y nombre interno del rol global |
| `permissions` | Lista de permisos efectivos en caché |
| `role_version`, `authorization_version` | Versiones para detectar cambios de autorización |
| `bypass` | Indicador calculado por el flujo de autorización |

No guardes contraseñas ni tokens de recuperación en esta identidad. No aceptes `role`, `permissions` o `bypass` enviados por el navegador como autorización. El middleware comprueba y refresca los datos del servidor.

Ejemplo de ruta privada:

```php
<?php
use RouteBuilder as Route;

Route::get('mis-documentos', 'documents/DocumentsController@index')
    ->middleware(['auth'])
    ->template('admin')
    ->view('documentsIndex')
    ->registerFinal();
```

El middleware debe ejecutarse antes del controlador. Consultar un ID desde una vista no protege una acción ni sustituye la autorización sobre los registros.

### Crear o actualizar la identidad

El controlador estándar de autenticación llama a `SessionManager::login()` después de verificar credenciales y resolver los permisos. Si integras otro proveedor de identidad, utiliza el mismo método con datos previamente verificados en el servidor:

```php
<?php
use GFrame\Auth\SessionManager;

// $identity ya contiene la identidad y autorización verificadas.
(new SessionManager())->login($identity);
```

`login()` regenera el ID, normaliza `auth`, crea los valores CSRF y registra la sesión autenticada en el driver. Autenticar credenciales mediante `AuthModel::login()` por sí solo no crea esta sesión. Ejecuta el inicio antes de enviar HTML o cabeceras.

Para actualizar el nombre mostrado después de guardar un perfil:

```php
<?php
use GFrame\Auth\SessionManager;

(new SessionManager())->updateIdentity(['name' => 'María Pérez']);
```

`updateIdentity()` conserva los campos que no recibe y no vuelve a autenticar al usuario. No lo uses para asignar roles ni para eludir el refresco de permisos. Los dos métodos admiten un segundo array para datos adicionales del proyecto; utiliza una clave propia, por ejemplo `project_preferences`, y no sobrescribas `auth`, `csrfToken`, `csrfTimestamp` o `lastActivity`.

## Actualización de permisos

Las sesiones administradas guardan `role_version` y `authorization_version`. Si cambia la plantilla del rol o una excepción individual, la siguiente operación recarga los permisos sin consultar la autorización en cada heartbeat y sin cerrar la sesión.

## Drivers

`database` es el valor generado para aplicaciones instaladas con MySQL o SQLite y funciona en hosting compartido. Cada dispositivo tiene una fila en `gframe_sessions`, identificada por el hash del ID de sesión, sin ID autoincremental. El driver busca esa fila y coteja en la misma consulta la versión de autorización y `users.status`. Si la cuenta ya no está activa, borra la sesión y devuelve una sesión vacía.

`redis` está destinado a aplicaciones distribuidas o con alta concurrencia. Requiere la extensión `phpredis` y conserva sesiones, propietarios e índices por usuario mediante claves con TTL y operaciones Lua atómicas. No consulta `users` en cada petición: comprueba el estado de la cuenta al registrar una nueva sesión y depende de que los servicios de suspensión o eliminación revoquen las sesiones. Conserva una clave temporal de bloqueo para cerrar la carrera entre suspensión e inicio de sesión; no utiliza generaciones.

`native` utiliza el manejador configurado directamente en PHP. Se reserva para sitios sin autenticación o integraciones que aporten su propio almacenamiento; no garantiza revocación multidispositivo desde GFrame.

```php
'session' => [
    'driver' => env('SESSION_DRIVER', 'database'),
    'connection' => 'main',
    'lifetime' => 1800,
    'idle_timeout' => 1800,
],
```

Para Redis:

```env
SESSION_DRIVER="redis"
SESSION_REDIS_HOST="127.0.0.1"
SESSION_REDIS_PORT="6379"
SESSION_REDIS_PASSWORD=""
SESSION_REDIS_DATABASE="0"
```

Configura también un `session.redis.prefix` distinto para cada proyecto que comparta la misma base Redis. Separa los nombres de cookie mediante `session.name` cuando varios proyectos comparten dominio. Las opciones de cookie `Secure`, `HttpOnly` y `SameSite` dependen de la configuración PHP del despliegue; usa HTTPS y comprueba las cabeceras reales. `SessionRuntime` activa el modo estricto, pero no establece por sí mismo todas esas opciones.

## Caducidad e inactividad

Los tiempos se expresan en segundos:

- `lifetime` controla el vencimiento renovable del almacenamiento; el mínimo efectivo es 60 segundos.
- `idle_timeout` limita la inactividad comprobada por el middleware; su mínimo también es 60 segundos.

No son una duración máxima absoluta desde el login. Las peticiones pueden renovar la actividad y el almacenamiento; una sesión usada de forma continua puede durar más que esos valores. Para un comportamiento sencillo, configura ambos con el mismo tiempo, como en el ejemplo anterior.

`Middleware::sessionTimeout()` devuelve `code => expired` al superar la inactividad y destruye la sesión. Su argumento `$refresh` decide si una comprobación válida actualiza `lastActivity`; las comprobaciones periódicas pueden utilizar `false` para no mantener artificialmente la actividad del usuario. Conserva el tratamiento estándar de respuestas de sesión expirada en tus peticiones AJAX.

## Cerrar sesión y revocar dispositivos

El cierre estándar pasa por la acción protegida del módulo auth-ui. Internamente utiliza:

```php
<?php
use GFrame\Auth\SessionManager;

(new SessionManager())->logout();
```

El método elimina el registro del dispositivo, vacía `$_SESSION`, caduca la cookie y destruye la sesión PHP. En una acción propia conserva protección de acceso, método de mutación y CSRF; no conviertas el cierre en una ruta GET pública.

Para revocar todos los dispositivos desde un servicio del proyecto:

```php
<?php
use GFrame\Session\SessionRuntime;

$registry = SessionRuntime::registry();
if ($registry === null) {
    throw new RuntimeException('La revocación requiere un driver administrado.');
}
$revoked = $registry->revokeUser($userID);
```

El ejemplo requiere `database` o `redis` y un ID obtenido del servidor. Para suspender una cuenta usa los servicios de usuarios existentes: además del estado persistido, aplican la revocación con bloqueo. `allowUser()` retira el bloqueo del registro, pero no cambia por sí mismo `users.status` ni recupera sesiones anteriores.

CSRF utiliza `csrfToken` y `csrfTimestamp` guardados en la sesión. Envía esos valores mediante los contratos del [frontend](frontend-core.md) en las acciones protegidas; no inventes otra cookie de autenticación o un segundo mecanismo CSRF. Las excepciones para rutas públicas pertenecen a la declaración de middleware.

## Creación, actualización y revocación

`SessionRuntime` conecta el driver de PHP antes del middleware. En el login, `SessionManager` regenera el ID y el driver crea explícitamente la fila o clave autenticada. Después, PHP pide al driver guardar la sesión al terminar cada petición. En base de datos, `write()` **solo actualiza** una sesión autenticada que todavía exista; `updateTimestamp()` solo modifica `last_activity` y `expires_at` de una fila existente. Si otro proceso la borró mientras se ejecutaba la petición, ninguna de esas operaciones puede recrearla. En Redis, las escrituras autenticadas comprueban atómicamente que las claves de datos y propietario sigan existiendo. La actualización de `$_SESSION['lastActivity']` por el middleware es el control de inactividad de la aplicación; no debe confundirse con los metadatos de vencimiento del almacenamiento.

Cada dispositivo recibe un identificador de sesión distinto. GFrame almacena solo su hash, nunca el valor original de la cookie. No crea otra fila en cada petición: actualiza la existente. Las sesiones anónimas pueden iniciarse antes del login; no dan acceso a rutas protegidas.

- Cerrar sesión borra solo la fila o clave de ese dispositivo. Todas las pestañas de un mismo navegador comparten ese ID, por lo que quedan desconectadas en su siguiente operación.
- Suspender, desactivar o eliminar debe revocar todas las sesiones. `users.status` impide nuevos inicios mientras la cuenta no esté activa. En Redis, el servicio de revocación también activa su clave temporal de bloqueo hasta la reactivación.
- Reactivar permite sesiones nuevas, pero no restaura las anteriores.
- Cambiar contraseña, recuperarla mediante token o cambiar de rol revoca las sesiones gestionadas sin bloquear la cuenta. El driver nativo no ofrece revocación multidispositivo.
- Cambiar permisos incrementa `roles.security_version`; cada sesión refresca sus permisos en la siguiente operación sin cerrar el acceso.
- Cambiar nombre, avatar o preferencias no requiere revocación.

Una petición que ya pasó el middleware puede terminar su operación de negocio si la revocación ocurre después; no se deshace el CRUD. Lo que se impide es que esa petición vuelva a guardar la sesión eliminada. El navegador puede conservar la cookie tras la revocación, pero en la siguiente petición el driver no encuentra la sesión y el middleware rechaza el acceso. Heartbeat actúa como aviso periódico, no como fuente de autorización.

## Instalación y actualización

El esquema `auth` y la migración de `auth-ui` crean solo `gframe_sessions`. Una instalación anterior puede conservar `gframe_session_users`, pero GFrame ya no la consulta ni la actualiza. La actualización no la borra automáticamente porque eso sería destructivo. Después de actualizar un proyecto, ejecuta `composer gframe:update` antes de cambiar `SESSION_DRIVER` a `database`.

Las sesiones vencidas del driver de base de datos se eliminan mediante la recolección de basura de PHP. Redis las elimina automáticamente por TTL.
