# Preparación de GFrame 0.9.0

Fecha de comprobación: 2 de octubre de 2026. Este documento no declara publicada la versión.

## Alcance

El paquete Composer `gorvet/gframe` incluye el núcleo, la estructura inicial, el instalador y el catálogo de módulos. Los módulos MVC mantienen sus originales en el paquete; el proyecto puede personalizarlos mediante vistas propias y herencia PHP. Las bibliotecas visuales y las entradas CLI no necesitan una estructura MVC.

Consulte [módulos runtime](modulos-runtime.md), [extensibilidad](extensibilidad.md), [instalación](instalacion.md) y las guías de cada módulo enlazadas desde el README. Las mejoras no urgentes permanecen en [mejoras pendientes](mejoras-pendientes.md); no se consideran implementadas por preparar esta versión.

## Resultados reproducibles

- `composer validate --no-check-publish`: configuración válida.
- `composer audit --locked --no-interaction`: sin avisos de vulnerabilidades en las dependencias bloqueadas.
- `php bin/validate-skills.php`: nueve skills válidas.
- `php bin/lint.php`: todos los archivos PHP válidos.
- `GFRAME_TEST_MYSQL=1 php packages/phpunit/phpunit/phpunit --colors=never`, con PHP 8.1.5: 320 pruebas, 3411 aserciones, sin fallos y una omisión. La prueba omitida requiere un ejecutable Nginx y PHP-CGI configurados.
- `node --test tests/js/*.test.cjs`, con las dependencias del entorno y Chrome configurados: 54 pruebas correctas de 55. El recorrido del instalador por navegador no pudo ejecutarse: Chrome rechazó el acceso a localhost con `ERR_NETWORK_ACCESS_DENIED`. No se contabiliza como una prueba superada ni como un defecto confirmado del instalador. El caso DOM sin red sí pasó; las pruebas HTTP PHP del instalador están incluidas en la suite anterior.
- `composer archive --format=zip`: paquete generado y comprobado sin `.playwright-mcp`, dependencias locales, caché PHPUnit ni archivos `.env`. Se añadieron exclusiones explícitas porque el primer archivo generado incluía capturas temporales pese a estar ignoradas por Git.
- Suite completa en PHP 8.4.26 portátil, con MySQL habilitado: 320 pruebas, 2879 aserciones, tres errores y un fallo. Las conexiones MySQL fallaron por permisos de socket; el servidor HTTP no pudo iniciar y una comprobación posterior recibió una respuesta nula. No demuestra un fallo del núcleo ni permite declarar aprobada la integración en PHP 8.4.
- PHP 8.4.26 sin MySQL habilitado y con `--filter '/^(?!.*(?:InstallerHttpTest|InstallerWizardHttpTest|ServerRoutingTest)).*$/'`: 314 pruebas, 2833 aserciones, sin fallos y dos omisiones correspondientes a MySQL. No se notificaron deprecaciones. La exclusión deja fuera las tres clases que requieren servidores locales; no equivale a aprobar la suite integral.
- Prueba real de Nginx con el ejecutable temporal disponible y PHP-CGI 8.1: no pudo iniciar el servidor en el puerto de prueba. Las aserciones de configuración pasaron; la integración del servidor permanece pendiente en este cierre, sin sustituir el resultado histórico de la guía de servidores.

## Comprobaciones pendientes antes de la etiqueta

- Completar la integración MySQL y HTTP en PHP 8.4 en un entorno que permita conexiones y escucha local.
- Completar el recorrido de navegador del instalador en un entorno que permita localhost.
- Ejecutar la prueba real de Nginx con `GFRAME_TEST_NGINX` y `GFRAME_TEST_PHP_CGI`.

No se debe presentar la publicación, la etiqueta ni la disponibilidad en Packagist como completadas mientras no existan evidencias de esas acciones. Las capturas temporales de `.playwright-mcp/` no forman parte del paquete ni del commit.
