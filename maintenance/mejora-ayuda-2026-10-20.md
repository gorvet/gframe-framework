# Ampliación de recorridos de ayuda

Alcance: completar la matriz del sitio de documentación, sin modificar código del core ni aplicaciones instaladas. Base documental: 4.3.1 y rama actual. Se aplican `skills/gframe-orchestrator/SKILL.md` y `skills/gframe-framework-maintenance/SKILL.md`, con sus referencias de documentación y flujo del repositorio.

Primer lote: elección de perfil, personalización y actualización de módulos, uso de conexiones/transacciones y revocación de sesiones. Contrastar nombres, restricciones y comportamiento con los manifiestos y las implementaciones correspondientes. Revisar enlaces y renderizado documental; no ejecutar suites generales, Composer ni publicar una versión implícitamente.

El sitio consume Markdown del paquete publicado en producción. Las ampliaciones de estos Markdown se ven localmente, pero requieren una futura distribución del paquete para llegar a producción. Los esquemas propios del sitio sí pueden desplegarse con su Git. No duplicar las guías en el sitio ni modificar su vendor para eludir esa separación.

## Evidencia del primer lote

Se ampliaron `docs/perfiles-instalacion.md`, `docs/modulos-runtime.md`, `docs/orm.md` y `docs/sesiones.md`. Sin cambios de API: no es necesario modificar skills ni añadir changelog de versión. Los esquemas del sitio documentan decisiones y límites, no interfaces nuevas.

La revisión documental del sitio local detecta 104 páginas y 691 enlaces, sin rutas ni fragmentos pendientes. Se renderizaron módulos runtime y ORM en navegador, con H1 único y canonical; la vista móvil del primero no presenta desbordamiento horizontal. Se inspeccionó visualmente el esquema de resolución integrado. No se realizaron actualizaciones reales de proyectos, mutaciones de cuentas ni transacciones de negocio; no se repitieron suites generales.

La matriz del sitio conserva las áreas restantes: roles por ámbito, API, multimedia, búsqueda, correo, campañas, cron, SEO y metas. Los ejemplos ampliados de este lote todavía no están en un tag publicado.

## Segundo lote: integraciones y búsqueda

Se amplían `api-access.md`, `http-client.md` y `lexical-search.md`. Fuentes contrastadas: `RouteApiCredentialProvider::authenticate()`, el middleware Bearer/CORS y su contexto de salida, `HttpClient::request()` y `LexicalSearchEngine::rank()`. Se usa además `skills/gframe-integrations/SKILL.md`. Se explicita qué aporta el framework y qué implementa el proyecto; no se añaden endpoints, pantallas, reintentos ni filtros automáticos inexistentes.

La receta PHP publicada de búsqueda se ejecutó sin aplicación instalada: «campañs» devuelve «Gestión de campañas», con `_search_score` y `snippet`. El sitio añade una captura de una interfaz expresamente identificada como ejemplo, con dos resultados calculados por el motor original, y un esquema compartido de canales HTTP. No se contactaron servicios externos ni se usaron credenciales reales.

## Centralización de la ayuda

La aclaración posterior del usuario sustituye la separación anterior: guías, imágenes, esquemas, pies e inventario pertenecen al framework. Se trasladaron intactos los 24 archivos visuales desde el sitio a `docs/assets/4.3.1/`; los 26 usos visuales se incorporaron a 25 Markdown canónicos. El inventario queda en `docs/assets/INVENTARIO.md` y los generadores, ejemplos, plan y matriz en `maintenance/help/`.

El sitio elimina `_screenshots.php` y sus copias públicas. Su repositorio documental valida los recursos dentro de `docs/assets/`, registra solo rutas concretas de imágenes y sirve el archivo de la misma fuente que los Markdown. No permite descargar Markdown, recorrer directorios ni salir de la raíz de recursos. El renderizado convierte las rutas relativas canónicas en figuras web; GitHub y otros lectores conservan Markdown estándar.

El cambio no publica un tag ni altera el paquete instalado. En producción se necesita distribuir e instalar una versión del framework que incluya estos recursos antes de activar la nueva presentación del sitio. La política se incorpora a la referencia canónica de mantenimiento para evitar nuevas copias independientes.

Verificación focalizada: SHA-256 de los 24 recursos coincide con sus originales del último commit del sitio. Un `git archive` incluye los 24 recursos y el inventario, con los mismos bytes. Contra esa distribución extraída, el sitio resuelve 25 guías con 26 figuras y sus pies; rechaza recorridos fuera de assets, Markdown y archivos inexistentes. El catálogo conserva 104 páginas y 719 enlaces sin rutas/fragmentos pendientes. Se renderizaron GFSelect, Alerts y ORM, con revisión móvil del primero e inspección visual de la imagen servida desde el framework. No se ejecutaron suites generales ni Composer.
