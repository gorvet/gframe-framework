# Skills oficiales de GFrame

Los skills de `skills/` se versionan junto con el framework y son la fuente oficial para Codex y Claude Code.

## Skills incluidos

- `gframe-core-architecture`: arranque, configuración, rutas, middleware y conexión entre framework y aplicación.
- `gframe-backend`: controladores, servicios, respuestas HTTP y permisos.
- `gframe-orm-models`: modelos, consultas, conexiones, dialectos y transacciones.
- `gframe-auth-access`: autenticación, sesiones, roles, permisos y cuenta propia.
- `gframe-frontend-admin`: vistas administrativas, meta, formularios, validación HTML5, AJAX y feedback.
- `gframe-frontend-public`: vistas públicas, recursos, metadatos, SEO y contenido.
- `gframe-ui-design-clean`: organización de pantallas y patrones Bootstrap adaptados al proyecto.
- `gframe-framework-maintenance`: mantenimiento del paquete, pruebas, documentación y publicación.
- `gframe-media-module`: biblioteca multimedia, ámbitos, cargas, selectores y relaciones.

Los skills guían al asistente de desarrollo; no son módulos PHP ni recursos que se carguen al ejecutar la aplicación.

Cada carpeta contiene `SKILL.md` y, cuando corresponde, referencias con contratos y ejemplos. Las [guías del framework](index.md) explican las APIs para el desarrollador; los skills indican al asistente qué convenciones aplicar al implementar esas APIs. Ambos se mantienen junto con la versión del paquete.

## Instalación

Desde PowerShell, situado en la raíz del repositorio del framework o en `packages/gorvet/gframe` de un proyecto instalado:

```powershell
.\bin\install-skills.ps1 -Target Codex
.\bin\install-skills.ps1 -Target Claude
.\bin\install-skills.ps1 -Target All
```

El instalador copia los nueve directorios `gframe-*` a los skills del usuario. Para Codex utiliza `CODEX_HOME/skills` si esa variable está definida, o `.codex/skills` en el perfil del usuario. Para Claude Code utiliza `.claude/skills` en ese perfil.

Sobrescribe los archivos de los skills de GFrame con los de la versión seleccionada y conserva los demás skills. Si modificaste una copia instalada, guarda esos cambios antes de ejecutar el script. El script no instala el framework en un proyecto ni cambia su configuración.

Después de actualizar GFrame, vuelve a ejecutarlo desde la versión que utilice tu proyecto para mantener alineados los contratos. En otros sistemas puedes copiar las carpetas `skills/gframe-*` completas al directorio de skills de tu asistente, conservando sus referencias.

## Utilizarlos en una tarea

Puedes indicar el skill por su nombre al solicitar trabajo. Por ejemplo:

```text
Usa $gframe-frontend-admin para añadir un listado de productos con filtros,
paginación AJAX y feedback del framework.
```

Una tarea puede necesitar varios: el listado usa las convenciones de frontend, el controlador las de backend y la consulta las del ORM. El asistente debe leer las instrucciones y las referencias aplicables antes de implementar.

Las instrucciones específicas del proyecto pertenecen a `AGENTS.md`, por ejemplo el ámbito de trabajo y las decisiones de identidad visual. Conserva los contratos técnicos en los skills, evitando copiarlos en varios archivos que después puedan contradecirse.

El skill `gframe-ui-design-clean` incluye criterios de UX y patrones Bootstrap para formularios, filtros, resultados, tablas, vistas con lateral, estados y responsive. Debe adaptarse a la identidad visual de cada aplicación; no funciona como una plantilla estética cerrada.

## Validación

```bash
composer skills:check
```

`composer check` también valida los skills. La comprobación valida su estructura y referencias; no sustituye las pruebas de una implementación. Al modificar rutas, contratos, configuración, permisos, componentes o flujos documentados, revisa el skill correspondiente.
