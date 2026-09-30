# Sesiones

GFrame usa `SessionRuntime` para seleccionar el almacenamiento sin cambiar controladores ni middleware.

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

## Creación, actualización y revocación

`SessionRuntime` conecta el driver de PHP antes del middleware. En el login, `SessionManager` regenera el ID y el driver crea explícitamente la fila o clave autenticada. Después, PHP pide al driver guardar la sesión al terminar cada petición. En base de datos, `write()` **solo actualiza** una sesión autenticada que todavía exista; `updateTimestamp()` solo modifica `last_activity` y `expires_at` de una fila existente. Si otro proceso la borró mientras se ejecutaba la petición, ninguna de esas operaciones puede recrearla. En Redis, las escrituras autenticadas comprueban atómicamente que las claves de datos y propietario sigan existiendo. La actualización de `$_SESSION['lastActivity']` por el middleware es el control de inactividad de la aplicación; no debe confundirse con los metadatos de vencimiento del almacenamiento.

Cada dispositivo recibe un identificador de sesión distinto. GFrame almacena solo su hash, nunca el valor original de la cookie. No crea otra fila en cada petición: actualiza la existente. Las sesiones anónimas pueden iniciarse antes del login; no dan acceso a rutas protegidas.

- Cerrar sesión borra solo la fila o clave de ese dispositivo. Todas las pestañas de un mismo navegador comparten ese ID, por lo que quedan desconectadas en su siguiente operación.
- Suspender, desactivar o eliminar debe revocar todas las sesiones. `users.status` impide nuevos inicios mientras la cuenta no esté activa. En Redis, el servicio de revocación también activa su clave temporal de bloqueo hasta la reactivación.
- Reactivar permite sesiones nuevas, pero no restaura las anteriores.
- Cambiar contraseña o rol revoca las sesiones sin bloquear la cuenta.
- Cambiar permisos incrementa `roles.security_version`; cada sesión refresca sus permisos en la siguiente operación sin cerrar el acceso.
- Cambiar nombre, avatar o preferencias no requiere revocación.

Una petición que ya pasó el middleware puede terminar su operación de negocio si la revocación ocurre después; no se deshace el CRUD. Lo que se impide es que esa petición vuelva a guardar la sesión eliminada. El navegador puede conservar la cookie tras la revocación, pero en la siguiente petición el driver no encuentra la sesión y el middleware rechaza el acceso. Heartbeat actúa como aviso periódico, no como fuente de autorización.

## Instalación y actualización

El esquema `auth` y la migración de `auth-ui` crean solo `gframe_sessions`. Una instalación anterior puede conservar `gframe_session_users`, pero GFrame ya no la consulta ni la actualiza. La actualización no la borra automáticamente porque eso sería destructivo. Después de actualizar un proyecto, ejecute `composer gframe:update` antes de cambiar `SESSION_DRIVER` a `database`.

Las sesiones vencidas del driver de base de datos se eliminan mediante la recolección de basura de PHP. Redis las elimina automáticamente por TTL.
