# Capacidades: presencia y profundidad de la ayuda

Contraste inicial: 20 de octubre de 2026. Esta matriz complementa el informe de páginas generado por el sitio. Una página por módulo no acredita que todas sus funciones estén explicadas. La revisión actual localiza documentación y apartados; todavía no certifica todas las recetas contra el código ni su reproducción.

## Ausencias confirmadas en la navegación anterior

Existían ocho guías públicas que el sitio no incorporaba al catálogo: autenticación PHP, acceso API, cliente HTTP saliente, cifrado, perfiles de instalación, dependencias frontend, helpers de compatibilidad e integración entre editor y multimedia. El catálogo ahora las recoge. Esto corrige su descubrimiento; no equivale a escribir ocho guías nuevas.

## Funciones que merecen hacerse visibles

Las rutas siguientes son relativas a la raíz del sitio de ayuda. Los apartados nombrados se han localizado en los Markdown del framework. La columna pendiente indica el siguiente trabajo documental, sin afirmar que la capacidad esté ausente del framework.

| Área y capacidad | Ayuda existente | Pendiente documental o visual |
| --- | --- | --- |
| Elegir el perfil de instalación | `/guias/perfiles-instalacion`: capacidades de cada perfil | Añadido recorrido de elección y límites sobre entidades y tenancy; texto pendiente de distribución del paquete. |
| Personalizar módulos por herencia y mantener originales separados | `/guias/modulos-runtime`: namespaces, carpetas de personalización, actualización y migración | Esquema integrado y recorrido de actualización con vista propia añadido al Markdown canónico; texto pendiente de distribución del paquete. |
| Conexiones, transacciones, dialectos y procesamiento por lotes | `/guias/orm`: apartados específicos de cada función | Esquema integrado y explicación del ámbito de conexión/estado añadida al Markdown canónico. Queda profundizar en lotes y dialectos; texto pendiente de distribución del paquete. |
| Revocar dispositivos y limitar inactividad | `/guias/sesiones`: caducidad y revocación | Añadido recorrido de cierre, revocación y suspensión, sin atribuir una pantalla nativa. Texto pendiente de distribución del paquete; comprobación funcional de los ejemplos no repetida. |
| Permisos globales, por tenant y excepciones | `/guias/permisos`: crear tenant, vincularlo, administrar roles y excepciones | Ejemplo guiado de dos ámbitos y efecto de cambiar permisos sobre una sesión. El esquema actual no sustituye ese recorrido. |
| Consumidores API, scopes, CORS y proveedores propios | `/guias/api-access`: apartados específicos | Recorrido de autenticación y autorización ampliado; esquema integrado también en cliente HTTP. Las denegaciones siguen en su tabla canónica. Texto nuevo pendiente de distribución del paquete. |
| Archivos y galerías: propietarios, orden, atributos y archivos remotos | `/modulos/media-library`: biblioteca/propietario, selección, galerías, seguridad | Las capturas actuales no muestran todas estas operaciones. Sustituir la captura recortada y añadir selección/atributos. |
| Búsqueda que normaliza tildes, pondera campos y genera fragmentos | `/modulos/lexical-search`: ejemplo PHP, opciones y filtro en navegador | Añadida elección PHP/JS y orden de autorización/búsqueda/paginación. Captura integrada con dos resultados calculados por el motor original e interfaz de ejemplo identificada. Texto nuevo pendiente de distribución del paquete. |
| Correo directo, en segundo plano, plantillas y adjuntos | `/guias/mail`: método, feedback, adjuntos, plantillas | Esquema que distinga aceptación del trabajo y entrega SMTP. La imagen de una plantilla no explica el transporte. |
| Campañas con audiencias, recurrencia, reglas y prevención de duplicados | `/modulos/notification-campaigns`: audiencias, reglas, recurrencia y estados | Ilustrar el flujo completo y revisar las instrucciones de operación. Una captura del formulario no cubre todo el módulo. |
| Cron persistente: concurrencia, recuperación y ejecución real/virtual | `/modulos/cron-runner`: apartados específicos | Esquema de los caminos de ejecución y explicación práctica de elección/configuración. |
| Sitemap dinámico, robots, llms.txt e indexación por ruta | `/guias/seo`: rutas del sistema, proveedores y política de indexación | Ejemplo de página pública frente a privada y diagrama de generación; no necesita una captura administrativa. |
| Metas dinámicas y deduplicación de recursos | `/guias/meta`: prioridades, recursos, robots y JSON-LD | Recorrido vista → grupo → plantilla y resultado HTML. |
| Editor: guardado, recuperación, multimedia y contenido dinámico | `/modulos/rich-text-editor` y guías de contenido | Corregir preview y márgenes; conservar la distinción entre editor y plantilla pública del proyecto. |
| Componentes: selección, tablas y feedback | Guías de GFSelect, GFTable y alertas | Mostrar GFSelect abierto y toast; revisar estados útiles, no solo el componente cerrado o un diálogo. |

## Revisión pendiente antes de declarar cobertura completa

### Primer lote de ampliación

Se ampliaron los Markdown canónicos del framework para perfiles de instalación, runtime de módulos, ORM y sesiones. Incluyen elección según necesidades, actualización con una vista propia, alcance de transacciones/estado de consulta y diferencias entre cerrar, revocar y suspender. Se contrastaron con `resources/install/profiles.php`, `ModuleRuntime`, el controlador original de Mi cuenta, `ORM::reset()` y los contratos de sesión documentados. No se ejecutaron operaciones sobre cuentas ni bases de datos de aplicaciones.

El sitio incorpora dos esquemas nuevos: resolución de personalizaciones y alcance de una transacción. El texto ampliado se ve localmente desde el repositorio; en producción seguirá llegando desde la versión instalada de Composer hasta que se distribuya una versión que incluya estos Markdown. Tras la centralización, los esquemas también se distribuyen desde el paquete del framework. No se duplicaron ni sustituyeron archivos del paquete instalado.

### Segundo lote de ampliación

API entrante, HTTP saliente y búsqueda se contrastaron con sus implementaciones, sin atribuir al framework endpoints de pedidos, pantallas de tokens/búsqueda, filtros de tenant ni políticas de reintentos. La receta PHP existente se ejecutó con «campañs» y devolvió la guía esperada. La captura adicional calcula dos resultados con el motor original sobre documentos ficticios; su interfaz se identifica como integración de ejemplo. El esquema de canales HTTP se reutiliza en las dos guías de integración.

1. Contrastar las entradas públicas del código con cada guía: opciones, límites, extensión y errores recuperables. El mapa histórico del framework es una pista, no una certificación actual.
2. Para cada capacidad, clasificar por separado: sin explicación, explicación parcial, receta reproducible y apoyo visual adecuado. No reducirlo a «tiene página».
3. Escribir o completar primero los recorridos de instalación/personalización, identidad/permisos, datos, procesos e integraciones. Mantener contenido/editor como otra área, sin concentrar toda la revisión allí.
4. Registrar por archivo las capturas o esquemas incorporados en [el inventario visual](../../docs/assets/INVENTARIO.md).

No se consideran funciones existentes las propuestas aplazadas: barra administrativa pública, salud del sitio y módulo de redirecciones. Tampoco se atribuye una pantalla nativa a servicios PHP que no la proporcionan.
