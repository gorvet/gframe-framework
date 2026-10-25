# Correcciones posteriores a la revisión de PR #2

> **Seguimiento conciliado — 2026-10-25:** PR #2 integrada. La nota final de PHPStan informativo fue superada por PR #3 y PR #7: nivel 5 obligatorio, cero avisos y baseline retirado. La matriz PHP 8.1–8.4 ya pasó en CI. Véase [estado y evidencia de cierre](../../cierre-auditorias-20261025.md).

Baseline: rama endurecimiento-seguridad, commit 3d0314dd7179a679e9d33a842a32c19a6ed0c255. Se conservan los cambios publicados por Claude y las decisiones del propietario. Skills canónicas aplicadas: orchestrator, framework-maintenance, core-architecture y orm-models, con los contratos de autenticación/sesiones ya seleccionados durante la revisión.

Alcance autorizado: corregir los bloqueos detectados, verificar y actualizar la rama de la PR antes de valorar su integración. Se comprueban reconexión, transacciones de historial y concurrencia SQLite, cookies sin emisión, captura de errores esperados en pruebas aisladas, suite completa y CI. No se modifican aplicaciones consumidoras.

El gestor expone existingConnection para inspeccionar su pool sin abrir conexiones. reconnect rechaza una transacción activa tanto allí como en el pool del ORM. La prueba de regresión inicia la transacción directamente desde DatabaseManager y comprueba que conexión y datos sin confirmar se conservan tras el rechazo.

El dialecto SQLite repite la transacción completa de historial tras rollback por SQLITE_BUSY/BUSY_SNAPSHOT, con una ventana de cinco segundos. CAS se reevalúa en cada intento. No se reintentan otros errores ni se modifica la API de dialectos personalizados. La prueba de fallo inyectado confirma que el primer INSERT se revierte, y la prueba multiproceso comprueba un único ganador.

Los tests de presencia y configuración de correo provocan errores deliberados y los capturan en archivos de log temporales, en vez de enviarlos a stderr del proceso aislado. No se eliminan los logs del código de producción. La configuración de cookies se omite si session.use_cookies está desactivado.

## Verificación local

- PHP 8.3.35: suite completa de 580 pruebas y 5736 aserciones, sin fallos ni advertencias; 14 omitidas por requisitos de integración. XML en `.phpunit.cache/pr2-fixes.xml` (ignorado por Git).
- Selección final de transacciones, presencia, correo y CSRF: 20 pruebas, 161 aserciones, sin fallos ni advertencias. La selección anterior a la limpieza de logs también pasó; no se suman resultados solapados.
- MySQL real: diez pruebas, 148 aserciones, sin fallos ni omisiones, para contenido, revisiones y preflight.
- Carrera de escritores de historial: cinco ejecuciones aisladas consecutivas, un ganador y un conflicto en cada una; seis aserciones por ejecución.
- JavaScript de autenticación y sesiones: siete pruebas correctas.
- Lint completo: 512 archivos PHP válidos. Skills: 14 válidas y 80 enlaces; diff sin errores de contenido.

El análisis estático PHPStan añadido por Claude continúa siendo informativo (`continue-on-error`), con 108 diagnósticos en el SHA anterior y baseline pendiente. No se declara que ese análisis haya pasado ni se silencian sus diagnósticos para conseguir un estado verde. Esta corrección atiende los bloqueos de runtime y pruebas de la revisión. La verificación de PHP 8.1–8.4 en Linux corresponde a la nueva ejecución CI de la PR.
