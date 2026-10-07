# Referencia de comandos

GFrame incluye scripts para crear proyectos, administrar módulos y comprobar el framework. Los scripts Composer del repositorio no se heredan automáticamente en los proyectos instalados.

## Desde el repositorio del framework

Ejecuta estos comandos en la raíz de `gframe-framework`, después de `composer install`:

| Comando | Función |
| --- | --- |
| `composer new -- ../mi-proyecto` | Genera el esqueleto en un destino nuevo o vacío. Completa después la instalación del proyecto. |
| `composer modules:list` | Lista el catálogo distribuido, no los módulos instalados en una aplicación. |
| `composer lint` | Comprueba la sintaxis PHP de los archivos incluidos por el script. |
| `composer test` | Ejecuta la suite PHPUnit del framework. |
| `composer skills:check` | Valida la estructura y referencias de los skills. |
| `php bin/gframe-context.php --project=RUTA` | Muestra contexto JSON del framework o aplicación, sin arrancar la aplicación. |
| `composer check` | Ejecuta lint, pruebas y validación de skills. |

`composer new` es un script propio de GFrame. Consulta [Instalación](instalacion.md) para generar y arrancar la aplicación.

### Obtener contexto de desarrollo

Desde el framework, ejecute `php bin/gframe-context.php`; para una aplicación, utilice el mismo archivo del paquete identificado con `--project=/ruta/al/proyecto`. Si el directorio de dependencias es `packages`, el comando desde la aplicación es `php packages/gorvet/gframe/bin/gframe-context.php --project=.`. No necesita cargar el autoloader y también funciona con `php -n`.

El JSON identifica el ámbito, ruta/versión/referencia del paquete, autoloader disponible, módulos registrados, rutas de las skills canónicas y extensiones del PHP CLI. Respeta `config.vendor-dir` y el `install-path` de Composer. Una versión desconocida se devuelve como `null`; la ausencia de metadata instalada produce un error, sin sustituirla por otro checkout. Los códigos de salida son 0 para contexto obtenido, 1 para contexto no disponible y 2 para argumentos inválidos.

Esta consulta no lee `.env`, ejecuta archivos PHP de Composer/configuración ni se conecta a servicios. Sus datos no prueban la salud del despliegue, el PHP web ni la versión cargada por un bridge personalizado. Utilícela para localizar la fuente y leer solo las instrucciones necesarias para el trabajo.

### Publicar únicamente recursos

Desde el repositorio:

```bash
php bin/modules.php list
php bin/modules.php publish /ruta/mi-proyecto/public flatpickr coloris
```

`publish` copia los recursos públicos de los módulos y sus dependencias al destino. Si omites los nombres, selecciona los módulos predeterminados. No registra el módulo en la aplicación, publica rutas MVC ni ejecuta migraciones. Para instalar una capacidad completa utiliza [Instalación de módulos](modulos-opcionales.md).

## Desde un proyecto instalado

El proyecto generado incluye el script `gframe:update`:

```bash
composer update gorvet/gframe
composer gframe:update -- --dry-run
composer gframe:update
```

El primer comando descarga la versión compatible; el actualizador aplica la versión ya descargada al proyecto. Consulta [Actualizaciones](actualizaciones.md) para revisar cambios y desplegar el lock comprobado.

| Opción del actualizador | Función |
| --- | --- |
| `--dry-run` | Muestra cambios sin aplicarlos. |
| `--preserve-custom` | Conserva archivos administrados modificados y comunica conflictos. |
| `--no-database` | Omite conexión y migraciones; no completa una actualización que necesite cambios de esquema. |
| `--modules=a,b` | Declara la lista completa de módulos deseados. |
| `--project=ruta` | Selecciona la raíz del proyecto que se actualizará. |

También puedes consultar la ayuda del ejecutable sin aplicar cambios:

```bash
php packages/gorvet/gframe/bin/gframe-update --help
```

## Otros puntos de entrada

- [Skills](skills.md): instalación mediante `bin/install-skills.ps1`.
- [Tareas programadas](cron-runner.md): ejecución periódica del runner del módulo.
- [Async](async.md): el framework lanza `bin/async-worker.php` internamente; no es un generador ni un comando para administrar proyectos.

Actualmente no se incluyen generadores de CRUD, controladores o modelos. Esos archivos se desarrollan con las convenciones de [Arquitectura](arquitectura.md), [Rutas](rutas.md) y [Vistas](vistas.md).


## Flujo recomendado por escenario

### Crear una aplicación nueva

```bash
composer install
composer new -- ../mi-proyecto
cd ../mi-proyecto
composer install
```

Después complete el instalador del proyecto, configure el servidor web y seleccione el perfil y los módulos necesarios. `composer new` crea el esqueleto; no sustituye la configuración de entorno ni la instalación de la base de datos.

### Actualizar una aplicación existente

```bash
composer update gorvet/gframe
composer gframe:update -- --dry-run
composer gframe:update
```

Revise primero el dry-run, especialmente cuando existan archivos administrados modificados. En producción debe desplegar un `composer.lock` ya validado y ejecutar `composer install`; no use una resolución abierta de dependencias como mecanismo normal de despliegue.

### Añadir un módulo después de instalar

Use `composer gframe:update -- --modules=LISTA_COMPLETA` con todos los módulos que desea conservar registrados, no solamente con el nuevo. El actualizador resuelve dependencias, publica archivos administrados y ejecuta las migraciones correspondientes. Quitar un nombre de esa lista no equivale a desinstalar por completo una capacidad ni elimina automáticamente sus datos.

### Publicar solamente recursos de una dependencia

`bin/modules.php publish` es útil para copiar recursos públicos concretos cuando no necesita instalar una capacidad completa. No crea rutas, modelos, tablas ni configuración de aplicación. Antes de usarlo, determine si realmente necesita un recurso aislado o el módulo funcional que lo administra.

## Dónde ejecutar cada comando

Hay tres contextos distintos:

| Contexto | Ejemplos | Qué modifica |
| --- | --- | --- |
| Repositorio del framework | `composer test`, `composer check`, `composer modules:list` | Código y validaciones del framework |
| Proyecto instalado | `composer gframe:update` | Archivos administrados, registro de módulos y migraciones del proyecto |
| Servidor / scheduler | `php bin/gframe-cron.php` | Ejecuta tareas ya registradas; no instala ni actualiza módulos |

Confundir estos contextos es una causa frecuente de errores. Los scripts Composer definidos en el repositorio del framework no aparecen automáticamente en un proyecto consumidor, y los comandos de un proyecto no deben ejecutarse sobre la carpeta `packages/gorvet/gframe` como si fuera la raíz de la aplicación.

## Comprobación y diagnóstico

Antes de publicar cambios del framework ejecute:

```bash
composer check
```

Si necesita aislar el problema:

```bash
composer lint
composer test
composer skills:check
```

Un fallo de sintaxis debe corregirse antes de interpretar fallos posteriores. Si una prueba documental falla, revise primero rutas, nombres de archivos y contratos descritos: varias pruebas verifican que la documentación corresponda a archivos y APIs reales.

`composer lint` comprueba PHP bajo `src`, `bin`, `tests`, `config`, `resources` y `maintenance`, más el ejecutable `bin/gframe-update` sin extensión. Incluye skeleton y archivos del instalador; conserva las bibliotecas PHP que ya se comprobaban dentro de los módulos. No recorre `packages`, `vendor`, `.git` ni carpetas de aplicaciones ajenas al conjunto seleccionado. Ejecuta `php -l`, sin cargar bootstrap ni ejecutar esos archivos; no sustituye pruebas funcionales.

Para comprobar una copia identificada del framework puede usar `php bin/lint.php --root <carpeta-framework>`. Un destino inexistente, sin PHP seleccionado o con errores de sintaxis devuelve fallo. Las pruebas del comando están en `tests/LintCommandTest.php` y utilizan fixtures temporales.

En un proyecto, utilice primero `composer gframe:update -- --dry-run` para distinguir un conflicto de archivos de un problema de base de datos. `--no-database` sirve para inspeccionar o aplicar cambios que no dependan del esquema, pero no convierte en completa una actualización que requiera migraciones.

## Reglas operativas

- No edite `composer.lock` manualmente para forzar una versión.
- No ejecute comandos de actualización directamente sobre `packages/gorvet/gframe`.
- No trate `publish` como sustituto de la instalación de módulos.
- No use `--no-database` para ocultar una migración fallida.
- No elimine módulos de la lista registrada esperando que sus datos desaparezcan.
- No dé por válido un despliegue solo porque Composer terminó sin error: compruebe rutas, recursos publicados, migraciones y tareas programadas cuando correspondan.

Para el ciclo completo de mantenimiento consulte [Actualizaciones](actualizaciones.md), y para la composición de módulos consulte [Módulos opcionales](modulos-opcionales.md) y [Módulos del framework](modulos-runtime.md).
