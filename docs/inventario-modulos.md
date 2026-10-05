# Inventario de módulos y capacidades

## Núcleo instalado con GFrame

- **Arranque y configuración**: carga por Composer, `.env`, configuración del proyecto y compatibilidad temporal con constantes históricas.
- **Enrutamiento**: rutas web, AJAX, API, webhook, sistema y SSE mediante `Router` y `RouteBuilder`.
- **Acceso API entrante**: Bearer por ruta, CORS, múltiples consumidores y proveedor reemplazable para credenciales por cliente o tenant.
- **Middleware**: autenticación, invitados, roles, permisos y ejecución global o por tenant.
- **MVC y renderizado**: controladores, modelos ORM, vistas, plantillas, metadatos por grupo y por vista.
- **Base de datos**: ORM, conexiones MySQL y SQLite, dialectos, transacciones y paginación.
- **Autenticación**: registro, verificación, acceso, recuperación, sesiones y expiración opcional de contraseñas.
- **Roles y permisos**: superadministrador protegido, roles configurables y permisos normalizados.
- **Errores**: respuestas web y de API, plantillas personalizables y detalle condicionado por debug.
- **SEO**: metadatos, JSON-LD, sitemap, robots.txt y llms.txt con activadores independientes.
- **Metricool**: carga opcional desde el footer, desactivada automáticamente durante debug.
- **Correo**: servicio único sobre PHPMailer, SMTP desde `.env`, plantillas y tema reutilizable.
- **Cliente HTTP**: peticiones seguras con TLS, cabeceras, JSON y contratos de respuesta estables.
- **Procesos asíncronos**: ejecución en segundo plano mediante `Async`.
- **Cron**: registro y ejecución de tareas programadas.
- **Heartbeat**: canales periódicos del servidor y cliente web opcional.
- **Cifrado**: utilidades de cifrado propias del framework.
- **Instalación**: perfiles, esquemas, configuración, módulos, primer superadministrador y bloqueo posterior.
- **Esqueleto de aplicación**: portada pública inicial, estructura MVC y asistente visual.
- **Footer**: plantillas independientes y opcionales para contenido, copyright y créditos, personalizables por grupo y vista.

## Base visual predeterminada

Se instala automáticamente en todos los proyectos:

- `bootstrap` 5.3.8;
- `jquery` 3.5.1;
- `sweetalert2` 11.10.0;
- `gframe-icons`;
- `alerts`: `alertToast`, `swalAlert` y estados de carga;
- `frontend-core`: formularios, errores, paginación y utilidades comunes;
- `gfselect`: selector enriquecido propio;
- `gf-table`: búsqueda y ordenación local de tablas;
- `error-pages`: plantilla y vistas web para errores 403, 404, 500 y 503.

## Módulos funcionales y requisitos por perfil

`managed` e `intranet` incluyen `auth-ui`, `self-account`, `admin-panel`, `user-admin` y `media-library`, junto con sus dependencias. `saas` añade `notifications` y `cron-runner`. Los demás componentes compatibles pueden añadirse durante la instalación o posteriormente. `static` no instala módulos que requieren tablas o autenticación.

- `auth-ui`: acceso, registro, verificación, recuperación, restablecimiento y cierre de sesión.
- `self-account`: pantalla Mi cuenta, cambio de contraseña y desactivación de la cuenta propia.
- `admin-panel`: escritorio, navbar, sidebar, tema y persistencia.
- `media-library`: archivos globales, por tenant o por usuario y relaciones con contenidos.
- `notifications`: inbox por usuario y tenant con transportes extensibles.
- `notifications-email`: adaptador que conecta notificaciones, colas y campañas con el soporte Mail del núcleo.
- `notification-campaigns`: envíos masivos inmediatos o programados sobre la cola y los transportes registrados.
- `user-admin`: listado, búsqueda, filtros, verificación, suspensión/restablecimiento, eliminación y asignación de roles mediante permisos; el superadministrador conserva acceso total.
- `wordpress-headless`: contenido y taxonomías de WordPress mediante BridgeFrame, con estilos de bloques integrados.
- `rich-text-editor`: componente reutilizable sobre TinyMCE con limpieza de contenido pegado desde Word.
- `heartbeat-client`: cliente web, controlador y ruta de sistema para los canales heartbeat.
- `cron-runner`: persistencia y ejecución CLI de tareas programadas.
- `markdown`: conversión de Markdown y HTML en PHP y JavaScript.
- `lexical-search`: búsqueda léxica sin base de datos propia.
- `password-utils`: política PHP de aceptación, generador seguro e indicador visual de contraseñas.

## Componentes visuales opcionales

- `aos`: animaciones al desplazarse.
- `chartjs` 4.4.2: gráficas y estadísticas.
- `coloris`: selector de colores.
- `flatpickr` 4.6.13: fechas y horas.
- `html2canvas` 1.4.0: captura de elementos HTML.
- `intl-tel-input` 24.7.0: teléfonos internacionales.
- `jquery-ui`: interacciones complementarias de jQuery.
- `luxon`: fechas, zonas horarias y duraciones.
- `owl-carousel` 2.3.4: carruseles.
- `purecounter` 1.5.0: contadores animados.
- `swiper` 11.2.10: deslizadores táctiles.
- `tinymce` 8.6.0: motor externo del editor enriquecido.
- `venobox` 2.0.4: cajas de luz.

Owl Carousel y Swiper son alternativas independientes. TinyMCE se instala automáticamente cuando se selecciona `rich-text-editor`. Los estilos para contenido WordPress forman parte de `wordpress-headless`.
