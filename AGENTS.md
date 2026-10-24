# Instrucciones de trabajo

- No expliques salvo que el usuario lo pida; comunica avances breves cuando sea necesario.
- En este repositorio se puede modificar el core del framework para las mejoras solicitadas. No modifiques el core de proyectos instalados. Si hay dudas sobre cómo operar, pregunta.
- Trabaja en el framework, no en demos ni copias instaladas, salvo autorización expresa.
- Conserva los cambios existentes y las tareas pendientes. Una petición nueva no elimina las anteriores.
- Si solo hay que trasladar o reproducir un archivo, copia o mueve el original intacto; no lo transcribas ni lo reinterpretes. Después aplica únicamente los cambios puntuales necesarios. No vuelques archivos completos en los parches para cambiar unas pocas líneas.

Las convenciones técnicas de vistas y sus comprobaciones se mantienen en la skill canónica `skills/gframe-frontend-admin/`, no se duplican aquí.

Para cada tarea de desarrollo, corrección o revisión de GFrame, aplica `skills/gframe-orchestrator/SKILL.md` y los especialistas que seleccione antes de editar. Las skills canónicas están en `skills/`; Composer las activa localmente en `.agents/skills` y `.claude/skills`. No uses copias globales de otra versión.

## Coordinación entre asistentes

Si otro asistente puede estar trabajando en paralelo, usa el canal compartido antes de editar: `php bin/agent-coord.php status` al empezar, `claim` sobre los archivos que vayas a tocar y `release` al terminar. Las reservas caducan solas. Trabaja en tu propia rama y worktree para no pisar a los demás. Detalles y hook opcional en [maintenance/coordinacion-agentes.md](maintenance/coordinacion-agentes.md).
