# html2canvas

Librería externa opcional para capturar una región HTML como lienzo. GFrame distribuye la versión 1.4.0 sin modificarla.

Seleccione `html2canvas` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public html2canvas`. Cargue `public/vendors/external/html2canvas/html2canvas.min.js` en el meta de la vista que necesite capturas. La aplicación decide qué elemento capturar y cómo utilizar el resultado.

## Capturar un elemento

```js
html2canvas(document.querySelector('#preview')).then(function (canvas) {
  document.querySelector('#captureResult').replaceChildren(canvas);
}).catch(function () {
  alertToast('No se pudo crear la captura.', 'error');
});
```

El ejemplo requiere `preview` y `captureResult`, además del módulo Alerts para el feedback. Se genera un canvas, no un PDF ni una captura exacta del navegador. Los recursos externos deben cumplir las restricciones de origen; `useCORS` no evita las reglas CORS del servidor. No capture contraseñas ni información privada innecesaria.

No tiene puente propio: utiliza los estilos del DOM capturado. [Documentación oficial y limitaciones](https://html2canvas.hertzen.com/documentation).
