# Revisión independiente de los hallazgos y cambios de Claude

Fecha: 2026-10-23. Revisión de solo lectura del código funcional; no se aplican correcciones ni se publica una versión. Se añaden únicamente este informe, una sonda reproducible y sus resultados con datos ficticios.

Baseline Git: `b1350313df23e62ca759a86ddd4213dde635010d`, más el árbol local. El diff actual mezcla los cambios iniciales de Claude con la continuación de Codex; no se atribuyen a Claude los ajustes posteriores de proxies, conservación de cookies o canales del router. Se revisan los cinco hallazgos de `HALLAZGOS-CLAUDE.md` y la ampliación local adicional de `LogHelper`, que no figura en ese inventario. La autoría individual de esta última no puede demostrarse solo con el árbol sin commits.

Convenciones aplicadas: skills canónicas del repositorio, orchestrator, framework-maintenance, core-architecture, auth-access y orm-models. Se conserva la decisión del propietario: mensajes de cuentas sin cambios, caducidad CSRF únicamente opcional para invitados y ninguna clave adicional para instalar.

## Problemas confirmados

### C-01 — P2: el endurecimiento del ORM rompe consultas SQL legítimas

En `src/database/ORM.php`, `having()` exige ahora un identificador simple y `join()` aplica el mismo validador al argumento de tabla. Esto rechaza `having('COUNT(*)', '>', 1)` y `join('roles AS r', 'r.id', '=', 'items.role_id')`, aunque sean expresiones definidas íntegramente por el desarrollador.

La sonda carga la versión HEAD del ORM bajo un nombre de clase diferente, exclusivamente en memoria, y compara el mismo ejemplo con el código actual. Ambas consultas se ejecutan correctamente en SQLite con HEAD; el árbol actual lanza «Identificador de columna inválido en la consulta» antes de ejecutarlas. El agregado devuelve un grupo con dos filas y el join devuelve las dos filas esperadas.

La defensa contra identificadores interpolados peligrosos es válida y debe conservarse. El problema es retirar capacidades legítimas sin una vía compatible explícita. `having('total', '>', 1)` sigue funcionando cuando `COUNT(*) AS total` se declara en select, pero obliga a migrar llamadas existentes; tampoco hay un `havingRaw()` nativo. El alias de tabla no tiene una sustitución equivalente mediante la API actual de join. Revisar validación segura de alias y una vía explícita para expresiones propias, con cobertura de compatibilidad. No se afirma que las rutas estándar actuales estén rotas: las consultas nativas examinadas usan identificadores simples.

### C-02 — P2: los secretos de objetos no se ocultan en el contexto de LogHelper

`src/utils/LogHelper.php`, `redact()`, recorre arrays, pero conserva objetos sin inspeccionarlos. Después `log()` los serializa con `json_encode`. Un contexto `['nested' => (object)['token' => 'dato-ficticio']]` conserva el valor del token, mientras el mismo contexto con un array lo oculta. También afecta a propiedades públicas sensibles de otros objetos serializables.

Es una carencia de la protección nueva de claves sensibles, no una prueba de que todos los logs actuales contengan credenciales. La sonda verifica el valor booleano de exposición y solo usa datos ficticios. Antes de presentar esa redacción como recursiva para el contexto completo, normalizar los objetos de manera acotada o rechazarlos/sustituirlos por una representación segura; considerar ciclos y profundidad al hacerlo. No se propone ocultar arbitrariamente todo el mensaje ni cambiar el formato histórico de `write()`.

### C-03 — P3: la nueva configuración de cookies causa la advertencia de pruebas

`SessionRuntime::start()` llama siempre a `applyCookieParams()`. La prueba `VisitorCsrfTest::testAnonymousSessionGetsStableFieldsWithoutLoginAndRotatesOnLogin` establece `session.use_cookies=0`; la llamada nueva a `session_set_cookie_params()` produce el aviso «Session cookies cannot be used when session.use_cookies is disabled».

La repetición con `--display-warnings` identifica exactamente `src/GFrame/Session/SessionRuntime.php:97`. No es correcto dar esa advertencia por anterior o ajena a esta modificación sin contrastarla: la llamada que la dispara no existe en HEAD. Es un problema de compatibilidad de configuración/prueba, no una vulnerabilidad remota. Decidir si el modo sin cookies debe conservarse o si el fixture debe usar el contrato admitido; en cualquier caso, no ocultar la advertencia con `@`.

## Dictamen de los cinco hallazgos iniciales

| Hallazgo | Dictamen |
| --- | --- |
| Identificadores y operadores ORM | Defensa útil ante argumentos inseguros. La explotación remota depende de que la aplicación les pase datos controlados por el visitante. No implica que las consultas estándar enlazadas fueran vulnerables. Corregir C-01 antes de publicar este cambio de contrato. |
| Banderas de cookie | Valores predeterminados más seguros razonables. La primera implementación podía degradar una política PHP existente y confiar en un proxy no declarado; la continuación ya conserva Secure/Strict/ámbito/duración y limita cabeceras a proxies explícitos. La prueba de conservación pasa. Queda C-03. |
| Redirección con backslash | Corrección válida del destino que los navegadores pueden interpretar como externo. La sonda confirma que `/\\evil.test`, `//evil.test` y el equivalente codificado quedan en una ruta interna; CRLF devuelve el fallback. No se confunde una ruta interna llamada evil.test con una redirección a ese dominio. |
| Cabeceras reenviadas | Endurecimiento correcto, condicionado por la configuración real del sitio. La continuación utiliza proxies IP/CIDR explícitos; pasa la prueba que rechaza un host reenviado por un visitante no confiable. No estaba corregido en la primera entrega de Claude. |
| Caducidad CSRF | Mejora opcional, no un fallo demostrado por la mera ausencia de un plazo propio. Un token ligado a la sesión puede ser válido durante esa sesión. El código conserva la decisión aprobada: invitados caducados reciben to_reload cuando se habilita el plazo; usuarios autenticados no tienen temporizador CSRF separado. Las cuatro variantes de la sonda pasan. |

## Límites y observaciones

- `join()` sigue interpolando su quinto argumento, el tipo de join, sin lista blanca. La sonda demuestra que SQL controlado en ese argumento puede cambiar la unión. Esto ya ocurre en HEAD y no se ha demostrado un endpoint que permita controlarlo al visitante; se registra como límite de la defensa, no como una vulnerabilidad nueva introducida por Claude. Mantener ese argumento definido en el código.
- `select()`, `groupBy()`, `orderByExpr()` y `whereRaw()` siguen siendo vías para expresiones de confianza. Conservarlas no es un fallo: su autorización de entrada pertenece a la aplicación.
- La rotación de LogHelper comprueba tamaño y renombra antes del bloqueo de escritura; no demuestra coordinación de rotaciones entre procesos. Se observó el diseño en código, pero no se ejecutó una prueba que acredite pérdida de entradas: no se presenta como un fallo reproducido.
- No se reabren cifrado o multimedia a partir de afirmaciones generales: el informe no aporta una explotación nueva de esos componentes y esta pasada no constituye una auditoría exhaustiva de ellos.

## Evidencia

- Sonda: `php maintenance/auditorias/endurecimiento-20261023/revision-codex-probes.php`. Resultados guardados en `revision-codex-resultados.json`. No se ejecuta la consulta con DROP del ejemplo; solo se compara el SQL compilado.
- Pruebas dirigidas: `php -d session.save_path=C:/xampp/tmp packages/bin/phpunit --filter 'Orm|Csrf|LogHelperTest|SecurityHardeningTest::testCookies|SecurityHardeningTest::testSameOrigin' --no-coverage --do-not-cache-result --log-junit .phpunit.cache/claude-review.xml`: 53 pruebas, 443 aserciones, sin fallos, una advertencia y una omitida.
- Repetición con `--display-warnings`: 53 pruebas, 444 aserciones, sin fallos, la misma advertencia y una omitida. Se registran como ejecuciones distintas; no se suman sus aserciones ni se declara la omisión como aprobada.
- Esta revisión usa PHP 8.3.35 y SQLite en memoria para las regresiones SQL. No afirma equivalencia de todas las consultas en MySQL ni verificación de navegador, SMTP o PHP 8.1.

Resultado: conservar el endurecimiento justificado, corregir C-01/C-02 y resolver C-03 antes de declarar esta entrega lista. Ninguna de estas correcciones se ha aplicado durante esta revisión.
