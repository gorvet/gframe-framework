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
| `composer check` | Ejecuta lint, pruebas y validación de skills. |

`composer new` es un script propio de GFrame. Consulta [Instalación](instalacion.md) para generar y arrancar la aplicación.

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
