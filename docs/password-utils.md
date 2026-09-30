# Password Utils

`password-utils` reúne la política de contraseña del backend y las utilidades JavaScript y CSS del frontend. La validación obligatoria reside en `GFrame\Auth\PasswordPolicy`, ubicada en `resources/modules/password-utils/src/` y cargada por Composer. Se aplica en el servidor al registrar usuarios, restablecer contraseñas y cambiarlas desde Mi cuenta. No sustituya esa validación por el JavaScript.

## Instalación

`auth-ui` incluye `password-utils` como dependencia. También puede seleccionarlo por separado en el instalador o publicarlo con `php bin/modules.php publish /ruta/del/proyecto/public password-utils`. Incluya `public/vendors/internal/passwordUtils/passwordUtils.css` y `passwordUtils.js` en el meta de las vistas que usen la interfaz. El indicador y la función `showPassword()` requieren jQuery; la generación y validación de longitud no. La clase PHP forma parte del paquete Composer y no se copia al proyecto.

## Regla de aceptación

La política actual acepta entre 8 y 72 **bytes UTF-8**. `passwordValidate(password)` aplica esos mismos límites en el navegador mediante `TextEncoder`. El servidor vuelve a comprobar la contraseña porque cualquier validación de cliente puede omitirse. El parámetro `email` se conserva en la firma JavaScript para no romper llamadas existentes, pero no altera esta regla.

`evaluatePassword(password, email)` produce una puntuación orientativa que premia longitud y variedad y penaliza coincidencias con el correo. `updateMeterPassword(password, email)` presenta esa puntuación en los elementos `.passwordMeter` como «Débil», «Media» o «Fuerte». Una contraseña marcada «Débil» todavía puede cumplir la política obligatoria; el medidor no debe bloquear su envío.

## Generar contraseñas

`generatePassword(length = 18)` devuelve una cadena con minúsculas, mayúsculas, cifras y símbolos. Una longitud fuera del intervalo de 8 a 72 caracteres vuelve al valor predeterminado de 18. Usa `window.crypto.getRandomValues()`; si esta API no está disponible, devuelve `null` y **no** recurre a `Math.random()`.

```js
const generated = generatePassword();
if (generated === null) {
  alertToast('No se puede generar una contraseña segura en este navegador.', 'error');
} else {
  document.querySelector('#register_password').value = generated;
  updateMeterPassword(generated, document.querySelector('#register_email').value);
}
```

La vista elige cómo mostrar ese aviso: `alertToast`, `swalAlert` o un mensaje junto al campo. No envíe una cadena vacía como si fuese una contraseña generada. La generación no guarda ni transmite la contraseña.

## Mostrar u ocultar el campo

`showPassword('.pswd', '.showPassword')` registra un controlador delegado. Dentro de cada `.input-group`, el botón cambia el `type` del campo entre `password` y `text` y su texto entre «Mostrar» y «Ocultar». Si no hay grupo, actúa sobre el primer campo que coincida con el selector. Use un botón `type="button"` para evitar envíos accidentales.

## Personalización

Las clases `.passwordMeter`, `.weak`, `.medium` y `.strong` pueden recibir estilos propios del proyecto. `updateMeterPassword()` busca todos los elementos `.passwordMeter` de la página; si hay varios formularios visibles a la vez, conviene integrar el indicador por formulario antes de reutilizar esa función sin cambios. No copie la puntuación al backend: allí la única fuente de aceptación es `PasswordPolicy`.
