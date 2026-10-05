# Actualización de proyectos GFrame

La actualización tiene dos pasos: descargar la versión compatible del paquete y sincronizar los archivos administrados y las migraciones del proyecto. Haz una copia de seguridad de los datos y revisa primero la vista previa:

```bash
composer update gorvet/gframe
composer gframe:update -- --dry-run
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

## Qué hace cada comando

| Comando | Resultado |
| --- | --- |
| `composer install` | Con un lock existente, instala sus versiones exactas. No selecciona automáticamente la última publicación. |
| `composer update gorvet/gframe` | Resuelve una versión permitida por `composer.json`, actualiza `composer.lock` y descarga el paquete. |
| `composer gframe:update` | Aplica al proyecto los archivos administrados y migraciones de la versión ya descargada. No busca versiones en Packagist. |

Ninguno de estos comandos reinstala el sitio ni vuelve a crear su cuenta inicial. No edites manualmente `composer.lock` para elegir una versión: cambia la restricción en `composer.json` cuando corresponda y deja que Composer resuelva el lock. Consulta [el funcionamiento de install y update en Composer](https://getcomposer.org/doc/01-basic-usage.md).

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

`--no-database` no completa una actualización que requiera migraciones. Ejecuta después el actualizador con acceso a la base de datos antes de utilizar las capacidades que dependan del nuevo esquema. `--preserve-custom` tampoco combina cambios: revisa cada conflicto y traslada tus personalizaciones a las capas del proyecto cuando proceda.

Si el proyecto aún no está instalado, el actualizador renueva el instalador y los archivos de arranque sin crear la configuración, las tablas ni el registro de instalación.

## Regla de personalización

El core y los archivos publicados por los módulos se consideran administrados por GFrame. No deben modificarse directamente porque una actualización puede reemplazarlos. Las aplicaciones personalizan controladores, modelos y servicios por herencia y sustituyen vistas desde `app`, con respaldo en los originales del módulo. Los contratos de transporte, persistencia y otras integraciones conectan capacidades externas. Consulte [módulos runtime](modulos-runtime.md).

## Añadir módulos después de instalar

Sigue el procedimiento de [Instalación de módulos](modulos-opcionales.md#añadir-módulos-a-un-proyecto-instalado). Utiliza el mismo actualizador, pero `--modules` recibe la lista completa deseada, no solo los módulos nuevos. No vuelvas a ejecutar `install.php` para añadirlos.

## Despliegue en producción

Despliegue el código y el `composer.lock` comprobados. Desde la raíz del proyecto ejecute:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Composer genera `packages/autoload.php`; si falta, el arranque no puede cargar GFrame. No despliegue únicamente los archivos de `app` ni copie dependencias sueltas. Cuando la versión incluya cambios en archivos publicados o migraciones, revise y ejecute también `composer gframe:update` conforme a la política de personalización del proyecto.

Sube `.env` por un canal privado, sin versionarlo. Desactiva debug y revisa [la URL del entorno](configuracion.md#url-automática-y-url-explícita), especialmente para tareas ejecutadas fuera de una petición web. El usuario del proceso PHP necesita permisos de escritura en los directorios utilizados por sesiones, procesos asíncronos, registros y cargas. Los envíos asíncronos requieren PHP CLI disponible; las campañas programadas necesitan la ejecución periódica descrita en [Tareas programadas](cron-runner.md).

Integre las reglas del servidor según [Apache y Nginx](servidores-web.md). Compruebe portada, contacto si existe, `robots.txt`, `sitemap.xml` y una URL inexistente. No declare el despliegue correcto solo porque la portada abre.

Si Git impide desplegar porque un archivo no versionado sería sobrescrito, conserve ese archivo fuera de la raíz pública antes de repetir el despliegue. Compare su personalización con el archivo versionado nuevo; no use un checkout forzado ni borre una configuración activa sin revisarla.
