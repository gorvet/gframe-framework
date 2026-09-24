# Auditoría inicial de compatibilidad

## Alcance

Se compararon aplicaciones con y sin tenant para identificar el núcleo común, las variaciones de implementación y las dependencias que deben permanecer opcionales.

## Resultados

- Entre los archivos divergentes están Router, RouteBuilder, Middleware, ORM, Render, Meta, Load, permisos y paginación.
- Los proyectos repiten Bootstrap, jQuery, SweetAlert, AOS, Venobox, `gfselect`, fuentes e iconos.
- `intlTelInput` aparece copiado con más de 500 archivos en varios proyectos.
- TinyMCE ocupa aproximadamente 11 MB en cada proyecto que lo utiliza.
- Los CSS comunes han divergido y no deben centralizarse sin una conciliación previa.

## Decisión

La extracción conserva únicamente las capacidades reutilizables. Ninguna aplicación concreta se considera fuente absoluta del framework.
