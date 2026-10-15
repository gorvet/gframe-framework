# Validación ambiental HTTPS y PHP-FPM

Trabajo solicitado después del cierre local de la etapa 04. No cambia sus criterios ni sustituye su auditoría histórica.

Skills canónicas aplicadas: gframe-orchestrator, gframe-framework-maintenance, gframe-background-jobs y gframe-integrations; referencias de mantenimiento, cron y transportes. Solo se añaden comprobaciones, sin modificar el runtime ni aplicaciones consumidoras.

## Evidencia previa

2026-10-15: CI de 514fabb aprobado, ocho jobs: PHP 8.1–8.4, cron MySQL, cron/CSRF Windows, JavaScript y metadata. Ejecución: https://github.com/gorvet/gframe-framework/actions/runs/38818300927.

## Método ambiental

`CronHttpsFpmTest` requiere GFRAME_TEST_NGINX y GFRAME_TEST_PHP_FPM. El job dedicado utiliza Ubuntu 24.04, Nginx y PHP-FPM 8.3 reales; crea certificado local de un día y una aplicación SQLite temporal, con el endpoint y las reglas Nginx originales publicados.

Comprueba POST autenticado, errores 403/405 preservados por FastCGI, endpoint sin cookie de sesión, rechazo de certificado no confiable y nombre incorrecto desde CronHttpLauncher, ticket virtual inválido sin efectos, envío virtual con TLS confiable, respuesta anterior a finalización del handler y disponibilidad de la misma sesión mientras el handler espera. Verifica límite de una tarea y estado persistido tras liberar la barrera. No utiliza cron ni sitios de producción.

Estado inicial (6abcc24): preparado para ejecución ambiental en CI. En Windows se omite explícitamente porque no hay PHP-FPM disponible. El resultado final de Linux se registra más abajo.

Primera ejecución ambiental (6abcc24): fallo reproducible, POST virtual cancelado con 499 antes de llegar al handler. Véanse pase 3 de AUDIT.md y delta 02. Corrección en verificación: fastcgi_ignore_client_abort on en el manejador Nginx; guía de despliegue y ejemplo exacto actualizados. Certificados, autenticación y ejecución real HTTP ya superaron sus aserciones antes del fallo.

## Resultado final — 2026-10-15

Validación ambiental aprobada en 6fbd1a0: https://github.com/gorvet/gframe-framework/actions/runs/38819363756, nueve jobs con conclusión success. Job HTTPS/FPM 116711877136: `OK (1 test, 33 assertions)`, sin omisiones ni fallos, 0,848 segundos de prueba. Nginx 1.24.0, PHP-FPM 8.3.6, cliente PHP CLI 8.3.35; Ubuntu 24.04. El certificado local se verifica explícitamente; no se desactiva TLS. Las dos conexiones negativas rechazan certificado no confiable y hostname incorrecto.

La prueba confirma que `fastcgi_finish_request` entrega la respuesta antes de liberar el handler, que la misma sesión sigue disponible y que la pasada respeta el límite y completa su estado. El test anterior sin fastcgi_ignore_client_abort on fallaba en el mismo punto: evidencia roja/verde de la protección requerida. La configuración de cada despliegue sigue siendo responsabilidad del sitio; no se realizó ninguna operación en producción.
