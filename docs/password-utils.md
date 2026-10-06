# Password Utils

`password-utils` reúne la política de contraseña del backend y las utilidades JavaScript y CSS del frontend. La validación obligatoria reside en `GFrame\Auth\PasswordPolicy`, ubicada en `resources/modules/password-utils/src/` y cargada por Composer. Se aplica en el servidor al registrar usuarios, restablecer contraseñas y cambiarlas desde Mi cuenta. No sustituya esa validación por el JavaScript.

## Instalación

`auth-ui` incluye `password-utils` como dependencia. También puede seleccionarlo por separado en el instalador o publicarlo con `php bin/modules.php publish /ruta/del/proyecto/public password-utils`. Incluya `public/vendors/internal/passwordUtils/passwordUtils.css` y `passwordUtils.js` en el meta de las vistas que usen la interfaz. El indicador y la función `showPassword()` requieren jQuery; la generación y validación de longitud no. La clase PHP forma parte del paquete Composer y no se copia al proyecto.

## Regla de aceptación

### Carga en una vista

En la meta del grupo o de la vista, declare los recursos después de jQuery, si este no está ya cargado por la plantilla:

```php
<?php

return [
    'css' => ['public/vendors/internal/passwordUtils/passwordUtils.css'],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/internal/passwordUtils/passwordUtils.js',
        'public/js/app/account/password.js',
    ],
];
```

`password.js` contiene la integración del proyecto, como la del siguiente ejemplo. El módulo no inicializa campos ni medidores automáticamente.

### Límites y puntuación

La política actual acepta entre 8 y 72 **bytes UTF-8**. `passwordValidate(password)` aplica esos mismos límites en el navegador mediante `TextEncoder`. El servidor vuelve a comprobar la contraseña porque cualquier validación de cliente puede omitirse. El parámetro `email` se conserva en la firma JavaScript para no romper llamadas existentes, pero no altera esta regla.

`evaluatePassword(password, email)` produce una puntuación orientativa que premia longitud y variedad y penaliza coincidencias con el correo. `updateMeterPassword(password, email)` presenta esa puntuación en los elementos `.passwordMeter` como «Débil», «Media» o «Fuerte». Una contraseña marcada «Débil» todavía puede cumplir la política obligatoria; el medidor no debe bloquear su envío.

Los límites en bytes no equivalen a `minlength` y `maxlength` de HTML para todos los caracteres. Por ejemplo, ocho letras ASCII ocupan ocho bytes, mientras que cuatro letras `á` también ocupan ocho bytes UTF-8. No recorte, normalice ni transforme la contraseña antes de comprobarla y guardarla.

## Campo y medidor

```html
<label for="account_password">Nueva contraseña</label>
<div class="input-group">
  <input id="account_password" name="password" class="form-control pswd"
         type="password" autocomplete="new-password" required>
  <button type="button" class="btn btn-outline-secondary showPassword">Mostrar</button>
</div>
<div class="passwordMeter d-none" aria-live="polite"></div>
<div id="account_passwordFeedback" class="invalid-feedback"></div>
```

```js
$(function () {
  showPassword('.pswd', '.showPassword');

  const password = document.querySelector('#account_password');
  function updatePassword() {
    const value = password.value;
    password.setCustomValidity(
      value && !passwordValidate(value)
        ? 'La contraseña debe ocupar entre 8 y 72 bytes UTF-8.'
        : ''
    );
    updateMeterPassword(value, '');
  }

  password.addEventListener('input', updatePassword);
  updatePassword();
});
```

Este ejemplo calcula el medidor y añade la regla de bytes a la validez nativa. Integre después el envío con la validación y el feedback de [Frontend core](frontend-core.md). El campo vacío sigue sujeto a `required`. Si hay un correo editable, páselo a `updateMeterPassword()` y vuelva a actualizar el indicador cuando cambie; la regla obligatoria no depende del correo.

## Generar contraseñas

`generatePassword(length = 18)` devuelve una cadena con minúsculas, mayúsculas, cifras y símbolos. Una longitud fuera del intervalo de 8 a 72 caracteres vuelve al valor predeterminado de 18. Usa `window.crypto.getRandomValues()`; si esta API no está disponible, devuelve `null` y **no** recurre a `Math.random()`.

```js
const generated = generatePassword();
if (generated === null) {
  alertToast('No se puede generar una contraseña segura en este navegador.', 'error');
} else {
  document.querySelector('#register_password').value = generated;
  document.querySelector('#register_password').dispatchEvent(new Event('input', { bubbles: true }));
}
```

La vista elige cómo mostrar ese aviso: `alertToast`, `swalAlert` o un mensaje junto al campo. No envíe una cadena vacía como si fuese una contraseña generada. La generación no guarda ni transmite la contraseña.

El ejemplo supone un campo `register_password` con su listener de validación y medidor. Asignar `.value` no dispara `input` por sí solo. El generador utiliza caracteres ASCII, de modo que su longitud coincide con los bytes exigidos por la política.

## Mostrar u ocultar el campo

`showPassword('.pswd', '.showPassword')` registra un controlador delegado. Dentro de cada `.input-group`, el botón cambia el `type` del campo entre `password` y `text` y su texto entre «Mostrar» y «Ocultar». Si no hay grupo, actúa sobre el primer campo que coincida con el selector. Use un botón `type="button"` para evitar envíos accidentales.

La delegación permite usar botones insertados por AJAX. Repetir la llamada con el mismo selector de botón reemplaza su listener, sin acumularlo. La función sustituye todo el texto del botón, por lo que un icono colocado dentro de este no se conserva al alternar.

## Uso en el backend

```php
<?php

use GFrame\Auth\PasswordPolicy;

$policy = new PasswordPolicy();
$password = 'MiClaveSegura2026!';

if (!$policy->accepts($password)) {
    throw new InvalidArgumentException('La contraseña debe ocupar entre 8 y 72 bytes UTF-8.');
}

$hash = $policy->hash($password);
$matches = password_verify($password, $hash);
```

`hash()` utiliza bcrypt con coste `12`, pero no llama a `accepts()` internamente. Compruebe la aceptación antes de generar el hash. Guarde únicamente el hash y verifique la contraseña con `password_verify()`; no compare hashes recién generados, porque incorporan una sal aleatoria.

La clase es `final`. Su constructor admite límites distintos, pero las utilidades JavaScript conservan 8–72 bytes y los flujos nativos de autenticación utilizan su política estándar. Crear otra instancia no cambia esos flujos automáticamente. Una política diferente requiere integrar coherentemente la validación del servidor y del formulario; no amplíe el máximo de bcrypt sin considerar su límite de 72 bytes.

## Personalización

Las clases `.passwordMeter`, `.weak`, `.medium` y `.strong` pueden recibir estilos propios del proyecto. `updateMeterPassword()` busca todos los elementos `.passwordMeter` de la página; si hay varios formularios visibles a la vez, conviene integrar el indicador por formulario antes de reutilizar esa función sin cambios. No copie la puntuación al backend: allí la única fuente de aceptación es `PasswordPolicy`.

Los colores del medidor utilizan las variables de Bootstrap/GFrame para peligro, aviso y éxito. Añada los ajustes visuales en el CSS del proyecto, sin editar los archivos publicados del módulo. No registre contraseñas en logs ni las incluya en URLs o respuestas AJAX.
