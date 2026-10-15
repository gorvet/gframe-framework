# Validación ambiental HTTPS y PHP-FPM

Trabajo solicitado después del cierre local de la etapa 04. No cambia sus criterios ni sustituye su auditoría histórica.

Skills canónicas aplicadas: gframe-orchestrator, gframe-framework-maintenance, gframe-background-jobs y gframe-integrations; referencias de mantenimiento, cron y transportes. Solo se añaden comprobaciones, sin modificar el runtime ni aplicaciones consumidoras.

## Evidencia previa

2026-10-15: CI de 514fabb aprobado, ocho jobs: PHP 8.1–8.4, cron MySQL, cron/CSRF Windows, JavaScript y metadata. Ejecución: https://github.com/gorvet/gframe-framework/actions/runs/38818300927.

## Método ambiental

`CronHttpsFpmTest` requiere GFRAME_TEST_NGINX y GFRAME_TEST_PHP_FPM. El job dedicado utiliza Ubuntu 24.04, Nginx y PHP-FPM 8.3 reales; crea certificado local de un día y una aplicación SQLite temporal, con el endpoint y las reglas Nginx originales publicados.

Comprueba POST autenticado, errores 403/405 preservados por FastCGI, endpoint sin cookie de sesión, rechazo de certificado no confiable y nombre incorrecto desde CronHttpLauncher, ticket virtual inválido sin efectos, envío virtual con TLS confiable, respuesta anterior a finalización del handler y disponibilidad de la misma sesión mientras el handler espera. Verifica límite de una tarea y estado persistido tras liberar la barrera. No utiliza cron ni sitios de producción.

Estado: preparado para ejecución ambiental en CI. En Windows se omite explícitamente porque no hay PHP-FPM disponible. No se declara validado hasta obtener el resultado del job Linux.
