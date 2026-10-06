# Distribución de las skills de GFrame

Revisión del 6 de octubre de 2026. Instalación por proyecto implementada en el lote 18; generador en el lote 19. Carga y descubrimiento de las catorce skills comprobados en Codex CLI/app-server aislado; Claude fuera del alcance por petición del usuario. Véase la [evidencia de carga](carga-complemento-codex-20261006.json).

## Contenido y fuente

Distribuir las catorce skills propias desde `skills/gframe-*`, incluido mantenimiento y orquestador. Mantener esa carpeta como única fuente canónica. Un artefacto generado copia los originales completos, con referencias y metadata, sin transcribirlos ni mantener otra edición independiente.

Stages y GORVET UI/UX Designer permanecen externos. No copiar sus archivos ni declararlos dependencias obligatorias. El orquestador los coordina por capacidad cuando están disponibles y corresponden al pedido. La ausencia de un complemento no autoriza instalarlo ni afirmar que se ejecutó.

## Formatos comprobados

OpenAI recomienda actualmente `plugin.json` en la raíz con el esquema Agent Plugins y las skills bajo `skills/`; MCP y otros componentes son opcionales. El formato anterior `.codex-plugin/plugin.json` sigue soportado. [Documentación de empaquetado](https://developers.openai.com/plugins/build/plugins).

Claude Code utiliza `.claude-plugin/plugin.json` y descubre `skills/` en la raíz del complemento. Sus componentes usan el namespace del complemento. [Referencia oficial](https://code.claude.com/docs/en/plugins-reference).

Codex descubre skills locales en `.agents/skills`, admite enlaces simbólicos y puede mostrar dos skills con el mismo nombre sin fusionarlas. [Descubrimiento local](https://learn.chatgpt.com/docs/build-skills).

Claude Code admite skills del proyecto en `.claude/skills`. [Ubicaciones oficiales](https://code.claude.com/docs/en/skills).

Los manifiestos locales instalados muestran ambos formatos de OpenAI: UX usa el portable y plugin-management el anterior. Esta inspección no acredita instalación o compatibilidad de un nuevo paquete GFrame en los clientes.

## Diseño de empaquetado

- Generar un artefacto de skills separado del paquete PHP. Identidad propuesta: `gframe-skills`, conservando los nombres `gframe-*` existentes.
- Generar un manifiesto portable y un adaptador Claude con la misma identidad/versión y contenido. No duplicar las skills por cliente. Verificar cada manifiesto con el cliente correspondiente antes de declarar compatibilidad real.
- No incluir MCP, apps, hooks, ejecutores, stages o UX. No empaquetar el repositorio completo, dependencias PHP, auditorías, `.env` o datos de aplicaciones.
- Recibir una versión de distribución explícita. No inventar una release ni deducirla de la fecha. Registrar raíz/referencia efectiva del framework y hashes del contenido para identificar qué instrucciones se distribuyeron. La versión del complemento no prueba por sí sola la versión de una aplicación.
- Comprobar que el artefacto contiene exactamente las catorce skills previstas y archivos idénticos a la fuente, y que sus enlaces siguen resolviendo. Probar primero en una ubicación temporal; publicar/instalar globalmente es otra acción.

## Asociación con el proyecto

El modo global anterior copia a Codex/Claude y sobrescribe archivos de GFrame; no distingue versiones de varios proyectos ni retira archivos antiguos. Se conserva por compatibilidad y solo se comprobó con un CODEX_HOME temporal.

El modo por proyecto recibe `-ProjectPath`, resuelve el paquete desde metadata Composer instalada y copia a `.agents/skills` para Codex y `.claude/skills` para Claude. No utiliza automáticamente este checkout ni la copia global más nueva. No ejecuta autoload/bootstrap: un bridge personalizado necesita comprobación adicional del paquete cargado. Por ahora rechaza enlaces/junctions en fuente y destino; no se implementó la alternativa de enlazado.

Antes de escribir, mostrar fuente, destino, versión/referencia, archivos nuevos/modificados/obsoletos y conflictos. Registrar hashes de los archivos administrados: sustituir únicamente los intactos; conservar personalizaciones y skills ajenas. Retirar un obsoleto solo si pertenece al registro y su hash sigue intacto. Un destino preexistente sin registro se considera conflicto, no propiedad automática del instalador.

Una operación debe poder repetirse sin cambios y detectar rutas que salgan del destino. No borrar ni desactivar copias globales automáticamente. Advertir duplicados mediante evidencia disponible, sin prometer que una skill local oculta otra global. El orquestador seguirá verificando el paquete efectivo, incluso con un complemento instalado.

## Comprobaciones del próximo lote

1. Vista previa sin escrituras; ejecución limitada al destino explícito.
2. Dos proyectos temporales con fuentes distintas conservan sus propias instrucciones.
3. Repetición sin cambios, preservación de archivos modificados y skills ajenas.
4. Obsoletos intactos eliminables frente a obsoletos personalizados conservados; rutas fuera del destino rechazadas.
5. Artefacto generado idéntico a originales, metadata coherente y referencias portables.
6. Validación/carga en cada cliente disponible; lo no ejecutado permanece pendiente.

El lote 18 implementa vista previa, copia por proyecto, registro/preservación y tratamiento de obsoletos. El lote 19 añade `bin/build-skills-plugin.ps1`: versión SemVer explícita, carpeta nueva fuera del checkout, vista previa sin escrituras, catálogo de catorce skills, enlaces portables, copia intacta y procedencia con hashes. Los dos manifiestos comparten identidad/versión y una sola carpeta de skills. No instala ni publica; una operación interrumpida puede dejar contenido parcial.

La prueba PowerShell verifica copias originales, hashes registrados, manifiestos coherentes, reproducibilidad para la misma fuente y rechazo de referencias inválidas, destinos existentes, escritura dentro del checkout, versiones inválidas y junctions. La versión `0.0.0-preview` del artefacto de revisión es solo de prueba. La carga real sigue pendiente: el comando Claude local apunta a un módulo ausente y el CLI de Codex disponible no ofrece un comando de validación de complemento local. No se repararon clientes ni se modificó su configuración.

Comprobación posterior: Codex CLI 0.159.2 sí permite registrar un marketplace local, instalar el paquete portable y consultar su descubrimiento mediante app-server. La prueba aislada instaló y habilitó las catorce skills sin errores; sus nombres se muestran como `gframe-skills:<skill>`. La ausencia de un comando específico de validación no impide esa prueba de carga. No se modificaron plugins/configuración globales ni se probó la selección automática en una conversación nueva. Por petición posterior del usuario, Claude queda fuera de los pendientes; se conserva su adaptador existente sin reparar ni verificar ese cliente.
