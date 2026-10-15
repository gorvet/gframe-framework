# Auditoría — etapa 04

## Pase 1 — 2026-10-15

Baseline: CONCILIACION.md cerrada el 2026-10-15, TAREAS.md principal implementada; HEAD 69ab6a2 con implementación HTTP sin commit. Windows, PHP 8.3.35, SQLite, PHPUnit 10.5.65.

**Veredicto: no coincide.**

| Evidencia | Procedimiento | Resultado |
|---|---|---|
| E-01 | `php packages/phpunit/phpunit/phpunit --filter 'CronHttpExecutor\|CronConcurrency\|VirtualCronHttp\|ProjectUpdateService\|DocumentationLinks'` | Salida 1: 28 pruebas, 981 aserciones; falta storage/finished en VirtualCronHttpTest. |
| E-02 | Mismo comando, repetición sin cambios | Salida 0: 28 pruebas, 991 aserciones. El fallo intermitente sigue pendiente. |
| E-03 | `php packages/phpunit/phpunit/phpunit --filter VirtualCronHttpTest` | Salida 0: 1 prueba, 21 aserciones. |

| CA | Resultado | Evidencia y hueco |
|---|---|---|
| CA-01 | cumple | E-02: visitas reales sin callback ni efecto; configuración y registro inspeccionados. |
| CA-02 | parcial | E-01 contradice la fiabilidad de la evidencia CLI. El fixture mantiene su barrera durante comprobaciones CSRF independientes. |
| CA-03 | parcial | E-02 acredita autenticación, métodos y ticket mal formado; falta intento HTTP con ticket de formato válido no reservado. |
| CA-04 | parcial | Transporte y endpoint inspeccionados, URLs inválidas probadas; completar prueba observable de parámetros que intentan escoger handler/límite. |
| CA-05 | cumple | E-02: publicación y actualización con preservación, documentación y enlaces. |

Sin desviaciones de producto. TLS/FPM/proxy reales no verificados; son validación ambiental excluida explícitamente de este cierre local en la conciliación. No se afirma aptitud de un servidor de producción. No se aplica mutación global: la barrera de procesos y las peticiones negativas proporcionan sensibilidad directa.

Corrección requerida: [TAREAS-delta-01.md](TAREAS-delta-01.md).

## Pase 2 — 2026-10-15

Mismo baseline y entorno que el pase 1; delta 01 implementado, sin cambios al contrato. Se conservan los cambios ajenos de medios. Auditoría realizada después de la implementación y su verificación.

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y fecha | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-04 | CA-01–05 | `php packages/phpunit/phpunit/phpunit --filter 'CronHttpExecutor\|CronConcurrency\|VirtualCronHttp\|ProjectUpdateService\|DocumentationLinks\|ServerRouting'` | Sin fallos; efectos y ausencia de efectos comprobados | Salida 0; 33 pruebas, 1040 aserciones, 2 omitidas | Windows/PHP 8.3.35/SQLite; 2026-10-15 | Salida de PHPUnit; fixtures temporales eliminados | Apache/Nginx reales omitidos por configuración ambiental |
| E-05 | CA-05 | `php bin/lint.php` | PHP válido | Salida 0; 435 archivos | Mismo entorno y fecha | Salida CLI | Sintaxis; no prueba servidor |
| E-06 | CA-05 | `php bin/validate-skills.php` | Enlaces locales válidos | Salida 0; 14 skills, 79 enlaces | Mismo entorno y fecha | Salida CLI | No verifica servicios externos |
| E-07 | CA-01–05 | `python <skills>/feature-stages/scripts/validate_stage.py maintenance/features/cron-configurable/04-ejecutor-http` | Sin errores estructurales | Salida 0; contrato válido | Python local; 2026-10-15 | Salida CLI | Cinco advertencias heredadas de formato del tipo de CA; baseline intacto |

### Resultado contra los criterios principales

| CA | Resultado | Afirmaciones atómicas y trazabilidad |
|---|---|---|
| CA-01 | cumple | T1/E-04: real devuelve respuesta sin registrar callback; no efecto ni lanzamiento persistido. Virtual sí registra. Configuración validada sin acceder a almacenamiento en register. |
| CA-02 | cumple | T2, delta D01-T1/E-04: respuesta CLI anterior a finalización del handler; finalización persistida y un intento. Socket HTTP retorna sin leer respuesta; pasada virtual ejecutada. Ambos usan CronExecutor, ticket, límite y bloqueo compartidos, con competencia, intervalo y recuperación probados en CronConcurrencyTest. |
| CA-03 | cumple | T2, D01-T2/E-04: POST autorizado ejecuta; credencial incorrecta, GET, endpoint deshabilitado y tickets ausentes/malformados rechazados antes del acceso. Ticket bien formado no reservado produce cron_skipped sin añadir efectos. |
| CA-04 | cumple | T2, D01-T2/E-04: query y cuerpo intentan handler inexistente y límite cero; se ejecuta exclusivamente el handler persistido. Destino procede de APP_URL; URLs inválidas rechazadas. Cabeceras recibidas por transporte contienen bearer/ticket, sin query secreta. Diagnóstico excluye ticket. Inspección del endpoint y del lanzador confirma ausencia de parámetros ejecutables y de secretos en respuestas/HTML/URL. |
| CA-05 | cumple | T3/E-04–07: updater publica archivo original y preserva personalizaciones; configuración de servidores, documentación y skills coherentes con cuatro caminos. Sin migración adicional: utiliza cron_runtime ya publicado. |

**Veredicto: coincide.**

Verificaciones globales y métodos de T1–T3 y delta cubiertos. Trazabilidad inversa revisada: cambios de runtime, módulo, esqueleto, documentación, skills y filtro CI pertenecen a T1–T3. Sin desviaciones de alcance. No se certifica HTTPS, PHP-FPM/proxy ni despliegue de producción: están expresamente fuera de la validación ambiental local acordada. No se cierran las etapas anteriores ni se publica una versión con este veredicto.

Durante la implementación del delta una ejecución intermedia mostró que el diagnóstico puede pertenecer a una pasada vacía posterior; se corrigió la expectativa del test, manteniendo la comprobación de completed y attempts=1. La ejecución final E-04 no contiene fallos ni avisos PHP. La suite completa anterior queda como evidencia histórica en VERIFICACION.md, no como ejecución nueva de esta auditoría.

## Pase 3 — validación ambiental posterior, 2026-10-15

Baseline sin cambios. Snapshot 6abcc24; prueba nueva con Nginx 1.24, PHP-FPM 8.3 y TLS local en Ubuntu 24.04. **Veredicto: no coincide.**

E-08: job 116710599386 de la ejecución https://github.com/gorvet/gframe-framework/actions/runs/38818977151; `php packages/phpunit/phpunit/phpunit --filter CronHttpsFpmTest`, salida 1, 1 prueba y 27 aserciones. POST real autenticado, rechazos de autenticación, certificados y tickets correctos. POST virtual interno termina en HTTP 499 en Nginx y el handler nunca empieza. La configuración documentada carece de `fastcgi_ignore_client_abort on`; cerrar el socket puede cancelar el envío a PHP-FPM antes de que ignore_user_abort de PHP tenga efecto.

CA-01, CA-03 y CA-04: cumple según E-04/E-08. CA-02: no cumple en este entorno. CA-05: parcial, falta requisito de despliegue. El pase 2 permanece como resultado histórico local, con su exclusión ambiental original. Nuevo trabajo: [delta 02](TAREAS-delta-02.md), sin alterar el baseline.

## Pase 4 — cierre tras delta 02, 2026-10-15

Mismo baseline. Snapshot 6fbd1a0; delta 02 implementado. Inspección de configuración, guías, skill y logs remotos posteriores a la corrección; no se modifica el runtime en esta auditoría.

| ID | CA | Procedimiento | Esperado | Observado/exit | Entorno y fecha | Artefacto | Limitación |
|---|---|---|---|---|---|---|---|
| E-09 | CA-01–04 | `php packages/phpunit/phpunit/phpunit --filter CronHttpsFpmTest` en job 116711877136 | TLS, FPM y ejecución independiente reales | Salida 0, 1 prueba/33 aserciones; sin omisiones | Ubuntu 24.04, Nginx 1.24, PHP-FPM 8.3.6; 2026-10-15T22:46:48Z | [CI](https://github.com/gorvet/gframe-framework/actions/runs/38819363756) y HTTPS-FPM.md | Fixture aislado, sin producción |
| E-10 | CA-01–05 | Consultar jobs y conclusión de CI 38819363756 | Todas las regresiones aprobadas | Nueve jobs success: PHP 8.1–8.4, MySQL, Windows cron/CSRF, HTTPS/FPM, JS, metadata | GitHub Actions; 2026-10-15 | Misma ejecución de CI | Pruebas opcionales ajenas pueden omitirse en las suites generales; el job FPM no se omite |
| E-11 | CA-05 | `php packages/phpunit/phpunit/phpunit --filter 'DocumentationLinks\|ServerRoutingTest::testNginxFragment'`; `php bin/validate-skills.php` | Documentación y fragmento consistentes | Salida 0, 5 pruebas/143 aserciones; 14 skills/79 enlaces | Windows PHP 8.3.35; 2026-10-15 | Salida CLI | No sustituye E-09 |

| CA | Resultado | Fundamento |
|---|---|---|
| CA-01 | cumple | E-09: visita real sin lanzamiento persistido ni handler; se confirma entorno fpm-fcgi y función de finalización. E-04/E-10 mantienen la regresión del registro. |
| CA-02 | cumple | E-09: virtual HTTP entrega respuesta con handler retenido por barrera, mantiene sesión disponible y termina solo al liberar; límite de una tarea y otra pendiente. TLS del lanzador verificado. E-10 conserva CLI, bloqueo compartido y recuperación. La sensibilidad se acredita con el fallo E-08 frente al éxito E-09. |
| CA-03 | cumple | E-09: ejecución real autenticada, 403/405 preservados, ticket virtual ausente rechazado y ticket inventado omitido sin efectos. E-04/E-10 mantienen endpoint deshabilitado. |
| CA-04 | cumple | E-09: certificado no confiable y hostname incorrecto rechazados antes de enviar; endpoint sin sesión. E-04/E-10 conservan destinos confiables, límites y handlers no seleccionables, diagnóstico sin secretos. |
| CA-05 | cumple | E-11 y comparación de configuración real con ejemplo: directiva de protección documentada y fragmento sin socket ni manejador PHP. E-10 conserva publicación y actualización con personalizaciones. |

**Veredicto: coincide.** Delta 02 y métodos principales cubiertos; etapa cerrada. Sin desviaciones ni aplicaciones modificadas. La comprobación ambiental ya está realizada en el entorno Linux indicado; no certifica la configuración de un sitio concreto. Los pases anteriores se conservan íntegros. No implica publicación de una etiqueta.
