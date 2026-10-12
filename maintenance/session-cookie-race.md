# Reproducción de la carrera de cookie de sesión

Fecha: 12 de octubre de 2026.

La prueba `tests/SessionCookieRaceTest.php` reproduce el mecanismo con dos servidores HTTP PHP independientes, el driver nativo y un directorio temporal de sesiones compartido. Utiliza los métodos reales `SessionRuntime::start()` y `SessionManager::login()`, con una identidad sintética y sin acceder a cuentas de una aplicación.

La petición pública llega con la cookie anónima A. Una barrera retrasa su arranque de sesión hasta que el cliente recibe la respuesta del login, que ha invalidado A y entregado B. La petición pública arranca entonces con A y devuelve una cookie C. Consultar la identidad con B devuelve el usuario 42; hacerlo con C devuelve un visitante. La respuesta pública contiene JavaScript y un `Set-Cookie` de sesión.

Las barreras fuerzan un orden posible de ejecución; no demuestran su frecuencia en producción. El cliente de pruebas presenta las cookies explícitamente para comprobar el efecto de sustituir B por C. No reproduce el comportamiento específico de Firefox ni el registro de un service worker real, y no verifica los drivers de base de datos o Redis.

Verificación ejecutada:

```text
php packages/phpunit/phpunit/phpunit --filter SessionCookieRaceTest
```

El resultado inicial fue una prueba aprobada y 16 aserciones. La prueba confirma el comportamiento actual que causa el problema; no acredita una corrección.

La corrección añade `RouteBuilder::noSession()` para recursos públicos GET web/system sin middleware. El punto de entrada estándar pasa `ABSPATH` al constructor del router, que inicia la sesión de las rutas normales antes de middleware y controladores. La rotación del login permanece intacta. Ser pública no implica automáticamente que una ruta funcione sin sesión.

La prueba concurrente utiliza ahora el router real y compara dos casos: la ruta normal reproduce la sustitución; la ruta `noSession()` no envía cookie y conserva el usuario 42 después de la respuesta tardía. Pasaron ambos casos, con 30 aserciones. La prueba de servidor Apache también verifica una ruta de controlador real que entrega JavaScript sin sesión ni `Set-Cookie`, y que el login normal sigue iniciando sesión. Nginx no se ejecutó en este entorno.
