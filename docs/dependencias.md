# Política de dependencias

## Ubicación de los paquetes PHP

GFrame utiliza `packages/` como directorio de instalación de Composer. El autoload se carga desde `packages/autoload.php` y los ejecutables se generan en `packages/bin/`.

Composer mantiene su estructura estándar `proveedor/paquete` dentro de esa carpeta. Por ejemplo, el framework se instala en una aplicación como `packages/gorvet/gframe` y PHPMailer como `packages/phpmailer/phpmailer`.

`packages/` es generado, no se versiona y nunca forma parte del archivo distribuible de GFrame.

## Dependencias externas administradas por Composer

| Función | Paquete |
|---|---|
| Correo SMTP | `phpmailer/phpmailer` |
| Serialización de closures para PHPAsync | `opis/closure` 3.7 |
| Server-Sent Events | `hhxsv5/php-sse` |

Estas librerías no deben copiarse dentro de `src` ni mantenerse manualmente en `core/vendors`.

## Componentes propios

- `PHPAsync`: pasa al componente asíncrono de GFrame y utiliza Opis Closure desde Composer.
- `GFrame\Security\Encryption`: utilidad opcional de cifrado autenticado AES-256-GCM incluida en el framework. Su formato es propio y no migra automáticamente datos cifrados por implementaciones anteriores.
- `gfselect`: componente propio de interfaz extraído y documentado en [GFSelect](gfselect.md).
- `password-utils`: utilidad propia extraída y documentada en [Password Utils](password-utils.md).
- Fuente de iconos [`gframe-icons`](gframe-icons.md): recurso propio extraído y documentado.

`TextClassifier` permanece en una aplicación de bots porque es un algoritmo específico para bots conversacionales. `opusConverter` es propio, pero queda retirado porque ya no se necesita. PHPMailer, Opis Closure y PHP-SSE son dependencias externas y no se copiarán al repositorio.

Los componentes propios podrán incorporarse al núcleo o convertirse en paquetes `gframe/*`. Las dependencias específicas de un proyecto permanecerán en ese proyecto.

## Dependencias del navegador

Bootstrap, jQuery, SweetAlert, AOS, Venobox y otras librerías de interfaz se revisarán en la etapa de recursos públicos. Se conservarán solamente los archivos de distribución necesarios; se excluirán demos, repositorios fuente, mapas innecesarios y archivos del sistema operativo.

## Regla de actualización

Composer fija las versiones en `composer.lock`. Las actualizaciones se prueban en el framework y luego se incorporan de forma explícita a cada aplicación. En una aplicación se usa `composer install`; no se copian paquetes manualmente.
