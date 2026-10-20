# Inventario de imágenes de la ayuda

Estado: actualización 4.3.1, 20 de octubre de 2026. Este archivo identifica qué existe, dónde se usa y qué falta. No debe deducirse la cobertura por el número de PNG ni abrir todos los archivos para reconstruirla.

## Archivos disponibles

Raíz común: `docs/assets/4.3.1/` del framework. Veinte capturas reales, diez esquemas y una ilustración original generada con IA. Se distribuyen en veintiocho guías; algunos recursos se reutilizan y una guía puede incluir varias figuras. Alerts incorpora diálogo y toast.

| Archivo | Tipo | Apartado y ruta | Qué muestra / intención | Estado |
| --- | --- | --- | --- | --- |
| `instalacion.png` | Captura real | Instalación, `/guias/instalacion` | Primer paso del asistente; proyecto «Estudio Nexo · Revista digital», perfil administrado y campos de cuenta todavía vacíos. Enseña qué se pide antes de continuar. | Integrada |
| `multimedia.png` | Captura real | Biblioteca multimedia, `/modulos/media-library` | Biblioteca con filtros, orden, selector de vista y archivos de portada/marca; recursos creados en una aplicación temporal. | Integrada |
| `editor.png` | Captura real | Editor, `/modulos/rich-text-editor` | Artículo de unas 430 palabras, secciones, listas, portada original y estado de autoguardado privado. El editor es nativo; el formulario editorial y sus acciones son una integración de ejemplo. | Integrada |
| `vista-previa.png` | Captura real | Vista previa, `/modulos/content-preview` | Artículo completo en la plantilla pública del proyecto, con anchura de lectura, jerarquía, listas, cita y portada. Los estilos de `maintenance/help/examples/article.css` son del ejemplo, no una mejora global del core. | Sustituida |
| `autenticacion.png` | Captura real | Autenticación, `/guias/autenticacion`, y `/modulos/auth-ui` | Formulario nativo de acceso, con enlaces de registro y recuperación; campos vacíos. La guía PHP distingue interfaz opcional y contrato del núcleo. | Integrada en dos guías |
| `mi-cuenta.png` | Captura real | Mi cuenta, `/modulos/self-account` | Pantalla nativa de cuenta y seguridad, con una identidad ficticia del entorno temporal y contraseñas vacías. | Integrada |
| `usuarios.png` | Captura real | Administración de usuarios, `/modulos/user-admin` | Listado nativo de cuentas, filtros y opciones de administración del actor autorizado. | Integrada |
| `autoguardado.png` | Captura real | Autoguardado privado, `/modulos/content-autosave` | Aviso de copia privada recuperable después de editar y volver a abrir un artículo completo. No simula la persistencia del borrador. | Integrada |
| `revisiones.png` | Captura real | Historial, `/modulos/content-revisions` | Dos versiones realmente guardadas, comparadas con el parcial nativo. Cambian el tamaño del equipo, la frecuencia editorial y el cierre. Página de integración propia, no endpoint universal. | Integrada |
| `publicacion-programada.png` | Captura real | Programación, `/guias/content-publication` | Artículo programado mediante la operación real, con estado y fecha UTC consultados de nuevo al servidor. | Integrada |
| `panel-administrativo.png` | Captura real | Panel, `/modulos/admin-panel` | Estructura nativa del escritorio, navegación, acceso a cuenta y entradas de los módulos instalados. No se inventan widgets de negocio. | Integrada |
| `notificaciones.png` | Captura real | Bandeja, `/modulos/notifications` | Avisos ficticios de acceso, soporte e incorporación, leídos y pendientes; acciones y enlaces de seguimiento del componente nativo. | Integrada |
| `campanas.png` | Captura real | Campañas, `/modulos/notification-campaigns` | Formulario nativo para preparar una bienvenida: título, mensaje, destinatarios y entrega. Borrador sin enviar ni programar. | Integrada |
| `correo.png` | Captura real de HTML renderizado | Mail, `/guias/mail`, y canal email, `/modulos/notifications-email` | Plantilla del framework con bienvenida de Estudio Nexo y enlace de acción. Es presentación local, no evidencia de SMTP ni de procesamiento de cola. | Integrada en dos guías |
| `selectores.png` | Captura real | GFSelect, `/modulos/gf-select` | Desplegable abierto con buscador y opciones completas, selección múltiple y variante deshabilitada. No persiste preferencias. | Sustituida |
| `tablas.png` | Captura real | GF Table, `/modulos/gf-table` | Seis solicitudes ficticias con responsables, estados, fechas, búsqueda y encabezados ordenables del componente real. | Integrada |
| `alertas.png` | Captura real | Alerts, `/modulos/alerts` | Confirmación nativa de swalAlert para revisar un cambio de acceso; no modifica permisos. | Integrada |
| `toast.png` | Captura real | Alerts, `/modulos/alerts` | Confirmación no modal mediante alertToast, con texto ficticio de preferencias guardadas. No realiza cambios en una cuenta. | Nueva |
| `permisos.svg` | Esquema técnico | Permisos, `/guias/permisos` | Flujo de identidad, bypass protegido, ámbito y permiso efectivo; autorización por registro a cargo del proyecto. No es una interfaz de roles. | Integrada |
| `modulos-runtime.svg` | Esquema técnico | Personalización de módulos, `/guias/modulos-runtime` | Decisión por archivo entre vista propia y original; efecto de una actualización sobre la personalización. | Nueva |
| `transacciones.svg` | Esquema técnico | ORM, `/guias/orm` | Flujo de apertura, escrituras, confirmación o reversión sobre una conexión; límites respecto a archivos y servicios externos. | Nueva |
| `canales-http.svg` | Esquema técnico | API, `/guias/api-access`, y cliente HTTP, `/guias/http-client` | Dirección de las llamadas, autenticación/contexto entrante y resultado de transporte saliente; límites de autorización y negocio. | Nueva, integrada en dos guías |
| `multimedia-atributos.png` | Captura real | Multimedia, `/modulos/media-library` | Modal nativo de detalles con nombre visible y texto alternativo guardados mediante el endpoint real en una aplicación temporal. No representa el panel interno del selector. | Nueva |
| `seleccion-multimedia.svg` | Esquema técnico | Multimedia, `/modulos/media-library` | Distingue persistencia del archivo, composición de IDs y guardado del formulario; efectos de cancelar o quitar de selección. | Nueva |
| `entrega-correo.svg` | Esquema técnico | Mail, `/guias/mail` | Solicitud Async, worker y aceptación SMTP; sin afirmar recepción ni reintentos automáticos. | Nueva |
| `flujo-campanas.svg` | Esquema técnico | Campañas, `/modulos/notification-campaigns` | Audiencia, despacho con deduplicación y procesamiento por canal; aclara el significado de completed. | Nueva |
| `caminos-cron.svg` | Esquema técnico | Cron, `/modulos/cron-runner` | Disparadores real/virtual y ejecutores CLI/HTTP, con límites de cadencia y autenticación. | Nueva |
| `politica-seo.svg` | Esquema técnico | SEO, `/guias/seo` | Configuración global, elegibilidad de ruta y salidas HTML/índices; la exclusión no reemplaza permisos. | Nueva |
| `capas-meta.svg` | Esquema técnico | Metas, `/guias/meta` | Cinco capas de archivos meta en su orden real y deduplicación por ruta de recursos. | Nueva |
| `busqueda.png` | Captura de integración de ejemplo | Búsqueda léxica, `/modulos/lexical-search` | Consulta «campañs» y dos resultados calculados por el motor PHP original, con títulos y snippets. La interfaz es propia del ejemplo; no es una pantalla distribuida por el módulo. | Nueva |
| `equipo-editorial.png` | Ilustración IA original | Recurso del artículo y biblioteca; visible dentro de las capturas de editor, biblioteca y preview | Mesa de trabajo cálida con portátiles, libreta, cerámica y plantas, para dar contexto al caso editorial Estudio Nexo. No es una captura de producto ni prueba una capacidad de IA de GFrame. | Fuente conservada; no se inserta como pantalla independiente |

La fotografía se generó expresamente para esta ayuda, sin imágenes de referencia ni marcas. **No se usa el fondo de Base Confías ni `home-hero.jpg` del esqueleto.** El logo de GFrame es un recurso de marca existente, no una imagen nueva. Los primeros borradores de capturas quedaron sustituidos: no hay archivos «Prueba editorial» adicionales que deban publicarse.

## Dónde se integran

Cada guía Markdown enlaza sus imágenes con `![texto alternativo](assets/4.3.1/archivo)` y mantiene su pie como párrafo en cursiva. Veintiocho guías tienen apoyo visual. Texto, imágenes y pies viajan juntos en el paquete Composer. El sitio transforma esos enlaces en figuras con dimensiones, carga diferida y ampliación, y sirve los originales desde el repositorio local o el paquete instalado. No mantiene una lista paralela ni copias en su carpeta pública.

### Corrección de encuadres

Se recapturaron multimedia, editor, preview, acceso, cuenta, usuarios, autoguardado, revisiones, programación, panel, notificaciones, campañas, correo, selectores, tabla y diálogo. El margen pertenece al PNG original, no a un relleno del contenedor web. `maintenance/help/capture-frame.cjs` conserva los iframes del editor y extiende el encuadre para incluir el desplegable abierto. Instalación conserva su captura anterior, que ya contenía el asistente completo.

## Ampliaciones visuales conservadas para seguimiento

El lote de revisión de permisos, multimedia, correo, campañas, cron, SEO y metas ya cuenta con apoyo visual. Los estados siguientes amplían recorridos concretos; no deben confundirse con áreas totalmente sin ilustrar.

Son un inventario de cobertura futura, no funcionalidades ausentes del framework ni una autorización para ampliar este cambio.

| Apartado | Captura que falta | Condición para hacerla |
| --- | --- | --- |
| Instalación | Conexión y validación de base de datos | Usar un esquema temporal; no mostrar usuario/clave reales. |
| Instalación | Selección de módulos y revisión final | Mantener el mismo caso de revista y mostrar dependencias como las resuelve el instalador. |
| Multimedia | Variantes de imagen | Los detalles y el texto alternativo ya tienen captura real; las variantes pueden ilustrarse aparte con la misma portada. |
| Multimedia | Selector dentro del editor y galería | Capturar el modal nativo y su selección aplicada al contenido. |
| Editor | Concentración, esquema y herramientas secundarias | Usar el mismo artículo; mostrar la tarea concreta, no otra pantalla inicial. |
| Historial | Confirmación y resultado de restauración | La comparación ya está ilustrada. Falta documentar visualmente una restauración completa sin cambios implícitos de publicación. |
| Papelera | Lista de eliminados, restauración y confirmación de borrado | El proyecto define esas pantallas. No inferir una interfaz nativa de artículos que GFrame no distribuye. |
| Publicación programada | Cambio de fecha y cancelación | La programación inicial y el estado guardado ya tienen captura. Falta enseñar sus transiciones posteriores. |
| Autenticación / cuenta | Recuperación de acceso y gestión de sesiones | Acceso, cuenta y usuarios ya están ilustrados. Ampliar con cuenta temporal, sin contraseñas, tokens o correos personales visibles. |
| Móvil | Biblioteca, editor y preview en pantalla estrecha | Capturas propias de móvil; no reducir una captura de escritorio. |
| Panel | Menú en móvil y personalización de accesos | La estructura de escritorio ya está ilustrada. No añadir estadísticas ficticias. |
| Notificaciones | Detalle de aviso y cambio de leído/no leído | La bandeja ya está ilustrada; completar las acciones con datos temporales. |
| Campañas | Previsualización de audiencia y seguimiento | El formulario ya está ilustrado; no afirmar que hubo envíos a partir de una captura. |
| Mail | Variantes de plantilla y contacto | La bienvenida ya está renderizada; distinguir presentación, lanzamiento y aceptación SMTP. |
| Permisos | Ejemplo de configuración de roles y denegación por recurso | El flujo tiene esquema. No inventar un administrador universal de roles; priorizar código y casos concretos. |
| Componentes | Estados de error, carga y selección dentro de modal | La tabla, los selectores y la confirmación ya tienen ejemplos propios. |

## Reproducción y mantenimiento

Los seis esquemas de este lote se generan con `python maintenance/help/create-diagrams.py`, sin dependencias externas. Para reproducir únicamente los atributos multimedia: configura `GFRAME_CAPTURE_FILES=multimedia-atributos.png` y ejecuta `node maintenance/help/capture-help.cjs`. El generador guarda realmente los atributos en su aplicación temporal y termina antes de los recorridos editoriales cuando esa es la única captura solicitada.

Los generadores son herramientas de contribución: ejecútalos desde un checkout completo del framework, con sus fixtures de `tests/`, PHP y Playwright/Chrome disponibles. El paquete Composer incluye la ayuda ya generada; no necesita ejecutar estos generadores para consultarla.

La captura de búsqueda se reproduce con `node maintenance/help/capture-search.cjs`. Su fixture `maintenance/help/examples/search.php` llama directamente al motor original y exige dos resultados; no instala una pantalla en el framework ni consulta datos de usuarios. La captura es una página de ejemplo con los resultados efectivos del algoritmo.

El generador está en el repositorio del framework: `maintenance/capture-help.cjs`. Recibe la carpeta destino y usa Playwright/Chrome, PHP y los fixtures del paquete. Requiere que `equipo-editorial.png` exista en destino. Ejemplo desde el framework, con Playwright disponible y `GFRAME_TEST_BROWSER` apuntando al ejecutable de Chrome:

```text
node maintenance/capture-help.cjs docs/assets/4.3.1
```

Para reproducir los encuadres vigentes ejecuta desde el framework `node maintenance/help/capture-help.cjs`. Genera diez capturas: biblioteca y atributos multimedia, editor, preview, acceso, cuenta, usuarios, autoguardado, revisiones y programación. Por defecto lee este framework y escribe en `docs/assets/4.3.1`; `GFRAME_SOURCE` permite indicar otra raíz y el primer argumento cambia el destino. No regenera la fotografía ni el instalador. La hoja visual se guarda en la caché ignorada del framework, no se publica.

Ambos generadores aceptan `GFRAME_CAPTURE_FILES`, una lista de nombres separados por comas, para sustituir solo los archivos indicados. Los recorridos necesarios se ejecutan en la aplicación temporal; no se repiten suites generales del framework.

La ampliación de módulos no editoriales se reproduce con `node maintenance/help/capture-modules.cjs`. `maintenance/help/modules-demo.php` instala únicamente una aplicación temporal, añade avisos ficticios y prepara ejemplos locales de plantillas y componentes. No conecta SMTP ni envía o programa campañas. El esquema de permisos es un SVG mantenido como fuente legible. La comprobación de esta ampliación se limita al renderizado de las imágenes y de sus nueve fragmentos documentales; no se ejecutan suites generales ni se actualiza Composer.

Crea aplicación y esquema SQLite temporales, copia los originales de aplicación y aplica etiquetas/textos solo en esas copias; captura interfaces reales y elimina el entorno temporal. El puente PHP transporta respuestas HTTP reales al navegador, no maqueta la UI. El original de la ilustración se conserva también en la salida de generación de imágenes; la copia necesaria para reproducir la ayuda está en este repositorio.

Al cambiar una pantalla, actualiza el archivo, la versión del pie y esta tabla; comprueba escritorio/móvil en el sitio, texto legible y ausencia de datos sensibles. Si se añade una captura, registra primero su tarea y guía. No generes nuevas fotos para sustituir innecesariamente la portada ya creada.
