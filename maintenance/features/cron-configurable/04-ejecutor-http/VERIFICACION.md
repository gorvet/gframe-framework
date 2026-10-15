# Verificación de implementación

Entorno local: Windows, PHP 8.3.35, PHPUnit 10.5.65, SQLite y MySQL/MariaDB de pruebas. No constituye auditoría ni cierre de etapa.

| Criterio | Evidencia |
|---|---|
| CA-01 | CronHttpExecutorTest inicia un servidor PHP real: en real, ReflectionProperty confirma registered=false; sin lanzamiento ni efectos ni last_launch_at. En virtual confirma registered=true. |
| CA-02 | El mismo servidor ejecuta una pasada real HTTP y una virtual HTTP por socket, sin utilizar Async ni exec para el segundo lanzamiento. VirtualCronHttpTest conserva la comprobación del worker CLI independiente. El test del socket no emite respuesta: el lanzador retorna tras escribir la solicitud. |
| CA-03 | POST autorizado ejecuta una tarea inocua; credencial incorrecta y ticket ausente no producen efectos. Unitarias rechazan GET, secreto ausente y endpoint deshabilitado antes de acceder a almacenamiento. Ticket obsoleto y competencia CLI/virtual se mantienen cubiertos por CronConcurrencyTest. |
| CA-04 | URLs con credenciales, query, protocolo no HTTP o inyección CRLF se rechazan; el request envía secretos únicamente en cabeceras. El endpoint no lee cuerpos ni parámetros de clase/límite. La configuración de diagnóstico no contiene secreto. |
| CA-05 | ProjectUpdateServiceTest comprueba publicación del endpoint y preservación de personalizaciones; tests de servidor revisan configuración distribuida y documentación. |

## Ejecuciones

- `php packages/phpunit/phpunit/phpunit --filter CronHttpExecutorTest`: 5 pruebas, 29 aserciones, sin fallos.
- `php packages/phpunit/phpunit/phpunit --filter 'CronHttpExecutor|DocumentationLinks|ProjectUpdateService|CronConcurrency|VirtualCronHttp'`: 28 pruebas, 988 aserciones, sin fallos (antes de las dos aserciones finales de registro y de publicación).
- `php packages/phpunit/phpunit/phpunit --filter 'Server|Htaccess|Nginx|ProjectUpdateService|DocumentationLinks'`: 18 pruebas, 885 aserciones, 2 omitidas por faltar ejecutables de servidores reales; sin fallos.
- Perfil MySQL con GFRAME_CRON_TEST_ENGINE=mysql y GFRAME_TEST_MYSQL_DSN configurado en el proceso de prueba: 15 pruebas y 126 aserciones, sin fallos.
- `php bin/lint.php`: 435 archivos válidos. `php bin/validate-skills.php`: 14 skills, 79 enlaces válidos.
- DocumentationLinksTest posterior a los cambios de guías: 4 pruebas, 121 aserciones, sin fallos.
- Validador de etapas: sin errores estructurales; advertencias heredadas sobre formato del tipo de criterio.

## Límites

No se ejecutaron despliegues, cron externo de producción ni pruebas TLS reales o PHP-FPM/proxy reales. La prueba HTTP utiliza servidor PHP temporal y TCP local; la verificación TLS está habilitada en el transporte y requiere validación del host de despliegue. El envío de bytes no confirma respuesta remota ni finalización; el ticket y diagnóstico persisten el límite. No se afirma que HTTP no consuma un proceso web.

## Suite completa final

`php packages/phpunit/phpunit/phpunit`: salida 0; 496 pruebas, 4728 aserciones, 11 omitidas por requisitos de entorno, sin fallos. Las aserciones añadidas después de iniciar la suite tienen ejecuciones focalizadas adicionales descritas arriba. Tiempo local: 5 minutos y 40 segundos.
