# Verificación de GFrame 2.0.2

Fecha: 12 de octubre de 2026. Entorno: Windows, PHP 8.3.35, Apache de pruebas y Edge en modo headless. Todos los proyectos y sesiones de pruebas se crearon en ubicaciones temporales.

Cambios revisados: envío del login por clic y Enter sin solicitudes simultáneas, rutas virtuales bajo `/app` sin entrega de archivos internos, y rutas públicas GET explícitas `noSession()` con arranque de sesión delegado al router. La prueba concurrente compara la respuesta pública normal con la respuesta sin sesión, incluida la conservación de la identidad autenticada. Apache también comprueba que la redirección de una ruta sin sesión no envíe cookies.

Resultados:

- Lint: 419 archivos PHP válidos.
- Suite PHP completa: 473 pruebas, 4570 aserciones, 9 omitidas y ningún fallo. Ejecutada con las variables de Apache de pruebas y `COMPOSER_PROCESS_TIMEOUT=1800`; el primer intento de `composer check` alcanzó su límite de 300 segundos. La repetición mediante `composer test` terminó en 11 minutos y 16 segundos.
- JavaScript: 84 pruebas aprobadas, incluidas las pruebas de navegador de login, GFSelect, instalador y montajes multimedia. Se utilizó `GFRAME_TEST_BROWSER` con la ruta de Edge y el Playwright del runtime local. El primer intento usó el valor `1`, incompatible con las pruebas anteriores; se uniformó el soporte de la ruta del ejecutable en las pruebas de login y GFSelect y se repitió toda la suite.
- Composer: `composer validate --strict` aprobado.
- Skills: 14 skills y 79 enlaces locales válidos; metadata YAML válida y 7 pruebas Python aprobadas. PyYAML 6.0.3 se instaló únicamente en un directorio temporal de comprobación.
- PowerShell: instalación de skills, 167 comprobaciones; empaquetado del complemento, 225 comprobaciones.
- `git diff --check`: aprobado. Revisión editorial de los documentos modificados sin errores críticos.

Las pruebas optativas que requieren servicios no configurados permanecen omitidas. No se ejecutó Nginx real ni MySQL estricto en este entorno. No se verificó un service worker real en Firefox ni una carrera de sesión con Redis o base de datos. La CI debe comprobar las restantes versiones PHP de su matriz.

Los proyectos con `index.php` o vista de login personalizados deben aplicar las adaptaciones documentadas en `docs/actualizaciones.md`. Esta verificación no actualiza aplicaciones instaladas ni publica la versión remota.
