# AOS

`aos` es una librería externa para animaciones al desplazarse. GFrame la distribuye como componente visual opcional; no modifica su código ni impone animaciones a las vistas.

Seleccione `aos` en el instalador o publique sus archivos con `php bin/modules.php publish /ruta/del/proyecto/public aos`. Se copian a `public/vendors/external/aos/`.

Para usarla, declare `aos.css` y `aos.js` en el meta de la vista o del grupo de vistas. La aplicación decide dónde inicializar `AOS` y qué elementos llevan atributos `data-aos`. GFrame no hace esa inicialización automáticamente.

Los archivos de GFrame coinciden con los de Dane y Base Confías. Bebots conserva una variante de `aos.js`; no se trasladó al paquete común.
