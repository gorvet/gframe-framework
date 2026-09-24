# Política de dependencias

## Dependencias externas administradas por Composer

| Función | Paquete |
|---|---|
| Correo SMTP | `phpmailer/phpmailer` |
| Serialización de closures para PHPAsync | `opis/closure` 3.7 |
| Server-Sent Events | `hhxsv5/php-sse` |
| Stemming usado por TextClassifier | `wamania/php-stemmer` |

Estas librerías no deben copiarse dentro de `src` ni mantenerse manualmente en `core/vendors`.

## Componentes propios

- `PHPAsync`: pasa al componente asíncrono de GFrame y utiliza Opis Closure desde Composer.
- `encryption`: paquete propio pendiente de extracción.
- `textClassifier`: paquete propio; su dependencia de stemming se instala con Composer.
- `gfselect`: componente propio de interfaz pendiente de extracción.
- `passwordUtils`: utilidad propia pendiente de extracción.
- Fuente de iconos `bebots`: recurso propio pendiente de extracción al paquete de interfaz.

`opusConverter` es propio, pero queda retirado porque ya no se necesita. PHPMailer, Opis Closure, PHP-SSE y PHP Stemmer son dependencias externas y no se copiarán al repositorio.

Los componentes propios podrán incorporarse al núcleo o convertirse en paquetes `gframe/*`. Las dependencias específicas de un proyecto permanecerán en ese proyecto.

## Dependencias del navegador

Bootstrap, jQuery, SweetAlert, AOS, Venobox y otras librerías de interfaz se revisarán en la etapa de recursos públicos. Se conservarán solamente los archivos de distribución necesarios; se excluirán demos, repositorios fuente, mapas innecesarios y archivos del sistema operativo.

## Regla de actualización

Composer fija las versiones en `composer.lock`. Las actualizaciones se prueban en el framework y luego se incorporan de forma explícita a cada aplicación.
