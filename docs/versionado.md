# Versionado

GFrame utiliza versionado semántico para el paquete completo.

- Las correcciones compatibles incrementan la versión de parche.
- Las capacidades nuevas compatibles incrementan la versión menor.
- Los cambios incompatibles incrementan la versión mayor.

Router, Middleware, ORM, Render y los demás componentes internos no tienen versiones independientes. El historial registra los cambios relevantes de cada versión de GFrame, no cada ajuste editorial o visual menor.

Un componente solo tendrá ciclo de versión propio si se extrae como paquete instalable independiente. Cada proyecto fija la versión exacta resuelta mediante `composer.lock`.

La estructura inicial y el instalador se versionan junto con `gorvet/gframe`. De esta forma, cada versión del framework conserva una plantilla compatible sin coordinar repositorios separados.
