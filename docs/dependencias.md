# Política de dependencias

## Ubicación de los paquetes PHP

GFrame utiliza `packages/` como directorio de instalación de Composer. El autoload se carga desde `packages/autoload.php` y los ejecutables se generan en `packages/bin/`.

Composer mantiene su estructura estándar `proveedor/paquete` dentro de esa carpeta. Por ejemplo, el framework se instala en una aplicación como `packages/gorvet/gframe` y PHPMailer como `packages/phpmailer/phpmailer`.

`packages/` es generado, no se versiona y nunca forma parte del archivo distribuible de GFrame.

## Dependencias externas administradas por Composer

| Función | Paquete |
|---|---|
| Correo SMTP | `phpmailer/phpmailer` |
| Serialización de closures para `Async` | `opis/closure` `^3.7` |
| Server-Sent Events | `hhxsv5/php-sse` |
| Variables de entorno | `vlucas/phpdotenv` |

Estas librerías no deben copiarse dentro de `src` ni mantenerse manualmente en `core/vendors`.

## Componentes propios

- `Async`: componente asíncrono de GFrame que serializa closures mediante `Opis\Closure\SerializableClosure` y las ejecuta con PHP CLI. Consulta [Async](async.md) para sus límites: no es una cola persistente ni ofrece reintentos automáticos.
- `GFrame\Security\Encryption`: utilidad opcional de cifrado autenticado AES-256-GCM incluida en el framework. Su formato es propio y no migra automáticamente datos cifrados por implementaciones anteriores.
- `gfselect`: componente propio de interfaz extraído y documentado en [GFSelect](gfselect.md).
- `password-utils`: utilidad propia extraída y documentada en [Password Utils](password-utils.md).
- Fuente de iconos [`gframe-icons`](gframe-icons.md): recurso propio extraído y documentado.

Las dependencias de negocio pertenecen al proyecto que las utiliza. PHPMailer, Opis Closure, PHP-SSE y PHP dotenv se instalan mediante Composer; no se mantienen copias manuales en el núcleo.

## Componentes con requisito de extensión PHP

Algunas capacidades del framework dependen además de extensiones del runtime y no de otro paquete Composer. Por ejemplo, el cliente HTTP saliente utiliza cURL. Consulta la guía de la capacidad y los requisitos generales antes de desplegarla.

## Dependencias del navegador

Bootstrap, jQuery, SweetAlert2, AOS, Venobox y otras bibliotecas de interfaz se distribuyen mediante el [catálogo de módulos](modulos-opcionales.md). Cada manifiesto registra sus dependencias y destinos públicos. Las bibliotecas externas conservan su autoría y licencia; las guías de cada una enlazan su fuente oficial.

El inventario de versiones y licencias de los archivos distribuidos está en [Dependencias frontend distribuidas](dependencias-frontend.md).

## Regla de actualización

Composer fija las versiones en `composer.lock`. Las actualizaciones se prueban en el framework y luego se incorporan de forma explícita a cada aplicación. En una aplicación se usa `composer install`; no se copian paquetes manualmente.
