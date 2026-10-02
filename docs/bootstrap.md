# Bootstrap

Bootstrap es una biblioteca de terceros para maquetación adaptable, formularios y componentes interactivos. Es una dependencia visual predeterminada de GFrame; no pertenece a GFrame.

## Integración

Los archivos se publican en `public/vendors/external/bootstrap/`. Los metadatos globales cargan su CSS antes de `variables.css` y `common.css`, y su JavaScript desde el footer. Usa `container`, `row`, `col-*` y sus componentes sin duplicar su comportamiento.

GFrame personaliza los componentes mediante variables y `bootstrap-buttons-compat.css`. El tema se identifica con `data-bs-theme`; no añadas un segundo selector de tema.

## Fuente oficial

[Proyecto y documentación de Bootstrap](https://getbootstrap.com/). El manifiesto del módulo declara la versión distribuida; las licencias de sus autores acompañan los archivos originales.
