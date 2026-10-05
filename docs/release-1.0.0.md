# GFrame 1.0.0

Primera versión publicada en GitHub, originalmente con el nombre Composer `gframe/framework`. Desde 1.0.1 el paquete se llama `gorvet/gframe`; la etiqueta histórica `v1.0.0` no se modifica. La publicación en GitHub y el alta en Packagist son operaciones distintas; una etiqueta no registra automáticamente un paquete nuevo.

## Alcance

Incluye el núcleo, los cuatro perfiles del instalador, módulos MVC personalizables mediante herencia PHP, bibliotecas visuales conectadas a variables, multimedia, autenticación, administración de usuarios, notificaciones, campañas, colas y correo. Las guías explican los contratos y los puntos de personalización.

PHP 8.1 es el entorno probado para esta publicación. No se declara certificada la integración con PHP 8.4.

## Organización del proyecto

- `app/`: código y vistas propios; solo se preparan carpetas vacías para las capas que existen en cada módulo.
- `config/`: configuración PHP, permisos, rutas y metadatos.
- `.htaccess`: reglas que Apache utiliza desde la raíz. En las versiones actuales, `nginx.conf` se publica junto a él; consulte [servidores web](servidores-web.md) para integrar Nginx.
- `packages/`: directorio de dependencias Composer; contiene GFrame y sus dependencias PHP. No se modifica manualmente.
- `storage/gframe-installed.json`: única lista de módulos instalados, bloqueo de instalación y hashes de actualización. SQLite puede guardar su archivo en `storage/`.
- `public/`: recursos publicados y accesibles desde el navegador.

## Migración desde instalaciones de desarrollo

Actualice el paquete y ejecute primero `composer gframe:update -- --dry-run`. La actualización publica la nueva ayuda Nginx y preserva archivos anteriores y personalizaciones. Si el servidor incluye `config/server/nginx.conf`, cambie su inclusión a `nginx.conf` antes de retirar manualmente la copia antigua. Ambos servidores bloquean el acceso web a `deployment/`.

Los proyectos anteriores que fijen `gorvet/gframe:^0.9` deben cambiar su requisito a `^1.0` antes de actualizar Composer. Si usan un repositorio local `path` con una versión simulada, también deben ajustar esa versión. El actualizador no cambia el `composer.json` propio del proyecto.

El archivo antiguo `config/modules.php` ya no se carga; puede retirarse después de revisar que ningún código propio lo utilice. Para comprobar un módulo instalado, use `ModuleRuntime::isInstalled()`; `has()` queda reservado para runtime MVC. La actualización no elimina carpetas vacías antiguas ni posibles personalizaciones.

La firma de `ProjectConfigWriter::write()` pasa a `write($projectRoot, $settings, $overwrite = false, $moduleEnvironment = [])`. Su resultado contiene `config` y `environment`, sin `modules`.
