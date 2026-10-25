# Verificación de infraestructura pendiente

Base: main `d477d760a3b2a3661b0c120dd258fed42a30a230`. Continuación de V-02 del [seguimiento histórico](cierre-auditorias-20261025.md). Solo pruebas y CI; no se modifica el core ni se instalan servicios en proyectos del usuario.

Convenciones seleccionadas: orchestrator, framework-maintenance y auth-access, con los contratos de sesiones y revocación. El resultado requiere ejecuciones reales; un test omitido impide aprobar el job dedicado.

## Comprobaciones preparadas

- PHP 8.4 contra MySQL 8: migraciones, preflight, instalación y recorridos HTTP de fixtures temporales.
- phpredis contra Redis 7: lectura/escritura, revocación desde otra instancia, escritura tardía que no restaura sesiones, bloqueo/desbloqueo, versiones de autorización global/usuario/tenant y expiración real. La cuenta se consulta en SQLite temporal; los Lua y TTL se ejecutan en Redis real. Limpieza limitada al prefijo aleatorio de cada prueba, sin vaciar bases Redis.
- Routing Nginx/PHP-CGI: ejecutar el test existente con recursos públicos, rutas virtuales, archivos internos protegidos, errores y canales.

Los resultados de CI se registrarán después de la ejecución. No se presenta esta preparación como V-02 resuelta. SMTP, personalización del pie, hosting real y cobertura visual universal conservan sus límites.
