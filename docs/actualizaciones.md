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

El core y los archivos publicados por los módulos se consideran administrados por GFrame. No deben modificarse directamente porque una actualización puede reemplazarlos. Las aplicaciones personalizan controladores, modelos y servicios por herencia y sustituyen vistas desde `app`, con respaldo en los originales del módulo. Los contratos de transporte, persistencia y otras integraciones conectan capacidades externas. Consulte [módulos runtime](modulos-runtime.md).

## Añadir módulos después de instalar

Los opcionales pueden instalarse más adelante sin volver a ejecutar `install.php`. `--modules` recibe la lista completa que se desea registrar, no solamente los módulos nuevos. Conserve los nombres de `storage/gframe-installed.json` y añada los nuevos a esa lista:

```bash
composer gframe:update -- --modules=LISTA_COMPLETA --dry-run
composer gframe:update -- --modules=LISTA_COMPLETA
```

Sustituya `LISTA_COMPLETA` por los identificadores separados por comas. Se resuelven dependencias, se publican archivos, se crean carpetas de personalización y se ejecutan las migraciones declaradas. `--no-database` no instala las tablas necesarias de un módulo funcional. Quitar un nombre de esa lista no constituye una desinstalación completa: no elimina automáticamente archivos ni datos.

## Despliegue en producción

Despliegue el código y el `composer.lock` comprobados. Desde la raíz del proyecto ejecute:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Composer genera `packages/autoload.php`; si falta, el arranque no puede cargar GFrame. No despliegue únicamente los archivos de `app` ni copie dependencias sueltas. Cuando la versión incluya cambios en archivos publicados o migraciones, revise y ejecute también `composer gframe:update` conforme a la política de personalización del proyecto.

Suba `.env` por un canal privado, sin versionarlo. Configure `APP_URL` con la URL HTTPS real y desactive debug. El usuario del proceso PHP necesita permisos de escritura en los directorios utilizados por sesiones, procesos asíncronos, registros y cargas. Los envíos asíncronos requieren PHP CLI disponible; las colas y campañas programadas requieren el trabajador o cron descrito en sus guías.

Integre las reglas del servidor según [Apache y Nginx](servidores-web.md). Compruebe portada, contacto si existe, `robots.txt`, `sitemap.xml` y una URL inexistente. No declare el despliegue correcto solo porque la portada abre.

Si Git impide desplegar porque un archivo no versionado sería sobrescrito, conserve ese archivo fuera de la raíz pública antes de repetir el despliegue. Compare su personalización con el archivo versionado nuevo; no use un checkout forzado ni borre una configuración activa sin revisarla.
