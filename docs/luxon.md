# Luxon

Librería externa opcional para trabajar con fechas, duraciones y zonas horarias. GFrame distribuye `luxon.min.js` en `public/vendors/external/luxon/`.

Seleccione `luxon` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public luxon`. Cargue el archivo JavaScript en el meta de las vistas que lo utilicen. La aplicación define los formatos y las reglas de fecha; el módulo solo proporciona la dependencia.

## Versión y zonas horarias

El archivo distribuido identifica **3.6.1** mediante `luxon.VERSION`, aunque el manifiesto no declara versión.

```js
const localDate = luxon.DateTime.fromISO('2026-10-05T09:30', {
  zone: 'America/Havana'
});
if (localDate.isValid) {
  const utcDate = localDate.toUTC().toISO();
  console.log(utcDate);
}
```

Declare la zona de entrada cuando la fecha no incluya offset y compruebe `isValid`. Convertir a UTC no implica guardar datos ni configurar la zona del servidor. La biblioteca no requiere puente visual.

[Documentación oficial](https://moment.github.io/luxon/). Compruebe la compatibilidad con la versión 3.6.1 distribuida.
