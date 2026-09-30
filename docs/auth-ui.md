# Interfaz de autenticación

El módulo `auth-ui` instala el acceso web estándar de GFrame: inicio y cierre de sesión, registro, verificación del correo, recuperación y restablecimiento de contraseña.

## Instalación

El módulo necesita el esquema `auth`. Al publicarlo, GFrame resuelve también `alerts`, `frontend-core`, `heartbeat-client` y `password-utils`.

```powershell
php bin/modules.php publish-project auth-ui C:\ruta\del\proyecto
```

La publicación agrega el controlador, las rutas web y AJAX, las vistas, la plantilla y los activos de autenticación. Los archivos existentes del proyecto no se sobrescriben salvo que se solicite expresamente.

## Rutas incluidas

| Método | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/login` | Iniciar sesión |
| `GET` | `/login/register` | Crear una cuenta |
| `GET` | `/login/recovery` | Solicitar recuperación |
| `GET` | `/login/reset?token=...` | Establecer una contraseña nueva |
| `GET` | `/login/verify?v=...` | Verificar una cuenta |
| `POST` | `/ajax/login` | Procesar el acceso |
| `POST` | `/ajax/logout` | Cerrar la sesión |
| `POST` | `/ajax/register` | Procesar el registro |
| `POST` | `/ajax/verification` | Reenviar el enlace de verificación |
| `POST` | `/ajax/recovery` | Solicitar el correo de recuperación |
| `POST` | `/ajax/reset-password` | Aplicar la contraseña nueva |

Las operaciones públicas usan `guest`, protección de mismo origen para AJAX y `honeypot`. Se excluye CSRF porque el token se genera al iniciar sesión. El cierre de sesión exige autenticación y conserva el CSRF automático.

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

`login_redirect` define el destino habitual. Cuando `AuthService` devuelve `must_change_password`, se utiliza `password_change_redirect`; su valor predeterminado es `/account`, del módulo `self-account`.

Si se activa la expiración o se utiliza `force_password_change`, la aplicación debe instalar `self-account` o reemplazar esa ruta por una pantalla equivalente.

## Contrato de respuestas

Las acciones devuelven arreglos estables. El controlador no lanza excepciones hacia la vista.

```php
[
    'status' => 'success|error|unauthorized',
    'code' => 'codigo_estable',
    'message' => 'Mensaje apto para el usuario',
]
```

El acceso exitoso añade `redirect` y `must_change_password`. Los servicios registran internamente las excepciones y devuelven `status` y `code`; el controlador agrega el mensaje. La vista o JavaScript decide si lo presenta en el formulario, mediante `alertToast`, `swalAlert` o una vista de error.

## Personalización y extensión

Los archivos publicados pertenecen a la aplicación y pueden adaptarse allí:

- `app/controllers/auth/AuthController.php`: reglas del proyecto y envío de correos;
- `app/views/auth`: campos y contenido de las pantallas;
- `app/views/templates/authTemplate.php`: estructura visual;
- `public/css/modules/auth/auth.css`: apariencia;
- `public/js/modules/auth/auth.js`: interacción y presentación de respuestas.

El constructor de `AuthController` admite un `AuthService` y un `SessionManager` alternativos. Esto permite probar el controlador o sustituir el acceso a usuarios sin cambiar las rutas.

Los consentimientos legales, perfiles, planes, áreas, datos personales y acciones posteriores al registro pertenecen a la aplicación. Pueden añadirse al controlador publicado sin incorporar esas reglas al framework.

## Correos

El registro, el reenvío de verificación y la recuperación envían enlaces absolutos construidos con `site_url`, usando `mailTemplate` y `MailService::sendTemplateAsync()`. La respuesta confirma la entrega al ejecutor asíncrono, no la recepción del correo; el worker registra fallos posteriores. Para aplicar otra plantilla, adapta `sendAccessMail()` en el controlador publicado o delega el envío a un servicio propio. Nunca devuelvas al navegador la excepción del transporte.

## Auditoría conjunta del frontend

Se cotejaron `AuthLogin.js`, `AuthLogout.js`, `AuthLostpassword.js`, `AuthRegister.js`, `AuthResetpassword.js` y `auth.css` de Base Confías, Bebots y Dane. La lógica JS de acceso, recuperación y registro coincide salvo diferencias de archivo sin cambios funcionales en el cotejo. Restablecimiento coincide en las tres fuentes. El cierre de Bebots admite el aviso fuera de vistas protegidas; Base Confías y Dane lo restringen a vistas protegidas. El CSS reciente de Base Confías incorpora más estructura y accesibilidad; Bebots y Dane conservan una variante anterior. La identidad y el fondo fotográfico de Base Confías no se deben copiar como valores obligatorios del framework.

En GFrame los flujos públicos se agrupan en `auth.js`; la verificación se resuelve por ruta web y el cierre/inactividad está en `heartbeat-client/session.js`. El panel aporta el control de cierre, pero no duplica su envío. No hace falta copiar los cinco archivos antiguos ni conservar sus nombres para cubrir sus capacidades.

Conexiones restauradas tomando Bebots como referencia funcional:

- El meta carga CSS/JS de `password-utils`; registro y restablecimiento conectan generador, medidor, validación de 8–72 bytes UTF-8 y mostrar/ocultar. La fuerza es orientativa y el backend valida de nuevo. Si no hay API criptográfica, el generador deja el campo para entrada manual.
- Los formularios usan `needs-validation`, `validationFeedback` con objeto jQuery y destinos `.validation_<id>`. Los envíos usan jQuery AJAX; mensajes normales pasan por `alertToast` y decisiones de autenticación por `swalAlert`, conservando los códigos exactos.
- El único cierre está en `heartbeat-client/session.js`, con `[data-gf-logout]`, confirmación, tokens globales, presentación de fallos y notificación mediante BroadcastChannel y storage. El panel no duplica ese envío. Sin almacenamiento, el canal sigue funcionando si está disponible.
- `rd` viaja con el login; el controlador solo admite rutas relativas dentro de la aplicación, sin esquemas, barras iniciales ni segmentos de recorrido. Un destino inválido usa la redirección configurada. El cambio obligatorio de contraseña siempre prevalece.
- El reenvío usa `/ajax/verification`, `AuthService::requestVerification()` y Mail. El token se elimina de la respuesta pública. El aviso de cuenta sin verificar aporta el botón desde un fragmento PHP, no HTML generado en JS.
- `auth.css` parte del CSS de Bebots, adapta selectores al ámbito auth y utiliza variables compartidas de Bootstrap. El meta carga `variables.css`; se elimina la paleta paralela `--gframe-auth-*`. No se copia el fondo fotográfico de Base Confías.

Se añaden pruebas de comportamiento JS para validación, doble envío, retorno, reenvío y cierre entre pestañas; pruebas PHP verifican el destino seguro, meta y plantilla de reenvío sin exponer el token. La revisión visual HTTP y la entrega real por SMTP siguen pendientes. El cotejo general de todos los módulos se realizará al final, según lo acordado.

## Reglas de seguridad

### Cotejo final con Bebots

El saludo del panel usa el nombre que aporte el perfil del proyecto; si no existe, muestra la parte del correo anterior a `@`. Este respaldo se calcula al renderizar y no se guarda como nombre en la sesión ni exige una columna `name` en `users`.

Si otra pestaña ya inició sesión, `auth.js` reconoce `already_logged` tanto en JSON normal como en un fallo HTTP con `responseJSON`. Continúa por `/login`, dejando al middleware web resolver el acceso autenticado; no concatena `rd` sin validación.

`UserModel::updateAuthUser()` acepta resultados `updated` y `no_change`, pero rechaza una fila inexistente. Los servicios capturan `Exception` y devuelven errores estables; no envían detalles técnicos al navegador.

Al cambiar el estado a `suspended` o `disabled`, el modelo genera un token nuevo y actualiza su fecha en la misma escritura. `setActive(false)` y la desactivación de cuenta pasan por esa operación. Reactivar no restaura los enlaces viejos. Recuperación no emite enlaces para esos estados y verificación/restablecimiento rechazan cuentas bloqueadas. Las implementaciones propias deben mantener esas garantías; cambiar directamente el estado mediante SQL evita la protección.

La rotación invalida enlaces. La revocación de sesiones es independiente y la ejecutan `UserAdministrationService` y `SelfAccountService` mediante el registro administrado de sesiones; heartbeat solo comprueba si la sesión sigue existiendo.

El esqueleto inicia `SessionRuntime`. Las aplicaciones con base de datos utilizan el driver `database`; las instalaciones de alta concurrencia pueden cambiar a `redis`. Ambos relacionan cada usuario con hashes de sus identificadores de sesión, nunca con los identificadores en claro. Suspender o desactivar bloquea al usuario y elimina todas sus sesiones; reactivar retira el bloqueo sin restaurarlas. Un fallo del registro se transforma en el contrato estable de la operación correspondiente.

Las pruebas cubren el saludo sin persistencia de nombre, `already_logged` por ambos caminos HTTP, escrituras fallidas, suspensión posterior a recuperación y rotación real en SQLite. Sigue pendiente la validación visual y HTTP en instalaciones limpias.

- No revele si una dirección desconocida existe durante la recuperación.
- Mantenga el estado suspendido fuera de los flujos de acceso y verificación.
- No agregue contraseñas ni tokens a las respuestas públicas.
- Conserve `block_external_ajax`, `guest`, `auth` y `honeypot` en sus rutas.
- Presente mensajes públicos a partir de `code`; registre el detalle técnico solo en el servidor.
