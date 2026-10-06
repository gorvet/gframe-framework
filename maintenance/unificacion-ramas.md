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

La rama `codex/reconstruccion-unificada` parte actualmente de `codex/reconstruccion-documentacion`. Las dos ramas fuente permanecen intactas. La integración física de la cobertura complementaria de auditoría se realizó por grupos verificados. Se conservaron como base las guías estructurales de reconstrucción y se incorporaron las referencias cuya documentación está ejecutada por tests o aporta cobertura no duplicada. `docs/primera-funcionalidad.md` no se incorporó para evitar un segundo tutorial CRUD paralelo; `docs/tutorial-productos.md` sigue siendo el recorrido canónico.


## Criterio editorial definitivo

La rama unificada no busca únicamente cubrir más áreas. El objetivo es **máxima profundidad + máxima cobertura**.

Para cada tema:

1. se conserva como base la versión más extensa, clara y técnicamente correcta;
2. se incorporan de la otra rama ejemplos, contratos, advertencias, APIs y pruebas que aporten información adicional;
3. una guía extensa no se sustituye por otra más corta solo porque esta última toque más casos;
4. si ambas son parciales, se amplía el documento usando runtime, tests y código como fuente de verdad;
5. el resultado debe evitar duplicación innecesaria sin perder detalle útil.

`http-client.md` representa el patrón deseado: una guía profunda y autosuficiente, complementada por cualquier contrato válido que aparezca en otra fuente. La misma regla debe aplicarse progresivamente al resto de áreas.


## Segunda auditoría de profundidad

Se realizó una segunda pasada con el criterio editorial definitivo, comparando la unificada contra **ambas** ramas fuente y revisando también runtime/tests cuando una diferencia podía ser contractual.

Hallazgos y correcciones principales:

- se recuperaron detalles útiles que habían desaparecido de la rama extensa, como migración de GF Table, fixture visual de GFSelect y verificación aislada de Alerts;
- se amplió la documentación nueva de auditoría que había quedado demasiado breve: comandos, respuestas, vistas, primera página, estilos comunes y numerosas dependencias frontend;
- se restituyeron detalles de composición del formulario de Campañas solo después de comprobarlos contra la vista runtime actual;
- se reforzaron instalación local, versionado, dependencias, acceso API, limpieza, skills y footer;
- se corrigió `docs/json-ld.md` para documentar `LogicException` en ciclos de presets y la omisión de `aggregateRating` vacío;
- se detectó que `docs/seo.md` había conservado semántica antigua pese a que el runtime canónico ya utilizaba `context.seo.indexable`; se reescribió esa política según `Meta`, `Sitemap`, `Llms`, `LegacyConfigBridge` y `SeoIndexabilityTest`;
- se conservaron fuera detalles de auditoría que ya eran obsoletos, por ejemplo el rate limit antiguo de Mail o una librería de serialización que no coincide con el runtime actual;
- los tests documentales dejaron de depender de un número exacto de bloques PHP: ahora permiten ampliar las guías y siguen validando la sintaxis de todos los ejemplos encontrados.

### Métrica de profundidad documental

Medida sobre los Markdown de `docs/` durante esta segunda auditoría:

| Rama | Guías | Tamaño total aprox. | Promedio por guía | ≥ 5 KB | ≥ 8 KB | < 2 KB |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `reconstruccion-documentacion` | 87 | 548 KB | 6.3 KB | 45 | 31 | 25 |
| `auditoria-reconstruccion` | 83 | 582 KB | 7.0 KB | 51 | 27 | 17 |
| `reconstruccion-unificada` | 93 | 746 KB | 8.0 KB | 69 | 40 | 4 |

La métrica no sustituye la revisión técnica, pero confirma que la cobertura adicional no se consiguió reduciendo sistemáticamente la profundidad de las áreas existentes. Los documentos todavía muy pequeños son principalmente alias o notas históricas deliberadamente breves.


## Cierre de la unificación

La comparación final contra las dos ramas fuente confirma:

- `codex/reconstruccion-documentacion` está completamente contenida en la historia de la unificada;
- la divergencia restante de `codex/auditoria-reconstruccion` corresponde a commits históricos independientes, no a una rama que deba fusionarse completa;
- los documentos donde auditoría seguía siendo físicamente mayor fueron revisados individualmente;
- `mail.md` conservaba contratos obsoletos de Laravel Serializable Closure y rate limit que contradicen el runtime actual basado en Opis; se descartaron;
- `seo.md` y `json-ld.md` conservaban formulaciones anteriores que ya fueron sustituidas por la política validada por runtime/tests;
- `servidores-web.md` conservaba detalles operativos útiles; la unificada ya contiene esos contratos y se añadió una lista explícita de comprobación de despliegue;
- `render.md` solo aportaba una referencia introductoria ya cubierta por `vistas.md`.

Por tanto, no queda pendiente una fusión global de ninguna de las dos ramas fuente. Cualquier diferencia futura debe evaluarse contra código y tests como cambio nuevo, no como deuda de esta unificación.

La rama `codex/reconstruccion-unificada` se considera la base documental canónica de esta reconstrucción cuando su CI final permanece verde.
