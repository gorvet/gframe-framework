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


## Relación entre skills y documentación

La documentación explica el framework a una persona; un skill traduce esos contratos en instrucciones operativas para un asistente. No debe inventar una API paralela. Si una referencia del skill contradice `docs/` o el runtime, corrija la fuente y el skill en la misma rama.

Cuando una guía cambia de forma material, revise qué skills dependen de ese contrato. Ejemplos:

- cambios de rutas o middleware -> `gframe-core-architecture` y `gframe-backend`;
- cambios de ORM -> `gframe-orm-models`;
- autenticación o permisos -> `gframe-auth-access`;
- metas, vistas o AJAX -> skills frontend;
- módulos multimedia -> `gframe-media-module`.

## Contenido de un skill

Un skill debe concentrarse en decisiones que el asistente necesita aplicar: archivos correctos, contratos, límites, flujo recomendado, referencias y criterios de comprobación. Evite copiar capítulos completos de documentación dentro de `SKILL.md`; enlace referencias versionadas para reducir divergencia.

Los ejemplos deben corresponder a APIs reales y utilizar nombres genéricos cuando no formen parte del framework. No convierta una decisión específica de una aplicación en regla global de GFrame.

## Evolución y compatibilidad

Los skills viajan con cada versión del paquete. Un proyecto que usa una versión anterior debe instalar los skills de esa versión si quiere que el asistente trabaje con sus contratos reales. Instalar los skills de `main` mientras la aplicación permanece en una release antigua puede sugerir APIs todavía no disponibles.

Después de actualizar el framework en un proyecto, reinstale los skills desde `packages/gorvet/gframe` para alinear las instrucciones locales con el código resuelto por Composer.

## Validación y revisión manual

`composer skills:check` detecta problemas estructurales y referencias inválidas, pero no demuestra que cada recomendación siga siendo la mejor práctica. Durante una modificación importante:

1. ejecute la validación;
2. abra las referencias afectadas;
3. compare ejemplos con código/tests actuales;
4. compruebe que no se conservan comandos o rutas retirados;
5. confirme que el skill no contradice la documentación canónica.

## Skills de proyecto

Las reglas específicas de una aplicación —estructura propia, tenant, identidad visual, convenciones internas o endpoints del negocio— no deben añadirse a los skills globales del framework salvo que se conviertan en un contrato reutilizable de GFrame. Manténgalas en las instrucciones del proyecto y deje que los skills oficiales describan únicamente el framework.
