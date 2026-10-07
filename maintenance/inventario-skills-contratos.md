# Inventario de mejoras de skills, nomenclatura y contratos

Fecha de revisión: 6 de octubre de 2026.

## Objetivo y alcance actual

Mejorar primero las instrucciones con las que un agente trabaja en GFrame, comprobar su cobertura y acordar nombres coherentes por contexto. Después abordar los cambios de contratos pendientes, uno por uno.

Este documento registra el inventario y los lotes de trabajo. El usuario autorizó avanzar el 6 de octubre de 2026: nomenclatura y respuestas/excepciones, instrucciones de tenant/sesiones y límites del mantenimiento. Las demás etapas siguen como propuestas; estos lotes no renombran código existente ni cambian contratos runtime. En este chat, el usuario aclaró que «ok» indica continuar con el siguiente lote del inventario; no se interpreta como autorización para acciones ajenas a ese lote.

Se conservan los cambios de código ya verificados y las tareas pospuestas. Véase [la revisión de la auditoría](revision-auditoria-20261006.md). El informe externo es una fuente de candidatos, no instrucciones que deban ejecutarse automáticamente.

## Punto de partida comprobado

- Hay nueve skills canónicas en `skills/gframe-*` y 37 módulos en el catálogo de `php bin/modules.php list`.
- El instalador `bin/install-skills.ps1` copia skills globales para Codex y Claude Code; no selecciona instrucciones por la versión de cada proyecto.
- `bin/validate-skills.php` comprueba estructura, archivos y algunas referencias; no ejecuta recetas ni demuestra la exactitud de cada contrato.
- Los cambios amplios anteriores de skills y los seis especialistas propuestos se retiraron del cambio activo. Sus borradores están respaldados; no se consideran implementados ni aprobados. El primer lote actual sustituye únicamente las contradicciones y convenciones detalladas en su registro, sin reaplicar aquel patch.
- La revisión de código anterior terminó con 420 pruebas, 4193 aserciones y seis omisiones. Las tres pruebas de integración SQL se ejecutaron además en MariaDB 10.4.24. No se comprobó MySQL 8.

## Orden propuesto y control del trabajo

| Etapa | Resultado que se busca | Dependencia | Estado |
| --- | --- | --- | --- |
| 01. Convenciones y nueve skills existentes | Reglas compatibles con código y documentación; nomenclatura acordada por contexto. | Inventario actual. | Correcciones y recetas en lotes 01–11 y 26; cierre semántico y nomenclatura restantes pendientes. |
| 02. Cobertura y recetas faltantes | Cada capacidad tiene un responsable y ejemplos comprobables, sin una skill por biblioteca. | Reglas compartidas de 01. | En curso; cuatro especialistas creados en lotes 12–15, comprobaciones restantes abiertas. |
| 03. Orquestador | Selección de especialistas según proyecto, versión y tarea, sin duplicar sus instrucciones. | Cobertura mínima de 01 y 02. | Implementado en el lote 16; evaluación de selección y límites registrada abajo. |
| 04. Descubrimiento, instalación y complemento | Instrucciones asociadas a la versión del proyecto y distribución mantenible. | Skills y selección estables. | Instalación por proyecto en lote 18; generador/artefacto de prueba en lote 19; carga real pendiente. |
| 05. Verificadores y CI | Comprobaciones de ejemplos, enlaces instalados, nomenclatura y entornos reales. | Cada lote define qué debe verificarse; algunos controles se incorporan antes de esta consolidación. | Controles implementados en lotes 21–25; ampliaciones pospuestas para mantener el foco. |
| 06. Contratos de cola, ORM y cron | Mejoras con compatibilidad, migración y evidencia propia. | Conciliar cada contrato; no depende de terminar todo el paquete de skills. | Pospuesto, conservado. |
| 07. Diagnóstico CLI y posible MCP | Reducir trabajo repetido con inspección estructurada. | Demostrar una necesidad que los comandos y skills no cubran. | Evaluación futura. |

Trabajar un solo lote a la vez. Antes de editar, concretar archivos, resultado, contratos afectados y verificación. Al terminar, actualizar su estado con evidencia y enumerar pendientes. Una corrección no justifica renombrar módulos o cambiar otros subsistemas. Si surge un hallazgo nuevo, se registra antes de ampliar el lote.

## Nomenclatura: distinguir contextos

No se puede decidir entre `variableID` y `variable-id` sin saber dónde aparece el nombre. Una variable o parámetro de función PHP/JS no admite el guion como parte del identificador; un nombre de atributo HTML sí puede usarlo.

| Contexto | Evidencia actual | Convención para código nuevo | Tratamiento del código existente |
| --- | --- | --- | --- |
| Variables y parámetros de funciones PHP | `$userID`, `$tenantID`, `$roleID`; también `$userId` en middleware y `$mediaId` en SEO. | camelCase con `ID` como sufijo de identificador: `$userID`, `$tenantID`, `$userIDs`. | Inventariar mezclas; renombrar locales solo dentro de un lote aprobado. |
| Variables y parámetros de funciones JS | Código propio de componentes y módulos, junto con APIs externas. | camelCase; preferir `userID` en nuevos identificadores de negocio propios de GFrame. | Revisar primero los componentes propios; conservar APIs externas y bibliotecas. |
| Clases PHP y JS | `NotificationController`, `SessionManager`, `GFSelect`. | PascalCase y nombres que describan la responsabilidad. | Conservar nombres públicos; no añadir `Controller`, `Service` o `Model` donde no corresponda. |
| Métodos y funciones | `markRead`, `resetPassword`; auth conserva los nombres públicos `registerAcount` y `recoveryAcount`. | camelCase y verbo que exprese la acción. | Un nombre público, aunque tenga una errata histórica, requiere alias o transición; no corregirlo por estética. |
| Tablas y columnas SQL | `users.user_id`, `tenant_memberships.tenant_id`. | snake_case: `user_id`, `tenant_id`. | Son contratos de persistencia; cualquier cambio exige migración y revisión de consumidores. |
| Claves de datos JSON, formularios y query | `user_id`, `notification_id`, `user_ids`; también `csrfToken` y `csrfTimestamp`. | snake_case para nuevos campos de negocio; respetar las claves técnicas existentes. | Trazar productor y consumidor. No cambiar CSRF, claves especiales o campos publicados automáticamente. |
| Parámetros de ruta | Placeholders que Router entrega como claves de `params`. | Nombres explícitos y coherentes con el contrato del endpoint; `id` puede seguir siendo suficiente. | No confundir el segmento URL con la clave del placeholder; preservar inferencia y rutas publicadas. |
| Segmentos URL, módulos y carpetas de skills | `user-admin`, `notification-campaigns`, `gframe-auth-access`. | kebab-case para nuevos nombres: `user-admin`. | Mantener alias existentes, como `gfselect` → `gf-select`; no duplicar módulos. |
| IDs y clases CSS propios | `userModal` y `userListMount` en usuarios; `campaign-users` en campañas; `all_items` en recetas admin. | Decidir un estilo para componentes nuevos; candidato: kebab-case con prefijo del componente. | Preservar selectores y nombres especiales. No aplicar una conversión masiva ni tocar clases Bootstrap. |
| Atributos `data-*` y variables CSS | `data-user-id`, `data-role-id`, `data-ml-mount`, `--bs-primary`. | kebab-case: `data-user-id`, `--gf-component-color`. | Mantener el contrato del componente y el acceso desde JS. |
| Constantes PHP y variables de entorno | `TENANT`, `TENANT_TABLE`, `MAIL_RATE_LIMIT_ENABLED`. | UPPER_SNAKE_CASE para nuevas constantes y variables de entorno. | Preservar las excepciones públicas y el bridge de compatibilidad. |
| Códigos de respuesta y permisos | `password_reset`, `invalid_token`; permisos como `users.view`. | snake_case para códigos; conservar `área.acción` para permisos. | No normalizar códigos en Router ni en JS ni convertir permisos a guiones. |

Ejemplo de un mismo dato a través de distintas capas: `data-user-id="7"` en HTML → `user_id` en el envío → `$userID` en PHP → columna `user_id`. Son identificadores de contextos diferentes que deben mapearse explícitamente. Ese recorrido existe en el módulo user-admin.

### Pendientes de nomenclatura

| ID | Trabajo | Evidencia y salida esperada |
| --- | --- | --- |
| N-01 | Terminado para código nuevo: sufijo `ID` en PHP y preferido en JS propio. | Referencia canónica añadida con la autorización del primer lote. No se han renombrado símbolos existentes. |
| N-02 | Variables y declaraciones públicas catalogadas en lotes 20 y 30. | [Inventario con evidencia](nomenclatura-20261006.md): firmas, parámetros y padres/traits declarados; consumidores externos y herencia efectiva se comprueban antes de cualquier renombrado futuro. Ningún renombrado aplicado. |
| N-03 | Recorrido ampliado a los ocho módulos con controladores MVC propios. | Mapeos de transporte/API/persistencia documentados en la revisión posterior de nomenclatura. Sin renombrados masivos ni certificación de consumidores externos. |
| N-04 | DOM de nueve composiciones nativas y dos listados multimedia comprobado. | Colisión real biblioteca/picker corregida, con prueba PHP y Edge real. Se conservan IDs históricos de la biblioteca; overrides y otros estados de aplicaciones requieren su propia comprobación. |
| N-05 | Referencia común enlazada desde las catorce skills en lote 30. | [Fuente canónica](../skills/gframe-core-architecture/references/naming-conventions.md); frontend-admin conserva las reglas de IDs/clases nuevos sin duplicarlas en core. |
| N-06 | Adopción delimitada en lote 30. | Aplicar las reglas a código nuevo y conservar APIs/claves/selectores actuales. Solo un cambio futuro de nombre justifica comprobar y migrar todos sus consumidores; no se impone limpieza estética automática. |

## Inventario de las nueve skills existentes

La tabla conserva los objetivos originales. El estado local consolidado se registra debajo de la lista activa, con evidencia y límites distintos para instrucciones, runtime e integración. Revisar los recorridos registrados no certifica cada API ni convierte cada recomendación del auditor en una modificación obligatoria.

| ID | Skill | Trabajo concreto | Comprobación antes de cerrar |
| --- | --- | --- | --- |
| S-01 | `gframe-core-architecture` | Precisar framework frente a aplicación, versión efectiva, prefijo URL frente a tipo declarado, configuración y tenant coherente con la corrección actual. | Contrastar Bootstrap, Router, RouteBuilder y TenantContextResolver; escenarios global/tenant y rutas declaradas. |
| S-02 | `gframe-backend` | Corregir `project_id` como supuesto tenant universal; conciliar errores esperados, `Exception`/`Throwable` y claves mínimas de respuesta; revisar nomenclatura de campos. | Receta ruta → controlador → servicio/modelo → respuesta; ejemplo con y sin `message`, sin inventar claves obligatorias. |
| S-03 | `gframe-orm-models` | Conciliar el ejemplo `catch (Throwable)` con backend; documentar el `whereIn([])` vigente, ordenación permitida, reset, conexiones y límites transaccionales. | Ejemplos reales MySQL/SQLite; separar comportamiento vigente de la propuesta C11. |
| S-04 | `gframe-auth-access` | Reflejar reset y revocación gestionada, garantías por driver, versiones de permisos, tenant y claves de identidad. Resolver referencias fuera de la skill instalada. | Login/reset/sesión y tenants; comprobar enlaces en una instalación temporal, sin modificar skills globales. |
| S-05 | `gframe-frontend-admin` | Revisar nombres de campos/IDs/selectores, runtime frente a archivos publicados y una receta ejecutable de formulario/listado. Mantener aquí las convenciones de vistas. | Flujo formulario → AJAX → backend → parcial; estados vacíos, paginación, validación, foco y dependencias reales. |
| S-06 | `gframe-frontend-public` | Aclarar metas/SEO, escaping por contexto, HTML confiable frente a saneado, rutas de assets y uso de componentes. | Página pública real con metas y recursos; conservar los contratos de SeoPolicy y Render. |
| S-07 | `gframe-media-module` | Separar instrucciones operativas de historia de extracción; contrastar scope/autorización, campos simples/múltiples, rutas y privacidad de descargas. | Biblioteca y picker independientes; comprobar carga, selección y ámbito. No deducir privacidad de una carpeta. |
| S-08 | `gframe-ui-design-clean` | Delimitar criterios visuales frente a requisitos técnicos y evitar repetir admin/public o todo GORVET UI/UX. | Tareas de UI representativas; conservar las decisiones explícitas del proyecto y referencias aprobadas. |
| S-09 | `gframe-framework-maintenance` | Separar mantenimiento de creación/actualización de proyectos, comprobaciones por motor y acciones autorizadas. | El workflow no debe ordenar commit, actualización de aplicaciones o publicación fuera de la petición. |

### Discrepancias comprobadas y candidatos

- **Corregido en el lote 01:** backend y ORM distinguen fallos de negocio, manejo de `Exception`, limpieza transaccional con `Throwable` y límite externo de errores. No se cambiaron catches del runtime.
- **Corregido en el lote 02:** backend y core condicionan `project_id` a la clave configurada y explican el rechazo de IDs contradictorios, además de distinguir autorización y scope del módulo.
- **Corregido en el lote 01:** backend y ORM distinguen sobres de operación de retornos ORM; `message` y las demás claves se incluyen según el consumidor, sin exigirlas universalmente.
- **Corregido en el lote 02 para auth y core:** los enlaces esenciales de sesiones/tenant son referencias incluidas en las skills copiadas. Las guías completas se localizan desde la versión del paquete usada por el proyecto. El descubrimiento automático por versión sigue pendiente en D-02.
- **Corregido en el lote 03:** mantenimiento separa cambios del framework, integración en una aplicación y distribución de skills. Commit, actualización, publicación y sincronización global se condicionan al alcance ya autorizado, sin pedir nuevamente el mismo permiso.
- **Candidato, no contradicción automática:** las skills frontend prohíben modificar el framework para necesidades particulares de una aplicación. Esa regla es correcta en ese modo; revisar únicamente si falta distinguir el mantenimiento del framework.
- **Candidato:** contenido repetido, instrucciones visuales amplias e historia de extracción. Evaluar utilidad y conservar reglas necesarias; no reescribir nueve skills por estética.

Después de acordar la nomenclatura, el primer lote de edición recomendado se limita a S-02 y S-03: coherencia de `Exception`/`Throwable` y claves mínimas de respuesta, sin cambiar runtime. Después, un lote de tenant y sesiones en S-01/S-02/S-04, otro de mantenimiento S-09 y finalmente las recetas de S-05–S-08. El alcance exacto de cada lote se concreta antes de editar.

## Cobertura por capacidades y candidatos a especialistas

El catálogo contiene 37 módulos. Las capacidades de `src/` también necesitan cobertura, aunque no sean módulos instalables. Los nombres nuevos son propuestas; ninguna cantidad de skills es un objetivo por sí misma.

| ID | Área o módulos | Responsable actual / candidato | Qué falta inventariar o comprobar |
| --- | --- | --- | --- |
| COV-01 | Auth, auth-ui, self-account, user-admin, password-utils | Auth, backend y ORM existentes. | Sesiones, membresías, extensión por herencia y campos HTTP completos. |
| COV-02 | media-library | Media y frontend-admin existentes. | Campo/picker, variantes, configuración, scopes y descargas privadas. |
| COV-03 | Mail, notifications, notifications-email | `gframe-notifications-mail`, creada en el lote 12. | Recetas y contratos comprobados localmente; pendientes SMTP real, interacción inbox en navegador y escenarios de fallo adicionales. |
| COV-04 | notification-campaigns | `gframe-campaigns`, creada en el lote 14. | Recetas locales comprobadas; pendientes recorridos HTTP/navegador y transporte real. |
| COV-05 | cron-runner, Async, workers, heartbeat | `gframe-background-jobs`, creada en el lote 13; auth/admin para heartbeat de sesión. | Contratos y recetas comprobados; pendientes scheduler del despliegue, coordinación de pestañas y fallos de proceso reales. |
| COV-06 | API, webhook, SSE, HttpClient, wordpress-headless | `gframe-integrations`, creada en el lote 15. | Contratos y recetas comprobados; pendientes proveedor remoto, TLS real, buffering/reconexión y aislamiento de negocio completo. |
| COV-07 | Creación, instalación, configuración, módulos y actualización | Maintenance/core existentes; `gframe-project-lifecycle` queda condicionado a una necesidad independiente demostrada. | Completar recetas de proyecto sin duplicar mantenimiento: dry-run, archivos administrados, personalizaciones, migraciones y versión resuelta. |
| COV-08 | admin-panel, error-pages, frontend-core, alerts, gf-select, gf-table, gframe-icons | Frontend-admin/public/core; referencia de componentes en lote 30. | Instrucciones de fuentes, assets, readiness y APIs cubiertas; QA de la pantalla real cuando se integren. |
| COV-09 | markdown, rich-text-editor, lexical-search y HtmlSanitizer | Receta compartida en lote 29 y paginación posterior. | Snippets y contratos locales comprobados; persistencia/autorización y editor reales dependen de la aplicación. |
| COV-10 | SEO, JsonLD, sitemap, robots, llms y multilenguaje | Frontend-public y core existentes. | Instrucciones acordes a SeoPolicy, URLs/metas y límites del idioma. |
| COV-11 | 16 módulos external-ui | Frontend-admin/public; carga selectiva y guía efectiva en lote 30. | Solo inicializar la biblioteca afectada según manifiesto/guía/versiones distribuidas; no se certifican todas las integraciones visuales por inspección. |
| COV-12 | Selección de contexto y especialistas | `gframe-orchestrator`, creado en el lote 16. | Matriz y descubrimiento local comprobados; distribución automática por proyecto/versionado sigue en D-02. |

Cada candidato se evalúa con una tarea real antes de crear su carpeta. Empezar por instrucciones ausentes que se repiten; una referencia dentro de una skill existente puede ser suficiente.

### Contraste de cobertura del lote 11

`ModuleCatalog::all()` devuelve 37 módulos, de los cuales 16 declaran `external-ui` y 21 son propios. El conteo utiliza manifiestos, no carpetas: `resources/modules/wordpress-styles/` contiene recursos sin `module.php` y no es un módulo adicional registrado. Las capacidades del núcleo, como Mail, Async y HttpClient, se revisan además de ese conteo.

La documentación pública existe para las áreas siguientes. La carencia comprobada es de instrucciones/recetas operativas en las skills actuales; mencionar una clase, una ruta de carpetas o una pantalla como referencia visual no cubre su flujo de negocio.

| Módulos registrados, sin repetir | Cantidad | Cobertura que se conserva | Pendiente concreto |
| --- | --- | --- | --- |
| auth-ui, self-account, user-admin, password-utils | 4 | Auth/backend/ORM; recetas de usuarios y Mi cuenta. | Completar registro/login/reset e integración de política de contraseñas, sin crear otra skill por módulo. |
| media-library | 1 | Media y referencias frontend. | Recorridos reales de picker/campos y límites de privacidad ya registrados. |
| admin-panel, error-pages, frontend-core, alerts, gf-select, gf-table, gframe-icons | 7 | Admin/public/core; integración por componente. | Referencias puntuales de componentes, focus/errores, assets y configuración. No requieren siete skills nuevas. |
| heartbeat-client | 1 | Auth/core/frontend para sesión; trabajos en segundo plano para la distinción de capacidades. | Canales/polling y sesión frente a ejecución persistente; heartbeat no sustituye un worker. |
| markdown, rich-text-editor, lexical-search | 3 | Backend/public/admin y guías de contenido. | Recetas específicas de saneamiento, editor y búsqueda con autorización previa. Ampliar referencias antes de separar una skill. |
| notifications, notifications-email | 2 | Especialista correo/notificaciones añadido en el lote 12; backend/admin sirven de soporte. | Completar comprobación de SMTP, interacción inbox y fallos, conservando los límites actuales de recuperación y entrega. |
| notification-campaigns | 1 | Especialista de campañas añadido en el lote 14; conserva soporte auth/admin. | Verificar recorridos reales de administración, recurrencia y entrega según proyecto. |
| cron-runner | 1 | Maintenance menciona compatibilidad Async; falta la operación normal de trabajos. | Especialista de cron/Async/workers, sin prometer recuperación que el código no implementa. |
| wordpress-headless | 1 | Backend/core cubren infraestructura; falta la integración BridgeFrame. | Especialista de integraciones, junto a API/webhook/SSE/HttpClient del núcleo. |
| bootstrap, jquery, sweetalert2, aos, chartjs, coloris, flatpickr, html2canvas, intl-tel-input, jquery-ui, luxon, owl-carousel, purecounter, swiper, tinymce, venobox | 16 | Frontend/admin/public con catálogo de dependencias. | Referencias de instalación/inicialización solo para las bibliotecas usadas por la tarea; no una skill por vendor. |
| **Total** | **37** | **Nueve skills canónicas actuales** | **Cobertura parcial no equivale a verificación semántica completa.** |

### Orden de especialistas y límites

| Orden | Candidato | Tarea representativa y evidencia de entrada | Límite de responsabilidad |
| --- | --- | --- | --- |
| 1 | `gframe-notifications-mail` | Enviar una plantilla desde un formulario y distinguirlo de crear un aviso inbox o encolar email. `MailService::sendTemplate/sendTemplateAsync`, `NotificationService::notify`, `NotificationQueueService::enqueue`, procesadores de inbox/email; `docs/mail.md`, `docs/notificaciones.md`, `docs/notifications-email.md`. | Elegir canal, payload, plantilla, autor/destinatario/tenant y resultado. Envío sync espera al transporte; `mail_queued` confirma lanzamiento Async, no entrega. K-01 implementa recuperación de reservas nativas sin garantizar exactamente una vez para envíos externos. |
| 2 | `gframe-background-jobs` | Programar una operación persistente frente a lanzar una Closure puntual. `Async::create`, `CronTaskService`, `CronScheduler`, runners/workers y heartbeat; `docs/async.md`, `docs/cron-runner.md`, `docs/heartbeat.md`. | Ejecución, contexto capturado, CLI, scheduling, errores y operación de workers. No volver a encolar dentro de un worker por defecto; no sustituir cron por polling ni afirmar éxito de negocio por finalización del handler. K-03 clasifica errores devueltos por handlers, separado del envelope de lote. |
| 3 | `gframe-campaigns` | Crear campaña con audiencia autorizada y seguir dispatch/estado/recurrencia. `CampaignService`, handlers de cron y `AccountDeactivationLifecycle`; `docs/notification-campaigns.md`. | Audiencia, permisos, ámbito y reglas de campaña/cuenta. Reutiliza entrega de notificaciones y ejecución de trabajos; no duplica esas APIs ni cambia la política de retención. |
| 4 | `gframe-integrations` | Consumir contenido BridgeFrame y exponer/consumir un canal HTTP con guardas. `WordPressClient`, `HttpClient`, Middleware/Router y acceso API; `docs/wordpress-headless.md`, `docs/http-client.md`, `docs/api-access.md`, `docs/rutas.md`. | Recetas separadas por transporte, contrato `bridgeframe/v2`, credenciales, scopes y errores. No confundir cliente HTTP saliente con autorización de API entrante ni escoger todos los transportes en cada tarea. |
| Después | `gframe-orchestrator` | Resolver proyecto/paquete y elegir especialistas para las tareas siguientes. | Router de instrucciones, sin implementar APIs ni ejecutar automáticamente todos los especialistas. Validar O-02 antes de empaquetar. |
| Condicionado | `gframe-project-lifecycle` | Crear/actualizar un proyecto identificado con dry-run y preservación. `ProjectScaffolder`, `ProjectInstaller`, `ProjectUpdateService`; `docs/instalacion.md`, `docs/actualizaciones.md`, `docs/comandos.md`. | Primero completar maintenance/core. Crear una skill independiente solo si la selección sigue confundiendo tareas de aplicación y mantenimiento; no duplicar la receta ni ampliar permisos. |

El orden sigue dependencias operativas, no una valoración de gravedad de fallos. En este lote no se crean carpetas nuevas ni se modifica ningún contrato. Cada especialista se implementa en un lote acotado, con una receta comprobada mediante colaboradores ficticios o un proyecto temporal; probar correo no requiere enviar mensajes reales.

### Casos de selección para el futuro orquestador

| Petición | Selección mínima prevista | Comprobación de alcance |
| --- | --- | --- |
| CRUD administrativo | Backend + ORM + admin, según superficie. | No cargar campañas/integraciones ni modificar el framework instalado por un requisito del proyecto. |
| Formulario público que envía correo | Public + backend + especialista correo. | Captcha/rate limit o envío en segundo plano solo conforme al contrato/tarea; no añadir una campaña por tratarse de email. |
| Contenido WordPress | Integraciones + public si hay vista. | Confirmar versión del contrato, datos y saneamiento; no migrar autenticación propia a WordPress por defecto. |
| Picker en un tenant | Media + admin; auth para membresía/sesión cuando cambie. | Scope activo en servidor y límites físicos de privacidad; no crear una nueva arquitectura de tenancy. |
| Campaña programada | Campañas + trabajos/notificaciones solo donde se intervenga. | Distinguir creación, reserva, dispatch y entrega, preservando errores y permisos. |
| Actualización de proyecto | Maintenance/core con receta de integración de proyecto. | Proyecto identificado, versión real, dry-run y preservación; sin publicación del framework ni sincronización global implícita. |

En el lote 11 estos casos solo se contrastaron documentalmente. En el lote 16 un agente independiente aplicó el orquestador a los seis casos y dos casos adversariales; la evidencia y los límites de esa evaluación se registran abajo. No se ejecutaron las implementaciones de esas ocho peticiones.

## Orquestador, distribución y validación

| ID | Pendiente | Resultado comprobable |
| --- | --- | --- |
| O-01 | Implementado en el lote 16. | Reconoce framework/aplicación, localiza paquete/versión y selecciona por superficie; no replica contratos ni amplía permisos. |
| O-02 | Selección comprobada en el lote 16. | Seis casos representativos y dos adversariales evaluados por un agente independiente; no equivale a completar implementaciones ni validar descubrimiento automático en otros clientes/versiones. |
| D-01 | Copia/portabilidad y descubrimiento Codex comprobados; Claude fuera del alcance solicitado. | Snapshot posterior: catorce skills, 68 archivos idénticos en caché, 79 enlaces y metadata válidos. Codex CLI/app-server descubre las catorce habilitadas sin errores; no certifica selección automática por un agente. |
| D-02 | Instalación por proyecto implementada en lote 18. | Fuente desde metadata instalada de Composer y destino explícito; dos proyectos con metadata/versiones distintas comprobados. Bridges personalizados y carga efectiva requieren comprobación adicional. |
| D-03 | Vista previa/preservación implementadas en lote 18. | Registro/hashes, personalizaciones, skills ajenas, carpetas no registradas y obsoletos comprobados. No ofrece transacción de todos los archivos ni soporte de junctions/enlaces. |
| D-04 | Generador portable probado e instalado en Codex aislado; Claude fuera del alcance solicitado. | Portable OpenAI y adaptador Claude existentes, solo skills propias; stages/UX externos opcionales. No incluir MCP por obligación. Se conserva el adaptador sin ampliar su implementación ni verificar ese cliente. |
| V-01 | Implementado el alcance acotado del lote 21. | Enlaces locales de todos los Markdown/copia portable y control YAML separado con PyYAML. Fragmentos, Markdown completo, campos desconocidos y carga real no certificados. |
| V-02 | Verificar semántica y recetas. | Símbolos/APIs existentes, ejemplos ejecutables y tareas representativas; estructura válida no equivale a instrucciones correctas. |
| V-03 | Cubrir nomenclatura sin falsos positivos. | Excluir vendors, APIs públicas preservadas y excepciones documentadas; distinguir variables de claves y selectores. |
| V-04 | Inventario en lote 22; jobs JS/YAML en 23–24; lint ampliado en 25. | [Cobertura de comprobaciones](cobertura-checks-20261006.md): CI PHP/JS/YAML, PowerShell aún separado e integraciones opt-in. Runs remotos del cambio pendientes. |
| M-01 | Evaluar diagnóstico CLI antes de MCP. | Identificar tareas repetidas y datos útiles; decidir si un servidor aporta algo frente a comandos y skills. |

## Contratos implementados tras autorización posterior

| ID | Cambio | Decisión aplicada | Evidencia |
| --- | --- | --- | --- |
| K-01 | Recuperación y protección de reservas de notificaciones. | Lease configurable (900 s), `available_at` reutilizado y generación `attempts`; extensión opcional del repositorio, renovación antes de enviar y confirmación protegida. Detener workers antiguos antes de actualizar; no prometer exactamente una vez para SMTP. | Expiración/recuperación, canal, generación antigua, reintento diferido, pérdida de reserva y rollback probados en SQLite; repositorio antiguo independiente conserva su contrato. |
| K-02 | `whereIn([])` y `orWhereIn([])` agregan una condición falsa. | AND/OR y grupos conservan semántica; un filtro opcional se omite explícitamente si vacío significa todos. Revisados consumidores propios; cambio compatible con listas no vacías, transición documentada para aplicaciones. | Lecturas, grupos y update/delete con lista vacía probados en SQLite. |
| K-03 | Cron clasifica resultados `error`/`failed`. | No reprogramar errores; sin `status` conserva éxito y otros estados no se reclasifican. El envelope de lote sigue independiente de sus fallos individuales. | Handler que devuelve error, excepción, ausencia de status y recurrencia probados con repositorio ficticio y SQLite. |
| K-04 | Journal por sentencia MySQL y bloqueo por base de datos. | Omitir sentencias confirmadas; bloquear resultado incierto y hash alterado, con resolución administrativa explícita tras revisar efectos. No repetir SQL arbitrario automáticamente. | Cinco pruebas en MariaDB real: actualización histórica/baseline, recuperación entre sentencias y ejecución/registro, hash alterado y bloqueo concurrente. No certifica MySQL 8. |

## Estado tras resolver los pendientes autorizados

### Estado y límites actuales

1. Terminado: trabajo acumulado guardado en tres commits separados (4a5a4ec, 74e2a2a y 806d264). Ramas históricas retiradas con autorización posterior del usuario y respaldo verificado; quedan main y origin/main. Los commits nuevos de main aún no se han enviado al remoto.
2. Revisión local consolidada de S-01–S-09 según la matriz de evidencia siguiente. S-02 incorpora el recorrido backend/consumidor en lote 28; las pruebas de entorno y cada flujo no inspeccionado conservan sus límites.
3. Huecos de instrucciones de contenido/componentes cubiertos en lotes 29–30 y paginación posterior, con las catorce skills existentes. Integraciones externas o pantallas reales no se dan por verificadas.
4. Firmas públicas catalogadas y referencia común desde las catorce skills. N-03/N-04 ampliados al alcance nativo documentado; colisión de IDs multimedia corregida con evidencia, sin limpieza estética masiva.
5. Codex CLI/app-server: instalación y descubrimiento reales comprobados en configuración aislada. Selección automática en una conversación y carga visual desktop no se certifican con esta prueba de lectura. Claude queda fuera del alcance por petición del usuario.

Fuera de la lista activa: ampliaciones de CI/integraciones y MCP, conservadas como propuestas pospuestas. K-01–K-04 fueron autorizados posteriormente e implementados según la tabla anterior. No se abren otros frentes por esa autorización.

### Evidencia local consolidada de las nueve skills originales

| Área | Instrucciones/recorridos comprobados | Evidencia | Límite conservado |
| --- | --- | --- | --- |
| S-01 Core | Destino/versión, configuración, tipo/canal de ruta y tenant. | Lotes 02/09/16; bootstrap y rutas reales en pruebas temporales. | Servidor Nginx/CGI y otros despliegues no comprobados. |
| S-02 Backend | Límites HTTP/modelo, errores/estados, fuentes runtime, Mi cuenta y usuarios. | Lotes 01/02/10/26/28; 27 pruebas dirigidas en el cierre. | No equivale a probar cada endpoint HTTP/CSRF. |
| S-03 ORM | Estado/clones/conexiones, IN vacío, ordenación y límites transaccionales. | Lotes 01/08, snippets SQLite, pruebas de documentación y K-02 posterior. | MySQL 8 y consumidores externos no certificados; listas vacías opcionales requieren transición explícita. |
| S-04 Auth | Credenciales, sesión, tenant, revocación y administración protegida. | Lotes 02/10; receta de cambio de contraseña y garantías por driver. | Redis real y todos los formularios HTTP no comprobados. |
| S-05 Admin | Fuentes/meta/assets, AJAX/parcial, componentes, editor, paginación y nombres. | Lotes 04/29/30 y aclaración de paginación; PHP/JS dirigidos. | Navegador, foco y unicidad global de IDs requieren páginas renderizadas. |
| S-06 Public | Home, metas/SEO/escaping, assets y contenido/búsqueda autorizada. | Lotes 05/29/30; guías y fuentes runtime contrastadas. | Indexación externa y QA de todas las páginas no comprobadas. |
| S-07 Media | Scope/autoría, campos/picker, relaciones y límites de privacidad. | Lote 06; snippets y pruebas de servicios. | Flujo completo de uploads/picker en navegador no comprobado. |
| S-08 UI | Alcance visual, coordinación UX y convenciones técnicas sin duplicarlas. | Lote 07 y referencias canónicas de admin/public. | No se ejecutó un rediseño ni QA visual de todas las pantallas. |
| S-09 Mantenimiento | Modos, autorizaciones, publicación, integración y distribución de skills. | Lotes 03/16/18/19/21 y carga Codex posterior. | Actualización de una aplicación real no comprobada; Claude fuera del alcance solicitado. |

No se abre otro frente por defecto. Las cuatro propuestas de contrato están implementadas y la revisión nativa de nomenclatura tiene evidencia posterior. Restan selección real en conversaciones, comprobación visual desktop y servicios de despliegue donde un proyecto requiera esos contratos. Esos límites no son defectos demostrados de las skills. Claude se retira de los pendientes por petición del usuario.

## Verificación de este inventario

Se leyeron las nueve `SKILL.md`, referencias concretas de backend/ORM/auth/frontend/core, el instalador y verificador de skills, documentación de skills, catálogo de módulos y muestras de código de middleware, SEO, usuarios, notificaciones y campañas. Las evidencias puntuales se localizaron con `rg`; no se presenta esta inspección como una auditoría exhaustiva de cada símbolo o receta.

La primera pasada creó este inventario; los lotes posteriores actualizan las skills indicadas abajo. No sustituyen los cambios de código anteriores ni los declaran publicados. El inventario permanece abierto para los lotes restantes.

## Registro del lote 01

Autorizado por el usuario con «adelante» el 6 de octubre de 2026. Alcance: reglas de nombres para código nuevo, retorno de operaciones y responsabilidad de los catches. Sin cambios de runtime, instalación global ni renombrados.

- Añadida `skills/gframe-core-architecture/references/naming-conventions.md` y enlazada desde core, backend y ORM.
- Ajustadas las reglas de backend y su referencia de límites; eliminada la obligación universal de `message` y el rechazo genérico de `Throwable`.
- Ajustadas ORM y sus tres referencias para conservar retornos reales, manejar excepciones y proteger rollback con relanzamiento del fallo original.
- No se dan por terminadas S-01, S-02 o S-03 completas: tenant, paginación, ordenación, conexiones y recetas pendientes conservan su lugar en el inventario.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: core, backend y ORM válidas.
- Doce enlaces de este lote comprobados en una copia temporal de las nueve skills. Esto no cierra las referencias antiguas de auth ni el descubrimiento por versión.
- Ambos ejemplos transaccionales se ejecutaron sobre SQLite usando DatabaseManager/ORM reales: ante RuntimeException y TypeError revierten la escritura y relanzan el mismo fallo. La dependencia del validador YAML se instaló únicamente en una carpeta temporal.
- `git diff --check` correcto. No se repite la suite runtime porque este lote modifica instrucciones y ejemplos, no código de ejecución.

## Registro del lote 02

Autorizado por el usuario con «sigue» el 6 de octubre de 2026. Alcance: instrucciones de tenant y sesiones en core/backend/auth, y referencias esenciales de auth que se conservan al copiar la skill. Sin cambios de runtime ni instalación global.

- Corregidas las fuentes de tenant en las referencias de core y backend. Se reconoce la clave configurada, sin tratar `project_id` como un fallback universal.
- Documentados los IDs coincidentes, ausentes, inválidos y contradictorios; la sesión requerida por multimedia; y la diferencia entre el bypass de superadministrator y la validación del scope.
- Añadida `skills/gframe-auth-access/references/sessions-and-tenancy.md`: identidad normalizada, driver database/Redis/native, disponibilidad del registro, reset/revocación y versiones de autorización.
- Auth y core sustituyen los enlaces a `../../../docs` por referencias locales y por instrucciones para localizar las guías completas en el paquete Composer resuelto.
- No se da por cerrada toda S-01, S-02 o S-04: quedan recetas, integración por versión y comprobaciones de las demás instrucciones. No se afirma que todos los módulos utilicen el resolver compartido.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: core, backend y auth válidas.
- Siete enlaces comprobados en una copia temporal de las nueve skills, incluidas todas las referencias Markdown de auth y las referencias modificadas de core/backend.
- Pruebas existentes dirigidas: 33 pruebas, 122 aserciones, sin fallos ni omisiones. Cubren reset, tenant, registro de sesiones database, permisos, SessionManager y scope multimedia. No se verificó un servidor Redis real.
- `git diff --check` correcto. El código de ejecución y las skills globales quedan sin cambios de este lote.

## Registro del lote 03

Autorizado por el usuario el 6 de octubre de 2026 con «sigue», aclarando también que «ok» significa continuar. Alcance: `gframe-framework-maintenance/SKILL.md` y sus dos referencias; actualización de este inventario. Cuatro archivos, sin cambios de runtime ni ejecución sobre aplicaciones instaladas.

- Eliminado el paso obligatorio de commit y actualización de una aplicación después de cualquier cambio del framework. Las acciones ya autorizadas continúan sin una nueva confirmación.
- Separadas las recetas de mantenimiento del repositorio, integración de una aplicación concreta y contratos de distribución/instalador.
- Diferenciados `composer update gorvet/gframe`, el actualizador del proyecto, dry-run, preservación de personalizados y actualizaciones pendientes de esquema, conforme a las guías vigentes.
- Diferenciada la edición de skills canónicas de su instalación global. No se modificó el instalador ni se sincronizaron copias globales.
- Ajustados los checks a la superficie afectada, conservando las comprobaciones completas exigibles para una publicación solicitada y las garantías existentes de instalador/migraciones.
- No se da por cerrada S-09 completa: la ampliación de CI, checks JS, cobertura de lint y pruebas MySQL reales sigue en V-04 y en los lotes que afecten esas superficies.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: maintenance válida. Dos enlaces comprobados en una copia temporal independiente de la skill.
- Revisión estructural y contraste con `docs/comandos.md`, `docs/actualizaciones.md` y los scripts Composer. No se presenta esta revisión como una prueba de comportamiento de un agente ni como una actualización de aplicación ejecutada.
- `git diff --check` correcto. No se repite la suite runtime para este lote de instrucciones.

## Registro del lote 04

Autorizado por el usuario con «ok», conforme a su indicación de continuar, el 6 de octubre de 2026. Alcance: la skill frontend-admin, tres referencias existentes, una receta nueva y este inventario. Seis archivos, sin cambios de runtime, renombrados ni instalación global.

- Corregida la suposición de que todo módulo usa `app/views/admin` y `public/js/app/admin`. Separados originales runtime, overrides del proyecto y destinos de assets definidos por el manifiesto.
- Contrastadas las rutas de user-admin, su meta, vista, parcial, script y controlador. Añadida una receta de cambio de rol y refresco del listado con permisos, CSRF, nombres de campos y respuesta HTML.
- Documentada la cadena `data-user-id` → `user_id` → `$userID`. Conservados los IDs y selectores actuales; la elección de una convención universal para IDs nuevos y la revisión de colisiones siguen pendientes en N-04.
- Corregida la ubicación documentada de `site_url` e `is_protected`: el footer estándar los define antes de sus scripts. Conservado el bridge local de tokens de user-admin.
- Diferenciados el patrón recomendado de validación por campo y el flujo actual de validez nativa. Las claves JSON comunes no se exigen todas en cada endpoint.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: frontend-admin válida. Ocho enlaces comprobados en una copia temporal de las nueve skills.
- Pruebas existentes dirigidas: 15 pruebas, 114 aserciones, sin fallos ni omisiones. Cubren UI/contratos de usuarios, permisos/protecciones, publicación y reutilización del parcial personalizado. Se ejecutaron con PHPUnit 10.5.65 del repositorio; el lanzador antiguo del PATH no es válido para este entorno.
- No se da por cerrada toda S-05 ni N-03: faltan otras recetas y la comprobación en navegador de foco, estados, peticiones concurrentes y apariencia. Las aserciones estáticas no prueban interacción en navegador.
- `git diff --check` correcto. No se repite la suite completa para cambios de instrucciones.

## Registro del lote 05

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: frontend-public y sus tres referencias existentes, más este inventario. Cinco archivos, sin cambios de runtime, templates, assets, aplicaciones instaladas ni skills globales.

- Separados archivos propios de la aplicación, originales del esqueleto y originales/overrides de módulos. Contrastado el recorrido real de home desde la ruta hasta controller, vista, group meta y template.
- Documentadas las capas efectivas de Meta/Render, precedencia y fusión de claves/assets, y la distinción entre `js` del footer y `hjs` del header.
- Aclarado que `metaTags.robots` se descarta: la política global y la ruta deciden la indexación. Conservados los alcances distintos de las exclusiones legacy y los controles de endpoints/debug.
- Contrastada la lectura directa de metas de Sitemap/Llms, sin controller ni la resolución runtime de Render. Documentados filtros de publicación/tenant explícitos y el mapeo de placeholders a columnas.
- Diferenciados texto escapado y HTML confiable/saneado; conservadas las claves propias de schema y las reglas canónicas de nombres/IDs mediante enlaces, sin duplicarlas ni renombrar consumidores.
- Aclarado que el header actual del esqueleto no imprime etiquetas Open Graph aunque Meta pueda almacenar valores sociales. No se añadió esa funcionalidad; cualquier ampliación del template requiere su propio alcance.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: frontend-public válida. Cinco enlaces comprobados en una copia temporal de las nueve skills.
- Pruebas existentes dirigidas: 32 pruebas PHP y 314 aserciones, más cuatro pruebas JS de navegación pública; sin fallos ni omisiones. Cubren política SEO, indexabilidad, metadatos, composición/JSON-LD, home y navegación con DOM simulado.
- No se da por cerrada toda S-06: faltan otras recetas de contenido y una comprobación en navegador con contenido/header reales. No se verificaron servicios externos de SEO ni indexación por buscadores.
- `git diff --check` correcto. No se repite la suite completa para este lote de instrucciones.

## Registro del lote 06

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: media-module, una referencia nueva y este inventario. Tres archivos, sin cambios de runtime, configuración, archivos multimedia, aplicaciones instaladas ni skills globales.

- Contrastados el manifiesto, wrappers publicados, controlador/vistas runtime y destinos de assets. Diferenciadas capas nativas y overrides del proyecto.
- Documentado el scope explícito de las llamadas PHP: omitirlo utiliza global aunque el proyecto configure tenant. Separadas identidad, membresía, permisos y autoría de carga.
- Documentados el resolver de tenant y los atributos/parámetros JS existentes, que deben coincidir con la sesión activa; no se renombraron ni se convirtieron en selectores de propietario.
- Añadida una receta del campo/selector, validación del guardado, previews por IDs, componentes insertados por AJAX y relaciones explícitas. Eliminar una selección o desvincular no equivale a borrar el archivo.
- Confirmado que los archivos nativos viven bajo `public/uploads` y el módulo no declara una descarga privada. El aislamiento del listado no protege el acceso físico por URL. No se implementó almacenamiento privado ni una ruta nueva.
- Separados cuota por scope, límite por archivo y límites PHP/servidor; conservadas la personalización por herencia, las variantes y los originales existentes.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: media válida. Tres enlaces comprobados en una copia temporal de las nueve skills.
- Pruebas existentes dirigidas: 18 pruebas PHP y 166 aserciones, más una prueba JS de normalización de IDs; sin fallos ni omisiones. Cubren scopes, referencias/previews, configuración, relaciones y comportamiento de servicios.
- No se da por cerrada toda S-07: faltan pruebas en navegador de picker/campos, uploads, cancelación y contenido añadido por AJAX. La prueba JS actual no cubre esos recorridos completos ni una descarga privada.
- `git diff --check` correcto. Checks de la skill ajustados a la superficie modificada, sin imponer una publicación completa tras una edición de instrucciones.

## Registro del lote 07

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: UI-design-clean y sus dos referencias existentes, más este inventario. Cuatro archivos, sin cambios de runtime, vistas, CSS, JavaScript, metadatos de agentes ni skills globales.

- Delimitado diseño/UX frente a contratos técnicos: enlazadas las referencias canónicas de admin/public para estructura, assets, meta, navegación y feedback. No se duplicaron sus reglas de nomenclatura o vistas.
- Conservadas las referencias aprobadas y el diseño solicitado. Una auditoría de código/skills no autoriza por sí sola rediseñar pantallas o controles.
- Alineada la preferencia de layout con Bootstrap/columnas/flex de las skills frontend. Matizados reset de filtros, navegación móvil y representaciones responsive para evitar imponer reemplazos universales.
- Separados framework y aplicación, con fuentes/assets determinados por el alcance y manifiesto. Preservados temas y controladores existentes, sin añadir otra infraestructura de diseño.
- Ajustado el checklist para registrar comprobado, fallido, no verificado o no aplicable. La inspección estática no acredita contraste, foco, apariencia responsive ni interacción.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: UI-design-clean válida. Ocho enlaces comprobados en una copia temporal de las nueve skills.
- Contraste documental con admin/public y evidencia de los patrones existentes en admin-panel, campañas y home. No se ejecutó un agente de prueba ni se presenta esta revisión como una auditoría visual de las pantallas.
- No se da por cerrada toda S-08: la aplicación de estas instrucciones a una tarea real y la verificación en navegador siguen pendientes. No se activó otra cadena completa de skills de diseño para esta edición documental.
- `git diff --check` correcto. Sin repetición de pruebas runtime, por tratarse de instrucciones y enlaces.

## Registro del lote 08

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: ORM-models y sus referencias de consultas/conexiones, más este inventario. Cuatro archivos, sin cambios de ORM, dialectos, esquema, aplicaciones instaladas ni skills globales.

- Documentado el comportamiento vigente de `whereIn([])` y `orWhereIn([])`: omiten la condición. Las selecciones/autorizaciones vacías deben detenerse en el consumidor; K-02 conserva separada la propuesta de cambiar el contrato del ORM.
- Contrastados `reset`, `newQuery` y `queryTable`: reset limpia la consulta pero conserva atributos/tabla/conexión; newQuery clona el estado; queryTable crea otra instancia y pierde overrides de conexión de la anterior.
- Añadido un ejemplo de ordenación por lista permitida. Columnas y expresiones no quedan protegidas por el binding de valores; las llamadas sucesivas añaden criterios y la dirección conserva su normalización actual.
- Corregido el ejemplo de listado para clonar una misma base y conservar filtros/conexión, limitar entradas y ordenar de forma estable. `paginate` devuelve filas sin calcular/clamp de metadatos; el límite del ejemplo es una política de aplicación.
- Aclaradas conexiones estáticas frente a overrides de instancia, propietario de la transacción, falta de savepoints/transacciones distribuidas y límites de rollback para DDL, archivos y servicios externos.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: ORM válida. Cuatro enlaces comprobados en una copia temporal de las nueve skills.
- Pruebas existentes dirigidas: cuatro pruebas y 36 aserciones, sin fallos ni omisiones. Cubren ejemplos de la guía ORM y búsquedas por dialecto; no equivalen a una ejecución MySQL completa.
- Comprobación temporal adicional contra ORM/SQLite reales: IN/OR vacíos, clonación/reset, atributos retenidos, conexiones main/aux y ejecución de los dos snippets actuales de ordenación/listado. El borrado con otro WHERE e IN vacío se ejecutó en una transacción revertida y se confirmó la restauración de filas; `deleteWhere` conserva su status `deleted`, no `success`.
- No se da por cerrada toda S-03: faltan ejemplos de otros motores y recorridos restantes. No se cambiaron retornos, catches ni semántica de consultas del runtime.
- `git diff --check` correcto. No se repite la suite completa para este lote de instrucciones y ejemplos.

## Registro del lote 09

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: core-architecture, referencias de carpetas/rutas, una referencia nueva de bootstrap/configuración/versión y este inventario. Cinco archivos, sin cambios de core, Composer, configuración de proyectos, runtime ni skills globales.

- Diferenciados el tipo declarado por RouteBuilder según el archivo llamador y el canal ejecutado por Router según el prefijo URL. Documentados helpers, carga ordenada de `routes_*.php`, comprobación API OPTIONS y `system` como categoría sin prefijo especial de ejecución.
- Contrastado el arranque real por Composer y el bridge del esqueleto. Documentados configuración estructurada frente a legacy, orden del bootstrap y arranque único por proceso.
- Aclarados fusión de mapas/reemplazo de listas, valores null, helpers de entorno, constantes que no se redefinen y límites de una reconfiguración en un worker ya iniciado.
- Añadida localización de la versión/raíz efectiva mediante metadata Composer, lock y archivo de la clase cargada. El registro de módulos y una skill global no prueban por sí solos la versión ejecutada; D-02 conserva pendiente automatizar ese descubrimiento.
- Diferenciados originales runtime, publicados y overrides. Eliminada la instrucción implícita de actualizar siempre el lock de una aplicación después de cambiar el framework; la integración sigue el alcance autorizado.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: core válida. Diez enlaces comprobados en una copia temporal de las nueve skills.
- Pruebas existentes dirigidas: nueve pruebas, 94 aserciones, sin fallos y con una omisión. La integración real de Nginx/PHP-CGI requiere `GFRAME_TEST_NGINX` y `GFRAME_TEST_PHP_CGI`, no configurados en esta ejecución.
- Comprobación temporal independiente con Bootstrap/ConfigRepository/Router reales: configuración del proyecto sobre defaults, constante previa conservada, segundo boot sin recarga, fusión de mapas/listas/null, tipo declarado API sin prefijo frente a canales web/api/ajax y ruta system como web. Verificadas también la clase cargada y la raíz Composer de este checkout, sin leer credenciales.
- No se da por cerrada toda S-01: quedan otros recorridos y la integración de servidor omitida. No se cambió inferencia ni se reconfiguró una aplicación instalada.
- `git diff --check` correcto. No se repite la suite completa para este lote de instrucciones.

## Registro del lote 10

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: entrypoints de auth/backend, una receta nueva de Mi cuenta y este inventario. Cuatro archivos, sin cambios de runtime, usuarios reales, contraseñas reales, APIs ni skills globales.

- Añadida una receta del cambio de contraseña de self-account con manifiesto, fuentes/publicados/overrides, ruta AJAX, tokens, campos, identidad de sesión, servicio, persistencia y respuesta al frontend.
- Contrastados los parámetros HTTP `current_password`, `new_password` y `new_password_confirmation` frente a los parámetros del servicio. Documentado que las contraseñas conservan bytes, espacios y caracteres, sin aplicarles saneamiento de texto visible.
- Aclarados PasswordPolicy por bytes frente a límites HTML, status/code sin claves adicionales obligatorias y mensajes añadidos por el controlador cuando faltan.
- Documentados disponibilidad del registro al construir el servicio, revocación sin exención de la sesión actual y falta de atomicidad distribuida entre escritura y revocación. No se prometió continuidad de sesión ni rollback de contraseña después de un error posterior.
- Corregida en backend la distinción entre tipo declarado y canal URL, coherente con el lote 09. Checks de auth ajustados al flujo afectado, sin exigir mutaciones de usuarios para una receta documental.
- Verificador del repositorio: nueve skills válidas. Validador de skill-creator: auth y backend válidas. Doce enlaces comprobados en una copia temporal de las nueve skills.
- Pruebas existentes dirigidas: 26 pruebas y 90 aserciones, sin fallos ni omisiones. Cubren servicio/UI de Mi cuenta y manejador de sesiones database; no prueban por sí solas un recorrido HTTP completo en navegador.
- Comprobación temporal contra controlador y servicio nativos, con repositorio/registro ficticios: contraseña con espacios y caracteres preservados, `user_id` de navegador ignorado, otro usuario sin cambios, revocación dirigida, retirada de `must_change_password`, perfil sin password/token e identidad ausente rechazada.
- No se dan por cerradas toda S-02/S-04 ni N-03: faltan otros flujos, peticiones HTTP/CSRF completas y comprobaciones de browser/Redis cuando correspondan. La receta no añade una garantía de idempotencia al botón deshabilitado.
- `git diff --check` correcto. No se repite la suite completa para este lote de instrucciones y receta.

## Registro del lote 11

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: únicamente este inventario. Sin nuevas skills, cambios de runtime, ejecutores, módulos instalados ni configuración global.

- Contrastados los 37 manifiestos y sus tipos/dependencias mediante ModuleCatalog; separados 16 external-ui y 21 módulos propios. La carpeta wordpress-styles no tiene manifiesto y no añade otro módulo al catálogo.
- Clasificados todos los módulos una sola vez y diferenciada documentación disponible de cobertura operativa de una skill.
- Priorizados cuatro especialistas: correo/notificaciones, trabajos en segundo plano, campañas e integraciones. Proyecto/lifecycle queda condicionado, porque maintenance/core ya cubren parte de esa operación.
- Delimitados sus puntos de entrada y responsabilidades para evitar duplicación, una skill por vendor o promesas de recuperación/entrega no implementadas. K-01 y K-03 permanecen como mejoras de contrato separadas.
- Preparados seis casos de selección mínima para un orquestador posterior. No se creó el orquestador ni se presentó una matriz documental como prueba de un agente.
- Verificación de catálogo/cobertura y existencia de fuentes locales; revisión editorial y `git diff --check`. No se repiten suites runtime para esta clasificación documental.
- No se da por cerrada la etapa de cobertura: faltan especialistas, recetas de componentes y evaluación de selección/distribución.

## Registro del lote 12

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: nueva skill `gframe-notifications-mail`, dos referencias y metadata mínima del agente, catálogo documental y este inventario. Seis archivos, sin cambios de runtime, SMTP, destinatarios reales, aplicaciones instaladas ni skills globales.

- Separados correo síncrono, lanzamiento Async, aviso inbox y cola persistente, con los códigos y garantías efectivos de cada resultado.
- Contrastados plantillas/escaping, configuración del worker, rate limit opt-in, ámbito del destinatario, rutas inbox, transporte y procesadores. No se promete verificación automática de membresía ni entrega por haber encolado.
- Documentadas las diferencias entre procesadores y la ausencia actual de recuperación por lease tras una caída. K-01 y K-03 permanecen como mejoras separadas; no se cambiaron contratos.
- Verificador del repositorio: diez skills válidas. Validador de skill-creator: nueva skill válida. Dos enlaces comprobados desde una copia temporal independiente.
- Pruebas existentes dirigidas: 35 pruebas y 285 aserciones, sin fallos ni omisiones, sobre plantillas, límites, email, inbox y persistencia SQLite.
- Ejecutadas las tres recetas extraídas de las referencias: lanzamiento con Async ficticio, aviso con repositorio ficticio y aislamiento de tenant, y cola/procesador reales sobre SQLite con MailSender ficticio. No se enviaron mensajes ni se contactó SMTP.
- La estructura y las pruebas locales no acreditan entrega real ni selección de un agente. Quedan pendientes SMTP real, interacción en navegador y demás escenarios de fallo aplicables; la cobertura completa no se da por cerrada.
- `git diff --check` correcto. No se repite la suite completa para este lote de instrucciones.

## Registros de los lotes 13–15

Autorizados conjuntamente por el usuario con «haz los 3 que faltan» el 6 de octubre de 2026. Alcance conjunto: tres skills, cada una con entrypoint, dos referencias y metadata mínima; catálogo documental y este inventario. Catorce archivos de instrucciones/documentación, sin cambios de runtime, módulos instalados, cron del servidor, servicios remotos ni copias globales.

### Lote 13: trabajos en segundo plano

- Creada `gframe-background-jobs`, con selección entre ejecución directa, Async, cron, procesadores y polling.
- Contrastados contexto serializado/CLI, tareas persistentes, controles, recurrencia, contadores, recuperación de bloqueos y contrato vigente de errores. Documentado que un array `status: error` no marca fallo en el scheduler y que no se garantiza captura de TypeError.
- Añadidas recetas de registro cron y canal heartbeat. Conservados sesión, noRefreshSession, payload backend, intervalo mínimo y límites de force/visibilidad; no se sustituye un worker por polling.

### Lote 14: campañas

- Creada `gframe-campaigns`, con audiencias/guard, dispatch, programación/recurrencia, reglas automáticas y límites del ciclo de desactivación.
- Contrastados permisos/ámbito, conteo por canal/destinatario, diferencias entre membresía ordinaria/automática, recurrencia registrada aparte, inyección no transferida a handlers nativos y cancelación sin retirada de trabajos.
- Añadidas recetas de campaña inbox y evento de bloqueo. Conservados cooldown, historial, renovación transaccional del token y requisitos del aviso previo a eliminación; no se ejecutó lifecycle contra usuarios reales.

### Lote 15: integraciones

- Creada `gframe-integrations`, con HTTP/WordPress saliente y API/webhook/SSE entrantes separados por responsabilidad.
- Contrastados formatos de body/JSON, sobre HTTP frente a negocio, BridgeFrame 2.0 y normalización recursiva, scopes/tenant explícitos, verificación de secretos y límites de replay/buffering.
- Añadidas recetas de carga de artículo y autorización de scope. No se alteraron autenticación, credenciales, transporte, HTML ni rutas del framework.

### Verificación conjunta

- Verificador del repositorio: trece skills válidas. Validador de skill-creator: las tres nuevas válidas; metadata YAML comprobada como mapas. Seis enlaces comprobados después de copiar las tres carpetas a una ubicación temporal independiente.
- Pruebas existentes dirigidas: 74 pruebas y 496 aserciones, sin fallos ni omisiones. Cubren Async, cron, heartbeat, campañas/audiencias/recurrencia/reglas/lifecycle, WordPress y guardas API/middleware, además de regresiones comprobadas de auditoría.
- Ejecutadas las seis recetas extraídas de las referencias. Cron real sobre SQLite confirmó persistencia y el comportamiento actual del payload de error; heartbeat real confirmó intervalo y restricción oculta incluso con force. Campaña con repositorios ficticios confirmó placeholders/tenant; evento automático real sobre SQLite confirmó encolado y supresión. WordPress usó transporte ficticio con respuesta objeto; scope faltante devolvió 403. Sin red, destinatarios reales ni trabajos de producción.
- Pendientes pruebas de selección de un agente, navegador/pestañas, scheduler del despliegue, SMTP/BridgeFrame/TLS reales y escenarios de interrupción aplicables. Las pruebas locales no cierran esos pendientes ni los cambios K-01–K-04.
- Revisión editorial y `git diff --check` correctos. No se repitió la suite completa por tratarse de instrucciones y recetas.

## Registro del lote 16

Autorizado por el usuario con «ok», conforme al significado de continuar con el siguiente lote, el 6 de octubre de 2026. Alcance: orquestador, referencia de selección y metadata mínima; correcciones puntuales de coherencia en maintenance, core y campaña; catálogo e inventario. Nueve archivos de instrucciones/documentación, sin cambios de runtime, aplicaciones, Composer, cron del servidor ni copias globales.

- Creado `gframe-orchestrator` para localizar el destino/paquete efectivo y seleccionar especialistas según responsabilidad/superficie. Conserva versiones desconocidas como tales y no considera una skill global más nueva evidencia de compatibilidad.
- Delimitados framework/aplicación, lectura selectiva, overrides/publicados, cambios ya autorizados y verificación proporcional. No impone delegación, documentos de etapas, rediseño, complemento, MCP, commits o releases.
- Evaluación independiente conforme a skill-creator: seis casos de selección (CRUD, contacto, WordPress, picker tenant, campaña programada y actualización) y dos adversariales (copia intacta a aplicación autorizada y dos releases con especialista ausente). El agente no editó ni implementó las peticiones.
- La evaluación encontró ambigüedades entre el router y referencias existentes. Se corrigieron únicamente lectura por modo, guardado/validación del picker, actualización a la versión objetivo con preservación explícita, autoload condicionado a ausencia de efectos y receta de campaña programada. Una segunda revisión independiente no encontró problemas materiales de selección/alcance en los ocho casos.
- Verificador del repositorio: catorce skills válidas. Validador de skill-creator: orquestador y tres especialistas afectados válidos. Metadata YAML del orquestador comprobada como mapa; sus trece destinos existen. Cuatro enlaces de los Markdown afectados resuelven después de copiar las skills a una ubicación temporal.
- Descubrimiento real en este checkout: metadata Composer y archivo de Bootstrap coinciden con la raíz confirmada; no se llamó al bootstrap ni se definió ABSPATH. Receta programada extraída del Markdown ejecutada con repositorios ficticios: conserva fecha futura/tenant y no despacha ni contacta destinatarios.
- No se actualizaron aplicaciones ni se verificó ejecución de las ocho implementaciones, distribución automática por cliente o dos releases realmente instaladas. D-01–D-04 y las verificaciones semánticas restantes permanecen abiertas.
- Revisión editorial y `git diff --check` correctos. No se repite la suite runtime para este lote de selección e instrucciones.

## Registro del lote 17

Autorizado por el usuario con «continúa» el 6 de octubre de 2026, aclarando que stages no es propio y que UX puede coordinarse como complemento externo. Alcance: diseño de distribución, referencia del orquestador, catálogo documental e inventario. Cuatro archivos, sin cambios de runtime, instalador, configuración de clientes, manifiestos publicados ni skills externas/globales.

- Contrastados manifiestos locales de UX y plugin-management y documentación oficial actual de OpenAI/Claude. Registrados formato portable, compatibilidad anterior, rutas por proyecto y posible coexistencia de nombres.
- Definido un complemento de catorce skills propias, con fuente canónica única y contenido copiado intacto. Stages y UX permanecen externos y opcionales según la tarea, sin dependencias obligatorias ni copia de archivos.
- Añadida coordinación explícita al router: etapas conservan sus gates de conciliación/tareas/auditoría; UX invocado conserva sus comprobaciones. Los contratos técnicos siguen en GFrame y no se activa un ciclo completo para toda corrección pequeña.
- Separada identidad/versión del complemento de la versión efectiva del proyecto. Definidos fuente/destino explícitos, vista previa, registro de hashes, conflictos y obsoletos administrados; no se promete que una copia local oculte otra global.
- Verificador: catorce skills válidas; orquestador válido en skill-creator. Copia temporal completa: 66 archivos idénticos por SHA-256 y 61 enlaces Markdown locales resueltos, sin enlaces faltantes.
- Revisión editorial del diseño sin errores ni advertencias; `git diff --check` correcto. No se generan releases ni se presenta la copia temporal como una instalación/carga verificada en los clientes.
- D-01–D-04 conservan pendientes de implementación e integración concretos en el diseño; no se repite una suite runtime para este lote documental.

## Registro del lote 18

Autorizado por el usuario con «ok» el 6 de octubre de 2026, después de explicar instalación por proyecto y vista previa. Alcance: entrada del instalador, helper nuevo, prueba PowerShell nueva, guía de skills, referencia de mantenimiento, changelog sin publicar, diseño e inventario. Ocho archivos; no se modificó core PHP, aplicaciones reales, módulos, etapas/UX ni configuración real de clientes.

- Añadidos `-ProjectPath`, `-DryRun` y `-Json`. El modo anterior sin ProjectPath se conserva; DryRun/Json se rechazan en ese modo para no presentar una vista previa ficticia.
- Fuente resuelta desde composer.json, vendor-dir y installed.json, con comprobación de identidad del paquete. No se ejecuta PHP/autoload/bootstrap ni se cae en este checkout ante metadata ausente. El propio standalone se reconoce por su identidad y no inventa versión.
- Destinos locales Codex/Claude con registro por cliente y SHA-256. Un archivo intacto se actualiza; una personalización o carpeta no registrada se conserva como conflicto. Los obsoletos se retiran solo si están registrados e intactos; las skills ajenas permanecen.
- Plan sin escrituras y ejecución recalculada; mutex local por destino y comprobación de hashes antes de mutar. Rutas manipuladas y junctions/enlaces rechazados. No se promete atomicidad global ni recuperación automática de una operación interrumpida.
- Prueba en procesos Windows PowerShell 5.1: 163 comprobaciones correctas con proyectos temporales de metadata/versiones distintas, ambos clientes, repetición sin cambios, conflictos registrados/no registrados, obsoletos, ruta fuera del destino, metadata ausente/incorrecta y destino junction. Copias completas de los 66 archivos canónicos verificadas por SHA-256 para Codex y Claude; modo global Codex comprobado únicamente con CODEX_HOME temporal.
- Sintaxis de los tres scripts comprobada; catorce skills válidas; maintenance válida en skill-creator. Revisión editorial y `git diff --check` correctos. Las pruebas PowerShell se ejecutan con el comando documentado, no mediante la suite PHPUnit.
- Carga de instrucciones en clientes, custom bridges, recuperación de interrupciones y artefacto de complemento siguen sin verificarse/implementarse según corresponda. No se instaló ninguna skill en proyectos reales ni en las carpetas globales del usuario.

## Registro del lote 19

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: generador PowerShell nuevo, prueba nueva, guía, referencia de mantenimiento, changelog sin publicar, diseño e inventario. Siete archivos del repositorio; sin cambios de core PHP, instalador por proyecto, Composer, aplicaciones, stages/UX ni configuración de clientes.

- Generador `bin/build-skills-plugin.ps1` con versión SemVer explícita y destino nuevo fuera del checkout. Vista previa sin escrituras; no sobrescribe artefactos existentes. Solo empaqueta las catorce skills propias, con una única copia del contenido y manifiestos portable/Claude coherentes.
- Copias originales verificadas por SHA-256, enlaces locales contenidos en las skills y metadata mínima comprobados. Registro de procedencia distingue HEAD de contenido efectivo con cambios sin commit. No se presenta un artefacto de prueba como release limpia ni como versión efectiva de una aplicación.
- `tests/BuildSkillsPluginTest.ps1`: 219 comprobaciones correctas en Windows PowerShell 5.1, incluidos hashes, reproducibilidad, versión inválida, destino existente/dentro del checkout, referencia fuera del paquete y junction. Catorce skills válidas en el verificador del repositorio; sintaxis PowerShell y revisión editorial comprobadas. No se repite la suite runtime por este generador independiente.
- Artefacto de revisión en `C:/Users/Juank de Gorvet/Downloads/GFrame-skills-preview-20261006`, versión exclusivamente de prueba `0.0.0-preview`: 66 archivos canónicos idénticos y 61 enlaces locales resueltos, más los dos manifiestos y el registro. No instalado ni publicado.
- Formatos contrastados con documentación y esquema oficiales; carga real pendiente. Claude local falla por ausencia de su módulo CLI; Codex disponible no ofrece validación local de complemento. No se repararon clientes ni se lanzó otro agente para sustituir esas comprobaciones.
- La primera prueba falló al retirar una junction desde PowerShell 5.1; la limpieza de fixtures nuevas se corrigió para retirar solo el enlace, sin recorrerlo. La revisión automática rechazó limpiar el residuo anterior con «blocked by policy»: queda `C:/Users/Juank de Gorvet/AppData/Local/Temp/gframe-plugin-test-b3abd75c-9c10-4ceb-808f-f27487136f39`. No afecta al artefacto ni se intentó una eliminación recursiva alternativa.
- Carga en clientes, revisión semántica restante, nomenclatura y K-01–K-04 conservan sus pendientes. La siguiente propuesta es inventariar nomenclatura sin renombrados masivos.

## Registro del lote 20

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: inventario de nomenclatura, generador de evidencia, JSON de evidencia y este registro. Cuatro archivos bajo maintenance; ninguna modificación de runtime, skills, complemento de prueba, aplicaciones o configuración de clientes.

- Inspeccionados 270 archivos PHP, 33 JS y seis HTML propios/plantillas, excluyendo módulos external-ui, bibliotecas PHP identificadas y demos/minificados. Manifiestos leídos como texto, sin ejecutarlos; PHP tokenizado, no búsqueda de variables en comentarios o cadenas.
- Registrados 23 nombres PHP distintos con sufijo ID/IDs, seis con Id/Ids y seis con guion bajo sin superglobales. Se clasifican como estilos y contratos, no como errores de funcionamiento. JS/HTML son candidatos léxicos, no análisis completo del lenguaje ni DOM.
- Contrastadas variantes locales en middleware, ORM, SEO y navegación; preservadas firmas públicas de sesión y compatibilidad, entrada opcional tenant del template, métodos históricos de auth y APIs externas. Reflexión de interfaces PHP confirma gc(max_lifetime), create_sid() y validateId(id).
- Verificados recorridos representativos de usuarios, campañas, multimedia, avisos y CSRF. Se conservan claves, selectores, valores de storage y parámetros configurables; N-02–N-04 quedan parciales con sus límites explícitos.
- Sintaxis del generador comprobada; dos ejecuciones producen evidencia idéntica. Los 309 hashes de fuentes y el hash del generador coinciden con este checkout. Revisión editorial y enlaces del nuevo inventario comprobados; `git diff --check` correcto. No se ejecuta una suite runtime para un inventario sin cambios funcionales.
- No se comprobó una colisión en páginas renderizadas ni se completó el catálogo de APIs públicas/argumentos nombrados. No se aplicó ninguna corrección de nombres por estética. La siguiente propuesta es ampliar el verificador de skills de forma acotada.

## Registro del lote 21

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: verificador PHP, control YAML separado nuevo, pruebas PHP/Python, guía, referencia de mantenimiento, changelog sin publicar e inventario. Ocho archivos; sin cambios de runtime, dependencias Composer, aplicaciones, instalación o publicación del complemento.

- `bin/validate-skills.php` conserva su entrada actual y añade `--root` para una carpeta de skills copiada. Revisa enlaces locales en todos los Markdown, imágenes y definiciones de enlaces; detecta destinos faltantes o fuera del conjunto distribuido mediante rutas resueltas. Ignora bloques de ejemplos, código inline y enlaces externos sin visitarlos. Añade detección de UTF-8 inválido.
- Control `bin/validate-skills-metadata.py` separado con Python 3.9+/PyYAML: parser YAML seguro, claves duplicadas, mapas/tipos, campos conocidos opcionales, referencias de iconos, política booleana y herramientas MCP declaradas. `{}` sigue válido; no se inventa interfaz o dependencia. Campos desconocidos no se certifican y no se añade PyYAML al framework PHP.
- Pruebas PHPUnit del CLI: ocho tests y 93 aserciones correctas. Copia temporal completa de los 66 archivos originales verificada por SHA-256, enlaces faltantes en referencias, imágenes/definiciones, archivo existente fuera de skills, ejemplos sin falsos positivos, rutas con espacios, compañero ausente/presente, UTF-8 y argumentos inválidos.
- Siete tests Python correctos: mapa vacío/futuro, YAML inválido/duplicado, false booleano frente a texto, interfaz opcional, nombre exacto en default_prompt, iconos contenidos, herramientas y archivo ausente/UTF-8. PyYAML ya disponible para la prueba mediante ruta temporal; no se modificó un entorno del usuario para instalarlo.
- Checkout y artefacto del lote 19 comprobados con ambos controles: catorce skills, 61 enlaces locales y catorce archivos de metadata válidos. El artefacto previo conserva su snapshot; no se sobrescribió ni instaló. Maintenance sigue válida en skill-creator.
- Revisión editorial, sintaxis PHP y `git diff --check` correctos. No se ejecuta una suite runtime ajena a esta comprobación. No se validan fragmentos, Markdown completo, contenido remoto, exactitud de recetas ni carga real en clientes; continúan como límites/pendientes distintos.
- El usuario pidió aclarar qué es el verificador; se explicó que detecta defectos de archivos y metadata, sin demostrar que las instrucciones describan correctamente el framework. La siguiente propuesta es inventariar CI/lint y cobertura disponible.

## Registro del lote 22

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: inventario de CI/lint y actualización de este registro. Dos documentos internos; ninguna edición de workflow, Composer, runtime, skills, tests o configuración de servicios.

- Leídos workflow GitHub, scripts Composer, configuración PHPUnit, lint, tests JS/PHP/PowerShell/Python y condiciones de omisión. El CI existente usa Ubuntu y PHP 8.1–8.4 con composer check; no se confundió ausencia inicial de resultados de rg en carpetas ocultas con ausencia de workflow. Se verificó el archivo real y su seguimiento en Git.
- Inventariados 86 archivos Test.php, 21 tests JS, dos pruebas PowerShell y una Python. Se distinguió archivo de prueba, caso, aserción y cobertura de líneas. No se volvió a ejecutar la suite completa ni se consultaron runs remotos para este inventario.
- Registrados checks aún fuera de CI: JS, parser YAML y tests Windows; rutas propias/ejecutable sin extensión fuera del recorrido de lint; servicios y navegador opt-in. CLI habitual con --help no se presenta como prueba sin extensiones.
- Definida incorporación gradual: JS, YAML, lint, Windows y después integraciones con entorno propio. No se enviaron correos, se conectaron servicios reales ni se actualizaron pipelines remotos.
- Enlaces locales, revisión editorial y git diff --check comprobados. El siguiente lote propuesto es automatizar las pruebas JS existentes, conservando todos los pendientes semánticos y de contratos.

## Registro del lote 23

Autorizado por el usuario con «adelante» el 6 de octubre de 2026, después de aclarar qué automatiza CI. Alcance: workflow, changelog sin publicar, estado de cobertura y este registro. Cuatro archivos; sin cambios de runtime, pruebas JS, Composer, skills, proyectos o configuración de clientes.

- Añadido job javascript independiente en Ubuntu, Node 24 con setup-node v7 y cache de paquetes desactivado. Ejecuta los 21 archivos existentes con node --test y reporter TAP. No instala npm/Playwright, genera assets ni habilita envío de correo o servicios externos.
- Ejecutada la suite local en Node 24.13.0: 80 tests, 78 pasan, cero fallos y dos omisiones de navegador. Después se ejecutó el comando exacto del workflow con expansión de glob en Git Bash: mismo resultado. No se presenta esa ejecución Windows como prueba del runner Ubuntu ni como validación de las dos pruebas omitidas.
- Workflow parseado con YAML; job PHP y eventos comparados con HEAD e intactos. El job nuevo no contiene continue-on-error: un fallo del comando genera fallo del job. No se cambiaron reglas de protección de ramas ni se promete que este check sea obligatorio para merge.
- Setup-node/opciones y rama Node contrastados con documentación oficial. Enlaces y revisión editorial comprobados; git diff --check correcto. No se repitió PHPUnit porque el cambio añade exclusivamente una ejecución JS ya existente.
- No se hizo commit/push ni se disparó un pipeline remoto. El primer run de GitHub con este cambio sigue pendiente. YAML de skills, lint completo, Windows, integraciones reales y demás contratos conservan sus lotes pendientes.

## Registro del lote 24

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: job YAML del workflow, changelog sin publicar, estado de cobertura y este registro. Cuatro archivos; sin cambios de runtime, validador, tests, metadata de skills, Composer, aplicaciones o configuración de clientes.

- Añadido job skills-metadata independiente en Ubuntu con setup-python v7 y Python 3.12. Instala PyYAML fijado en 6.0.3 dentro del runner, ejecuta el control existente y sus pruebas unittest. Versiones/opciones contrastadas con fuentes oficiales; no se añadió Python/PyYAML como requisito del paquete PHP.
- Comprobación local con Python 3.12.14 y PyYAML 6.0.3 ya disponibles en el entorno de pruebas: catorce skills válidas, siete tests correctos y ninguna omisión. No se instaló nada en el entorno global del usuario.
- Workflow parseado con YAML: triggers/job PHP conservados frente a HEAD, configuración/comando JS preservados y pasos de metadata comprobados. No contiene continue-on-error. No se cambiaron reglas de merge ni se afirma obligatoriedad por protección de rama.
- Revisión editorial, enlaces y git diff --check correctos. No se repitió PHPUnit/JS porque solo se añadió ejecución del control YAML existente, sin cambios en esas superficies.
- Sin commit/push ni run remoto; resultado del job en Ubuntu pendiente de subir los cambios. Lint, Windows, integraciones reales, semántica de skills y contratos conservan sus lotes pendientes. El siguiente lote propuesto es completar lint de fuentes propias publicadas y del ejecutable sin extensión.

## Registro del lote 25

Autorizado por el usuario con «ok» el 6 de octubre de 2026. Alcance: lint, prueba del comando nueva, guía de comandos, changelog sin publicar, cobertura e inventario. Seis archivos; sin cambios de runtime, workflow, Composer, skills, aplicaciones o configuración de clientes.

- Lint amplía el conjunto a config, resources completo y maintenance, conservando src/bin/tests y los PHP de módulos/bibliotecas ya incluidos. Añade bin/gframe-update explícitamente por ser PHP sin extensión. No recorre vendor/packages, .git ni directorios fuera del conjunto seleccionado.
- Entrada --root para copiar/verificar fixtures, fallo por raíz inexistente/vacía y recuento de archivos. Procesos php -l con argumentos directos, sin shell ni ejecución de archivos PHP comprobados; no se carga bootstrap.
- LintCommandTest: nueve tests, 32 aserciones, sin fallos. Errores reales de sintaxis en configuración, instalador, skeleton, maintenance y ejecutable sin extensión son detectados. Shebang y rutas con espacios funcionan; código con efectos permanece sin ejecutar. Fuentes/tests/módulos previos siguen cubiertos y dependencias/aplicaciones ajenas quedan fuera.
- Lint completo local: 402 archivos válidos con PHP 8.3.35. Sintaxis, documentación, enlaces y revisión editorial comprobados; git diff --check correcto. No se repitió la suite runtime porque el cambio es un comando de comprobación, no comportamiento del framework.
- La ampliación será utilizada por composer check existente cuando se suba el cambio; no se hizo commit/push ni run remoto. Job Windows e integraciones conservan sus pendientes; el siguiente lote propuesto es CI para las dos pruebas PowerShell en temporales.
- Durante el lote el usuario preguntó por el alcance de las skills: se aclaró que cubren tanto aplicaciones sobre GFrame como mantenimiento/desarrollo del framework, con destino y versión efectiva identificados por el orquestador.

## Registro del lote 26

Autorizado por el usuario con «ok» después de pedir recuperar el foco en las skills. Alcance: entrada y referencia de backend, y este inventario. Tres archivos; revisión del orquestador sin editarlo.

- Confirmado que el orquestador distingue framework, aplicación e integración, selecciona especialistas por responsabilidad y conserva las etapas/UX como coordinación condicional. Esta lectura no sustituye la evaluación de ocho casos registrada en el lote 16 ni certifica todas las recetas.
- Comprobada una imprecisión en backend: rutas MVC, clases y carpetas de assets se describían como ubicaciones universales de aplicación. El manifiesto user-admin declara originales runtime, rutas publicadas y destino JS distintos; ModuleRuntime prioriza overrides del proyecto y después fuentes nativas, con clases según namespace.
- Aclarados el origen autorizado, las rutas originales frente a publicadas y la resolución de clases. Evidencia leída en `resources/modules/user-admin/module.php`, `src/GFrame/Modules/ModuleRuntime.php`, `src/GFrame/Modules/ModuleAssetPublisher.php` y `src/routing/Router.php`. Ninguna de esas fuentes de runtime se modifica en este lote.
- CI Windows queda pospuesto tras la corrección de rumbo solicitada. La revisión semántica de backend continúa abierta para el recorrido de operaciones y consumidores; no se declara toda S-02 terminada.
- Verificador del repositorio: catorce skills y 61 enlaces locales válidos. Backend válida en skill-creator; `git diff --check` correcto. Verificación por lectura de contratos y controles de instrucciones; no se repite una suite runtime para estas aclaraciones.

## Registro del lote 27

El usuario pidió mantener la lista de pendientes, guardar el trabajo acumulado y revisar ramas antiguas. Se organiza el contenido existente en commits de runtime, skills/documentación y herramientas, sin ampliar funcionalidades ni ejecutar un push.

- Repetición dirigida antes del cierre: AuditRegressionTest, MediaRuntimeTest, SkillsValidatorTest y LintCommandTest; 26 pruebas y 237 aserciones correctas. Catorce skills y 61 enlaces válidos; `git diff --check` correcto. Las pruebas anteriores conservan sus límites e integraciones pendientes.
- Retirada la rama local `codex/integracion-main-local`, que apuntaba al mismo commit de partida que main. Conservada `codex/auditoria-reconstruccion`, con commits divergentes; no se eliminaron ramas remotas ni se equiparó divergencia con trabajo perdido.
- Lista activa explicitada arriba. El siguiente lote sigue siendo S-02; no se retoma CI Windows, MCP ni las mejoras de contratos pospuestas.

## Limpieza de ramas autorizada

El usuario autorizó retirar las ramas históricas después de comprobar su integración selectiva y conservar un respaldo. Bundle completo verificado en `C:/Users/Juank de Gorvet/Downloads/GFrame-historial-ramas-20261006-173201.bundle`, incluidos los HEAD local y remoto divergentes.

- Eliminadas las tres ramas remotas `codex/auditoria-reconstruccion`, `codex/reconstruccion-documentacion` y `codex/reconstruccion-unificada`, mediante operación atómica condicionada a sus hashes comprobados. Retirada también la rama local de auditoría.
- Confirmados main local y main remoto como únicas ramas restantes. No se enviaron los commits nuevos de main ni se modificó el código. S-02 sigue siendo el siguiente pendiente activo.

## Registro del lote 28: cierre del recorrido backend

- Contrastados servicio, modelo, controlador, Router y JS de Mi cuenta y user-admin. Backend ahora explicita `unauthorized/forbidden`, estados ORM frente al envelope HTTP, errores de negocio con HTTP 200 en AJAX y resolución de parciales runtime con limpieza del buffer.
- No se cambian productores, consumidores ni códigos. La receta existente de contraseña ya comprobaba retornos sin message y mensajes añadidos por el controlador; se conserva sin duplicarla ni crear otra capa.
- Pruebas dirigidas de servicios/UI de ambas áreas: 27 pruebas y 155 aserciones correctas. La comprobación es de contratos locales; no acredita navegador, peticiones CSRF completas ni Redis real.
- S-02 queda revisada para los contratos y recorridos representativos del inventario, con las limitaciones anteriores. Nuevos endpoints requieren su comprobación concreta; no se certifica cada ruta del framework por extrapolación.

## Registro del lote 29: contenido y búsqueda

- COV-09 dispone ahora de una receta compartida desde backend, admin y public: Markdown por perfil, ciclo de editor antes de serializar/reemplazar, sanitización explícita del servidor y ranking de filas previamente autorizadas. No se crea otra skill ni un almacenamiento de artículos.
- Contrastados los tres manifiestos, servicios PHP, vista/meta del editor y APIs JS. El primer diagnóstico aislado no registró el módulo lexical-search y no pudo cargar su servicio; se corrigió el montaje de la prueba con ModuleRuntime y se documentó esa precondición, sin atribuir el fallo al framework.
- Tres snippets ejecutados con fuentes reales, contenido ficticio y selección autorizada vacía; correctos. Pruebas existentes: 23 PHP/89 aserciones y tres JS, sin fallos. Las pruebas JS utilizan entornos simulados y no certifican interacción TinyMCE en navegador.
- Verificador: catorce skills y 66 enlaces locales. COV-09 queda cubierto en instrucciones y recetas locales; autorización HTTP completa, persistencia del proyecto y QA de navegador se comprueban en el proyecto que integre estas capacidades.

## Registro del lote 30: componentes y nomenclatura

- COV-08/COV-11 reciben una referencia compartida de componentes y carga selectiva: readiness de frontend-core, límites de GFSelect/GFTable, alerts/iconos y lectura de la guía/manifiesto de cada vendor necesario. No se añaden dieciséis skills ni se actualizan bibliotecas. Doce pruebas JS existentes correctas; no equivalen a QA de navegador.
- Referencia común de nombres enlazada desde las once entradas que faltaban, sin copiar sus reglas. Frontend-admin fija kebab-case para IDs/clases de componentes nuevos, preservando selectores actuales y contratos externos.
- Catálogo AST de 272 fuentes PHP propias, 150 tipos y 763 declaraciones públicas; parámetros, referencias/variádicos y padres/traits declarados. Las tres variantes públicas identificadas se preservan por compatibilidad. No se ejecutan fuentes inspeccionadas ni se cambia Composer/core.
- N-03/N-04 mantienen el límite de sus muestras: no se ha comprobado cada transporte ni unicidad de IDs en todas las páginas renderizadas. Esas comprobaciones pertenecen a cambios concretos de consumidores/pantallas; ninguna mezcla de estilos se presenta como fallo de funcionamiento.

## Aclaración de paginación y búsqueda

El usuario preguntó si estaban documentadas y cómo se usan/crean. Verificadas las guías actuales de ORM, helpers PHP, frontend-core y lexical-search; la guía léxica ya incluye instalación, PHP/JS y herencia. Añadido un acceso explícito a paginación en el índice, sin crear una guía duplicada.

- La referencia admin explica las APIs existentes de PaginationHelper/creaPaginacion, total/página frente a controles, callback, guardas para cero/una página y límites de IDs fijos. Una prueba JS existente correcta.
- La receta léxica añade cálculo de metadatos y slice después de rank sobre el conjunto autorizado. El tamaño de página debe ser positivo y acotado; no se presenta ese array como un envelope HTTP completo.

## Registro del lote 31: carga real disponible

- Empaquetado posterior con 68 archivos de skills y 79 enlaces, versión de prueba 0.0.0-review.1. Codex CLI 0.159.2 registra el marketplace, instala el plugin portable y lo habilita en CODEX_HOME temporal; sin modificaciones globales, bootstrap de aplicación ni turnos de modelo.
- App-server consultado mediante initialize/skills/list conforme al esquema generado por ese mismo binario: catorce skills de la caché del complemento, habilitadas, sin errores. Namespace confirmado y documentado en orquestador/guía. No fue necesario añadir otro manifiesto o cambiar el generador.
- Los 68 hashes de caché coinciden con el artefacto, sus 79 enlaces y catorce archivos YAML son válidos. [Evidencia persistida](carga-complemento-codex-20261006.json). La prueba comprueba descubrimiento, no obediencia automática de todas las instrucciones ni carga de la interfaz desktop.
- Claude --version falla por falta del módulo cli.js. La comprobación queda pendiente por entorno; no se instala/repara software del usuario para ocultar ese límite.
- Ejecutado además el snippet de ranking/paginación con página solicitada fuera de rango y resultado vacío; correcto. No se modifica la semántica del ORM o los helpers existentes.
- Prueba del empaquetado posterior: 225 comprobaciones correctas. Las catorce skills pasan skill-creator, enlaces/metadata de checkout y caché son válidos y la revisión editorial no detecta errores críticos; `git diff --check` correcto. Cambios guardados por bloques sin push de main.

Cambio de alcance posterior: el usuario retira Claude de los pendientes. Se conserva la evidencia histórica y el adaptador existente, sin reparar el cliente ni exigir su validación para continuar.

### Lote 32: resolución de los pendientes autorizados

- K-01–K-04 implementados y documentados, incluidas transiciones de aplicaciones y límites de entrega/reanudación. Commits separados `faaa891`, `ce36e75` y `479db8a`; no se modificaron proyectos instalados.
- N-03 ampliado al recorrido de los ocho controladores MVC propios. N-04 comprobado en nueve composiciones renderizadas y en biblioteca/picker simultáneos. La colisión multimedia comprobada se corrigió en `c31f221`, conservando IDs de la biblioteca y payloads. Prueba PHP más Edge real sin red; controles de filtros/paginación independientes.
- Suite PHP con DSN MySQL de pruebas: 444 pruebas, 4437 aserciones, cero fallos y tres omisiones. Las dos omisiones de instalación/preflight MySQL se ejecutaron después con su flag opt-in: dos pruebas y quince aserciones correctas. Queda Nginx/PHP-CGI sin binario Nginx identificado. MariaDB local es 10.4.24; no equivale a MySQL 8.
- Revisión dirigida de la última protección de hash, recuperación de cola y verificador: quince pruebas y 144 aserciones correctas. El verificador de copias compara ahora contra la fuente canónica, sin fijar un número histórico de enlaces.
- JavaScript con Edge: ochenta pruebas pasan de ochenta y una. La restante falla al navegar al servidor temporal con `ERR_NETWORK_ACCESS_DENIED`; es un bloqueo del navegador/entorno y se conserva como no verificada. No se cambian políticas del navegador para ocultarlo. La prueba multimedia nueva y el catálogo del instalador sin red sí pasan.
- Catorce skills pasan skill-creator y metadata; 79 enlaces válidos. Empaquetado con 225 comprobaciones correctas. Snapshot `0.0.0-review.2` instalado únicamente en CODEX_HOME temporal; app-server descubre las catorce skills habilitadas, sin errores, y los 68 hashes coinciden. [Evidencia actualizada](carga-complemento-codex-contratos-20261006.json). Sin turnos de modelo ni modificación de plugins globales.
- Lint final de 408 archivos correcto, incluida la sintaxis/ejecución de las fuentes añadidas; revisión editorial sin errores críticos y diff sin errores. El cierre local no incluye push, publicación, actualización de aplicaciones, SMTP/Redis reales, MySQL 8 ni certificación de selección automática/visual desktop. Esas comprobaciones dependen del proyecto o cliente efectivo; no queda otro cambio de código demostrado dentro de este inventario.

Fuentes locales para contrastar los hallazgos:

- [Skills y su instalación](../docs/skills.md), [instalador](../bin/install-skills.ps1) y [verificador](../bin/validate-skills.php).
- [Reglas backend](../skills/gframe-backend/SKILL.md), [fuentes de tenant](../skills/gframe-backend/references/framework-conventions.md) y [ejemplo de catches ORM](../skills/gframe-orm-models/references/model-boundaries-and-contracts.md).
- [Referencias auth](../skills/gframe-auth-access/references/authentication-contracts.md) y [workflow de mantenimiento](../skills/gframe-framework-maintenance/references/repository-workflow.md).
- [Nombres de usuario en HTML](../resources/modules/user-admin/application/app/views/user-admin/_userList.php), [envío desde JS](../resources/modules/user-admin/javascript/user-admin.js) y [recepción PHP](../resources/modules/user-admin/application/app/controllers/user-admin/UserAdminController.php).
- [Variantes Id en SEO](../src/seo/SitemapDataProvider.php), [middleware](../src/middleware/Middleware.php) y [API pública de auth](../docs/autenticacion.md).
- [Mapa de capacidades](capability-map.md) y [revisión del código ya realizada](revision-auditoria-20261006.md).
