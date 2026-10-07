# Revisión de documentación para desarrolladores

Esta lista pertenece al mantenimiento del repositorio, no a la ayuda pública. La revisión avanza por tema; comprobar enlaces no equivale a revisar el contenido completo.

El inventario por componente, documento existente, estado y orden de ejecución está en [Plan de documentación](plan-documentacion.md).

## Terminados en esta revisión

- [x] SEO automático: configuración, rutas generadas, sitemap dinámico, publicación, schema, robots, llms y comprobaciones. Corregida la afirmación de exclusión universal de rutas con permisos; documentados límites de metas runtime y robots en subcarpetas. Pruebas de documentación y SEO: 21 pruebas, 431 aserciones.
- [x] Metas: organización global, template, grupo y vista; prioridad, datos dinámicos, etiquetas, carga y deduplicación de CSS/JS y sustitución de metas de módulos. Pruebas de documentación y MetaSeoTest: 21 pruebas, 429 aserciones.
- [x] ORM y dialectos: guía `docs/orm.md`, consultas, resultados, escrituras, relaciones, conexiones, transacciones y límites de portabilidad. Ejemplos ejecutados contra SQLite en memoria; pruebas de documentación y dialectos: 17 pruebas, 419 aserciones. No se probó la guía completa sobre un servidor MySQL en esta ronda.
- [x] Middleware: guía `docs/middleware.md`, controles, orden, sesión, CSRF/honeypot, contexto, canales, tenants y límites reales de ampliación. Pruebas de documentación, API, sesiones y permisos: 33 pruebas, 447 aserciones. Añadida al índice y catálogo de guías de la web local.
- [x] Render, vistas y templates: guía `docs/render.md`, estructura de datos, constructores, composición HTML, metas, resolución aplicación/módulo, partes, footer y límites. Pruebas de documentación, metas y runtime: 28 pruebas, 496 aserciones; web local: 116 comprobaciones. No se modificó Render ni se publicó una versión.
- [x] Router y RouteBuilder: guía `docs/rutas.md`, declaraciones por canal, parámetros, prioridad, inferencias, módulos y límites. Ejemplos de registro y sintaxis comprobados; conjunto de documentación: 6 pruebas, 339 aserciones. Añadida a la navegación de la web local; no publicada en producción.
- [x] Arquitectura, carpetas y recorrido de una petición: tres capas, responsabilidades, bootstrap y diferencia entre ejecución web y canales directos. Ejemplo de portada basado en el skeleton. Pruebas de documentación: 4 tests, 308 aserciones.
- [x] Instalación: requisitos, generación de proyecto, perfiles, pasos y errores de base de datos.
- [x] Configuración: archivos, variables, lectura y ejemplos propios.
- [x] Panel administrativo: pantalla, escritorio, menú, estilos y tema.
- [x] Gestión de usuarios: permisos, operaciones, roles, personalización y almacenamiento.
- [x] Barra de la web: versión junto a GitHub, búsqueda en el flujo y menú móvil desde la izquierda. Comprobada a 1200 px y 390 px en una vista previa local.

## Pendientes

- [ ] Primera pantalla: tutorial completo para empezar desde cero; la explicación de arquitectura y recorrido está revisada.
- [ ] Módulos runtime y extensibilidad: evitar duplicación y separar migraciones históricas del uso actual.
- [ ] Catálogo e inventario: requisitos, opcionales y módulos de terceros sin repetir las guías.
- [ ] Auth, sesiones, permisos y Cuenta y seguridad.
- [ ] Multimedia.
- [ ] Notificaciones, campañas y transporte de correo.
- [ ] Mail, tareas programadas y heartbeat.
- [ ] Metadatos, SEO, footer, navegación pública y errores.
- [ ] Formularios, alertas, contraseñas, selects, tablas e iconos.
- [ ] Markdown, buscador, editor y sanitización.
- [ ] Integraciones y bibliotecas de terceros.
- [ ] Actualizaciones, despliegue, desarrollo local, versiones y skills.
- [ ] Revisión final del índice, referencias cruzadas, ejemplos y navegación de la web.

## Requisitos añadidos a la cola

- [ ] Al terminar los temas en curso, revisar de nuevo la introducción de la web: breve, explicativa y centrada en ventajas prácticas. Incluir SaaS y multitenancy, distinguiendo soporte del framework y lógica que desarrolla el proyecto. Evitar formulaciones «es/no es», enumeraciones repetidas y el título «Una base para desarrollar, no un sitio terminado». Revisar los metadatos SEO para que coincidan con el texto final.
- [ ] Organizar la presentación en tres capas consecutivas: núcleo PHP; módulos de ampliación PHP o híbridos PHP/JavaScript; módulos de vista y frontend. Clasificar por responsabilidad comprobada, no mezclar las explicaciones de las capas y distinguir bibliotecas externas dentro de la capa visual. Identificar «PagoRUTIL» antes de asignarle una categoría definitiva.
- [ ] Recorrido de una petición: explicar desde la URL o entrada HTTP hasta la respuesta, incluyendo bootstrap, resolución de ruta, middleware, controlador y salida mediante Render o respuesta de datos. Mostrar dónde intervienen servicios, modelos y ORM sin presentar esas capas opcionales como pasos obligatorios. Comprobar el orden real contra la implementación.
- [ ] Tipos de rutas: documentar por separado las declaraciones y respuestas de páginas, AJAX, API, SSE y webhooks, con ejemplos ejecutables, métodos HTTP, parámetros, autenticación, permisos y protección CSRF según los contratos reales de cada tipo. Señalar diferencias entre capacidades del framework y lógica que debe aportar el proyecto.
- [ ] Arquitectura de carpetas completa: explicar propósito, propiedad y resolución de los archivos del núcleo, aplicación, módulos, configuración, assets públicos, dependencias y almacenamiento; relacionar cada carpeta con el recorrido de la petición.
- [ ] ORM y dialectos: explicar API común, selección del motor, responsabilidades de los dialectos, diferencias soportadas entre MySQL y SQLite y cómo ampliar el soporte si existe un contrato vigente. Incluir consultas y transacciones reales, evitando equiparar compatibilidad parcial con soporte completo.
- [ ] Definir una convención de nombres para los módulos propios, incluidos GF Select y GF Table. Evaluar nombre visible, identificador del módulo y API por separado; no renombrar contratos existentes sin comprobar compatibilidad. La elección entre nombres unidos o separados por guion sigue pendiente.
- [ ] Alertas: explicar el feedback del backend mediante swalAlert y alertToast, sus contratos, opciones, estilos y ejemplos. Distinguirlo de las notificaciones internas.
- [ ] Frontend core: documentar sus utilidades, dependencias, inicialización y uso en las vistas.
- [ ] GF Select y GF Table: explicar marcado, inicialización, opciones, eventos, datos y formas admitidas de ampliación con ejemplos reales.
- [ ] Errores: explicar errores HTTP, respuestas del backend, feedback del frontend y personalización de sus vistas.
- [ ] Markdown: explicar conversión, uso en PHP y JavaScript, seguridad y límites.
- [ ] Panel administrativo: comprobar que la guía revisada cubra funcionamiento, uso y ampliación, no solo presentación.
- [ ] Heartbeat: documentar ejecución, configuración, canales existentes y creación de canales propios, con ejemplos comprobados.
- [ ] Identificar el componente al que corresponde «Power Utility» antes de documentarlo; no atribuirle funciones por su nombre.
- [ ] Media Library: explicar biblioteca, subida, selectores, ámbitos compartidos/usuario/tenant, configuración y ampliación.
- [ ] Integraciones y componentes externos: explicar el puente con GFrame, indicar la versión incluida y enlazar la documentación oficial correspondiente a esa versión cuando exista. No reproducir toda la documentación del proveedor.
- [ ] Metas de vistas: guía propia para capas de grupo/vista, combinación y prioridad, etiquetas, schema y carga de CSS/JavaScript por vista. Enlazarla desde Render y SEO sin duplicar su contenido.
- [ ] Multilenguaje: verificar detección y declaración del idioma en rutas y cómo se seleccionan vistas, textos y metas. Documentar únicamente lo implementado y señalar lo que requiera código del proyecto.
- [ ] Inventario de cobertura: asegurar una guía para cada componente del núcleo, módulo adicional, conector y mecanismo vigente de ampliación; no documentar como actuales las extensiones retiradas.

Cada guía debe explicar qué hace el componente, cómo funciona, cómo se usa y cómo se amplía, con rutas de archivos y ejemplos de código comprobados. Si un componente no ofrece un contrato de ampliación, indicar el límite en lugar de inventar uno. La separación de assets por vista no justifica promesas de rendimiento sin mediciones.

## Criterio de cierre por tema

Las peticiones nuevas se añaden a esta cola sin descartar las anteriores. Terminar el tema en curso antes de pasar al siguiente; revisar un tema cada vez.

En la presentación de módulos, usar «Módulos del framework», no «módulos originales y personalizaciones separadas». Explicar primero qué ofrecen y cómo se utilizan. Explicar después, en un apartado independiente, cómo ampliar los módulos desde el proyecto mediante los contratos existentes. No presentar la separación de archivos como definición del módulo ni confundir los componentes obligatorios del núcleo con módulos opcionales.

Explicar la tarea del desarrollador, los requisitos, las ubicaciones y un ejemplo comprobado contra el código. Indicar el resultado y los límites relevantes. Una regla se explica en su guía principal y se enlaza desde las demás. Los razonamientos de implementación, progreso y auditoría no se publican como ayuda. No añadir entradas al changelog por correcciones editoriales menores.

## Comprobación de la barra

## Correcciones de lectura y cobertura

- JSON-LD: crear una guía propia junto a SEO, con presets disponibles, tipos soportados, parámetros requeridos, composición y ejemplos comprobados. Mantener el tema separado de autenticación.
- Autenticación: el hallazgo histórico de caducidad del token está resuelto. `validateAcount` comprueba `token_updated_at` mediante `TokenManager::isValidTimestamp`; AuthVerificationTokenTest confirma activación con token vigente y rechazo sin escrituras del caducado (6 de octubre de 2026: 2 pruebas, 6 aserciones).

- Orden de primeros pasos actualizado por petición del usuario: Instalación, Apache y Nginx, Configuración. Apache utiliza el .htaccess incluido; Nginx requiere integrar el fragmento del proyecto, sin modificar la configuración PHP nativa del panel.

- WordPress headless: al revisar su guía, enlazar el plugin oficial BridgeFrame en https://github.com/gorvet/bridgeframe. Incorporar sus contratos comprobados sin mezclar este tema con la revisión actual de multilenguaje.
- Multilenguaje: guía propia con traducciones en la aplicación, idioma en rutas/controladores/metas, AJAX y límites de hreflang/sitemap; sin cambios funcionales en el core.

- Contrato SEO acordado e implementado: configuración global `enabled` / `allow_indexing`, decisión por ruta `context.seo.indexable`, metas solo descriptivas y JSON-LD. Robots permanece con Disallow cuando el sitio bloquea indexación; JSON-LD se omite con SEO desactivado. Guías y skill canónica actualizadas; SeoPolicyTest cubre la matriz global y la coherencia HTML/sitemap/llms. Pendiente de publicación del paquete, sin tocar el core de proyectos instalados.

- Retiradas las aclaraciones repetidas sobre la capa PHP en Router, middleware y ORM.
- Configuración colocada inmediatamente después de Instalación en la navegación; índice conservado como fuente de enlaces, sin entrada redundante en el lateral.
- Ampliada la referencia ORM: condiciones, selección, resultados, agregados, paginación, lotes, escrituras y casts. Ejemplos PHP comprobados por sintaxis y ejecución SQLite.
- Añadido el recorrido de los listados AJAX y reemplazo de HTML parcial en Frontend core.
- Aclaradas las opciones globales SEO y las exclusiones de ruta, sitemap, llms y meta robots.
- Añadida una introducción práctica a las sesiones. Su revisión completa y los restantes temas del inventario siguen pendientes.

## Evidencia visual de la barra

VERDICT: PASS

- SCOPE / EVIDENCE: capturas y DOM de la barra a 1200 px; panel móvil abierto a 390 px; cierre mediante Escape y devolución del foco al botón.
- COMPOSITION: identidad y enlaces a la izquierda, buscador en el flujo central y GitHub con etiqueta de revisión a la derecha. Se conserva el fondo primario del proyecto. El panel móvil mide 280 px y deja visible el fondo de bloqueo.
- FINDINGS / CORRECTIONS: retirado el centrado absoluto del buscador y la versión del logo; sustituido collapse por offcanvas izquierdo de Bootstrap. Medidas DOM a 1200 px confirman separación entre menú, buscador y GitHub, sin desbordamiento horizontal.
- LIMITS / DEFERRALS: comprobación local, no despliegue de producción. La revisión completa del contenido sigue pendiente según la lista anterior.
# Revisión de JSON-LD

Guía pública en `docs/json-ld.md`, situada junto a SEO. Ejemplos comprobados contra SchemaComposer y JsonLD. Pendientes funcionales detectados: presets compuestos sin resolución recursiva, SearchAction por defecto aunque no exista buscador, valoración incompleta del molde SaaS y filtros que omiten cero o false. No se modificó el core para estos puntos.
