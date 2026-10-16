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

### Versión 4.1.0: biblioteca, escritura y autoguardado privado

Actualice el paquete y aplique los archivos administrados con la vista previa y su política de personalizaciones. La biblioteca amplía selección, galerías, atributos existentes y gestión; TinyMCE incorpora scroll propio, herramientas contextuales, concentración, esquema y protección ante pérdidas. Los overrides del campo y de sus metadatos deben adoptar los atributos/recursos nuevos o usar las vistas originales.

El aviso de salida se basa en contenido confirmado: integre `getSaveSnapshot` antes del envío y `markSaved` tras éxito real, según [el editor](rich-text-editor.md#protección-y-recuperación). Las copias locales necesitan una `draft_key` por ámbito/contenido. Para autoguardado remoto, añada `content-autosave` a la lista completa de módulos, ejecute su instalación/migración y conecte una ruta de la aplicación que autorice el registro. No activa publicación automática ni modifica artículos existentes. Al publicar devuelva la nueva revisión a `markSaved(snapshot, revision)`. Consulte [la guía de autoguardado](content-autosave.md).

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

### Ejecutor HTTP del cron

Después de instalar la versión que incluya el ejecutor HTTP, sincronice `cron-runner` con la vista previa y `--preserve-custom`: ahora publica también `gframe-cron.php` en la raíz. No requiere una migración nueva sobre el esquema cron de 4.0.0. `.env` se conserva: añada `GFRAME_CRON_EXECUTOR=cli|http` y, solo para HTTP, `GFRAME_CRON_HTTP_SECRET` y APP_URL absoluta. Sin executor explícito se conserva CLI. Consulte [los cuatro caminos](cron-runner.md#cuatro-caminos-de-ejecución), su autenticación y requisitos de hosting. Verifique la propagación de Authorization, un rechazo sin credencial y una tarea inocua. En real, las visitas no registran el disparador virtual. El comando CLI sigue disponible; el programador del servidor debe adoptar explícitamente la llamada PHP o HTTP elegida.

### Actualización a 4.0.0: cron configurable y CSRF de visitantes

La etiqueta `v4.0.0` corresponde a Composer `4.0.0`. Es una versión mayor porque habilita CSRF en las operaciones públicas de Auth, requiere la coordinación persistente del scheduler y cambia el código de salida del CLI cuando falla un handler. Prepare primero la actualización de cada aplicación en desarrollo o staging, ajuste la restricción a `^4.0` y conserve su `composer.lock` validado. Si su restricción actual está en 2.x, revise también [la transición a 3.0.0](release-3.0.0.md), especialmente si utilizaba las relaciones genéricas de medios.

1. Detenga los disparadores antiguos y el cron del servidor durante la migración y espere a que terminen los workers existentes. Los ejecutores antiguos no conocen el bloqueo nuevo.
2. Ajuste la restricción de `gorvet/gframe` a la versión publicada elegida y ejecute `composer update gorvet/gframe` en desarrollo/staging.
3. Revise `composer gframe:update -- --dry-run --preserve-custom` y aplique `composer gframe:update -- --preserve-custom`. Los conflictos se conservan, no se fusionan: adapte el ejecutor, las rutas Auth y los templates personalizados que aparezcan.
4. Si necesita cron y no está registrado, añada `cron-runner` a la lista completa actual de módulos mediante `--modules=LISTA_COMPLETA_CON_CRON_RUNNER`, primero con `--dry-run --preserve-custom`. No sustituya la lista por un único módulo. Sus migraciones crean el almacenamiento si falta; si hay tablas creadas manualmente sin historial de migraciones, revise su esquema e historial antes de aplicar el cambio.
5. Añada manualmente a `.env` `GFRAME_CRON_MODE=real` o `virtual`, `GFRAME_CRON_MIN_INTERVAL=60` y `GFRAME_CRON_BATCH_LIMIT=10`. Configure `GFRAME_PHP_BINARY` si no se detecta PHP CLI. Compruebe `php bin/gframe-cron.php --status`, una tarea inocua y los formularios públicos antes de reactivar el modo elegido.

Las sesiones anónimas reciben tokens CSRF. Las rutas Auth distribuidas dejan de excluir CSRF y sus vistas originales incluyen los campos. En vistas propias, utilice `GFrame\Security\CsrfToken::fields()` dentro del formulario o envíe los campos de `#tokens` por AJAX. Conserve `honeypot`, revise las exclusiones CSRF propias y excluya de caché compartida las páginas con tokens. Un header personalizado que ya publica `csrfToken`/`csrfTimestamp` desde sesión sigue siendo compatible.

La actualización no retira automáticamente disparadores particulares de una aplicación. Sustitúyalos en un cambio de esa aplicación, preservando handlers y reglas de negocio. Evite dejar un ejecutor antiguo que llame al scheduler de otra versión.

#### Sitio de documentación en producción

En el sitio `gframe-docs` inspeccionado, producción lee los Markdown desde el paquete instalado `gorvet/gframe`; `DOCS_REPOSITORY` solo se utiliza en modo local. Actualizar una copia externa del repositorio no cambia por sí solo la documentación publicada en producción. Actualice la dependencia del sitio y despliegue su lock comprobado.

Es un sitio sin base de datos: no necesita instalar `cron-runner` ni activar `virtual` para mostrar documentación o utilizar el CSRF anónimo. Puede mantener `GFRAME_CRON_MODE=real` sin programar un cron. Para este perfil, revise y aplique la sincronización con `--no-database --preserve-custom`. No utilice `--no-database` en una aplicación cuyas tareas sí requieren la migración.

Su catálogo de guías es propio del sitio. Los cambios en `cron-runner`, `middleware`, `sesiones`, `auth-ui` y `actualizaciones` aparecen al instalar el paquete nuevo; cualquier guía completamente nueva requiere además revisar su catálogo y rutas. Compruebe navegación, búsqueda, formulario de contacto, assets y caché de PHP/servidor después de desplegar. No se ha modificado ese sitio desde el framework.

En producción, despliegue el código y lock ya probados, haga copia de seguridad y ejecute `composer install --no-dev --prefer-dist --optimize-autoloader`, después la vista previa y sincronización correspondientes. No ejecute un `composer update` abierto directamente en producción. Recargue PHP-FPM u OPcache si la política del despliegue lo requiere y reactive el cron elegido tras verificar el resultado.

### Actualización a 3.0.0

Consulta [la transición a 3.0.0](release-3.0.0.md) antes de cambiar la restricción Composer a `^3.0`. Esta versión retira la API de relaciones genéricas de medios y aplica una migración que elimina su tabla auxiliar. Los IDs de imagen y los JSON de galerías guardados por cada CRUD se conservan.

### Actualización a 2.0.3

Sincroniza los assets administrados de Auth, errores y medios después de descargar el paquete. Las vistas originales se actualizan con Composer; las personalizaciones en `app/` se conservan y requieren revisión manual si deben adoptar el nuevo enlace o los controles del campo de medios. Los labels de Auth vuelven a los estilos comunes y el footer comparte el fondo de la página. En medios, la vista previa aparece encima de los botones y las galerías sin `fragment` explícito utilizan `gthumb`; un valor explícito conserva su variante.

### Actualización a 2.0.2

Sincroniza los archivos administrados después de descargar el paquete: esta versión cambia `index.php`, `.htaccess`, `nginx.conf` y el JavaScript de login. Si preservas un `index.php` personalizado, adapta su arranque antes de utilizar `noSession()`, siguiendo [recursos públicos sin sesión](rutas.md#recursos-públicos-sin-sesión). Una vista personalizada de login debe asociar el botón Acceder al formulario `login` mediante `type="submit" form="login"`, porque el script escucha ahora el envío del formulario. Las rutas existentes conservan sus sesiones; ninguna se convierte automáticamente en una ruta sin sesión.

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


## Contrato de cobertura del actualizador

Las pruebas de `ProjectUpdateServiceTest` comparan los archivos publicados por los módulos y los archivos administrados del proyecto con la política del actualizador. Cada archivo base nuevo debe quedar cubierto por la actualización o tener una excepción explícita y justificada.

Esto convierte la política de actualización en un contrato comprobable: añadir un archivo al esqueleto sin decidir cómo se instala, actualiza o preserva debe hacer fallar la suite antes de publicar una versión.

Cuando añada una capacidad al framework, compruebe como mínimo:

1. si el archivo pertenece al paquete runtime o debe publicarse en el proyecto;
2. si una actualización puede reemplazarlo o debe preservarlo;
3. si necesita una migración de base de datos;
4. si requiere registrar un módulo nuevo en `storage/gframe-installed.json`;
5. si `--dry-run` informa correctamente el cambio;
6. si `--preserve-custom` protege una modificación local administrada;
7. si la suite cubre la nueva ruta de actualización.

Una instalación limpia y una actualización desde una versión anterior son recorridos distintos. Verifique ambos antes de considerar completa una capacidad nueva.

## Migraciones y recuperación

Si habilita cron virtual HTTP con Nginx/PHP-FPM, configure también `fastcgi_ignore_client_abort on` en el manejador del endpoint. Sin esa opción el cierre del socket interno puede cancelar el lanzamiento. Consulte el [manejador exacto de cron](servidores-web.md#endpoint-http-del-cron).

SQLite ejecuta cada migración en una transacción. MySQL aplica DDL con commits implícitos, por lo que GFrame no lo envuelve en una transacción y registra la migración únicamente cuando terminan todas sus sentencias. No cambie una migración publicada o parcialmente aplicada.

Las adiciones simples de columnas ya presentes se omiten solo cuando tipo, nulabilidad y valor predeterminado coinciden. Esto permite actualizar los esquemas históricos de autenticación sin duplicar `authorization_version`. Una definición incompatible detiene la actualización. No ejecute migraciones dentro de una transacción de la aplicación ni lance dos actualizaciones simultáneas.

MySQL utiliza `gframe_migration_statements` para registrar cada sentencia y el hash de las sentencias analizadas por `SqlStatementParser`. Las sentencias confirmadas (`done`) se omiten al reintentar; cambiar ese contenido después de comenzar provoca un rechazo. Un bloqueo por conexión impide que dos ejecutores de GFrame migren simultáneamente la misma base de datos. SQLite conserva su transacción por migración y no necesita este registro adicional.

Si una migración MySQL falla o se interrumpe, el registro `started` tiene un resultado incierto: el SQL puede haberse aplicado antes de escribir `done`. La actualización se detiene para evitar repetir efectos. Compruebe la sentencia y sus efectos en la base de datos, y confirme desde una herramienta administrativa con `MigrationRunner::frameworkDefault()->resolveInterruptedStatement($pdo, $migrationID, $numeroDeSentencia, $yaAplicada)`. El número empieza en 1 según `SqlStatementParser`. `true` confirma que ya se aplicó y permite omitirla; `false` elimina el intento para ejecutarla otra vez. No seleccione `false` sin comprobar que sus efectos están ausentes o revertidos. Después repita la actualización normal; no modifique la migración original.

Las interrupciones anteriores a esta mejora no tienen registro por sentencia y requieren revisión manual antes de reintentar. El registro no convierte el DDL de MySQL en una operación atómica ni garantiza recuperación automática de SQL arbitrario. Haga copia de seguridad y pruebe la actualización con el motor utilizado en producción.
