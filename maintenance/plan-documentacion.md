# Plan de documentación de GFrame

Documento de trabajo, no forma parte de la ayuda pública. Inventario comprobado contra `docs/`, `resources/modules/` y `src/` el 4 de octubre de 2026.

## Estados y criterio

La presentación pública se organizará en tres capas, sin intercalar componentes entre ellas:

1. **Núcleo PHP del framework**: bootstrap, arquitectura, configuración, Router, middleware, Render, ORM y demás servicios internos. Explicar su recorrido y responsabilidades antes de presentar ampliaciones.
2. **Módulos de ampliación del núcleo**: módulos PHP o híbridos PHP/JavaScript que añaden funcionalidades de aplicación. Su clasificación depende de la responsabilidad real, no solo de que contengan archivos PHP o JavaScript.
3. **Módulos de vista y frontend**: utilidades y componentes de interfaz. Dentro de esta capa, separar los desarrollos de GFrame de las bibliotecas externas y sus puentes de integración.

Las guías transversales de instalación, actualización y despliegue acompañan estas capas sin convertirlas en categorías mezcladas. Clasificar cada componente contra el código antes de modificar el catálogo público. El nombre referido como «PagoRUTIL» queda por identificar, junto con la referencia anterior a «Power Utility»; no asumir que ambos nombres corresponden al mismo componente.

- Revisada: guía revisada en esta ronda. No implica que todos sus ejemplos y contratos estén cubiertos; los requisitos añadidos después se indican aparte.
- Existente, pendiente: existe un documento, pero no se ha comprobado todavía su cobertura ni su adecuación para desarrolladores. No equivale a documentación completa.
- Sin guía propia: no hay un documento dedicado en `docs/`; puede haber menciones en otras guías.
- Por identificar: falta confirmar a qué componente se refiere el nombre solicitado.

Cada tema se termina antes de iniciar el siguiente. Al cerrar una fila, registrar la guía, los ejemplos comprobados y los límites explicados. No marcar una fila completa solo porque el archivo exista.

## 1. Introducción y primeros pasos

| Tema | Documento actual | Estado / trabajo pendiente |
| --- | --- | --- |
| Introducción | Portada de la web | Revisión final pendiente: reducir verbosidad, explicar ventajas concretas e incluir SaaS y multitenancy según capacidades reales. Evitar definición por negación y retirar «Una base para desarrollar, no un sitio terminado» |
| Instalación | `docs/instalacion.md` | Revisada |
| Configuración | `docs/configuracion.md` | Revisada |
| Arquitectura y carpetas | `docs/arquitectura.md` | Revisada; capas, carpetas, recorrido y ejemplo de portada |
| Primera pantalla desde cero | Sin guía propia | Crear tutorial con ruta, controlador, vista y metas |

## 2. Núcleo: orden de ejecución

| Tema | Documento actual | Estado / trabajo pendiente |
| --- | --- | --- |
| Recorrido de una petición y bootstrap | `docs/arquitectura.md` | Revisado en su guía principal; ramas web y canales directos comprobadas contra Router y Render |
| Router y Route Builder | `docs/rutas.md` | Revisada; declaraciones, canales, parámetros, inferencias, módulos, guardas y límites. Ejemplos PHP comprobados por RoutingDocumentationTest |
| Render | `docs/render.md` | Revisada; datos, constructores, montaje HTML, metas, templates, partes, footer y límites. Ejemplos y contratos comprobados con RenderDocumentationTest, MetaSeoTest y ModuleRuntimeTest |
| Middleware | `docs/middleware.md` | Revisada; controles disponibles, orden, sesión, CSRF, contexto, tenancy y límites de ampliación. Contratos comprobados con MiddlewareDocumentationTest y pruebas existentes de acceso API, sesiones y permisos |
| ORM y dialectos | `docs/orm.md` | Revisada; modelos, consultas, resultados, escrituras, relaciones, conexiones, transacciones y límites de dialectos. Ejemplos ejecutados con SQLite en memoria; expresiones MySQL/SQLite comprobadas por OrmLikeDialectTest. No se afirma una ejecución integral de esta ronda en servidor MySQL |
| Metas de grupo y vista | `docs/meta.md` | Revisada; organización global/template/grupo/vista, combinación, datos dinámicos, assets y resolución de módulos |
| SEO automático | `docs/seo.md` | Revisada; política global y por ruta unificada en el core, metas descriptivas y JSON-LD, endpoints, URLs dinámicas y migración. SeoPolicyTest comprueba bloqueo global, debug, robots permanente, exclusiones HTML/sitemap/llms y ausencia de sobreescritura desde metas |
| Schema y JSON-LD | `docs/json-ld.md` | Guía propia con catálogo real, campos, ejemplos, composición y validación. Presets compuestos corregidos y comprobados con protección contra ciclos. Conservación de cero y false cubierta por pruebas de regresión; no queda pendiente ese filtro |
| Multilenguaje | `docs/multilenguaje.md` | Revisada; configuración, prefijos, traducciones del proyecto, vistas compartidas, metas/JSON-LD, enlaces, canales directos y límites reales de sitemap/hreflang. Ejemplos y reconocimiento de idioma comprobados por LanguageDocumentationTest |
| Autenticación | `docs/autenticacion.md` | Revisada; contratos MVC, identidad, registro, login, recuperación, correo encolado y ampliación. Ejemplos ejecutados con SQLite por AuthenticationDocumentationTest. Caducidad de verificación resuelta; AuthVerificationTokenTest comprueba tokens vigentes y caducados (6 de octubre de 2026: 2 pruebas, 6 aserciones) |
| Sesiones | `docs/sesiones.md` | Revisada: identidad, login, actualización, logout, drivers, revocación, caducidad renovable, cookies y CSRF. Ejemplos y contratos comprobados con pruebas de sesiones |
| Permisos | `docs/permisos.md` | Revisada: plantillas, rutas, autorización global y tenant, administración de roles, excepciones, jerarquía y creación transaccional de membresías. Contratos y ejemplos comprobados |
| Acceso API | Apartado de `docs/rutas.md`; referencia `docs/api-access.md` | Integrado en Rutas: consumidores, Bearer, scopes explícitos, aislamiento tenant, CORS, respuestas y límites del proveedor |
| Correo y envío en segundo plano | `docs/mail.md` | Revisada: SMTP, métodos síncronos/asíncronos, opciones, resultados, plantillas y tema, antispam, feedback y límites del worker |
| Async | `docs/async.md` | Revisada: creación, datos capturados, servicios, bootstrap CLI, configuración, fallos, seguridad y límites sin cola persistente |
| Heartbeat del servidor | `docs/heartbeat.md` | Revisada: registro por herencia y callable, contexto, intervalos, ejecución forzada, errores y límites |
| Errores del núcleo | `docs/errores.md` | Revisada: contratos HTTP y negocio, canales, handler global, personalizaciones runtime y assets gestionados |
| Sanitización HTML | `docs/html-sanitizer.md` | Revisada: integración backend, etiquetas, atributos, URLs, contextos de salida y límites de ampliación |
| Limpieza y demás utilidades internas | `docs/limpieza.md` | Inventario documentado de caducidad y limpieza existentes. Falta funcional: herramienta general de limpieza del core; no existe un comando unificado |

## 3. Módulos del framework

Los identificadores de esta tabla son los actuales del código, no una decisión sobre la futura convención de nombres visibles.

| Módulo | Documento actual | Estado / trabajo pendiente |
| --- | --- | --- |
| admin-panel | `docs/panel-administrativo.md` | Revisada; comprobar cobertura de ampliación solicitada después |
| user-admin | `docs/user-admin.md` | Revisada |
| self-account | `docs/self-account.md` | Revisada: campos HTTP, contratos servicio/controlador, contraseña, desactivación, perfil y ampliación mediante herencia e inyección |
| auth-ui | `docs/auth-ui.md` | Revisada: rutas y campos, formularios, redirecciones, correo, personalización por herencia, metas y seguridad |
| frontend-core | `docs/frontend-core.md` | Existente, pendiente; utilidades y contratos de formularios |
| alerts | `docs/alerts.md` | Existente, pendiente; swalAlert y alertToast, no inbox |
| gfselect | `docs/gfselect.md` | Existente, pendiente; opciones, eventos y ejemplos |
| gf-table | `docs/gf-table.md` | Existente, pendiente; datos, eventos y ejemplos |
| gframe-icons | `docs/gframe-icons.md` | Existente, pendiente |
| password-utils | `docs/password-utils.md` | Existente, pendiente |
| error-pages | `docs/errores.md` | Existente, pendiente; vistas y ampliación |
| heartbeat-client | `docs/heartbeat-client.md` | Existente, pendiente; conexión con canales del servidor |
| cron-runner | `docs/cron-runner.md` | Existente, pendiente |
| media-library | `docs/media-library.md` | Revisada: ámbitos y almacenamiento público, rutas y campos HTTP, procesador, subidas, selectores externos, relaciones y validación backend de IDs. Pruebas del módulo y ejemplo de galería comprobados |
| notifications | `docs/notificaciones.md` | Revisada: creación inmediata, campos, ámbitos, consultas, estado, modal AJAX, transportes y ampliación por herencia. Ejemplos ejecutados con SQLite; correo y campañas se revisan por separado |
| notifications-email | `docs/notifications-email.md` | Revisada: payload, encolado, transporte síncrono dentro de workers, plantillas, cron, reintentos, estados y límites. Contratos comprobados con pruebas del transporte y ejemplos de documentación |
| notification-campaigns | `docs/notification-campaigns.md` | Existente, pendiente |
| markdown | `docs/markdown.md` | Existente, pendiente |
| lexical-search | `docs/lexical-search.md` | Existente, pendiente |
| rich-text-editor | `docs/rich-text-editor.md` | Existente, pendiente; distinguir integración propia de TinyMCE |
| wordpress-headless | `docs/wordpress-headless.md` | Existente, pendiente |
| wordpress-styles | Sin guía propia | Comprobar alcance y documentar el puente de estilos |
| «Power Utility» | Por identificar | Confirmar componente; no asumir que es password-utils |

## 4. Bibliotecas de terceros

Cada fila requiere explicar únicamente su integración con GFrame, assets, inicialización, puente de estilos y límites. Verificar la versión distribuida y enlazar su fuente y documentación oficial; no atribuir la biblioteca a GFrame.

| Biblioteca / identificador | Documento actual | Estado |
| --- | --- | --- |
| Bootstrap / bootstrap | `docs/bootstrap.md` | Existente, pendiente |
| jQuery / jquery | `docs/jquery.md` | Existente, pendiente |
| jQuery UI / jquery-ui | `docs/jquery-ui.md` | Existente, pendiente |
| SweetAlert2 / sweetalert2 | `docs/sweetalert2.md` | Existente, pendiente |
| TinyMCE / tinymce | `docs/tinymce.md` | Existente, pendiente |
| AOS / aos | `docs/aos.md` | Existente, pendiente |
| Chart.js / chartjs | `docs/chartjs.md` | Existente, pendiente |
| Coloris / coloris | `docs/coloris.md` | Existente, pendiente |
| Flatpickr / flatpickr | `docs/flatpickr.md` | Existente, pendiente |
| html2canvas / html2canvas | `docs/html2canvas.md` | Existente, pendiente |
| intl-tel-input | `docs/intl-tel-input.md` | Existente, pendiente |
| Luxon / luxon | `docs/luxon.md` | Existente, pendiente |
| Owl Carousel / owl-carousel | `docs/owl-carousel.md` | Existente, pendiente |
| PureCounter / purecounter | `docs/purecounter.md` | Existente, pendiente |
| Swiper / swiper | `docs/swiper.md` | Existente, pendiente |
| VenoBox / venobox | `docs/venobox.md` | Existente, pendiente |

## 5. Integración, mantenimiento y cierre

| Tema | Documentos actuales | Estado / trabajo pendiente |
| --- | --- | --- |
| Convención de nombres | Sin decisión cerrada | Definir nombres visibles e identificadores sin romper APIs |
| Instalación y ampliación de módulos | `modulos-runtime.md`, `extensibilidad.md`, `modulos-opcionales.md` | Existentes, pendientes; eliminar duplicaciones y extensiones retiradas |
| Catálogo y dependencias | `inventario-modulos.md`, `dependencias.md`, `dependencias-frontend.md`, `paquetes-visuales.md` | Existentes, pendientes; requeridos, opcionales y terceros |
| Navegación pública y footer | `navegacion-publica.md`, `footer.md` | Existentes, pendientes |
| Actualización y desarrollo local | `actualizaciones.md`, `instalacion-local.md` | Existentes, pendientes |
| Servidores y despliegue | `servidores-web.md` | Existente, pendiente |
| Versionado | `versionado.md` | Existente, pendiente |
| Skills | `skills.md` | Existente, pendiente |
| Notas históricas y publicación inicial | `migracion-nombre-composer.md`, `release-1.0.0.md` | Revisar si deben quedar fuera de la ayuda principal |
| Índice, enlaces, navegación y ejemplos | `index.md` y web | Revisión final después de cerrar los temas |

## Orden y seguimiento

1. Cerrar la presentación introductoria.
2. Arquitectura, carpetas y recorrido de la petición.
3. Router, Render, middleware y ORM, uno por uno.
4. Metas, SEO y multilenguaje.
5. Terminar el resto del núcleo PHP antes de pasar a los módulos de ampliación PHP o híbridos. Clasificar las filas actuales de módulos propios según su responsabilidad real.
6. Documentar después los módulos de vista y frontend; separar dentro de esa capa los propios y las bibliotecas externas. No intercalar estos componentes con ORM, Router u otros componentes del núcleo.
7. Mantenimiento, índice y comprobación final de cobertura.

Las precisiones de cada tarea se conservan en `revision-documentacion.md`. Esta matriz es el inventario de cobertura y orden, no reemplaza esas instrucciones. Las nuevas peticiones se incorporan sin borrar pendientes ni declarar terminados temas no verificados.
