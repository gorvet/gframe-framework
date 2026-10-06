# Luxon

Librería externa opcional para trabajar con fechas, duraciones y zonas horarias. GFrame distribuye `luxon.min.js` en `public/vendors/external/luxon/`.

Seleccione `luxon` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public luxon`. Cargue el archivo JavaScript en el meta de las vistas que lo utilicen. La aplicación define los formatos y las reglas de fecha; el módulo solo proporciona la dependencia.

## Versión y zonas horarias

La versión distribuida es **3.6.1**, identificada mediante `luxon.VERSION` y registrada en el manifiesto del módulo.

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


## Parseo, formato y persistencia

Distinga siempre entre una fecha civil, una fecha/hora sin zona y un instante absoluto. `DateTime.fromISO()` interpreta la cadena según su contenido y las opciones de zona; no asuma UTC cuando la cadena no incluye `Z` u offset.

Para persistencia de instantes, convierta explícitamente a UTC o al formato acordado con el backend. Para mostrar al usuario, convierta desde el valor persistido a la zona de la aplicación o del usuario.

```js
const stored = luxon.DateTime.fromISO('2026-10-05T13:30:00Z');
const local = stored.setZone('America/Havana');
console.log(local.toFormat('dd/LL/yyyy HH:mm'));
```

## Validación

Compruebe `isValid` antes de utilizar una fecha. Una entrada inválida conserva información de diagnóstico en la instancia, pero no debe llegar a persistencia como si fuera un valor correcto.

No utilice fechas del navegador como mecanismo de autorización o vencimiento seguro sin validación del servidor. El cliente puede tener reloj o zona incorrectos.

## Integración con formularios

Luxon complementa selectores como [Flatpickr](flatpickr.md): Flatpickr gestiona la interacción y Luxon puede convertir entre zona local y UTC. Mantenga una única política temporal y documente en qué punto se realiza cada conversión.

## Duraciones y calendario

Una duración fija de 24 horas no siempre equivale a «mañana a la misma hora local» en zonas con cambios de horario. Para reglas de calendario, utilice operaciones de fecha en la zona correspondiente y pruebe los límites de cambio horario cuando el proyecto los tenga.

## Diagnóstico

Si una hora aparece desplazada, inspeccione la cadena original, su offset, la zona indicada al parsear y la zona usada al mostrar. No corrija el resultado sumando horas manualmente sin identificar antes qué interpretación temporal falló.
