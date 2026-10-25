# Verificación de infraestructura pendiente

Base: main `d477d760a3b2a3661b0c120dd258fed42a30a230`. Continuación de V-02 del [seguimiento histórico](cierre-auditorias-20261025.md). Solo pruebas y CI; no se modifica el core ni se instalan servicios en proyectos del usuario.

Convenciones seleccionadas: orchestrator, framework-maintenance y auth-access, con los contratos de sesiones y revocación. El resultado requiere ejecuciones reales; un test omitido impide aprobar el job dedicado.

## Comprobaciones preparadas

- PHP 8.4 contra MySQL 8: migraciones, preflight, instalación y recorridos HTTP de fixtures temporales.
- phpredis contra Redis 7: lectura/escritura, revocación desde otra instancia, escritura tardía que no restaura sesiones, bloqueo/desbloqueo, versiones de autorización global/usuario/tenant y expiración real. La cuenta se consulta en SQLite temporal; los Lua y TTL se ejecutan en Redis real. Limpieza limitada al prefijo aleatorio de cada prueba, sin vaciar bases Redis.
- Routing Nginx/PHP-CGI: ejecutar el test existente con recursos públicos, rutas virtuales, archivos internos protegidos, errores y canales.

Los resultados de CI se registrarán después de la ejecución. No se presenta esta preparación como V-02 resuelta. SMTP, personalización del pie, hosting real y cobertura visual universal conservan sus límites.

## Primera ejecución

[CI 40171290826](https://github.com/gorvet/gframe-framework/actions/runs/40171290826), commit `6574d5c`: MySQL/HTTP y Redis reales, PHP 8.4.27, 36 pruebas/764 aserciones, sin omisiones ni fallos; job 121102466808. Redis 7.4.11 y MySQL 8.0.46 reales en contenedores temporales.

Nginx/PHP-CGI llegó a 79 aserciones y falló porque el test antiguo esperaba `recovery_requested` enviando un POST sin CSRF; el framework devolvió correctamente `fail_csrf`. Job 121102466609. El resto de la matriz pasó. Se conserva este fallo como evidencia de una expectativa obsoleta del fixture, no se cambia la protección del framework.

La corrección de la prueba conserva el caso negativo y añade GET del formulario, cookie de sesión y token/timestamp reales antes del POST válido. Requiere una nueva ejecución de Nginx/PHP-CGI para cerrar ese escenario.

## Segunda ejecución de infraestructura

[CI 40171556708](https://github.com/gorvet/gframe-framework/actions/runs/40171556708), commit `6897107`: doce jobs aprobados. Nginx/PHP-CGI: 1 prueba/85 aserciones, sin omisiones; job 121103266424. MySQL/HTTP y Redis con PHP 8.4.27: 36 pruebas/764 aserciones, sin omisiones; job 121103266379. Los tres escenarios concretos de V-02 quedan acreditados, sin extender el resultado a producción.

## Recorridos de navegador

Suite local completa con Chrome, Playwright 1.62.1 y PHP 8.3.35, base d477d76: 99 casos, 95 aprobados, cuatro fallidos, sin omisiones. Instalador, gestión y selector fallan al navegar a loopback con `ERR_NETWORK_ACCESS_DENIED`; no se presentan como fallos demostrados del producto. El autoguardado falla al comparar timestamps tomados cuando Node recibe los eventos de red, en lugar del instante de envío en el navegador. La repetición aislada vuelve a fallar.

Sonda independiente en memoria, sin editar el runtime: envíos separados por 5005,6 ms en `performance.now()` y 5005 ms en Date.now() del navegador; recepción de esos mismos eventos en Node separada por 4933 ms. La sonda completa termina aprobada, 1 caso. La llegada del evento al arnés no es el reloj del planificador; la demora de cada evento varía.

El test conserva el umbral de 4800 ms y observa el envío real de XMLHttpRequest mediante el reloj monótono del navegador. No modifica temporizadores ni tráfico del producto. La nueva ejecución con demora deliberada del arnés debe confirmar que no se confunde esa demora con el intervalo del editor.

Se añade un job Chromium que ejecuta la suite completa en Linux, donde no se hereda el bloqueo local. Dependencias solo en el directorio temporal del runner; sin package.json ni cambios del core. La cobertura de todos los paquetes visuales opcionales sigue siendo más amplia que estos recorridos y no se da por completada automáticamente.
