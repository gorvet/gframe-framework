# Autenticación

La autenticación comprueba quién está usando la aplicación. GFrame proporciona registro, verificación del correo, inicio y cierre de sesión y recuperación de contraseña. Las pantallas y los endpoints estándar pertenecen a [Interfaz de autenticación](auth-ui.md); esta guía explica el contrato PHP que utilizan.

## Componentes y recorrido

```text
formulario → ruta AJAX → middleware → AuthController → AuthModel → ORM
                                         ↓
                                SessionManager o correo
                                         ↓
                                  respuesta JSON
```

| Componente | Responsabilidad |
| --- | --- |
| `AuthController` del módulo `auth-ui` | Leer campos HTTP, coordinar el flujo, preparar correos y respuesta pública |
| `GFrame\Auth\AuthModel` | Validar credenciales, persistir cuentas y gestionar tokens |
| `GFrame\Auth\SessionManager` | Crear, actualizar y destruir la identidad de sesión |
| `RoleModel` y servicios de permisos | Resolver la autorización que se incorpora a la identidad |
| `AuthInstallationService` | Crear la primera cuenta del proyecto durante la instalación |

AuthModel utiliza las tablas normalizadas `users` y `roles`. El nombre, teléfono, avatar y demás datos personales pertenecen al perfil de la aplicación.

## Operaciones del modelo

| Método | Entrada | Resultado principal |
| --- | --- | --- |
| `registerAcount($email, $password)` | Correo y contraseña | `account_registered`, con `data.user_id` y token interno |
| `login($email, $password)` | Credenciales | `authenticated` o `password_change_required`, con `data.user` |
| `validateAcount($token)` | Token de verificación | `account_verified` |
| `verifyAcount($email)` | Correo para reenviar verificación | `verification_requested`, con token si corresponde |
| `recoveryAcount($email)` | Correo para solicitar recuperación | `recovery_requested`, con token interno si corresponde |
| `resetPassword($token, $password)` | Token y nueva contraseña | `password_reset` |

Los nombres `Acount` son los identificadores actuales de la API. Cada operación devuelve `status` y `code`; `data` aparece cuando hay información adicional. El modelo no envía correos ni crea una sesión automáticamente.

Ejemplo de consulta de credenciales desde PHP:

```php
use GFrame\Auth\AuthModel;

$result = (new AuthModel())->login($email, $password);
if (($result['status'] ?? '') === 'success') {
    $user = $result['data']['user'];
    $mustChangePassword = $result['data']['must_change_password'];
}
```

Este ejemplo comprueba credenciales; el controlador estándar añade la autorización y llama a SessionManager antes de responder al navegador. No crees una sesión copiando sin más todo `data.user` en `$_SESSION`.

## Registro y verificación

El registro normaliza el correo, comprueba la política de contraseña y crea una cuenta `unverify` con el rol `registered`. No inicia sesión, no asigna el rol de administrador y no crea tenants ni perfiles del negocio.

AuthController utiliza el token para preparar el enlace `login/verify?v=...` y encola el correo. Al abrirlo, `validateAcount()` activa la cuenta y rota el token. Después se puede iniciar sesión mediante el formulario de acceso.

El registro y el envío son operaciones diferentes: si falla el encolado del correo, la cuenta puede existir ya aunque la respuesta indique `mail_delivery_failed`. Solicita el reenvío de verificación en lugar de repetir la inserción; consulta [Correo](mail.md).

## Inicio de sesión

AuthModel concede acceso únicamente al estado activo, `verify` por defecto. Los estados pendientes, suspendidos o desconocidos no crean una sesión.

`GFrame\Auth\SessionManager` regenera la sesión al acceder, mantiene la identidad normalizada en `$_SESSION['auth']`, admite claves transitorias del proyecto y destruye la sesión al salir. El middleware utiliza `auth.id`, `auth.role_id`, `auth.role`, `auth.permissions`, `auth.role_version` y `auth.bypass`.

El controlador devuelve `redirect`, relativo a la base del sitio, por ejemplo `admin` o `account`. El frontend antepone `site_url` una sola vez. El campo `rd` permite un destino de retorno validado por el servidor; un cambio obligatorio de contraseña tiene prioridad sobre ese retorno.

`invalid_user` no distingue públicamente entre un correo inexistente y una contraseña incorrecta. No aceptes identidad, rol ni permisos enviados por el formulario como autorización.

## Identidad y rutas protegidas

En una acción protegida puedes consultar la identidad:

```php
$identity = $_SESSION['auth'] ?? [];
$userID = (int)($identity['id'] ?? 0);
$email = (string)($identity['email'] ?? '');
```

La identidad incluye también `name` y `authorization_version`. El nombre puede estar vacío; no implica una columna obligatoria en `users`. No contiene la contraseña ni un token de recuperación.

Protege el acceso en la ruta, antes de ejecutar la acción:

```php
use RouteBuilder as Route;

Route::get('area', 'home/AreaController@index')
    ->template('home')
    ->view('areaIndex')
    ->middleware(['auth'])
    ->registerFinal();
```

`auth` exige sesión; `admin`, `role:*` y `can:*` añaden autorización. Leer `auth.id` no sustituye esas comprobaciones. Consulta [Middleware](middleware.md) y [Permisos](permisos.md).

El cierre estándar pasa por `ajax/logout`, protegido por autenticación y CSRF, y llama a `SessionManager::logout()`. Revoca la sesión actual, vacía sus datos y elimina la cookie. La configuración del almacenamiento y la revocación de otros dispositivos se explican en [Sesiones](sesiones.md).

## Cambios de autorización

Los permisos efectivos se cargan al iniciar sesión. `admin`, `role:*` y `can:*` leen esa fotografía sin consultar las tablas de autorización en cada operación. Cada rol conserva `security_version`. Al cambiar sus permisos mediante `RolePermissionService`, la versión aumenta; en la siguiente petición de cada dispositivo el manejador detecta la diferencia, recarga una sola vez los permisos actuales y actualiza su propia sesión sin cerrar el acceso.

Un cambio de rol global revoca las sesiones del usuario porque altera su identidad de autorización. Los cambios de rol por tenant y las excepciones individuales incrementan la versión de autorización del usuario y recargan la sesión en la siguiente operación.

La edición de la cuenta actual utiliza SelfAccountService y UserModel, no el registro de AuthModel. Consulta [Cuenta y seguridad](self-account.md). Las plantillas, excepciones individuales y membresías se explican en [Permisos](permisos.md).

## Cuenta inicial del proyecto

Toda instalación debe crear el rol protegido `superadministrator` y asignarlo al primer usuario. Ese rol pasa `admin` y `can:*` sin asignaciones adicionales, no puede concederse a otra cuenta, degradarse ni eliminarse. Los demás roles obtienen capacidades desde su plantilla JSON; `admin.access` concede acceso al middleware `admin`.

`GFrame\Auth\AuthInstallationService` crea la primera cuenta mediante `UserModel`, la deja verificada y le asigna el rol `superadministrator`. Rechaza nuevas ejecuciones cuando ya existe algún usuario.


## Recuperación y tokens

La solicitud devuelve `recovery_requested` también cuando no existe una cuenta recuperable. El controlador extrae el token interno, lo retira de la respuesta pública y encola el enlace `login/resetpassword?rp=...`. No devuelvas el resultado bruto del modelo al navegador.

`resetPassword()` comprueba token, caducidad y estado, cambia el hash y rota el token. El anterior deja de servir. Las cuentas suspendidas o desactivadas no obtienen acceso mediante recuperación.

TokenManager genera valores aleatorios de 32 bytes, representados en 64 caracteres hexadecimales, y tiene una duración predeterminada de 86 400 segundos. Para cambiarla, inyecta el manager en el modelo:

```php
use GFrame\Auth\AuthModel;
use GFrame\Auth\TokenManager;

$model = new AuthModel(tokens: new TokenManager(3600));
```

La duración se comprueba en `resetPassword()`. `validateAcount()` no comprueba actualmente la fecha del token: no presupongas que el enlace de verificación caduca por configurar TokenManager. Recuperación y verificación comparten las columnas de token de la cuenta; generar uno nuevo reemplaza el anterior.

## Contraseñas y configuración

PasswordPolicy acepta por defecto entre 8 y 72 **bytes UTF-8**, no caracteres, y guarda hashes bcrypt. El indicador visual de fuerza no cambia esa regla. Nunca guardes una contraseña en texto claro ni expongas hashes.

La expiración de contraseñas está desactivada por defecto. Si la activas, `password_changed_at` y `force_password_change` determinan el cambio obligatorio. Integra estas opciones en el bloque `auth` de tu configuración existente:

```php
return [
    'auth' => [
        'login_redirect' => 'admin',
        'password_change_redirect' => 'account',
        'password_expiration' => ['enabled' => true, 'days' => 90, 'warning_days' => 7],
    ],
];
```

El middleware limita las operaciones hasta completar el cambio obligatorio. Cambiar la configuración no reescribe las contraseñas guardadas.

Los esquemas de referencia para MySQL y SQLite están en `resources/database/schema`. El esquema normalizado no utiliza `is_super_admin` ni listas configurables de roles administrativos.

## Ampliar desde el proyecto

Personaliza el controlador en `app/controllers/auth-ui/AuthController.php`, con namespace `App\Controllers\AuthUi`, heredando `GFrame\Modules\AuthUi\Controllers\AuthController`. Para cambiar persistencia o política, hereda AuthModel en un modelo del proyecto e inyéctalo mediante `parent::__construct(auth: $model)`.

Una subclase no sustituye por sí misma los `new AuthModel()` existentes: el controlador debe utilizarla explícitamente. Conserva contratos de sesión, permisos, respuestas y eliminación de tokens. Consulta [Módulos runtime](modulos-runtime.md).

Las rutas públicas del módulo usan `guest`, honeypot y sus exclusiones CSRF declaradas; no copies esas exclusiones a acciones privadas. Las vistas, perfiles y reglas del negocio se mantienen en el proyecto.
