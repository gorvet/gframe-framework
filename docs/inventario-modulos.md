# Inventario de módulos y capacidades

## Núcleo instalado con GFrame

- **Arranque y configuración**: carga por Composer, `.env`, configuración del proyecto y compatibilidad temporal con constantes históricas.
- **Enrutamiento**: rutas web, AJAX, API, webhook, sistema y SSE mediante `Router` y `RouteBuilder`.
- **Middleware**: autenticación, invitados, roles, permisos y ejecución global o por tenant.
- **MVC y renderizado**: controladores, modelos ORM, vistas, plantillas, metadatos por grupo y por vista.
- **Base de datos**: ORM, conexiones MySQL y SQLite, dialectos, transacciones y paginación.
- **Autenticación**: registro, verificación, acceso, recuperación, sesiones y expiración opcional de contraseñas.
- **Roles y permisos**: superadministrador protegido, roles configurables y permisos normalizados.
- **Mi cuenta**: consulta de los datos base del usuario conectado, cambio de contraseña y desactivación protegida. Los perfiles ampliados pertenecen a cada aplicación.
- **Errores**: respuestas web y de API, plantillas personalizables y detalle condicionado por debug.
- **SEO**: metadatos, JSON-LD, sitemap, robots.txt y llms.txt con activadores independientes.
- **Metricool**: carga opcional desde el footer, desactivada automáticamente durante debug.
- **Correo**: PHPMailer y tema reutilizable para mensajes.
- **Cliente HTTP**: peticiones seguras con TLS, cabeceras, JSON y contratos de respuesta estables.
- **Procesos asíncronos**: ejecución en segundo plano mediante `Async`.
- **Cron**: registro y ejecución de tareas programadas.
- **Heartbeat**: canales periódicos del servidor y cliente web opcional.
- **Cifrado**: utilidades de cifrado propias del framework.
- **Instalación**: perfiles, esquemas, configuración, módulos, primer superadministrador y bloqueo posterior.
- **Esqueleto de aplicación**: portada pública inicial, estructura MVC y asistente visual.

## Base visual predeterminada

Se instala automáticamente en todos los proyectos:

- `bootstrap` 5.3.8;
- `jquery` 3.5.1;
- `sweetalert2` 11.10.0;
- `gframe-icons`;
- `alerts`: `alertToast`, `swalAlert` y estados de carga;
- `frontend-core`: formularios, errores, paginación, tablas, Markdown y utilidades comunes.

## Módulos funcionales opcionales

- `media-library`: archivos globales, por tenant o por usuario y relaciones con contenidos.
- `notifications`: cola persistente, procesamiento por lotes y transporte de correo.
- `user-admin`: administración de usuarios reservada al superadministrador.
- `wordpress-headless`: contenido y taxonomías de WordPress mediante BridgeFrame.
- `rich-text-editor`: componente reutilizable sobre TinyMCE con limpieza de contenido pegado desde Word.
- `heartbeat-client`: cliente web para los canales heartbeat.
- `gfselect`: selector enriquecido propio.
- `password-utils`: indicador y utilidades de contraseñas.

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
- `wordpress-styles`: estilos para contenido procedente de WordPress.

Owl Carousel y Swiper son alternativas independientes. TinyMCE se instala automáticamente cuando se selecciona `rich-text-editor`. `wordpress-styles` se instala con `wordpress-headless`.
