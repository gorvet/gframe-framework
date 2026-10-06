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


## Recursos, CORS y fuentes

La captura reconstruye la apariencia del DOM en un canvas; no toma una fotografía del navegador. Imágenes, fuentes y otros recursos remotos pueden quedar fuera si no permiten el acceso necesario. `useCORS` solicita el uso de CORS, pero el servidor remoto debe responder con cabeceras compatibles.

Espere a que imágenes y fuentes necesarias estén cargadas antes de capturar cuando la fidelidad sea importante. Una captura iniciada demasiado pronto puede utilizar dimensiones o tipografías de fallback.

## Escala, tamaño y memoria

Capturas grandes consumen memoria proporcional al tamaño del canvas. Evite capturar páginas completas de forma indiscriminada en móviles o equipos con poca memoria. Delimite el elemento necesario y ajuste la escala solo cuando exista una razón concreta.

Si necesita descargar una imagen, convierta el canvas en el formato requerido después de la captura. Esa conversión pertenece a la aplicación; el módulo no añade almacenamiento, descarga ni generación de PDF.

## Contenido dinámico

Capture después de que la vista haya terminado de renderizar y de que los componentes visuales relevantes estén en su estado final. Elementos ocultos, overlays temporales o animaciones pueden producir un resultado distinto al esperado.

Para una vista que actualiza un preview mediante AJAX, espere a que el fragmento se inserte y sus imágenes terminen de cargar antes de invocar `html2canvas()`.

## Privacidad y seguridad

No capture áreas que puedan contener contraseñas, tokens, correos privados o datos de otros usuarios si la imagen va a compartirse o descargarse. La captura ocurre en el navegador, pero el resultado puede terminar fuera del contexto protegido de la aplicación.

## Diagnóstico

Si faltan imágenes, revise CORS y rutas. Si el resultado aparece con otra tipografía, confirme la carga de fuentes. Si la captura está recortada, revise dimensiones y overflow del elemento. Si el navegador se bloquea, reduzca el área o la escala antes de asumir un error del framework.
