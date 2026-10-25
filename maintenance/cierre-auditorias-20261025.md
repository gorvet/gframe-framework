# Conciliación del seguimiento histórico — 2026-10-25

## Alcance y corte

Petición: actualizar auditorías y conciliaciones para que las tareas ya realizadas no sigan figurando como pendientes. Base: `e5bbc4e6520d0802e0969ae8adfc12af7d158444`, `main` y etiqueta `v4.4.1`. Solo cambia seguimiento interno; no cambia código, criterios de aceptación, pruebas ni aplicaciones consumidoras. Las mejoras aplazadas conservan su lista y prioridad.

Este registro concilia estados con evidencia posterior. No sustituye ni reescribe los dictámenes de cada snapshot y no constituye una nueva auditoría funcional completa de las dieciséis etapas.

## Etapas y conciliaciones

Las dieciséis conciliaciones están cerradas. Sus listas principales no contienen casillas abiertas y sus auditorías finales tienen veredicto `coincide`.

| Etapa | Evidencia de cierre vigente | Seguimiento corregido |
| --- | --- | --- |
| Guardado y presencia | [Auditoría](features/content-editing/01-save-presence/AUDIT.md), pasada 1 | Sin trabajo activo |
| Historial y restauración | [Auditoría](features/content-revisions/01-history-restore/AUDIT.md), pasada 1; [corrección de publicación](auditorias/alineacion-4.3.0/CORRECCIONES.md) | Índice ya no propone implementar lo entregado |
| Preview privada | [Auditoría](features/content-preview/01-private-preview/AUDIT.md), pasada 1 | Principal `auditada`, índice sin trabajo activo |
| Papelera | [Auditoría](features/content-trash/01-lifecycle/AUDIT.md), pasada 1 | Principal `auditada`, índice sin trabajo activo |
| Cron 01 | [Auditoría](features/cron-configurable/01-ejecucion-coordinada/AUDIT.md), pasada 1 | Conciliación remite al cierre posterior |
| Cron 02 | [Auditoría](features/cron-configurable/02-disparador-configurable/AUDIT.md), pasada 2 | Delta 01 `auditada`; dependencia cerrada |
| Cron 03 | [Auditoría](features/cron-configurable/03-operacion-y-actualizacion/AUDIT.md), pasada 2; [Linux/MySQL](features/cron-configurable/CI-20261019.md) | Delta 01 `auditada`; B-01 resuelto y dependencias cerradas |
| Cron 04 | [Auditoría](features/cron-configurable/04-ejecutor-http/AUDIT.md), pase 4 | Deltas 01–02 `auditada`; dependencias anteriores cerradas |
| CSRF de visitantes | [Auditoría](features/csrf-visitantes/01-formularios-publicos/AUDIT.md), pasada 2 | Delta 01 `auditada`; nota de implementación distinguida del cierre |
| Multimedia 01 | [Auditoría](features/media-library-improvements/01-selector/AUDIT.md), pase 4 | Deltas 01–03 `auditada` |
| Multimedia 02 | [Auditoría](features/media-library-improvements/02-management/AUDIT.md), pasada 1 | Sin trabajo activo |
| Multimedia 03 | [Auditoría](features/media-library-improvements/03-writing/AUDIT.md), pasada 2 | Delta 01 `auditada` |
| Multimedia 04 | [Auditoría](features/media-library-improvements/04-editor-scroll/AUDIT.md), pasada 1 | Sin trabajo activo |
| Multimedia 05 | [Auditoría](features/media-library-improvements/05-writing-tools/AUDIT.md), pasada 1 | Sin trabajo activo |
| Multimedia 06 | [Auditoría](features/media-library-improvements/06-writing-protection/AUDIT.md), pasada 1 | Dependencia 05 cerrada; excepción histórica sin efecto actual |
| Multimedia 07 | [Auditoría](features/media-library-improvements/07-server-autosave/AUDIT.md), pasada 1 | Publicación posterior reflejada en índice y conciliación |

Los nueve deltas se marcan `auditada` porque la última pasada de su etapa los incluye expresamente contra el principal. No se convierten las comprobaciones de implementación de cada delta en auditorías nuevas.

## Auditorías antiguas conciliadas

| Registro | Resultado comprobado y cierre posterior | Estado actual |
| --- | --- | --- |
| [Extracción de módulos](auditoria-modulos.md) | Recorridos de selector/campo: auditorías de Multimedia 01–03; PHP 8.4: CI de PR #7. Publicación de assets: `ModuleCatalogTest` | Casillas demostradas marcadas; las integraciones más amplias permanecen delimitadas abajo |
| [Reconstrucción documental](auditoria-reconstruccion-final.md), [comparación](auditoria-main-unificada.md) y [unificación](unificacion-ramas.md) | La [reconstrucción](../docs/reconstruccion-documentacion.md) registra DOC-001–027 cubiertos/corregidos; `main` ya integra las ramas y las guías | Cerrado el trabajo de aquellas ramas; no queda una fusión global pendiente |
| [Integración local](integracion-local-main.md) y [ajustes posteriores](ajustes-documentacion-20261006.md) | PHP de XAMPP CLI 8.3.35; la integración y versiones están en `main` y publicadas | Requisito local mínimo superado; no se certifica otro servidor PHP por inferencia |
| [Revisión de octubre 6](revision-auditoria-20261006.md) | C01–C08/C12 corregidos; los contratos pospuestos de cola/ORM/cron tienen resolución posterior en el lote 32 del [inventario](inventario-skills-contratos.md) | No reaplicar el patch respaldado ni tratar sus propuestas como cambios pendientes de aquella entrega |
| [Plantillas](auditorias/plantillas-correo/AUDIT.md) | Pasadas 2–4 cierran homogeneidad, saludo, año y publicación mediante updater. Aviso administrativo implementado en `RegistrationAdminNotifier` y registrado en [mejoras](mejoras-pendientes.md) | Cerradas esas correcciones; entrega SMTP y créditos personalizados de correo siguen separados |
| [Alineación 4.3.0](auditorias/alineacion-4.3.0/AUDIT.md) | H-01/H-02 corregidos y publicados en [4.3.1](release-4.3.1-verification.md); regresión de restauración incluida en E-02 | Hallazgos cerrados; las omisiones históricas no se convierten en aprobaciones universales |
| [Seguridad inicial](auditorias/alineacion-4.3.0/SEGURIDAD-INICIAL.md) y [continuación](seguridad-auditoria-20261023.md) | Autenticación, cookies/proxies, límites, protocolos, cabeceras y canales corregidos; [PR #2](https://github.com/gorvet/gframe-framework/pull/2) integrada. La clave de despliegue fue rechazada por el propietario | Cerrado el alcance autorizado del framework; no hay que reintroducir la clave ni cambiar mensajes de cuenta |
| [Revisión de Claude](auditorias/endurecimiento-20261023/REVISION-CLAUDE-CODEX.md) | C-01 conserva expresiones/alias en posiciones propias del código; C-02 redacta objetos; C-03 respeta sesiones sin cookies. Corrección y regresiones en PR #2 | C-01–C-03 cerrados; se conservan los límites sobre SQL definido por código y rotación concurrente de logs |
| [Correcciones de PR #2](auditorias/endurecimiento-20261023/PR2-CORRECCIONES.md) | Reconexión/transacciones, carrera SQLite y captura de logs corregidas; [PR #7](https://github.com/gorvet/gframe-framework/pull/7) retira el baseline PHPStan | La suite fallida y PHPStan informativo son snapshots superados, no bloqueos actuales |
| [CI y lint](cobertura-checks-20261006.md) | JS, YAML y lint ejecutados en GitHub; jobs MySQL y HTTPS/FPM activos. PHPStan obligatorio sin baseline | Cerradas las subidas y verificaciones remotas antiguamente pendientes; no incluye todas las herramientas PowerShell ni SMTP/Redis |
| [Cola de campañas](cola-correcciones-campanas.md) | Diez peticiones marcadas; integración por módulos y pruebas posteriores | Sin tareas activas de esa cola; no reabre mejoras futuras |
| [BridgeFrame](bridgeframe-pending.md) | El usuario confirmó la adaptación v2 el 6 de octubre | Retirado del backlog de GFrame; no acredita integración externa en vivo |
| [Skills y contratos](inventario-skills-contratos.md), [distribución](distribucion-skills.md) | Lotes 31–33, instalación/carga en Codex y resolución K-01–K-04/M-01 documentadas | No quedan esos cambios pospuestos de código; Claude fue excluido expresamente de esa validación |

## Verificaciones que no se marcan como realizadas

Estos puntos son límites concretos de evidencia, no nuevas funcionalidades ni razones para reabrir una etapa cuyo alcance aprobado ya se auditó:

- **V-01 — cobertura visual restante:** prueba visual de cada biblioteca opcional y de todas las vistas/recursos de todos los módulos en una instalación limpia. Selector/editor y recorridos concretos sí tienen auditorías y pruebas. No equivalen a esa cobertura universal. Fuente: [auditoría de módulos](auditoria-modulos.md).
- **V-02 — infraestructura restante:** routing general con Nginx/PHP-CGI, integración MySQL/HTTP específicamente en PHP 8.4 y sesiones Redis con servicio real. Nginx/PHP-FPM para cron y la matriz PHP sin esas integraciones sí están acreditados. Fuente: [preparación histórica](preparacion-0.9.0.md) y [CI/lint](cobertura-checks-20261006.md).
- **V-03 — correo externo:** diagnóstico de recepción del registro inicial y compatibilidad en clientes remotos. Pruebas de render, cola y transporte falso no prueban aceptación SMTP ni recepción. No se envió correo ni se accedió a proveedores en este cierre. Fuente: [plantillas](auditorias/plantillas-correo/AUDIT.md).
- **V-04 — personalización del pie del correo:** el año y la homogeneidad están implementados; el contenido personalizado de créditos del pie web no se reutiliza automáticamente en las plantillas de correo. Se conserva como decisión/integración pendiente; no se cambia el motor por marcar una lista. Fuente: [plantillas](auditorias/plantillas-correo/AUDIT.md).
- **V-05 — hosting y consumidores:** cabeceras y bloqueos efectivos del servidor de producción, despliegue de cada aplicación y pruebas con proveedores reales. Las comprobaciones de fixtures no certifican esos entornos. Fuente: [seguridad inicial](auditorias/alineacion-4.3.0/SEGURIDAD-INICIAL.md).

Las ampliaciones de documentación/capturas permanecen en [su plan](help/PLAN.md) y [el inventario visual](../docs/assets/INVENTARIO.md). Las funcionalidades aplazadas permanecen en [mejoras pendientes](mejoras-pendientes.md); no se implementan ni se dan por canceladas aquí.

## Evidencia de esta conciliación

| ID | Procedimiento y oráculo | Resultado / límite |
| --- | --- | --- |
| E-01 | Ejecutar `validate_stage.py` sobre cada carpeta con CONCILIACION.md y revisar principales, deltas y última pasada de AUDIT.md | 16/16 salen con código 0. Avisos de formato legado de tipos de CA; no se alteran criterios para ocultarlos. JSON completo local en `~/.gframe-agents/cierre-auditorias-stage-validation.json` |
| E-02 | `php packages/phpunit/phpunit/phpunit --filter 'SecurityHardening\|AuditRegression\|JsonLd\|ContentPreview\|ContentTrash\|ContentRevisions\|ScheduledContent\|ModuleCatalog\|AuthVerification\|VisitorCsrf\|LogHelper\|DatabasePreflight' --no-progress` | PHP 8.3.35, base e5bbc4e: 81 pruebas, 694 aserciones, sin fallos; una omisión MySQL preflight. XML local `~/.gframe-agents/cierre-auditorias-php.xml`. No se suma a otras selecciones solapadas |
| E-03 | Consultar logs de CI de PR #7 y comprobar configuración real del workflow | [Run 40168481806](https://github.com/gorvet/gframe-framework/actions/runs/40168481806), diez jobs aprobados. PHP 8.4.27: 581 pruebas/5737 aserciones, 14 omisiones, sin deprecaciones PHP notificadas. Node emite sus propios avisos; no se confunden con PHP. PHPStan y rechazo de `return.type` aprobados |
| E-04 | Contrastar cada casilla modificada con archivos y resultados posteriores citados; comprobar diff, enlaces locales y validador de etapas después de editar | 45 Markdown y 203 enlaces relativos comprobados, sin destinos ausentes; ningún CA modificado y cuerpos originales de AUDIT.md conservados. Dieciséis principales y nueve deltas en estado auditada, sin casillas abiertas. JSON local `~/.gframe-agents/cierre-auditorias-documental.json`. No mide cobertura de líneas ni sustituye las pruebas de entorno de V-01–V-05 |
| E-05 | `GFRAME_TEST_MYSQL=1`, `GFRAME_TEST_APACHE=C:/xampp/apache/bin/httpd.exe`, `GFRAME_TEST_APACHE_PHP_MODULE=C:/xampp/php/php8apache2_4.dll`; PHPUnit con filtro `ServerRoutingTest::testRealApache\|DatabasePreflightTest::testMysql\|RegistrationAdminNotice\|MailTemplates\|NotificationEmail\|ProjectUpdateService` | 24 pruebas, 845 aserciones, sin omisiones ni fallos, PHP 8.3.35. Apache aislado y base MySQL temporal; no se modifica un sitio instalado ni se envía correo. XML local `~/.gframe-agents/cierre-auditorias-integraciones.xml`. El filtro no implica que exista una prueba para cada expresión; cubre las clases/casos descubiertos por PHPUnit |
| E-06 | `php packages/phpunit/phpunit/phpunit --filter RegistrationAdminNotifier --no-progress` | 1 prueba, 15 aserciones, sin omisiones ni fallos. Comprueba el aviso administrativo con transportes de prueba; no acredita entrega SMTP |

Los datos de ejecución y artefactos temporales quedan fuera del paquete. No se modifica el changelog público ni se prepara una versión por esta actualización de seguimiento.
