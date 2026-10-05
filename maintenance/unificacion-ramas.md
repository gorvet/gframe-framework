# Unificación de las dos reconstrucciones

Rama de trabajo: `codex/reconstruccion-unificada`.

Fuentes comparadas:

- `codex/reconstruccion-documentacion`
- `codex/auditoria-reconstruccion`

Las dos ramas fueron desarrolladas de forma independiente por agentes distintos. Ninguna se considera canónica por defecto.

## Regla de decisión

1. Código y tests son la fuente de verdad.
2. Una guía que existe solo en una rama se conserva si coincide con el runtime.
3. Si existe en ambas, se compara exactitud, cobertura, claridad, reproducibilidad y encaje con la arquitectura documental.
4. Si ambas difieren del runtime, se corrigen ambas ideas y se documenta el contrato real.
5. Las correcciones de runtime se conservan únicamente cuando el problema está demostrado y queda cubierto por pruebas.
6. No se decide por número de líneas, fecha o rama de origen.
7. No se comparará todavía con `main`; primero se cierra esta unificación.

## Decisiones iniciales

| Área | Decisión | Motivo |
| --- | --- | --- |
| Tutorial CRUD Productos | `docs/tutorial-productos.md` como base canónica | Declara prerrequisitos, perfiles, permisos y separación de responsabilidades. La versión `primera-funcionalidad.md` de auditoría se usará como fuente de mejoras puntuales, no como segundo tutorial paralelo. |
| Helpers PHP | `docs/helpers-php.md` como base | Distingue correctamente API global classmapped vigente de wrappers de `LegacyCompatibility.php`; evita llamar legacy a toda clase global. |
| HTTP saliente | `docs/http-client.md` de reconstrucción como base | Es referencia más completa. Se incorporarán ejemplos/advertencias útiles de auditoría si no duplican contenido. |
| Encryption | sintetizar sobre la versión de reconstrucción | Explica mejor gestión y rotación de claves; auditoría aporta advertencias prácticas que pueden integrarse. |
| SEO / JSON-LD | resolver por runtime + tests combinados | Ambas ramas corrigieron aspectos distintos. Debe quedar una sola política coherente en `Meta`, `Robots`, `Sitemap`, `Llms`, `SchemaComposer`, `JsonLD` y presets. |
| Documentación amplia de módulos/UI | traer desde auditoría | `auditoria-reconstruccion` revisó y amplió gran cantidad de módulos visuales y añadió tests documentales que no existen en la otra rama. |
| Guías estructurales de arquitectura de uso | conservar desde reconstrucción | `guia-desarrollo.md`, `identidad-autorizacion.md`, `procesos-segundo-plano.md`, `modulos-en-aplicacion.md`, `autoload-proyecto.md` y `perfiles-instalacion.md` resuelven el problema de aprendizaje transversal. |
| Identidad/Auth/Sesiones/Permisos | mantener dos capas | `identidad-autorizacion.md` funciona como mapa mental transversal; las guías detalladas `autenticacion.md`, `sesiones.md`, `permisos.md` y `middleware.md` conservan la referencia técnica profunda. No deben fusionarse en un único documento gigante. |
| Segundo plano | mantener guía de decisión + referencias | `procesos-segundo-plano.md` explica cuándo usar ejecución directa, `Async`, Cron o colas. `async.md`, `cron-runner.md`, Mail y Notifications conservan contratos y APIs específicas. |
| Módulos | mantener guía conceptual + referencia runtime | `modulos-en-aplicacion.md` aclara capacidad instalable, runtime MVC, componente frontend y funcionalidad propia del proyecto. `modulos-runtime.md`, `modulos-opcionales.md` y `extensibilidad.md` quedan como referencia técnica. |

## Estado

La rama `codex/reconstruccion-unificada` parte actualmente de `codex/reconstruccion-documentacion`. Las dos ramas fuente permanecen intactas. La integración física de archivos se hará después de revisar cada grupo solapado contra código y tests.
