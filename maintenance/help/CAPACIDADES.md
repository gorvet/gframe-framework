# Capacidades: presencia y profundidad de la ayuda

Contraste inicial: 20 de octubre de 2026. Esta matriz complementa el informe de páginas generado por el sitio. Registra revisión documental, fuentes consultadas e ilustraciones; no certifica una auditoría funcional exhaustiva de cada API ni del framework completo.

## Ausencias confirmadas en la navegación anterior

Existían ocho guías públicas que el sitio no incorporaba al catálogo: autenticación PHP, acceso API, cliente HTTP saliente, cifrado, perfiles de instalación, dependencias frontend, helpers de compatibilidad e integración entre editor y multimedia. El catálogo ahora las recoge. Esto corrige su descubrimiento; no equivale a escribir ocho guías nuevas.

## Funciones que merecen hacerse visibles

Las rutas siguientes son relativas a la raíz del sitio de ayuda. Los apartados nombrados se han localizado en los Markdown del framework. La columna pendiente indica el siguiente trabajo documental, sin afirmar que la capacidad esté ausente del framework.

| Área y capacidad | Ayuda existente | Pendiente documental o visual |
| --- | --- | --- |
| Elegir el perfil de instalación | `/guias/perfiles-instalacion`: capacidades de cada perfil | Añadido recorrido de elección y límites sobre entidades y tenancy; texto pendiente de distribución del paquete. |
| Personalizar módulos por herencia y mantener originales separados | `/guias/modulos-runtime`: namespaces, carpetas de personalización, actualización y migración | Esquema integrado y recorrido de actualización con vista propia añadido al Markdown canónico; texto pendiente de distribución del paquete. |
| Conexiones, transacciones, dialectos y procesamiento por lotes | `/guias/orm`: apartados específicos de cada función | Revisados conexión, transacción, estado de consulta, lotes y dialectos contra ORM. Esquema integrado y criterios prácticos ampliados; pendiente publicar el paquete. |
| Revocar dispositivos y limitar inactividad | `/guias/sesiones`: caducidad y revocación | Añadido recorrido de cierre, revocación y suspensión, sin atribuir una pantalla nativa. Texto pendiente de distribución del paquete; comprobación funcional de los ejemplos no repetida. |
| Permisos globales, por tenant y excepciones | `/guias/permisos`: crear tenant, vincularlo, administrar roles y excepciones | Añadido caso de dos tenants, excepción y retirada, con límites del actor y efecto sobre sesiones. Contraste con RoleModel/UserPermissionService; conserva el esquema de resolución existente. |
| Consumidores API, scopes, CORS y proveedores propios | `/guias/api-access`: apartados específicos | Recorrido de autenticación y autorización ampliado; esquema integrado también en cliente HTTP. Las denegaciones siguen en su tabla canónica. Texto nuevo pendiente de distribución del paquete. |
| Archivos y galerías: propietarios, orden, atributos y archivos remotos | `/modulos/media-library`: biblioteca/propietario, selección, galerías, seguridad | Recorte corregido; nueva captura nativa de atributos guardados y esquema archivo/selección/formulario. Tabla de efectos y límites del selector contrastada con su contrato. |
| Búsqueda que normaliza tildes, pondera campos y genera fragmentos | `/modulos/lexical-search`: ejemplo PHP, opciones y filtro en navegador | Añadida elección PHP/JS y orden de autorización/búsqueda/paginación. Captura integrada con dos resultados calculados por el motor original e interfaz de ejemplo identificada. Texto nuevo pendiente de distribución del paquete. |
| Correo directo, en segundo plano, plantillas y adjuntos | `/guias/mail`: método, feedback, adjuntos, plantillas | Revisados métodos y códigos de MailService. Esquema de solicitud/worker/SMTP integrado; sin afirmar recepción ni realizar envíos reales. |
| Campañas con audiencias, recurrencia, reglas y prevención de duplicados | `/modulos/notification-campaigns`: audiencias, reglas, recurrencia y estados | Revisados despacho, deduplicación y estado completed frente a cola/entrega. Formulario nativo y esquema del flujo integrados; sin programar campañas reales. |
| Cron persistente: concurrencia, recuperación y ejecución real/virtual | `/modulos/cron-runner`: apartados específicos | Revisados disparador real/virtual y ejecutor CLI/HTTP contra VirtualCron y configuración documentada. Esquema de caminos y criterios de elección integrados. |
| Sitemap dinámico, robots, llms.txt e indexación por ruta | `/guias/seo`: rutas del sistema, proveedores y política de indexación | Contraste con SeoPolicy y políticas documentadas. Tabla pública/privada/excluida y esquema global/ruta/salidas integrados. |
| Metas dinámicas y deduplicación de recursos | `/guias/meta`: prioridades, recursos, robots y JSON-LD | Contraste con Meta y resolución de archivos documentada. Recorrido de ficha, cinco capas y deduplicación ilustrados. |
| Editor: guardado, recuperación, multimedia y contenido dinámico | `/modulos/rich-text-editor` y guías de contenido | Preview y márgenes corregidos; estilos de lectura identificados como propios del ejemplo. Se conserva la separación entre componente nativo y plantilla del proyecto. |
| Componentes: selección, tablas y feedback | Guías de GFSelect, GFTable y alertas | GFSelect abierto completo y toast ya ilustrados; las capturas corresponden a los componentes reales. Estados adicionales conservados como ampliación posterior. |

## Evidencia y mantenimiento de cobertura

### Cierre documental de los puntos 2 y 3

Revisados permisos por ámbito, multimedia, correo, campañas, cron, SEO y metas. Se completó además la explicación de lotes y dialectos del ORM. Cada área mantiene su guía canónica y apoyo visual: seis esquemas nuevos y una captura de atributos multimedia guardados mediante el endpoint original en una aplicación temporal. No se añadieron funciones al runtime ni se atribuyeron pantallas inexistentes.

Fuentes: `RoleModel`, `UserPermissionService`, componentes y controlador multimedia, `MailService`, `CampaignService`, `NotificationQueueWorker`, `VirtualCron`/`CronRuntime`, `SeoPolicy`, `Meta` y `ORM::chunkById()`. No se enviaron correos ni campañas ni se ejecutaron tareas de negocio. La publicación permanece aplazada por instrucción del usuario. El inventario conserva los estados visuales adicionales para ampliación posterior.

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
