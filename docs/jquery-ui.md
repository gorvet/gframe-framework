# jQuery UI

Librería externa opcional para interacciones basadas en jQuery. El módulo `jquery-ui` declara `jquery` como dependencia y publica sus archivos en `public/vendors/external/jquery-ui/`.

Seleccione `jquery-ui` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public jquery-ui`. Cargue `jquery-ui.css` y `jquery-ui.min.js` en el meta de la vista, después de jQuery. El paquete incluye `jquery.ui.touch-punch.min.js` para las pantallas que lo necesiten. La aplicación decide qué interacción inicializar.
