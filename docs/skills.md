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
- `gframe-notifications-mail`: correo, plantillas, inbox y colas de entrega; distingue lanzamiento, persistencia y envío.
- `gframe-background-jobs`: Async, cron persistente, workers y canales heartbeat, con sus límites de ejecución y recuperación.
- `gframe-campaigns`: audiencias, campañas, recurrencia, reglas automáticas e integración del ciclo de desactivación.
- `gframe-integrations`: HTTP saliente, WordPress/BridgeFrame y canales entrantes API, webhook y SSE.
- `gframe-orchestrator`: identifica proyecto/versión y selecciona las skills necesarias para la tarea, sin ampliar su alcance.

Los skills guían al asistente de desarrollo; no son módulos PHP ni recursos que se carguen al ejecutar la aplicación.

Las skills de etapas y los complementos externos de UX se coordinan cuando la tarea los requiere y están disponibles; no se copian dentro de GFrame. Las etapas gestionan conciliación, tareas y auditoría; GFrame conserva los contratos técnicos. Un pipeline UX invocado conserva sus propias comprobaciones junto con las convenciones frontend de GFrame.

Cada carpeta contiene `SKILL.md` y, cuando corresponde, referencias con contratos y ejemplos. Las [guías del framework](index.md) explican las APIs para el desarrollador; los skills indican al asistente qué convenciones aplicar al implementar esas APIs. Ambos se mantienen junto con la versión del paquete.

## Instalación

### Por proyecto

Para mantener las instrucciones del paquete instalado en cada aplicación, indica su raíz y revisa primero la vista previa:

```powershell
.\bin\install-skills.ps1 -ProjectPath 'C:/ruta/al/proyecto' -Target All -DryRun
.\bin\install-skills.ps1 -ProjectPath 'C:/ruta/al/proyecto' -Target All
```

`-Target Codex` escribe en `.agents/skills`; `-Target Claude`, en `.claude/skills`. `All` procesa ambos. `-Json` devuelve el informe estructurado, también junto con `-DryRun`. La vista previa no crea carpetas ni registros ni modifica archivos; la ejecución vuelve a calcular el plan con la fuente actual.

La fuente se obtiene de `composer.json`, su `config.vendor-dir` y la metadata `composer/installed.json` de `gorvet/gframe`, incluida su ruta instalada. No utiliza automáticamente la versión del checkout desde el que ejecutas el script ni el lock como prueba de instalación. Si el destino es el propio paquete standalone, utiliza sus skills y deja la versión desconocida cuando no está declarada. No ejecuta PHP, autoload ni bootstrap: una aplicación con un bridge personalizado debe contrastar además qué paquete carga realmente.

Cada cliente guarda el registro `gframe-skills.json` junto a su carpeta `skills`, con fuente, versión/referencia disponibles y hashes SHA-256. El plan distingue `create`, `update`, `unchanged`, `remove`, `forget` y `conflict`. Solo actualiza archivos administrados intactos y retira archivos obsoletos cuyo hash coincide con el registro. Conserva personalizaciones y skills ajenas; una carpeta preexistente sin registro no se adopta ni se completa automáticamente. Los conflictos requieren revisión y no significan que todas las instrucciones quedaron sincronizadas.

El modo rechaza registros/rutas inválidos y enlaces simbólicos o junctions en fuente/destino; no escribe fuera del destino a través de ellos. Una operación interrumpida puede dejar cambios parciales y exige revisar el informe, los archivos y el registro antes de repetirla; no ofrece una transacción de todos los archivos. Los directorios vacíos pueden conservarse. No modifica ni desactiva skills globales: revisa posibles duplicados sin asumir que una copia local oculta otra.

### Global, por compatibilidad

Desde PowerShell, situado en la raíz del repositorio del framework o en `packages/gorvet/gframe` de un proyecto instalado:

```powershell
.\bin\install-skills.ps1 -Target Codex
.\bin\install-skills.ps1 -Target Claude
.\bin\install-skills.ps1 -Target All
```

El instalador copia los directorios `gframe-*` incluidos en la versión elegida a los skills del usuario. Para Codex utiliza `CODEX_HOME/skills` si esa variable está definida, o `.codex/skills` en el perfil del usuario. Para Claude Code utiliza `.claude/skills` en ese perfil.

Sobrescribe los archivos de los skills de GFrame con los de la versión seleccionada y conserva los demás skills. Si modificaste una copia instalada, guarda esos cambios antes de ejecutar el script. El script no instala el framework en un proyecto ni cambia su configuración.

Después de actualizar GFrame, vuelve a ejecutarlo desde la versión que utilice tu proyecto para mantener alineados los contratos. En otros sistemas puedes copiar las carpetas `skills/gframe-*` completas al directorio de skills de tu asistente, conservando sus referencias.

Sin `-ProjectPath` se conserva el modo global anterior, que sobrescribe copias de GFrame. `-DryRun` y `-Json` requieren el modo por proyecto. Ninguno de los modos genera o publica un complemento.

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

La instalación por proyecto tiene comprobaciones independientes de filesystem y procesos PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/InstallProjectSkillsTest.ps1
```

Estas pruebas usan proyectos y destinos temporales; no instalan skills en aplicaciones reales ni acreditan su carga en la interfaz del cliente.


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

## Generar el complemento

Desde el checkout del framework, `bin/build-skills-plugin.ps1` genera una carpeta independiente con las catorce skills propias, `plugin.json` portable, `.claude-plugin/plugin.json` y `gframe-skills-source.json` con procedencia y hashes SHA-256. Las skills se copian intactas; stages y UX permanecen externos. Los manifiestos siguen los formatos oficiales de [OpenAI](https://developers.openai.com/plugins/build/plugins) y [Claude](https://code.claude.com/docs/en/plugins-reference).

Codex expone las skills del complemento con su namespace, por ejemplo `gframe-skills:gframe-backend` y `gframe-skills:gframe-orchestrator`. Utiliza el nombre exacto que muestra el cliente; los nombres de carpetas canónicos siguen siendo `gframe-backend` y `gframe-orchestrator`. Una copia global con nombre parecido no demuestra que proceda del mismo paquete.

Indique una versión de distribución SemVer y una carpeta nueva fuera del repositorio, cuyo padre ya exista. Este ejemplo usa una versión de prueba, no una release publicada:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File bin/build-skills-plugin.ps1 -Version 0.0.0-preview -OutputPath 'C:/ruta/existente/gframe-skills-preview' -DryRun
powershell -NoProfile -ExecutionPolicy Bypass -File bin/build-skills-plugin.ps1 -Version 0.0.0-preview -OutputPath 'C:/ruta/existente/gframe-skills-preview'
```

La vista previa no escribe archivos. La ejecución rechaza destinos existentes, referencias que salgan de las skills, catálogos inesperados y enlaces/junctions. Verifica hashes al copiar; una interrupción puede dejar una carpeta parcial que debe revisarse, no distribuirse. No sobrescribe, instala ni publica el resultado. El registro identifica el HEAD cuando está disponible y el contenido efectivo, incluidos cambios sin commit; no afirma que el checkout sea una release limpia ni que esa versión corresponda a una aplicación.

Compruebe el generador con `powershell -NoProfile -ExecutionPolicy Bypass -File tests/BuildSkillsPluginTest.ps1`. La estructura y las copias verificadas no prueban carga o selección de skills en los clientes; esa integración se comprueba por separado.

## Validación y revisión manual

`composer skills:check` detecta problemas estructurales y referencias inválidas, pero no demuestra que cada recomendación siga siendo la mejor práctica. Durante una modificación importante:

1. ejecute la validación;
2. abra las referencias afectadas;
3. compare ejemplos con código/tests actuales;
4. compruebe que no se conservan comandos o rutas retirados;
5. confirme que el skill no contradice la documentación canónica.

El verificador PHP revisa enlaces locales en todos los Markdown de las skills, incluidas referencias, imágenes y definiciones de enlaces. Comprueba que los archivos existan dentro de la carpeta distribuida de skills; un enlace entre especialistas requiere que su compañero esté copiado. Omite ejemplos en bloques de código y enlaces HTTP/mailto; no visita servicios externos ni valida fragmentos de encabezados. No es un parser completo de Markdown ni evalúa APIs, recetas o selección por un agente.

Para comprobar una copia fuera del checkout:

```powershell
php bin/validate-skills.php --root 'C:/ruta/al/complemento/skills'
```

La metadata YAML tiene un control separado con Python 3.9 o posterior y PyYAML disponible en ese intérprete. El comando PHP y Composer no adquieren esa dependencia:

```powershell
python bin/validate-skills-metadata.py
python bin/validate-skills-metadata.py --root 'C:/ruta/al/complemento/skills'
```

El control YAML rechaza sintaxis inválida, claves duplicadas, secciones/tipos incorrectos y rutas de iconos fuera de la skill. Comprueba campos conocidos de interfaz, política y herramientas cuando existen. `{}` sigue siendo metadata válida y no se exige inventar textos de interfaz, dependencias o políticas. Campos desconocidos se conservan sin certificar su semántica. Las pruebas específicas son `tests/SkillsValidatorTest.php` y `python -m unittest discover -s tests -p test_skill_metadata.py`, esta última con PyYAML disponible.

## Skills de proyecto

Las reglas específicas de una aplicación —estructura propia, tenant, identidad visual, convenciones internas o endpoints del negocio— no deben añadirse a los skills globales del framework salvo que se conviertan en un contrato reutilizable de GFrame. Manténgalas en las instrucciones del proyecto y deje que los skills oficiales describan únicamente el framework.
