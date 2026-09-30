# Auditoría maestra de extracción de GFrame

Este documento registra qué se ha extraído al repositorio `gframe-framework` y qué continúa incompleto o fuera del paquete. Las aplicaciones se usan únicamente como fuentes de comparación; no se considera que una capacidad esté terminada solo porque exista una clase o un manifiesto.

La revisión de una capacidad debe cubrir, cuando corresponda: núcleo PHP, modelo o proveedor de datos, servicio, controlador, rutas, vistas, plantilla, CSS, JavaScript, esquemas MySQL y SQLite, configuración, manifiesto, perfiles del instalador, pruebas y documentación.

## Fuentes comparadas

- Base Confías: autenticación reciente, Mi cuenta, errores, heartbeat, multimedia y editor.
- Dane: administración de usuarios y notificaciones con más operaciones.
- Bebots: Mi cuenta, administración, notificaciones y cron.
- Libros: correo y parámetros del tema.
- AIPrint MVP y LiangApp Web: contraste de versiones anteriores y recursos compartidos.

## Resultado general

El núcleo principal y los módulos reutilizables están extraídos. Quedan pendientes la verificación visual e integral en instalaciones limpias, la validación de PHP 8.4 y la integración en aplicaciones reales.

## Núcleo

- [x] Arranque por Composer y puente temporal para aplicaciones existentes.
- [x] Configuración por capas y compatibilidad temporal con constantes históricas.
- [x] Router y constructor de rutas.
- [x] ORM, conexiones MySQL/SQLite, dialectos, transacciones y paginación.
- [x] Renderizado base y metadatos.
- [x] Heartbeat del servidor y registro de canales.
- [x] Procesos asíncronos.
- [x] Cifrado.
- [x] `Meta`: capas global, de grupo y de vista verificadas. Se descartan exclusiones de recursos; cada vista declara solo lo que necesita.
- [x] `Email`: soporte Mail único con SMTP desde `.env`, PHPMailer, plantillas, tema y envío directo o asíncrono, sin fachada heredada.
- [x] Correo: `notifications-email` funciona como adaptador sin duplicar el motor ni persistir credenciales SMTP.
- [x] API Bearer/CORS: token por ruta, múltiples consumidores, proveedor reemplazable, origen y preflight verificados.
- [x] SEO: los proyectos nuevos reciben las rutas iniciales para `sitemap.xml`, `robots.txt` y `llms.txt`; Meta y SEO están documentados y probados.
- [x] Cron: API reutilizable para registrar, reprogramar, pausar, reanudar y cancelar tareas; incluye recurrencia, reserva segura y recuperación de bloqueos.

## Módulos funcionales

| Módulo | PHP y datos | Controlador y rutas | Vista y plantilla | CSS y JS | SQL/configuración | Instalador/pruebas | Estado comprobado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Errores | Sí | Respuesta interna | Sí | Sí | No aplica | Sí | Completo; contratos, canales, publicación y escape verificados |
| Autenticación web | Sí | Sí | Sí | Sí | Esquema auth | Sí | Completo; servicios, contratos, excepciones, rutas, publicación y cambio obligatorio verificados |
| Heartbeat y sesión | Sí | Sí | No aplica | Sí | MySQL/SQLite y Redis | Sí | Completo; revocación multidispositivo, drivers, versiones de autorización, canales y publicación verificados |
| Mi cuenta | Sí | Sí | Sí | Sí | Esquema auth | Sí | Completo; contratos extensibles, seguridad, publicación y operaciones verificadas |
| Administración de usuarios | Sí | Sí | Sí | Sí; usa Bootstrap compartido | Esquema auth | Sí | Completo; contratos, permisos, filtros, protecciones y publicación verificados |
| Multimedia | Sí | Sí | Sí | Contratos reparados | MySQL/SQLite, límites, cuotas y URL remota | Sí | Campo, selector, hotlink HTTPS y modal cotejados; falta prueba visual en una aplicación instalada |
| Notificaciones | Inbox, cola y contratos | Sí | Sí | Sí | MySQL/SQLite y migraciones | Sí | Completo; correo y campañas funcionan como addons separados |
| Cron | Planificador reutilizable | CLI | No aplica | No aplica | MySQL/SQLite y migraciones | Sí | Completo |
| Editor enriquecido | Sí | No aplica | Sí | Sí | TinyMCE | Sí | Completo; publicación, plugins, licencia, configuración y ciclo de vida verificados |
| WordPress headless | Cliente y contratos | No aplica | No aplica | Estilos opcionales | Variables de entorno | Sí | Completo; instalación, TLS, endpoints, errores y contrato BridgeFrame documentados |

### Multimedia: capacidades verificadas

El cotejo directo con BaseConfías, Bebots y Dane detectó que la primera extracción redujo el frontend y omitió la carga del selector. Se restauraron los flujos genéricos en GFrame. La paridad visual aún requiere probar una aplicación instalada. Véase [biblioteca multimedia](media-library.md).

- [x] Modelo, almacenamiento, procesador, ámbitos `global`, `tenant` y `user`.
- [x] Listado, carga y eliminación básicos.
- [x] Selector y campo reutilizables básicos.
- [x] Esquemas MySQL y SQLite.
- [x] Contrato de repositorio extensible y respuestas homogéneas.
- [x] Permisos separados para consulta, carga y eliminación.
- [x] Aislamiento de listado, eliminación y relaciones por ámbito.
- [x] Límite de carga y validación real de extensión, MIME e imágenes.
- [x] Documentación de uso, configuración y extensión.
- [x] Detalles de un medio.
- [x] Edición y guardado de metadatos.
- [x] Ingesta completa desde petición.
- [x] Ingesta de contenido generado en base64.
- [x] Cuotas de almacenamiento.
- [x] Sincronización entre disco y base de datos.
- [ ] Recorridos completos del selector y del campo en navegador.
- [x] Registro seguro de URL externa (*hotlink*) y controles genéricos del modal cotejados con los proyectos de origen.
- [ ] Pruebas de interacción del frontend y cotejo final de paridad antes de marcar Multimedia como completo.

### Notificaciones: alcance modular

- [x] Inbox aislado por usuario y tenant.
- [x] Marcar una o todas como leídas.
- [x] Eliminación lógica y expiración.
- [x] Contratos extensibles de repositorio y transporte.
- [x] Integración con heartbeat.
- [x] Parcial PHP y JavaScript reutilizable; página de historial paginada.
- [x] Esquemas MySQL y SQLite.
- [x] Addon `notifications-email`: puente hacia Mail para colas y campañas, registro recurrente con cron y reintentos contados una vez, sin configuración SMTP administrativa.
- [x] Módulo independiente de campañas masivas, con audiencias extensibles, múltiples canales, cron y control de estado.
- [ ] Addons futuros para WhatsApp, Telegram, push y otros canales.

## Capa visual compartida

- [x] Bootstrap, jQuery, SweetAlert2, iconos de GFrame, alertas y utilidades frontend están catalogados como módulos predeterminados.
- [x] `frontend-core` conserva el cargador y cuatro utilitarios; Markdown y GFTable tienen módulos propios. Véase [frontend-core](frontend-core.md).
- [x] Los paquetes opcionales detectados están catalogados.
- [x] QR y Opus Converter fueron excluidos por decisión expresa.
- [x] TextClassifier permanece en Bebots por ser parte de su algoritmo conversacional.
- [x] Estructura del panel administrativo: plantilla, navbar, sidebar, modo oscuro y persistencia compartidos. Menús y escritorio quedan a cargo de cada aplicación; pendiente la comprobación visual HTTP de instalaciones limpias.
- [x] Footer del esqueleto: áreas opcionales de contenido, copyright y créditos mediante plantillas PHP con prioridad de vista, grupo y proyecto. Documentado en `docs/footer.md`.
- [ ] Falta comprobar versión, licencia, dependencias, publicación y prueba mínima de cada paquete visual opcional.

### Inventario de paquetes visuales

| Paquete | Manifiesto | CSS | JavaScript/recursos | Estado |
| --- | --- | --- | --- | --- |
| `bootstrap` | Sí | Sí | Sí | Presente |
| `jquery` | Sí | No | Sí | Presente |
| `sweetalert2` | Sí | Sí | Sí | Presente |
| `gframe-icons` | Sí | Sí | Sí, incluidas fuentes | Presente; regla CSS limitada a `<i class="gicon-*">` |
| `alerts` | Sí | Sí | Sí | Presente |
| `frontend-core` | Sí | No | Sí, cargador y cuatro utilitarios | Auditado y documentado |
| `aos` | Sí | Sí | Sí | Presente; archivos y declaración de publicación verificados |
| `chartjs` | Sí | No | Sí | Presente; archivos y declaración de publicación verificados |
| `coloris` | Sí | Sí | Sí | Presente; `examples.html` se conserva como referencia de uso |
| `flatpickr` | Sí | Sí | Sí | Presente; publicación en bloque verificada |
| `gfselect` | Sí | Sí | Sí | Actualizado desde Dane; publicación y contrato verificados |
| `html2canvas` | Sí | No | Sí | Presente; publicación en bloque verificada |
| `intl-tel-input` | Sí | Sí | Sí, incluidas utilidades y recursos | Presente; publicación en bloque verificada |
| `jquery-ui` | Sí | Sí | Sí | Presente; publicación en bloque verificada |
| `luxon` | Sí | No | Sí | Presente; publicación en bloque verificada |
| `owl-carousel` | Sí | Sí | Sí | Presente; publicación en bloque verificada |
| `password-utils` | Sí | Sí | Sí | Validación alineada con backend; generación segura verificada |
| `purecounter` | Sí | No | Sí | Presente; publicación en bloque verificada |
| `swiper` | Sí | Sí | Sí | Presente; publicación en bloque verificada |
| `tinymce` | Sí | Sí | Sí, incluidos idiomas, plugins, skins y temas | Presente; publicación en bloque verificada |
| `venobox` | Sí | Sí | Sí | Presente; publicación en bloque verificada |
| `wordpress-headless` (estilos) | Sí | Sí | No | Estilos integrados y conectados con variables CSS del proyecto |

Todos los manifiestos consultados apuntan a archivos de origen existentes.

## Dependencias y paquetes PHP

- [x] Paquete Composer identificado como `gframe/framework`.
- [x] PHPMailer, Opis Closure, PHP-SSE y Dotenv declarados mediante Composer.
- [x] PHPAsync permanece en el núcleo y utiliza Opis Closure.
- [x] Cifrado, utilidades, ORM, Router, Render, middleware y servicios comunes están en `src`.
- [x] No se detectaron copias activas de QR, Opus Converter ni TextClassifier.
- [ ] Compatibilidad con PHP 8.4 sin deprecaciones, si las dependencias externas lo permiten. PHP 8.1 queda como base actual por decisión del proyecto; la restricción `^8.1` no demuestra compatibilidad de ejecución con 8.4.

## Instalador y perfiles

- [x] Perfiles `static`, `managed`, `intranet` y `saas` declarados.
- [x] Manifiestos de módulos disponibles.
- [x] Esquemas MySQL y SQLite instalables.
- [x] Creación del primer superadministrador contemplada.
- [x] Esqueleto público básico presente.
- [x] Instalar cada perfil desde cero y comprobar portada o acceso privado, login, `robots.txt` y Bootstrap por HTTP con SQLite.
- [ ] Verificar por HTTP cada vista publicada de los módulos funcionales y todos sus recursos. Esta comprobación corresponde a la auditoría de cada módulo.
- [ ] Probar autenticación, cierre por inactividad, Mi cuenta, usuarios, multimedia, notificaciones, cron, errores y SEO en instalaciones limpias durante la auditoría de cada módulo.

Auditoría del instalador (29 de septiembre de 2026): los cuatro perfiles se instalan y arrancan por HTTP con SQLite; SaaS se probó además sobre una base MySQL temporal. Las credenciales administrativas inválidas se rechazan antes de publicar, los conflictos de configuración se detectan antes de copiar el esqueleto y una publicación fallida permite reintentar. `robots.txt` responde con `Disallow: /` cuando se desactiva la indexación. El instalador queda cerrado en PHP 8.1; PHP 8.4 y los recorridos completos de cada módulo mantienen sus casillas propias. Véase [instalación](instalacion.md).

## Archivos comunes todavía fuera de GFrame

La comparación por ruta y contenido idéntico entre Base Confías, Bebots y Dane encontró estos candidatos. Deben clasificarse como vigentes, duplicados históricos o parte reutilizable antes de trasladarlos.

- [x] `app/views/templates/adminTemplate.php`: incorporada en `admin-panel`.
- [x] `public/js/app/admin/dashboard.js`: histórico sin código funcional activo; no se incorpora.
- [x] `public/js/app/admin/sidebar.js`: incorporado en `admin.js`; se conserva el límite CSS de 992px por decisión del usuario.
- [x] `public/js/app/admin/darkmode.js`: controlador propio en `admin.js`, conectado al selector nativo `data-bs-theme`.
- [x] `public/js/app/admin/persistencia_darkmode.js`: incorporada en `preload.js` sin duplicar el controlador del tema.
- [x] `public/js/app/admin/persistencia_sidebar.js`: incorporada en `preload.js`.
- [x] `app/views/templates/mail/mailTemplate.html`: incorporada al esqueleto y conectada con el registro y tema de Mail.
- [x] `app/views/templates/mail/contactTemplate.html`: incorporada al esqueleto con contenido escapado y parámetros de Mail.
- [x] `public/css/install/install.css`: histórico no utilizado por el instalador actual; se descarta y no se incorpora a GFrame.
- [x] `app/views/admin/parts/udashboard.php`: vacío en las tres fuentes; se descarta y no se incorpora a GFrame.
- [x] `app/views/admin/user/userAdd.php`: marcador sin formulario ni lógica; no se incorpora.
- [x] Frontend de autenticación: cinco JS y CSS cotejados juntos; conexiones restauradas con pruebas específicas y documentadas en `docs/auth-ui.md`. Pendiente la revisión visual general.
- [x] Cotejo final de Auth con Bebots: respaldo del saludo sin campo nombre, `already_logged`, comprobación de escrituras y rotación de token en suspensión/desactivación corregidos. No se incorporan planes, negocios ni regalos de bienvenida. Pendiente la validación HTTP en instalaciones limpias.
- [x] `app/views/templates/.adminTemplate.php`: demo histórica de NiceAdmin sin uso, confirmada por el usuario; no se incorpora a GFrame.
- [x] `app/views/home/bhomeIndex.php`: pantalla de mantenimiento sin referencias en las tres fuentes; descartada, sin copia en GFrame ni cambios en los proyectos.
- [x] `public/js/app/home/mngnoadmin.js`: navegación pública incorporada al esqueleto; uso y extensión en `docs/navegacion-publica.md`.
- [x] `public/js/app/home/home.js`: recurso propio de las páginas de cada proyecto; no se incorpora. GFrame conserva su portada mínima y su CSS, con JavaScript solo cuando lo requiera su comportamiento.
- [x] `debug.php`: archivo idéntico en las tres fuentes que solo imprime `$_SERVER['PROJECT']`, sin referencias encontradas; descartado por decisión del usuario. No existe una copia en GFrame y los originales no se modifican.

## Evidencia de comparación del núcleo

- [x] Las rutas relativas del núcleo PHP de Base Confías están cubiertas por `src`, salvo `core/Load.php`, que es el puente deliberado de la aplicación.
- [x] Las diferencias antiguas `Config.php` y dependencias copiadas en otros proyectos están cubiertas por la configuración por capas o Composer.
- [x] La integración temática de correo cotejada con Libros se incorporó al soporte Mail y a las plantillas del esqueleto.

## Regla para revisiones posteriores

Las revisiones futuras continúan desde las casillas abiertas de este documento. No deben reiniciar el inventario ni marcar un módulo como completo por la sola presencia de archivos.
