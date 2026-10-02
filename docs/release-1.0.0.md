# GFrame 1.0.0

Primera versión del paquete Composer `gframe/framework`. La publicación en GitHub mediante la etiqueta `v1.0.0` y el alta en Packagist son operaciones distintas; la etiqueta no registra automáticamente un paquete nuevo en Packagist.

## Alcance

Incluye el núcleo, los cuatro perfiles del instalador, módulos MVC personalizables mediante herencia PHP, bibliotecas visuales conectadas a variables, multimedia, autenticación, administración de usuarios, notificaciones, campañas, colas y correo. Las guías explican los contratos y los puntos de personalización. Las mejoras no urgentes siguen en [mejoras pendientes](mejoras-pendientes.md).

PHP 8.1 es el entorno probado para esta publicación. Los resultados históricos y las restricciones de navegador, Nginx y PHP 8.4 se conservan en [preparación anterior](preparacion-0.9.0.md). El usuario confirmó que el instalador completó una instalación de web no administrada. Esa comprobación manual no implica que todos los perfiles ni todas las integraciones externas se hayan probado visualmente.

La suite completa tras estos ajustes pasó en PHP 8.1.5 con MySQL habilitado: 320 pruebas, 3393 aserciones y una omisión, correspondiente al servidor Nginx no configurado para esa ejecución. Composer, auditoría de dependencias, sintaxis PHP y nueve skills fueron validados. El archivo Composer se comprobó sin dependencias locales, secretos ni capturas; incluye `deployment/nginx.conf` y no su ubicación anterior.

## Organización del proyecto

- `app/`: código y vistas propios; solo se preparan carpetas vacías para las capas que existen en cada módulo.
- `config/`: configuración PHP, permisos, rutas y metadatos.
- `deployment/nginx.conf`: fragmento para integrar manualmente en el servidor. `.htaccess` permanece en la raíz, donde Apache lo utiliza.
- `packages/`: directorio de dependencias Composer; contiene GFrame y sus dependencias PHP. No se modifica manualmente.
- `storage/gframe-installed.json`: única lista de módulos instalados, bloqueo de instalación y hashes de actualización. SQLite puede guardar su archivo en `storage/`.
- `public/`: recursos publicados y accesibles desde el navegador.

## Migración desde instalaciones de desarrollo

Actualice el paquete y ejecute primero `composer gframe:update -- --dry-run`. La actualización publica la nueva ayuda Nginx y preserva archivos anteriores y personalizaciones. Si el servidor incluye `config/server/nginx.conf`, cambie su inclusión a `deployment/nginx.conf` antes de retirar manualmente la copia antigua. Ambos servidores bloquean el acceso web a `deployment/`.

Los proyectos anteriores que fijen `gframe/framework:^0.9` deben cambiar su requisito a `^1.0` antes de actualizar Composer. Si usan un repositorio local `path` con una versión simulada, también deben ajustar esa versión. El actualizador no cambia el `composer.json` propio del proyecto.

El archivo antiguo `config/modules.php` ya no se carga; puede retirarse después de revisar que ningún código propio lo utilice. Para comprobar un módulo instalado, use `ModuleRuntime::isInstalled()`; `has()` queda reservado para runtime MVC. La actualización no elimina carpetas vacías antiguas ni posibles personalizaciones.

La firma de `ProjectConfigWriter::write()` pasa a `write($projectRoot, $settings, $overwrite = false, $moduleEnvironment = [])`. Su resultado contiene `config` y `environment`, sin `modules`.
