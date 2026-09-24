# Auditoría inicial de proyectos

## Alcance

Se compararon Baseconfías, Bebots, DANE, Libros y RAG. AIPrint MVP se incorpora a la siguiente revisión de compatibilidad. La instalación WordPress ubicada en `aiprint` queda fuera del framework.

## Resultados

- 106 archivos del core son idénticos en los cinco proyectos comparados.
- 30 archivos comparten ubicación, pero contienen implementaciones diferentes.
- Entre los archivos divergentes están Router, RouteBuilder, Middleware, ORM, Render, Meta, Load, permisos y paginación.
- Los proyectos repiten Bootstrap, jQuery, SweetAlert, AOS, Venobox, `gfselect`, fuentes e iconos.
- `intlTelInput` aparece copiado con más de 500 archivos en varios proyectos.
- TinyMCE ocupa aproximadamente 11 MB en cada proyecto que lo utiliza.
- Los CSS comunes han divergido y no deben centralizarse sin una conciliación previa.

## Decisión

La primera extracción toma como punto de partida el core más reciente de Baseconfías y contrasta cada componente sensible con Bebots. No se considera a ningún proyecto como fuente absoluta del framework.
