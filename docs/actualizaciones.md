# Actualización de proyectos GFrame

Los proyectos actualizan primero el paquete PHP y después sincronizan los módulos y la base de datos:

```bash
composer update gorvet/gframe
composer gframe:update
```

El segundo comando:

- lee los módulos instalados desde `storage/gframe-installed.json`;
- ejecuta una sola vez las migraciones pendientes;
- añade archivos nuevos;
- reemplaza los archivos administrados de los módulos, aunque hayan sido modificados directamente;
- registra la versión, las migraciones y las nuevas huellas de archivos.

También sincroniza los archivos base gestionados de `ProjectScaffolder::UPDATE_PATHS`: `.htaccess`, arranque e instalador, metadatos globales, header/footer generales, plantillas de correo, CSS compartido, `colores.html` y presentación y JavaScript del home. El instalador registra sus huellas desde el primer día para que `--preserve-custom` distinga una versión anterior intacta de una modificación local.

No reemplaza los archivos propios del proyecto: `.env`, configuración generada, rutas base del proyecto, permisos propios, `composer.json`, README, `.gitignore`, controlador del home, fragmentos de copyright/créditos ni imágenes de marca. Son decisiones explícitas, no omisiones. Los controladores, modelos y vistas runtime de módulos se actualizan con el paquete Composer; no se copian encima de las personalizaciones de `app`.

Las pruebas de `ProjectUpdateServiceTest` comparan todos los archivos publicados por todos los módulos con la actualización y exigen que cada archivo del esqueleto esté cubierto o tenga una excepción explícita. Añadir un archivo base sin decidir su política hace fallar esa comprobación.

## Vista previa

```bash
composer gframe:update -- --dry-run
```

La vista previa no modifica archivos, la base de datos ni el registro de instalación.

## Proyectos anteriores

Un proyecto sin `storage/gframe-installed.json` debe indicar una vez sus módulos:

```bash
composer gframe:update -- --modules=auth-ui,self-account,user-admin,media-library
```

Los archivos existentes que pertenezcan a los módulos indicados pasan a quedar administrados y pueden ser reemplazados. Después de esta primera ejecución, el proyecto utiliza el registro generado.

Si el proyecto aún no utiliza Composer, primero debe incorporar `gorvet/gframe`, cargar `packages/autoload.php` y añadir este script a su `composer.json`:

```json
{
    "scripts": {
        "gframe:update": "@php packages/gorvet/gframe/bin/gframe-update"
    }
}
```

## Opciones

- `--dry-run`: presenta los cambios sin aplicarlos.
- `--preserve-custom`: conserva temporalmente archivos administrados que fueron modificados y los informa como conflictos.
- `--no-database`: omite la conexión y las migraciones.
- `--modules=a,b`: define o modifica los módulos registrados.
- `--project=ruta`: actualiza otra raíz de proyecto.

`composer.lock` debe versionarse después de comprobar la actualización. En producción se despliega el lock validado y se usa `composer install`; no se ejecutan actualizaciones abiertas directamente en el servidor.

## Regla de personalización

El core y los archivos publicados por los módulos se consideran administrados por GFrame. No deben modificarse directamente porque una actualización puede reemplazarlos. Las aplicaciones amplían su comportamiento desde fuera mediante servicios propios, adaptadores, composición e implementaciones de los contratos públicos del framework.
