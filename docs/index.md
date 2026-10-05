# Documentación de GFrame

La documentación está organizada primero por **lo que quieres construir** y después por referencia técnica. Si estás empezando con GFrame, no necesitas leer todos los subsistemas antes de crear una funcionalidad.

## Empieza aquí

1. [Instalación y perfiles](instalacion.md): crea el proyecto.
2. [Qué instala cada perfil](perfiles-instalacion.md): diferencia real entre `static`, `managed`, `intranet` y `saas`.
3. [Desarrollar una aplicación con GFrame](guia-desarrollo.md): modelo mental, carpetas y recorrido de una petición.
4. [Tutorial: Productos de extremo a extremo](tutorial-productos.md): controller, service, model/ORM, vista, AJAX y permisos en una funcionalidad completa.
5. [Mapa de capacidades y módulos](inventario-modulos.md): comprueba qué ya existe antes de implementarlo desde cero.
6. [Arquitectura](arquitectura.md): referencia del núcleo, módulos y recorrido interno.

Para preparar el entorno consulta también [Apache y Nginx](servidores-web.md), [Configuración y entorno](configuracion.md) y [Desarrollo con una copia local](instalacion-local.md).

## Construir una aplicación

### Páginas, rutas y presentación

- [Router y declaración de rutas](rutas.md).
- [Render, vistas y templates](render.md).
- [Metadatos y recursos de vistas](meta.md).
- [Footer](footer.md).
- [Multilenguaje: rutas, textos y vistas](multilenguaje.md).

### Datos, clases y lógica de negocio

- [ORM, modelos y dialectos](orm.md).
- La organización recomendada de controllers, services y models se introduce en [Desarrollar una aplicación con GFrame](guia-desarrollo.md).
- [Autoload del proyecto y de módulos](autoload-proyecto.md): diferencia entre clases ordinarias de `app/`, clases del core y personalizaciones namespaced de módulos runtime.
- [Helpers PHP del core](helpers-php.md).
- [Extensión por herencia y contratos](extensibilidad.md).

### Formularios, AJAX y frontend

- [Utilidades frontend y formularios](frontend-core.md).
- [Alertas](alerts.md).
- [GFSelect](gfselect.md).
- [GFTable](gf-table.md).
- [Iconos GFrame](gframe-icons.md).
- [Editor de texto enriquecido](rich-text-editor.md).
- [Markdown y HTML](markdown.md).
- [Búsqueda léxica](lexical-search.md).

### Acceso, identidad y seguridad

Empieza por [Identidad, autenticación, permisos y sesiones](identidad-autorizacion.md), que conecta el recorrido completo. Después usa las referencias especializadas:

- [Middleware y control de acceso](middleware.md).
- [Autenticación](autenticacion.md).
- [Interfaz de autenticación](auth-ui.md).
- [Sesiones](sesiones.md).
- [Roles, permisos y membresías](permisos.md).
- [Cuenta y seguridad](self-account.md).
- [Administración de usuarios](user-admin.md).
- [Utilidades de contraseñas](password-utils.md).
- [Sanitización de HTML](html-sanitizer.md).
- [Cifrado de datos de aplicación](encryption.md).

## Añadir capacidades

### Archivos y contenido

- [Biblioteca multimedia](media-library.md).
- [Editor de texto enriquecido](rich-text-editor.md).
- [Markdown y HTML](markdown.md).

### Correo y notificaciones

- [Correo y plantillas](mail.md).
- [Notificaciones](notificaciones.md).
- [Transporte de correo para notificaciones](notifications-email.md).
- [Campañas de notificaciones](notification-campaigns.md).

### Procesos fuera de la petición

- [Elegir entre ejecución directa, Async, Cron y colas](procesos-segundo-plano.md): guía de decisión práctica.
- [Tareas en segundo plano con Async](async.md): lanza una operación en otro proceso, sin cola persistente ni reintentos automáticos.
- [Cron runner](cron-runner.md): tareas persistentes, futuras o recurrentes.
- [Heartbeat](heartbeat.md): actualización periódica del cliente y estado de sesión.

### Integraciones y transporte HTTP

- [Acceso API](api-access.md): otros sistemas consumen endpoints de tu aplicación.
- [Cliente HTTP saliente](http-client.md): tu aplicación consume servicios externos.
- [WordPress headless](wordpress-headless.md).

### SEO y publicación

- [SEO](seo.md): configuración real, sitemap, robots, llms e indexación.
- [JSON-LD](json-ld.md): grafo Schema.org y límites actuales de presets.
- [Metadatos y recursos de vistas](meta.md).

## Módulos y extensibilidad

Empieza por [Usar módulos en una aplicación](modulos-en-aplicacion.md) para distinguir funcionalidad propia, capacidad instalable, runtime MVC y componente frontend.

Después consulta la referencia:

- [Mapa de capacidades y módulos](inventario-modulos.md).
- [Catálogo y dependencias](modulos-opcionales.md).
- [Módulos, originales y personalizaciones](modulos-runtime.md).
- [Extensión por herencia y contratos](extensibilidad.md).
- [Panel administrativo](panel-administrativo.md).

Los módulos funcionales con guía propia se enlazan también desde las secciones de uso correspondientes: autenticación, cuenta, usuarios, multimedia, notificaciones, cron y WordPress headless.

## Bibliotecas de terceros

GFrame integra estas bibliotecas, pero no es su autor. Cada guía identifica la fuente oficial y explica su publicación o uso dentro del framework.

- [Bootstrap](bootstrap.md), [jQuery](jquery.md) y [SweetAlert2](sweetalert2.md).
- [AOS](aos.md), [Chart.js](chartjs.md), [Coloris](coloris.md) y [Flatpickr](flatpickr.md).
- [html2canvas](html2canvas.md), [intl-tel-input](intl-tel-input.md), [jQuery UI](jquery-ui.md) y [Luxon](luxon.md).
- [Owl Carousel](owl-carousel.md), [PureCounter](purecounter.md), [Swiper](swiper.md), [TinyMCE](tinymce.md) y [VenoBox](venobox.md).
- [Puentes visuales y variables](paquetes-visuales.md).

## Instalación, actualización y mantenimiento

- [Instalación y perfiles](instalacion.md).
- [Qué instala cada perfil](perfiles-instalacion.md).
- [Configuración y entorno](configuracion.md).
- [Actualizaciones y despliegue](actualizaciones.md).
- [Apache y Nginx](servidores-web.md).
- [Desarrollo con una copia local](instalacion-local.md).
- [Errores](errores.md).

## Herramientas, dependencias y versiones

- [Skills oficiales](skills.md).
- [Política de dependencias](dependencias.md).
- [Versionado](versionado.md).
- [Migración del nombre Composer](migracion-nombre-composer.md).
- [Alcance de la primera versión](release-1.0.0.md).
- [Historial de versiones](../CHANGELOG.md).

## Estado de la reconstrucción documental

La auditoría y el backlog vivo están en [Reconstrucción de la documentación de GFrame](reconstruccion-documentacion.md). Ese documento clasifica qué está cubierto, qué está oculto, qué está incompleto y qué contradice el código actual.
