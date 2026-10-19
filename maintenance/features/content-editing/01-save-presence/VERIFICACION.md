# Verificación de implementación

Baseline del 2026-10-19, sobre `939b5c6`, más cambios locales de esta etapa y roadmap previo conservado. No se modifican aplicaciones consumidoras. Este registro no constituye veredicto de auditoría.

## Entorno y contratos

Windows, PHP 8.3.35, SQLite, MySQL local de pruebas y Chrome headless con Playwright. Los esquemas MySQL creados por pruebas tienen prefijos aleatorios `gframe_content_test_`, `gframe_audit_` y `gframe_check_`; se eliminan después de las pruebas. No se consultan usuarios reales.

El guardado se integra por herencia sobre ORM. Se añadió una operación genérica `whereExact`, con SQL en dialectos nativos y rechazo seguro si un dialecto externo no aporta esa capacidad. La interfaz existente sigue siendo implementable sin añadir métodos obligatorios. No se cambiaron las comparaciones existentes.

## Ejecuciones focalizadas

- `php packages/phpunit/phpunit/phpunit --filter ContentEditing --log-junit .phpunit.cache/content-editing-implementation-final.xml`, con DSN MySQL de prueba: 5 pruebas, 106 aserciones, exit 0, sin omisiones.
- `GFRAME_CONTENT_TEST_ENGINE=mysql`, `GFRAME_TEST_MYSQL_DSN` configurado y `php packages/phpunit/phpunit/phpunit --filter 'ContentEditingTest::testTenCompetitions' --log-junit .phpunit.cache/content-editing-mysql-race.xml`: 1 prueba, 50 aserciones, exit 0. Diez competencias, un ganador por revisión.
- `php packages/phpunit/phpunit/phpunit --filter 'ContentEditingTest::testExistingCustomDialect'`: 1 prueba, 1 aserción, exit 0. Un dialecto que implementa solo la interfaz histórica sigue siendo construible y rechaza whereExact antes de consultar una conexión.
- `GFRAME_TEST_BROWSER` apunta a Chrome y `NODE_PATH` a las dependencias del runtime; `node --test tests/js/content-editing.browser.test.cjs`: 1 prueba real de navegador, sin omisiones, exit 0, 43,751 segundos. PHP/SQLite reales detrás del transporte interceptado, conforme al patrón de las pruebas existentes; no sustituye una validación de despliegue de aplicación.

## Sensibilidad y resultados negativos conservados

1. Primera prueba de presencia/migración falló por falta del esquema base auth en el fixture. Se preparó el esquema base y su baseline antes de migrar el módulo, igual que una instalación administrada. No se añadieron tablas de artículos al paquete.
2. Primera prueba MySQL de revisión `R1` contra `r1` encontró un defecto real: la comparación normal del ORM seguía la collation permisiva. Resultado de la suite de ese corte: 38 pruebas/1121 aserciones, un fallo. Se añadió whereExact en ORM y expresiones por dialecto, conservando el fallo como sensibilidad del caso. El modelo usa ahora esa operación; el caso de mayúsculas y el espacio final rechazan la escritura.
3. El primer transporte de navegador falló por usar en el fixture una función legacy no cargada sin bootstrap. Se usa SanitizeHelper directamente y se reportan los errores del proceso PHP en el runner. No se modificó el bootstrap del framework para acomodar el fixture.
4. Publicación de assets exigía una raíz temporal existente; se corrigió la preparación del fixture. Una ejecución amplia con DSN MySQL sin base encontró además una prueba ORM que requiere base seleccionada: la suite global posterior utiliza su propia base temporal, sin cambiar esa prueba.
5. La primera suite completa terminó con 521 pruebas/5219 aserciones, cinco omisiones y un fallo en VirtualCronHttpTest: el diagnóstico podía encontrar ocupado el worker vacío lanzado por la última respuesta. Una repetición aislada pasó (1 prueba/21 aserciones). Se sincroniza el fixture con el fin del ticket y del worker, con plazo de ocho segundos; se conserva la aserción de inactividad y se añade que no quede ticket pendiente. No se cambió el cron ni sus criterios.

## Cobertura

- Guardado: carrera real, revisión antigua/exacta, ámbito ajeno, rollback y conexión nombrada; modificación de PK/revisión/ámbito rechazada y builder con OR pendiente rechazado.
- Presencia: scopes separados, misma persona en varias sesiones, caducidad de 90 segundos, retorno tras desconexión, tokens/recursos ajenos sin exposición.
- Controlador: sesión ausente, tenant contradictorio, token inválido, usuario/recurso de POST ignorados; falta de tabla retorna un error público sin SQL.
- Cliente: CSRF real del middleware en fixture, dos formularios con conflicto, texto retenido, confirmación explícita, una petición activa, visibilidad, destrucción e intervalo completo sin nuevos pings después de destruir.
- Instalación: migración MySQL/SQLite repetible, publicación por manifest y vista readonly omitida; ejemplos PHP del documento ejecutados contra SQLite y HTML escapado.

## Controles globales

Trazabilidad inversa: VersionedContentModel, ORM::whereExact y sus dialectos responden a T1/CA-01–02; modelo/servicio/controlador de presencia y esquemas responden a T2/CA-03–04; vista/meta/JS a T3/CA-05; catálogo, documentación, skills y pruebas a T4/CA-06. La sincronización de VirtualCronHttpTest es una corrección de verificación global dentro de T4, sin cambiar su resultado esperado ni el runtime previamente cerrado. No se modificó el alcance de las conciliaciones anteriores.

Suite completa final: `php packages/phpunit/phpunit/phpunit --log-junit .phpunit.cache/content-editing-full-final.xml`, con base MySQL temporal seleccionada, exit 0; 522 pruebas/5226 aserciones, cinco omisiones ambientales ajenas a esta etapa (HTTPS/FPM, preflight MySQL optativo, instalación SaaS optativa y servidores Nginx/Apache). Ninguna prueba de content-editing se omite. Lint: 465 archivos válidos. Enlaces de textos modificados: 163 comprobados, cero ausentes. Skills: 14 válidas, 79 enlaces. `node --check resources/modules/content-editing/public/content-editing.js` termina en 0. Suite JS general: 95 pruebas, 80 aprobadas y 15 de navegador omitidas por configuración; la nueva prueba de navegador se ejecutó por separado sin omisiones.

## Límites

No se valida producción ni se activan migraciones de tablas de negocio. Todos los escritores deben adoptar la revisión y la aplicación debe autorizar registro/tenant. La presencia es orientativa, depende de red/relojes y conserva registros caducados de recursos que no se vuelvan a abrir. No hay revisión histórica ni publicación automática. Las pruebas de navegador usan la integración PHP real mediante transporte interceptado; no certifican un proxy o servidor web de despliegue.
