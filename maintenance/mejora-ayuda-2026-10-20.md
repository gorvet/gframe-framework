# Ampliación de recorridos de ayuda

Alcance: completar la matriz del sitio de documentación, sin modificar código del core ni aplicaciones instaladas. Base documental: 4.3.1 y rama actual. Se aplican `skills/gframe-orchestrator/SKILL.md` y `skills/gframe-framework-maintenance/SKILL.md`, con sus referencias de documentación y flujo del repositorio.

Primer lote: elección de perfil, personalización y actualización de módulos, uso de conexiones/transacciones y revocación de sesiones. Contrastar nombres, restricciones y comportamiento con los manifiestos y las implementaciones correspondientes. Revisar enlaces y renderizado documental; no ejecutar suites generales, Composer ni publicar una versión implícitamente.

El sitio consume Markdown del paquete publicado en producción. Las ampliaciones de estos Markdown se ven localmente, pero requieren una futura distribución del paquete para llegar a producción. Los esquemas propios del sitio sí pueden desplegarse con su Git. No duplicar las guías en el sitio ni modificar su vendor para eludir esa separación.

## Evidencia del primer lote

Se ampliaron `docs/perfiles-instalacion.md`, `docs/modulos-runtime.md`, `docs/orm.md` y `docs/sesiones.md`. Sin cambios de API: no es necesario modificar skills ni añadir changelog de versión. Los esquemas del sitio documentan decisiones y límites, no interfaces nuevas.

La revisión documental del sitio local detecta 104 páginas y 691 enlaces, sin rutas ni fragmentos pendientes. Se renderizaron módulos runtime y ORM en navegador, con H1 único y canonical; la vista móvil del primero no presenta desbordamiento horizontal. Se inspeccionó visualmente el esquema de resolución integrado. No se realizaron actualizaciones reales de proyectos, mutaciones de cuentas ni transacciones de negocio; no se repitieron suites generales.

La matriz del sitio conserva las áreas restantes: roles por ámbito, API, multimedia, búsqueda, correo, campañas, cron, SEO y metas. Los ejemplos ampliados de este lote todavía no están en un tag publicado.
