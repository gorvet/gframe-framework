# Versionado

GFrame utiliza versionado semántico para el paquete completo.

- Las correcciones compatibles incrementan la versión de parche.
- Las capacidades nuevas compatibles incrementan la versión menor.
- Los cambios incompatibles incrementan la versión mayor.

Router, Middleware, ORM, Render y los demás componentes internos no tienen versiones independientes. El historial registra los cambios relevantes de cada versión de GFrame, no cada ajuste editorial o visual menor.

Un componente solo tendrá ciclo de versión propio si se extrae como paquete instalable independiente. Cada proyecto fija la versión exacta resuelta mediante `composer.lock`.

La estructura inicial y el instalador se versionan junto con `gorvet/gframe`. De esta forma, cada versión del framework conserva una plantilla compatible sin coordinar repositorios separados.

## Restricción y versión instalada

`composer.json` declara qué versiones acepta el proyecto; `composer.lock` registra la versión exacta seleccionada:

| Restricción de ejemplo | Versiones permitidas |
| --- | --- |
| `1.0.2` | Solo esa versión. |
| `~1.0.2` | Desde 1.0.2 hasta antes de 1.1.0. |
| `^1.0` | Versiones compatibles desde 1.0.0 hasta antes de 2.0.0. |

Una restricción compatible permite actualizar, pero no descarga nuevas versiones por sí sola. Consulta [Actualizaciones](actualizaciones.md) y [las restricciones de Composer](https://getcomposer.org/doc/articles/versions.md).

## Documentación y publicaciones

La documentación completa viaja en `docs/` dentro del paquete, aunque el proyecto no active todos los módulos. Una corrección local de esos Markdown puede revisarse antes de publicar mediante [una copia local](instalacion-local.md).

Cada publicación etiquetada conserva su código y documentación. No se modifica una etiqueta existente para introducir cambios: las correcciones posteriores se publican en otra versión cuando estén listas. Agrupa los ajustes editoriales; el changelog resume cambios útiles para el desarrollador, sin registrar cada frase, tilde o ajuste menor.

Cuando una API se incorpora o cambia en una versión concreta, su guía puede indicar «Disponible desde…» o incluir un apartado de compatibilidad con un ejemplo de adaptación. Esa referencia usa la versión de GFrame, no una versión independiente inventada para el módulo.


## Cómo decidir el incremento

La decisión depende del contrato público, no del tamaño del diff.

- **Parche**: corrige un comportamiento defectuoso sin exigir cambios al consumidor, ajusta documentación o mejora internamente una implementación compatible.
- **Menor**: añade una API, módulo, opción o capacidad compatible con proyectos existentes.
- **Mayor**: elimina o cambia de forma incompatible una API, archivo administrado, contrato de respuesta, configuración o comportamiento del que dependan aplicaciones existentes.

Una refactorización extensa puede seguir siendo parche si conserva los contratos. Una modificación de una sola línea puede requerir versión mayor si cambia un contrato público.

## Qué forma parte del contrato

Considere como superficie versionada, entre otros:

- clases y métodos públicos;
- firmas y tipos esperados;
- códigos de respuesta consumidos por frontend o integraciones;
- nombres y comportamiento de configuración;
- rutas y middleware;
- esquema y migraciones de módulos;
- archivos publicados que el proyecto administra;
- estructura de vistas de módulos cuando existe personalización documentada;
- comandos y opciones CLI;
- formatos persistidos, como cifrado o colas.

Los detalles internos que no se exponen pueden cambiar sin incrementar la versión mayor, siempre que sus efectos observables permanezcan compatibles.

## Prelanzamientos y desarrollo

Las ramas de desarrollo pueden contener cambios todavía no publicados. No trate `dev-main` como equivalente a una versión estable. Los proyectos que necesiten reproducibilidad deben apuntar a versiones publicadas y conservar su lock.

Si se preparan versiones preliminares, documente claramente qué contratos todavía pueden cambiar y evite usarlas como dependencia silenciosa de producción.

## Publicar una versión

Antes de etiquetar:

1. ejecute la suite completa y las comprobaciones de skills/documentación;
2. revise migraciones y actualización desde una versión anterior;
3. confirme versiones/licencias de dependencias externas modificadas;
4. actualice changelog o nota de release;
5. compruebe que documentación y ejemplos corresponden al código de esa etiqueta;
6. etiquete una vez y no reescriba esa etiqueta después.

Si aparece un problema tras publicar, corríjalo en una versión posterior. La inmutabilidad de las etiquetas permite que `composer.lock` siga siendo reproducible.

## Compatibilidad documental

Una guía puede evolucionar sin que cada corrección editorial produzca una versión. Sin embargo, si la documentación revela que el contrato real cambió, ese cambio debe clasificarse por su impacto técnico y no esconderse como «solo documentación».

Cuando una guía describe varias generaciones del framework, identifique claramente desde qué versión existe una capacidad y qué migración necesita un proyecto anterior.
