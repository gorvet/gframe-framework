# Política de dependencias

## Ubicación de los paquetes PHP

GFrame utiliza `packages/` como directorio de instalación de Composer. El autoload se carga desde `packages/autoload.php` y los ejecutables se generan en `packages/bin/`.

Composer mantiene su estructura estándar `proveedor/paquete` dentro de esa carpeta. Por ejemplo, el framework se instala en una aplicación como `packages/gorvet/gframe` y PHPMailer como `packages/phpmailer/phpmailer`.

`packages/` es generado, no se versiona y nunca forma parte del archivo distribuible de GFrame.

## Dependencias externas administradas por Composer

| Función | Paquete |
|---|---|
| Correo SMTP | `phpmailer/phpmailer` |
| Serialización de closures para PHPAsync | `laravel/serializable-closure` 1.3 |
| Server-Sent Events | `hhxsv5/php-sse` |

Estas librerías no deben copiarse dentro de `src` ni mantenerse manualmente en `core/vendors`.

## Componentes propios

- `PHPAsync`: componente asíncrono de GFrame que utiliza Laravel Serializable Closure desde Composer.
- `GFrame\Security\Encryption`: utilidad opcional de cifrado autenticado AES-256-GCM incluida en el framework. Su formato es propio y no migra automáticamente datos cifrados por implementaciones anteriores.
- `gfselect`: componente propio de interfaz extraído y documentado en [GFSelect](gfselect.md).
- `password-utils`: utilidad propia extraída y documentada en [Password Utils](password-utils.md).
- Fuente de iconos [`gframe-icons`](gframe-icons.md): recurso propio extraído y documentado.

Las dependencias de negocio pertenecen al proyecto que las utiliza. PHPMailer, Laravel Serializable Closure y PHP-SSE se instalan mediante Composer; no se mantienen copias manuales en el núcleo.

## Dependencias del navegador

Bootstrap, jQuery, SweetAlert2, AOS, Venobox y otras bibliotecas de interfaz se distribuyen mediante el [catálogo de módulos](modulos-opcionales.md). Cada manifiesto registra sus dependencias y destinos públicos. Las bibliotecas externas conservan su autoría y licencia; las guías de cada una enlazan su fuente oficial.

## Regla de actualización

Composer fija las versiones en `composer.lock`. Las actualizaciones se prueban en el framework y luego se incorporan de forma explícita a cada aplicación. En una aplicación se usa `composer install`; no se copian paquetes manualmente.
