# Etapa 05 — Herramientas de escritura

**Estado:** cerrada
**Baseline:** 2026-10-15; el usuario autoriza continuar con organización de herramientas, acciones contextuales de medios y acceso a guardar/publicar. Se conserva TinyMCE, su scroll propio y el guardado de cada aplicación.

## Resultado observable

Una barra principal más breve, acciones junto a la imagen o galería seleccionada y acceso opcional al botón de guardado existente sin salir del editor.

## Dependencias

Etapas 03 y 04 cerradas.

## Entra

- Aclaración del usuario (2026-10-15): mejorar también la experiencia de texto. Se implementará selección contextual de formatos, menú «/» al inicio del párrafo y contador nativo de palabras como decisiones técnicas; se conserva el HTML y TinyMCE.
- Grupo nativo «Más herramientas» para opciones secundarias, sin eliminar funciones. Conservar barras/modos personalizados explícitos.
- Acciones contextuales de cambiar imagen, editar galería y quitar del texto; quitar es reversible con Deshacer y no borra medios de la biblioteca.
- Opción PHP `save_button`: ID explícito de un botón del mismo formulario. Botón y Ctrl/Cmd+S delegan el clic existente tras sincronizar contenido sin marcarlo guardado. Respetar validación nativa, disabled, modo lectura, petición de medios pendiente e instancias diferentes.
- Documentación, pruebas reales de TinyMCE y revisión de escritorio/móvil y teclado.

## No entra

Endpoints de guardado/publicación, publicación automática, modificar permisos o rutas, inventar acciones de negocio, editar asides de proyectos instalados, edición de píxeles, metadatos nuevos ni reemplazo del editor.

## Criterios de aceptación

- **CA-01 — condición de salida:** barra predeterminada → formato básico visible y herramientas secundarias accesibles en grupo nativo; proyectos con toolbar/modo propio conservan configuración funcional; móvil y teclado utilizables. Evidencia sistema.
- **CA-02 — condición de salida:** seleccionar imagen/galería → acciones contextuales pertinentes; cambiar/editar conserva el flujo del picker; quitar afecta solo al documento, es reversible y mantiene cambios pendientes. Evidencia sistema.
- **CA-03 — invariante acumulado:** guardar opcional → clic del botón autorizado del mismo formulario, contenido sincronizado y validación existente; no hay envío si está deshabilitado, es de otro formulario, modo lectura o medios pendientes; la integración no declara éxito ni limpia dirty. Evidencia sistema.
- **CA-04 — invariante acumulado:** editor sin configuración de guardado, scroll, biblioteca y callbacks → contratos y ciclos conservados; documentación/skills y publicación coherentes, sin backend ni esquema nuevos. Evidencia sistema y estructural.

- **CA-05 — condición de salida:** texto → formatos contextuales, menú «/» con filtrado y teclado al principio del párrafo, cancelación sin pérdida de texto, deshacer y contador de palabras; sin dependencia de medios. Evidencia sistema.

## Riesgos y compatibilidad

Los grupos nativos requieren toolbar_mode floating; si un proyecto elige otro modo, las herramientas del grupo propio se muestran en línea. El botón objetivo conserva toda la responsabilidad de envío, permisos, CSRF, errores y éxito. La opción no se activa por inferencia sobre un botón de publicar.

## Preguntas abiertas

Ninguna para esta integración explícita. Diseño del aside de cada proyecto fuera de esta etapa.
