# Interfaz de autenticación

El módulo `auth-ui` instala el acceso web estándar de GFrame: inicio y cierre de sesión, registro, verificación del correo, recuperación y restablecimiento de contraseña.

## Instalación

El módulo necesita el esquema `auth`. Al publicarlo, GFrame resuelve también `alerts`, `frontend-core`, `heartbeat-client` y `password-utils`.

```powershell
php bin/modules.php publish-project auth-ui C:\ruta\del\proyecto
```

La publicación agrega las rutas web y AJAX y los activos de autenticación. El controlador, las vistas y la plantilla originales permanecen en el módulo; el proyecto utiliza las carpetas de personalización descritas en [módulos runtime](modulos-runtime.md). Los archivos existentes del proyecto no se sobrescriben salvo que se solicite expresamente.

## Rutas incluidas

| Método | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/login` | Iniciar sesión |
| `GET` | `/login/register` | Crear una cuenta |
| `GET` | `/login/lostpassword` | Solicitar recuperación |
| `GET` | `/login/resetpassword?rp=...` | Establecer una contraseña nueva |
| `GET` | `/login/verify?v=...` | Verificar una cuenta |
| `POST` | `/ajax/login` | Procesar el acceso |
| `POST` | `/ajax/logout` | Cerrar la sesión |
| `POST` | `/ajax/register` | Procesar el registro |
| `POST` | `/ajax/verifyacount` | Reenviar el enlace de verificación desde el JS original |
| `POST` | `/ajax/validateacount` | Validar el token desde el JS original |
| `POST` | `/ajax/lostpassword` | Solicitar recuperación desde el JS original |
| `POST` | `/ajax/resetpassword` | Aplicar la contraseña nueva desde el JS original |
| `POST` | `/ajax/verification` | Reenviar el enlace de verificación |
| `POST` | `/ajax/recovery` | Solicitar el correo de recuperación |
| `POST` | `/ajax/reset-password` | Aplicar la contraseña nueva |

Las rutas `verifyacount`, `validateacount`, `lostpassword` y `resetpassword` son parte del contrato del módulo. Las rutas con nombres nuevos quedan como alias de compatibilidad. Si se cambia el nombre de una ruta o de un parámetro, hay que documentar la equivalencia, actualizar todas las llamadas del JavaScript y las vistas, y comprobar el flujo completo antes de publicar el módulo. No basta con renombrar la ruta del servidor.

Las operaciones públicas usan `guest`, protección de mismo origen para AJAX y `honeypot`. Se excluye CSRF porque el token se genera al iniciar sesión. El cierre de sesión exige autenticación y conserva el CSRF automático.

### Campos de los formularios

| Operación | Campos que lee el controlador |
| --- | --- |
| Login | `login_email`, `login_password`, `rd` opcional |
| Registro | `register_email`, `register_password` |
| Reenvío de verificación | `login_email` |
| Validación por AJAX | `vtoken` |
| Recuperación | `recovery_email` |
| Restablecimiento | `rpuser_token` (alias `reset_token`), `reset_password` |
| Logout | Tokens CSRF del contrato de sesión |

Conserva también el campo honeypot `middle_name` y los IDs que usan los scripts si sustituyes una vista. Cambiar únicamente el atributo `name` puede dejar un formulario visualmente correcto cuyo controlador recibe campos vacíos.

La página de restablecimiento obtiene el token de `rp` en la URL; la verificación web utiliza `v`. El registro no inicia sesión automáticamente: crea una cuenta pendiente y solicita el envío del enlace. Consulta [Autenticación](autenticacion.md) para estados de cuenta y contratos del modelo.

## Configuración

```php
'auth' => [
    'login_redirect' => 'admin',
    'password_change_redirect' => 'account',
    'password_expiration' => [
        'enabled' => false,
        'days' => 90,
        'warning_days' => 7,
    ],
],
```

`login_redirect` define el destino habitual. Si no se configura, el acceso lleva a `/admin`, publicado por `admin-panel`. Cuando `AuthModel` devuelve `must_change_password`, se utiliza `password_change_redirect`; su valor predeterminado es `/account`, del módulo `self-account`.

Si se activa la expiración o se utiliza `force_password_change`, la aplicación debe instalar `self-account` o reemplazar esa ruta por una pantalla equivalente.

## Contrato de respuestas

Las acciones devuelven arreglos estables. El controlador no lanza excepciones hacia la vista.

El canal AJAX devuelve JSON también ante errores como `invalid_token`, `expired` o `forbidden`; el router no sustituye esa respuesta por una página HTML. Los módulos pueden incluir un fragmento renderizado en `html`. En Auth se conserva el patrón original: el servidor devuelve `status`, `code` y `message`, y el JS construye las opciones de SweetAlert según el código. Las páginas web de error siguen siendo HTML.

```php
[
    'status' => 'success|error|unauthorized',
    'code' => 'codigo_estable',
    'message' => 'Mensaje apto para el usuario',
]
```

El acceso exitoso añade `redirect` y `data.must_change_password`. El campo `redirect` siempre contiene una ruta relativa a la raíz de la aplicación, sin barra inicial, dominio ni protocolo: `admin`, `account` o `admin/items?page=2`. El cierre devuelve `login`. Todos los consumidores construyen el destino con `site_url + response.redirect`; no aceptan alternativamente una URL absoluta. El retorno `rd` se valida en el servidor y el cambio obligatorio de contraseña tiene prioridad.

`AuthModel` conserva `status` y `code` en la raíz y coloca los datos de la operación en `data`: `data.user`, `data.first_login`, `data.must_change_password`, `data.user_id` y `data.token`, según la operación. El controlador elimina `data.token` y el identificador interno del registro antes de responder al navegador. Las operaciones sin datos adicionales pueden omitir `data`, igual que los otros servicios. Este cambio requiere actualizar los consumidores de Auth al mismo tiempo; no se duplican las claves antiguas fuera de `data`.

Esta regla sustituye el comportamiento anterior, que devolvía URLs absolutas en Auth. Al actualizar una aplicación, deben actualizarse juntos el controlador y todos los JS que consumen `redirect`. Los enlaces completos de correos, activos y cabeceras HTTP se construyen a partir de estas rutas cuando su uso requiere una URL completa; no son variantes del campo `redirect`.

Los servicios registran internamente las excepciones y devuelven `status` y `code`; el controlador agrega el mensaje. La vista o JavaScript decide si lo presenta en el formulario, mediante `alertToast`, `swalAlert` o una vista de error.

## Personalización y extensión

Declare una subclase en `app/controllers/auth-ui/AuthController.php`, con namespace `App\Controllers\AuthUi`, que extienda `GFrame\Modules\AuthUi\Controllers\AuthController`. Inyecte su modelo propio en el constructor mediante `parent::__construct(...)`. Consulte [herencia y migración](modulos-runtime.md) y [extensión desde proyectos](extensibilidad.md#herencia-de-controladores-de-módulos). Las URLs y el contrato MVC no cambian.

Los originales permanecen en el módulo. Cree solo las personalizaciones que necesite:

- `app/controllers/auth-ui/AuthController.php`: reglas del proyecto y envío de correos;
- `app/views/auth-ui`: campos y contenido de las pantallas;
- `app/views/templates/authTemplate.php`: estructura visual;
- `public/css/modules/auth/auth.css`: apariencia;
- `public/js/modules/auth/AuthLogin.js`, `AuthRegister.js`, `AuthLostpassword.js` y `AuthResetpassword.js`: interacción y presentación de respuestas.

El constructor de `AuthController` admite un modelo `AuthModel` y un `SessionManager` alternativos. Esto permite probar el controlador o sustituir el acceso a usuarios sin cambiar las rutas.

Ejemplo mínimo para aportar un nombre de destinatario desde el perfil del proyecto:

```php
<?php
namespace App\Controllers\AuthUi;

class AuthController extends \GFrame\Modules\AuthUi\Controllers\AuthController
{
    protected function mailRecipientName(string $email): string
    {
        // Consulta aquí el perfil propio por correo, sin alterar la tabla users.
        return parent::mailRecipientName($email);
    }
}
```

Para un modelo propio, construye su instancia en el constructor y llama a `parent::__construct(auth: $model)`. El modelo debe conservar el contrato de AuthModel; heredar una clase por sí solo no sustituye las instancias que utiliza el controlador original.

Las metas de las vistas originales están junto a cada vista en el módulo. Una meta personalizada en `app/views/auth-ui/` permite añadir CSS o JavaScript del proyecto. Los assets publicados son gestionados por el actualizador: utiliza archivos adicionales del proyecto en lugar de editar sus originales cuando quieras conservar ajustes entre versiones.

Los consentimientos legales, perfiles, planes, áreas, datos personales y acciones posteriores al registro pertenecen a la aplicación. Pueden añadirse al controlador personalizado sin incorporar esas reglas al framework.

## Correos

El registro, el reenvío de verificación y la recuperación envían enlaces absolutos construidos con `site_url`, usando `mailTemplate` y `MailService::sendTemplateAsync()`. La respuesta confirma la entrega al ejecutor asíncrono, no la recepción del correo; el worker registra fallos posteriores. Para aplicar otra plantilla, adapta `sendAccessMail()` en el controlador personalizado o delega el envío a un servicio propio. Nunca devuelvas al navegador la excepción del transporte.

El asunto identifica la operación y el proyecto; el cuerpo saluda al destinatario sin repetir el enlace del botón. `mailRecipientName()` utiliza `name` si lo proporciona el modelo y, en su ausencia, el identificador del correo antes de `@`. Un controlador personalizado puede sobrescribir este método para consultar el perfil propio sin añadir campos al esquema estándar.

## Comportamiento de la interfaz


GFrame publica los cuatro archivos JS originales de las pantallas de Auth. El cierre/inactividad está en `heartbeat-client/session.js`; el panel aporta el control de cierre, pero no duplica su envío.

Los formularios y scripts mantienen estas conexiones:

- El meta carga CSS/JS de `password-utils`; registro y restablecimiento conectan generador, medidor, validación de 8–72 bytes UTF-8 y mostrar/ocultar. La fuerza es orientativa y el backend valida de nuevo. Si no hay API criptográfica, el generador deja el campo para entrada manual.
- Los formularios usan `needs-validation`, `validationFeedback` con objeto jQuery y destinos `.validation_<id>`. Los envíos usan jQuery AJAX; mensajes normales pasan por `alertToast` y decisiones de autenticación por `swalAlert`, conservando los códigos exactos.
- El único cierre está en `heartbeat-client/session.js`, con `[data-gf-logout]`, confirmación, tokens globales, presentación de fallos y notificación mediante BroadcastChannel y storage. El panel no duplica ese envío. Sin almacenamiento, el canal sigue funcionando si está disponible.
- `rd` viaja con el login; el controlador solo admite rutas relativas dentro de la aplicación, sin esquemas, barras iniciales ni segmentos de recorrido. Un destino inválido usa la redirección configurada. El cambio obligatorio de contraseña siempre prevalece.
- El reenvío usa `/ajax/verifyacount`, `AuthModel::verifyAcount()` y Mail. El token se elimina de la respuesta pública. `/ajax/verification` permanece como alias.
- `auth.css` conserva la estructura de las pantallas de autenticación y las variables compartidas de Bootstrap. La plantilla utiliza el logotipo original de GFrame publicado en `public/img/logo.png`; la aplicación puede personalizar el fondo.


## Reglas de seguridad

### Sesiones y estados de cuenta

El saludo del panel usa el nombre que aporte el perfil del proyecto; si no existe, muestra la parte del correo anterior a `@`. Este respaldo se calcula al renderizar y no se guarda como nombre en la sesión ni exige una columna `name` en `users`.

Si otra pestaña ya inició sesión, `auth.js` reconoce `already_logged` tanto en JSON normal como en un fallo HTTP con `responseJSON`. Continúa por `/login`, dejando al middleware web resolver el acceso autenticado; no concatena `rd` sin validación.

`AuthModel::updateAuthUser()` acepta resultados `updated` y `no_change`, pero rechaza una fila inexistente. El modelo de autenticación captura `Exception` y devuelve errores estables; no envía detalles técnicos al navegador. `UserModel` conserva su actualización de cuentas para administración y Mi cuenta, sin ejecutar los flujos de login, registro, verificación ni recuperación.

Al cambiar el estado a `suspended` o `disabled`, el modelo genera un token nuevo y actualiza su fecha en la misma escritura. `setActive(false)` y la desactivación de cuenta pasan por esa operación. Reactivar no restaura los enlaces viejos. Recuperación no emite enlaces para esos estados y verificación/restablecimiento rechazan cuentas bloqueadas. Las implementaciones propias deben mantener esas garantías; cambiar directamente el estado mediante SQL evita la protección.

La rotación invalida enlaces. La revocación de sesiones es independiente y la ejecutan `UserAdministrationService` y `SelfAccountService` mediante el registro administrado de sesiones; heartbeat solo comprueba si la sesión sigue existiendo.

El esqueleto inicia `SessionRuntime`. Las aplicaciones con base de datos utilizan el driver `database`; las instalaciones de alta concurrencia pueden cambiar a `redis`. Ambos relacionan cada usuario con hashes de sus identificadores de sesión, nunca con los identificadores en claro. Suspender o desactivar bloquea al usuario y elimina todas sus sesiones; reactivar retira el bloqueo sin restaurarlas. Un fallo del registro se transforma en el contrato estable de la operación correspondiente.


- No revele si una dirección desconocida existe durante la recuperación.
- Mantenga el estado suspendido fuera de los flujos de acceso y verificación.
- No agregue contraseñas ni tokens a las respuestas públicas.
- Conserve `block_external_ajax`, `guest`, `auth` y `honeypot` en sus rutas.
- Presente mensajes públicos a partir de `code`; registre el detalle técnico solo en el servidor.
