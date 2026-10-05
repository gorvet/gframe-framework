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
