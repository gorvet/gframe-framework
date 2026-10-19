# Verificación de GFrame 4.3.0

## Entrega

Capacidades optativas de guardado/presencia, historial, preview privada, papelera y publicación programada. Las aplicaciones conservan tablas, permisos, rutas y políticas propias. El generador solicita ahora `^4.3`; antes seguía solicitando `^2.0.1`. Guía de actualización y changelog preparados para `v4.3.0`, sin añadir una propiedad version al manifiesto Composer.

Se conservan las propuestas de multimedia avanzada, patrones e importación/exportación, así como los aplazamientos de salud del sitio, redirecciones y barra administrativa. Ningún consumidor existente fue actualizado.

## Comprobaciones

- Suite PHP final: 530 pruebas, 5321 aserciones, 14 omisiones documentadas y ningún fallo.
- Lint completo: 493 PHP válidos.
- JavaScript: 98 pruebas, 81 aprobadas, 17 omisiones de navegador; ningún fallo. La prueba conjunta de navegador real del commit `0c1655c` pasó con aplicación instalada, sesiones, HTTP y cron CLI; véase [su verificación](editorial-integration-verification.md).
- MySQL editorial: 6 pruebas, 133 aserciones, sin omisiones; migraciones MySQL: 8 pruebas, 41 aserciones, sin omisiones. Los esquemas temporales se eliminan al terminar.
- Generador y skills del proyecto: 7 pruebas, 27 aserciones; skills canónicas: 14 y 79 enlaces válidos.
- `composer validate --strict`: correcto; `composer audit`: sin avisos de vulnerabilidades.
- Instalación Composer desde ZIP: versión resuelta 4.3.0; runtime cargado desde `packages/gorvet/gframe` del consumidor, no desde el checkout. Post-autoload activa las skills locales del consumidor. Aplicación managed/SQLite instalada, lifecycle editorial ejecutado y nueve archivos de runtime/scaffold comparados por SHA-256 con el origen.

Los comandos y logs están en `.phpunit.cache/release-4.3.0-*`: `final.xml`, `lint.log`, `js.log`, `mysql.xml`, `mysql-migrations.xml`, `scaffold.xml`, `composer-validate.log`, `composer-audit.log`, `consumer-final.log` y `consumer-probe.log`. Entorno: Windows/PHP 8.3.35, SQLite y MySQL local, 2026-10-19.

## Incidencias y límites

La primera suite había cargado la expectativa antigua del generador cuando se ajustó la restricción a 4.3. Su único fallo fue esa expectativa; se repitió la suite completa después de cerrar ambos archivos. En futuras entregas se deben cerrar los cambios antes de arrancar la suite completa.

La extracción del ZIP utiliza `php -d extension=zip` solo para ese comando; no se modifica php.ini. El archivo se construye con `git archive` del árbol preparado para incluir solo archivos versionados; se descartó una primera creación de archivo mediante Composer que recorría el checkout. La prueba Composer usa un repositorio local de paquete para 4.3.0 y el ZIP real; no demuestra por sí sola que Packagist ya haya indexado la etiqueta remota.

La suite general omite por configuración 14 casos: ocho migraciones MySQL cubiertas por separado y seis comprobaciones ambientales restantes (HTTPS/FPM, preflight MySQL, instalación SaaS/MySQL, bindings MySQL y servidores Nginx/Apache). No se presentan esas seis como aprobadas. No se valida un despliegue de producción ni se configura el cron del sistema.
