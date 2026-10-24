# Correcciones de seguridad de la auditoría

Fecha: 2026-10-23.

Primera intervención autorizada después de la revisión independiente: H-01, H-09 y cierre del preflight del instalador cuando ya existe configuración. Se conservan los cambios locales anteriores y no se aplican las series externas en bloque.

Convenciones seleccionadas: `skills/gframe-orchestrator`, `gframe-framework-maintenance`, `gframe-core-architecture`, `gframe-auth-access`, `gframe-backend` y `gframe-frontend-public`. Correo conserva Async y su contrato de lanzamiento; no se cambia la infraestructura de entrega.

Verificación prevista: escape y recuperación de los valores JSON; enlaces de correo capturados sin SMTP; rechazo de URL de seguridad no válida; recuperación genérica si no se puede lanzar correo; GET y POST del instalador con configuración y sin lock; recorrido HTTP de los cuatro perfiles; pruebas de autenticación y documentación afectadas.

El control de acceso a instalaciones nuevas y el límite integral de tiempo de MySQL necesitan verificación específica. No se considera resuelto ese riesgo con una sonda que solo lee un byte. Cookies, limitador, colas y decisiones funcionales conservan su lugar en el trabajo pendiente.

## Resultado de la primera intervención

- H-01: corregido en la serialización. La prueba usa también el bloque real de Meta y su análisis HTML con DOM; conserva el texto original al decodificar JSON.
- H-09: los enlaces de AuthController usan una URL configurada HTTP/HTTPS, sin credenciales, query ni fragmento. No se utiliza site_url ni el Host como respaldo. Configurar APP_URL es requisito de actualización para las aplicaciones que dependían de la URL automática.
- Recuperación: una configuración ausente o un fallo de lanzamiento no cambia la respuesta pública entre correos existentes e inexistentes. Se elimina también el contenedor data vacío que diferenciaba ambos casos. Los errores siguen registrados en el servidor; no se promete entrega.
- H-04/H-11/H-18: cerrado el acceso al preflight cuando existe configuración pero falta el lock. Probado para app.php, .env y dos variantes anteriores, tanto GET como POST, antes de publicar activos. Se conservan instalación nueva y redirección de aplicaciones instaladas.

No se da por resuelto el acceso no autorizado a un asistente nuevo expuesto públicamente ni el presupuesto completo de tiempo de MySQL. La guía explica la necesidad de restringir el acceso durante un despliegue nuevo. Estas cuestiones permanecen prioritarias antes de pasar a debilidades.

## Evidencia

- Autenticación, JSON-LD y documentación afectada: 44 pruebas, 273 aserciones, sin fallos.
- Instalador, sus cuatro perfiles HTTP y preflight: 26 pruebas, 717 aserciones, sin fallos, dos omitidas por requisitos del entorno. Incluye el nuevo rechazo de configuración sin lock.
- Pruebas finales de seguridad, cuenta, administración y permisos: 45 pruebas, 262 aserciones, sin fallos. Se solapan con la primera selección y no se suman como casos únicos.
- Lint del repositorio: 499 archivos PHP válidos. Skills: 14 válidas y 80 enlaces locales comprobados. git diff --check sin errores.
- Configuración, enlaces de documentación y render: ocho pruebas, 145 aserciones, sin fallos.
- Sin MySQL real, navegador, staging ni PHP 8.1 en esta intervención. La suite completa anterior pertenece a la revisión previa y no se presenta como verificación de este cambio.

No se hicieron commits, publicaciones ni cambios en aplicaciones consumidoras.

## Continuación autorizada

Se conservan los mensajes de registro, login y reenvío por decisión del propietario. Se corrigen límites concurrentes de autenticación, hash de relleno, cookies/proxies, plazo integral del preflight, protocolos HTTP, cabeceras configurables y coherencia de rutas. El correo de acceso utilizará la cola existente cuando esté instalada, con alternativa síncrona acotada para instalaciones sin ese módulo; no se crean dependencias obligatorias ni procesos por correo.

El propietario rechazó la clave de despliegue propuesta durante la implementación: se retiraron su código, comando, formulario y requisito documental. El instalador conserva el flujo visual al copiar o desplegar desde GitHub. El asistente nuevo público es una decisión de despliegue, no un fallo pendiente que justifique reintroducir esa clave.

Baseline: árbol local con cambios previos conservados. Skills canónicas seleccionadas: orchestrator, framework-maintenance, core-architecture, backend, auth-access, integrations, notifications-mail y background-jobs. Verificación: pruebas de concurrencia y almacenamiento, proxies/cookies, protocolos/redirecciones, canales, acceso al instalador, servidor MySQL silencioso y cola con transporte falso; después suite PHP, lint y skills. SMTP, staging y versiones de PHP no disponibles se registrarán separadamente.

### Cambios y comprobaciones de la continuación

- `AuthRateLimiter`: reservas de IP e identidad bajo un bloqueo común antes del trabajo, contadores con vencimiento, almacenamiento acotado y rechazo ante corrupción/fallo. Ocho procesos concurrentes con cupo uno admiten exactamente una solicitud. El controlador rechaza el segundo intento antes de llamar al modelo y conserva el mensaje de credenciales incorrectas.
- `AuthModel`: hash de relleno de coste 12 para correo ausente, sin modificar los estados ni los mensajes de login.
- `RequestSecurity` y cookies: solo proxies IP/CIDR configurados aportan cabeceras reenviadas; la IP se resuelve desde el extremo confiable de la cadena. La política automática conserva Secure/Strict/path/domain/lifetime existentes. SameSite=None requiere Secure.
- `SecurityHeaders`: nosniff y política de referencia conservadora; CSP, restricciones de marcos y HSTS se configuran por proyecto. No se sustituyen cabeceras ya emitidas por la aplicación.
- Router: incoherencias de canal rechazadas antes de sesión/middleware/acción; system conserva web y AJAX (heartbeat), pero no API/webhook/SSE.
- HttpClient: protocolos HTTP/HTTPS tanto en URL inicial como redirecciones. Una redirección HTTP local funciona; una hacia file no lee el archivo.
- `AccessMailService`: usa la cola nativa instalada, sin proceso por correo; si falta el módulo, envío SMTP síncrono con timeout por operación. Los tokens vencidos/sustituidos se marcan fallidos sin reintentos. Una cola no disponible no provoca un envío alternativo.
- Preflight MySQL: proceso CLI con credenciales por stdin y plazo integral de cinco segundos, terminación ante servidor silencioso; no se utiliza la sonda de un byte del parche externo. Requiere proc_open y PHP CLI con PDO MySQL. La instalación visual conserva su flujo y sus cuatro perfiles.

Pruebas ejecutadas con PHP 8.3.35:

- Selección de seguridad/autenticación/correo: 25 pruebas, 238 aserciones, sin fallos.
- MySQL real (`GFRAME_TEST_MYSQL=1`), instalador y selección de seguridad/cola: 17 pruebas, 302 aserciones, sin fallos ni omisiones. Incluye base inexistente, vacía, ocupada, credenciales inválidas, servidor inaccesible y los cuatro perfiles HTTP del asistente.
- Contratos documentales, cola y canales web/AJAX/system: nueve pruebas, 85 aserciones, sin fallos.
- Lint: 510 archivos PHP válidos. Skills: 14 válidas, 80 enlaces locales. `git diff --check` sin errores de contenido.

Estas selecciones se solapan y no se suman como pruebas únicas. Sin SMTP externo, navegador interactivo, staging ni PHP 8.1. No se publican cambios ni se actualizan aplicaciones consumidoras.

Suite completa: `php -d session.save_path=C:/xampp/tmp packages/bin/phpunit --no-coverage --do-not-cache-result --log-junit .phpunit.cache/security-continuation-final.xml`: 570 pruebas, 5704 aserciones, un fallo, una advertencia y 14 omisiones. No se presenta como suite verde. El único fallo fue `ContentRevisionsTest::testConcurrentHistoryWritersHaveOneWinner`: uno de sus procesos terminó con código 255 en vez de 0. Su repetición aislada pasó (una prueba, seis aserciones). La revisión previa de DeepSeek/Codex ya registra ese mismo caso como intermitente; esta intervención no modifica el modelo ni la prueba de revisiones. No se atribuye una causa nueva sin evidencia.

Después de la recopilación de esa suite se añadieron dos comprobaciones puntuales: el controlador se detiene antes de llamar al modelo al agotar cupo (incluida en la selección posterior de 25 pruebas) y el guarda de origen ignora un host reenviado por un visitante no confiable (una prueba, tres aserciones, correcta). La comprobación de system también se amplió para conservar AJAX y rechazar API. El lint y la validación final de skills permanecen correctos.
