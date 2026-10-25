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
