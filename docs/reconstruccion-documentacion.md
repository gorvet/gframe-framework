# Reconstrucción de la documentación de GFrame

Este registro conserva las decisiones de la reconstrucción original. La integración local posterior actualiza Opis Closure a la rama 4 e incorpora la protección antispam de Mail; para esos contratos vigentes, consulta [Async](async.md), [Correo y plantillas](mail.md) y [Política de dependencias](dependencias.md).

Esta rama reconstruye la documentación de GFrame tomando **el código como fuente de verdad**. La documentación existente se reutiliza cuando coincide con el runtime, se corrige cuando quedó obsoleta y se reorganiza cuando el problema es de aprendizaje o descubribilidad.

## Objetivo

La documentación debe permitir que un desarrollador construya una aplicación real sin tener que conocer de antemano la implementación interna del framework.

La pregunta principal deja de ser «¿qué clases existen?» y pasa a ser «¿cómo hago esta tarea con GFrame?».

Ejemplos:

- crear una pantalla `/productos`;
- listar y guardar datos;
- actualizar un fragmento por AJAX;
- proteger una acción con autenticación o permisos;
- subir y relacionar archivos;
- enviar correo o notificaciones;
- sacar trabajo fuera de la petición web;
- programar una tarea;
- consumir una API externa;
- instalar, extender o personalizar un módulo.

## Principios

1. **El código manda.** Toda afirmación debe poder justificarse con el runtime, instalador, módulos o pruebas actuales.
2. **No confundir convención con obligación.** La estructura recomendada del proyecto y el comportamiento impuesto por el runtime se documentan por separado.
3. **Separar aprendizaje y referencia.** Las guías prácticas enseñan recorridos completos; las páginas especializadas documentan contratos y casos límite.
4. **No documentar cada clase interna como API pública.** Primero se clasifica como API de proyecto, punto de extensión, compatibilidad legacy o infraestructura interna.
5. **Conservar lo que ya está bien.** Rutas, Render, Middleware, ORM, Sesiones, Media, Notificaciones, Mail y Cron ya contienen bastante referencia útil.
6. **Hacer visibles las capacidades.** Una capacidad técnicamente documentada pero ausente del recorrido principal sigue siendo difícil de descubrir.
7. **Usar recorridos verticales.** Los conceptos se introducen dentro de funcionalidades completas.
8. **Señalar compatibilidad legacy.** Los wrappers y contratos históricos no deben enseñarse como primera opción cuando existe una API preferida.

## Capas de la nueva documentación

### Aprender GFrame

Debe explicar en orden:

- qué es GFrame;
- cómo queda un proyecto instalado;
- cómo viaja una petición;
- qué responsabilidad tiene cada carpeta;
- cómo crear una funcionalidad completa;
- cómo evolucionarla con AJAX, permisos, multimedia, correo, notificaciones y procesos en segundo plano.

### Guías por tarea

Ejemplos:

- crear una página;
- crear un CRUD;
- crear un listado AJAX;
- proteger una acción;
- consumir una API externa;
- trabajar con archivos;
- enviar correo;
- elegir Async o Cron;
- instalar o personalizar un módulo.

### Referencia técnica

Aquí permanecen Router, Render, Middleware, ORM, SessionRuntime, módulos, Mail, Media, Notifications y demás contratos detallados.

## Mapa de capacidades detectadas

La auditoría del código confirma, entre otras, estas áreas:

- bootstrap y configuración;
- routing web, AJAX, API, webhook, system y SSE;
- render, vistas, templates, meta y footer;
- middleware, autenticación, roles y permisos;
- sesiones `database`, `redis` y `native`;
- ORM, conexiones y dialectos;
- módulos, catálogo, dependencias, publicación y runtime;
- multimedia;
- correo y plantillas;
- notificaciones, transportes, colas y campañas;
- ejecución asíncrona;
- cron y tareas persistentes;
- heartbeat;
- cliente HTTP saliente;
- cifrado y sanitización HTML;
- SEO, sitemap, robots, JSON-LD y `llms.txt`;
- WordPress headless;
- utilidades PHP y frontend;
- instalación, perfiles y actualización;
- gestión de errores y respuestas por canal.

El inventario de `resources/modules` ya enumera los módulos existentes. El problema documental principal no era contar carpetas, sino explicar **qué capacidad resuelve cada pieza y cómo se integra en una aplicación**.

## Backlog vivo

Estados:

- **cubierto**: existe una guía o referencia suficientemente útil para esta fase;
- **parcial**: existe, pero falta integración, precisión o descubribilidad;
- **corregido**: existía una contradicción concreta y ya se saneó en esta rama;
- **en auditoría**: falta cerrar la comparación código/documentación.

| ID | Área | Estado | Prioridad | Resultado / siguiente acción |
| --- | --- | --- | --- | --- |
| DOC-001 | Guía real de desarrollo | cubierto | P0 | `guia-desarrollo.md`: recorrido URL → ruta → middleware → controller → service → model/ORM → view/template → respuesta. |
| DOC-002 | Índice y navegación | cubierto | P0 | `index.md` reorganizado por tareas y recorridos; conserva referencia técnica y expone referencias útiles antes huérfanas. |
| DOC-003 | Primera funcionalidad completa | cubierto | P0 | `tutorial-productos.md`: CRUD vertical con web, AJAX, partial, ORM y permisos. Declara los prerrequisitos reales de Auth, `admin-panel` y stack frontend. |
| DOC-004 | Services | cubierto | P0 | Introducidos en guía y tutorial como capa opcional para lógica reutilizable. |
| DOC-005 | CRUD + AJAX | cubierto | P0 | Tutorial muestra carga web inicial, mutaciones AJAX, CSRF, permisos y recarga de fragmento. |
| DOC-006 | Auth + permisos + sesiones | cubierto | P0 | `identidad-autorizacion.md` conecta autenticación, identidad, middleware, permisos, tenant y reglas de recurso. |
| DOC-007 | Módulos | cubierto | P0 | `modulos-en-aplicacion.md` separa capacidad instalable, runtime MVC, componente frontend y funcionalidad propia. |
| DOC-008 | Helpers PHP | cubierto | P1 | `helpers-php.md` distingue helpers/clases globales vigentes de funciones wrapper legacy. |
| DOC-009 | Cliente HTTP saliente | cubierto | P1 | `http-client.md`; se distingue claramente de API entrante. |
| DOC-010 | Cifrado | cubierto | P1 | `encryption.md`: AES-256-GCM, claves, rotación y límites reales. |
| DOC-011 | Async | cubierto | P1 | Enlazado desde índice y comparado con Cron/colas en `procesos-segundo-plano.md`. |
| DOC-012 | Cron | cubierto | P1 | Conserva referencia y ahora forma parte de la guía de decisión. |
| DOC-013 | Media | corregido/cubierto | P1 | `media-library.md` verificado contra servicio y procesador; retirada de defaults la clave residual `media.max_upload_bytes` y fijada la fuente real mediante test. |
| DOC-014 | Notificaciones | cubierto | P1 | Inbox, cola, transportes, email y campañas contrastados con `NotificationService`, cola, `CampaignService` y `EmailQueueProcessor`. |
| DOC-015 | Mail | corregido/cubierto | P1 | `mail.md` contrastado con `MailService`; retirado el contrato ficticio de rate limiting que el runtime no implementa. |
| DOC-016 | SEO | corregido/cubierto | P1 | `seo.md` y `json-ld.md` verificados; `Robots` ya no anuncia un sitemap desactivado y el comportamiento queda cubierto por test. |
| DOC-017 | Autoload de aplicación vs módulos | cubierto | P1 | `autoload-proyecto.md` explica Bootstrap basename map frente a ModuleRuntime namespaced overrides y aclara que classmap/global no equivale a legacy. |
| DOC-018 | Instalador y perfiles | cubierto | P1 | `perfiles-instalacion.md` verificado contra `profiles.php`, `InstallationProfileCatalog`, `ProjectInstaller`, `ProjectConfigWriter` y `SchemaInstaller`. |
| DOC-019 | `src/database/ORM_GUIDE.md` | corregido | P0 | Sustituido por nota interna actual; ya no enseña `core/database` ni `config/Config.php`. |
| DOC-020 | README / `composer new` | corregido | P1 | README aclara que `composer new` es un script del repo y no un comando nativo de Composer. |
| DOC-021 | Mapa principal de capacidades | cubierto | P0 | Cerrada la pasada sobre Media, Notifications, Mail, Heartbeat, WordPress Headless y errores/respuestas. |
| DOC-022 | Compatibilidad legacy | cubierto | P1 | Clasificados classmap vigente, wrappers de `LegacyCompatibility.php`, fallbacks heredados de configuración y contratos de compatibilidad concretos. No se detectó una red general de aliases/deprecations oculta. |
| DOC-023 | Serialización Async | corregido | P0 | `async.md`, `mail.md` y `dependencias.md` reflejan `Opis\Closure\SerializableClosure` / `opis/closure:^3.7`. |
| DOC-024 | Autoload y namespaces | corregido/cubierto | P0 | Documentado que el proyecto generado no trae PSR-4 general `App\`; clases normales y overrides de módulos siguen contratos distintos. |
| DOC-025 | Heartbeat | cubierto | P1 | `heartbeat.md` verificado contra `HeartbeatMaster`: intervalos, visibilidad, force, sesión, handlers y contrato por canal. |
| DOC-026 | WordPress Headless | cubierto | P1 | `wordpress-headless.md` verificado contra contrato BridgeFrame 2.0, HTTPS, token, envelope y mapeo de errores. |
| DOC-027 | Errores y respuestas | cubierto | P1 | `errores.md` verificado contra `ErrorResponder` y `ErrorHandler`, incluidos canales AJAX/API/webhook/SSE/system y producción/debug. |

## Trabajo realizado en esta rama

### Columna vertebral nueva

- `docs/guia-desarrollo.md`
- `docs/tutorial-productos.md`
- `docs/identidad-autorizacion.md`
- `docs/modulos-en-aplicacion.md`
- `docs/procesos-segundo-plano.md`
- `docs/autoload-proyecto.md`

### Capacidades antes poco descubribles

- `docs/http-client.md`
- `docs/encryption.md`
- `docs/helpers-php.md`

### Navegación y onboarding

- `docs/index.md` reorganizado por tareas.
- `README.md` reconstruido para separar creación de proyecto de instalación del paquete.
- `docs/tutorial-productos.md` declara ahora que `template('admin')` depende de `admin-panel` y que el ejemplo protegido requiere Auth/permisos. Los perfiles `managed`, `intranet` y `saas` proporcionan esa base; `static` no.
- `navegacion-publica.md`, `heartbeat-client.md`, `dependencias-frontend.md` y `limpieza.md` quedaron enlazados desde sus secciones naturales del índice en lugar de permanecer como referencias útiles pero huérfanas.

### Contradicciones saneadas

- `src/database/ORM_GUIDE.md`: rutas/configuración antiguas retiradas.
- `docs/async.md`: serialización corregida a Opis Closure.
- `docs/mail.md`: dependencia de Async corregida a `opis/closure:^3.7`.
- `docs/dependencias.md`: retirada la referencia antigua a Laravel Serializable Closure / `PHPAsync`; documentado `opis/closure` + `Async`.
- `docs/mail.md`: eliminado el supuesto soporte `rate_limit`, `mail.rate_limit`, `MAIL_RATE_LIMIT_*` y `mail_rate_*`; `MailService` no implementa ese contrato.
- `docs/errores.md`: el `noindex` se atribuye al metadato real de `error-pages`, no al status HTTP como mecanismo generador del meta robots.
- `docs/limpieza.md`: retirada una referencia residual a archivos de límites de correo inexistentes.
- `docs/helpers-php.md` y `docs/autoload-proyecto.md`: corregida la ambigüedad entre clases globales classmapped vigentes y compatibilidad legacy.

### Instalación y perfiles verificados

- `resources/install/profiles.php` define de forma explícita `static`, `managed`, `intranet` y `saas`.
- `InstallationProfileCatalog` normaliza perfiles y filtra módulos opcionales incompatibles.
- `ProjectInstaller` resuelve defaults + módulos del perfil + opcionales + dependencias, instala esquemas y escribe el lock.
- `ProjectConfigWriter` genera `config/app.php` y `.env`, incluido `SESSION_DRIVER` según exista o no base de datos.
- `SchemaInstaller` aplica tenancy antes de auth y después los esquemas de módulos resueltos.
- `docs/perfiles-instalacion.md` coincide con ese comportamiento en esta revisión.

### Subsistemas contrastados en la pasada final

- **Media:** `MediaLibraryService` y `MediaProcessor` coinciden con la referencia sobre scopes, carga, hotlink, Base64, variantes, relaciones y cuota.
- **Notifications:** el flujo inbox/cola/transportes y campañas coincide con `NotificationService`, `CampaignService` y los procesadores de cola.
- **Notifications Email:** los reintentos, backoff y estados documentados coinciden con `EmailQueueProcessor`.
- **Mail:** los cuatro métodos públicos principales, opciones reales y semántica de Async se contrastaron con `MailService`.
- **Heartbeat:** registro de canales, intervalo mínimo, visibilidad, `force`, contexto de sesión y resolución de handlers coinciden con `HeartbeatMaster`.
- **WordPress Headless:** contrato BridgeFrame 2.0, HTTPS obligatorio, bearer token, envelope y códigos se contrastaron con `WordPressClient`.
- **Errores:** aliases, HTTP codes, vistas web y respuesta por canal se contrastaron con `ErrorResponder` y `ErrorHandler`.

### Compatibilidad y residuos históricos

- Composer classmapea deliberadamente múltiples áreas globales del core; una clase global no se considera legacy por ese solo hecho.
- `LegacyCompatibility.php` concentra wrappers globales que sí deben tratarse como compatibilidad y no como estilo recomendado nuevo.
- `config/bootstrap.php` y `core/Config.php` siguen soportados como fallbacks heredados; el patrón recomendado actual continúa siendo `config/app.php` con los defaults del paquete.
- `LegacyConfigBridge` mantiene constantes históricas para consumidores existentes a partir de la configuración estructurada.
- no se detectó una red general de `class_alias()` o marcadores `deprecated` que constituya una capa adicional de migración.
- se revisaron `src/heartbeat/README.md`, `src/seo/SCHEMA_GUIDE.md`, `AGENTS.md`, `CONTRIBUTING.md`, `SECURITY.md` y el contexto histórico del `CHANGELOG.md`.

## Hallazgos de runtime corregidos

La auditoría encontró dos desacoples de runtime además de los problemas documentales. Ambos se corrigieron con cambios mínimos y tests dedicados.

### Robots y sitemap

`Robots::render()` ahora añade `Sitemap: <site_url>/sitemap.xml` únicamente cuando `SEO_ENABLE_SITEMAP_XML` está habilitado o no está definido. De esta forma coincide con la condición que registra `/sitemap.xml` en `routes_system.php`.

`MetaSeoTest` comprueba expresamente que un sitemap desactivado no sea anunciado por `robots.txt`.

### Fuente del límite multimedia

Se retiró `media.max_upload_bytes` de `config/defaults.php` porque el runtime no lo consumía. La fuente del límite por archivo permanece en `resources/modules/media-library/config/media.php`, leída por `MediaProcessor::getMaxUploadBytes()`.

`ConfigurationDefaultsTest` fija ese contrato y evita que vuelvan a existir dos fuentes aparentes para el mismo límite.

## Estado de esta pasada

La auditoría documental y los hallazgos de runtime detectados durante ella quedan cerrados en esta rama:

- las capacidades principales tienen recorrido práctico o referencia descubrible;
- el bloque legacy está clasificado;
- las referencias útiles que estaban fuera de navegación se incorporaron al índice;
- los contratos documentales ficticios o antiguos encontrados fueron retirados o corregidos;
- los dos desacoples de runtime confirmados fueron corregidos y cubiertos por pruebas;
- `docs/hallazgos-integracion.md` conserva el historial necesario para comparar con otras ramas sin reintroducir errores por conflicto.

## Convivencia con otras ramas

Esta reconstrucción se desarrolla en `codex/reconstruccion-documentacion` para no interferir con cambios simultáneos de código o documentación.

Cuando otra rama cambie APIs, rutas, módulos o comportamiento documentado, la fusión debe hacerse comparando primero el código resultante. No se resolverá un conflicto documental escogiendo automáticamente «la versión más nueva»: después de integrar, **el código vuelve a ser la fuente de verdad**.

## Criterio de finalización

La reconstrucción estará suficientemente cerrada cuando un desarrollador nuevo pueda:

1. instalar GFrame;
2. entender la estructura del proyecto;
3. crear una funcionalidad completa sin leer primero la implementación del framework;
4. encontrar la capacidad adecuada para autenticación, permisos, AJAX, archivos, correo, notificaciones, tareas, integraciones y SEO;
5. pasar de una guía práctica a la referencia técnica cuando necesite detalles;
6. distinguir API recomendada, compatibilidad legacy e infraestructura interna;
7. saber qué instala cada perfil y qué sigue siendo responsabilidad del proyecto.
