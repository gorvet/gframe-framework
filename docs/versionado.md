# Versionado

GFrame utiliza versionado semántico para el paquete completo.

- Las correcciones compatibles incrementan la versión de parche.
- Las capacidades nuevas compatibles incrementan la versión menor.
- Los cambios incompatibles incrementan la versión mayor.

Router, Middleware, ORM, Render y los demás componentes internos no tienen versiones independientes. Cada modificación se registra por componente en el historial de cambios y forma parte de una versión única de GFrame.

Un componente solo tendrá ciclo de versión propio si se extrae como paquete instalable independiente. Cada proyecto fija la versión exacta resuelta mediante `composer.lock`.
