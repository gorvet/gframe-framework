# Etapa 01 — Selector multimedia

**Estado:** cerrada
**Baseline:** 2026-10-15; autorización de implementación en chat.

## Resultado observable

Subir, seleccionar, editar atributos existentes, ordenar y confirmar desde el selector reusable.

## Dependencias

Ninguna nueva. Se reutiliza media-library existente.

## Entra

- Selección de subidas válidas, progreso por archivo, fallo parcial, límites y protección de reaperturas.
- Edición explícita de nombre visible y alt usando rutas existentes. El servidor mantiene media.edit, CSRF y ámbito.
- Bandeja de selección renderizada en PHP, quitar y mover mediante botones de teclado; resultado ordenado. El modo append se mantiene salvo edición explícita de composición.
- Descartar respuestas obsoletas del listado; etiquetas y estados de selección accesibles, adaptación móvil y pruebas de navegador.

## No entra

Renombrado físico, leyenda/descripción nuevas, migraciones, procesamiento visual, lista avanzada, borrado masivo, escritura/Gutenberg y cambios en proyectos instalados.

## Criterios de aceptación

- **CA-01 — invariante:** subir desde selector → seleccionar solo éxitos válidos en orden de lote, respetar máximo; cerrar/reabrir no recibe selecciones de la sesión anterior; evidencia sistema.
- **CA-02 — salida:** seleccionar archivo → consultar/guardar nombre visible y alt desde el mismo modal; error de permisos no modifica datos; evidencia sistema.
- **CA-03 — invariante:** selección múltiple → consultar toda la selección, quitar/reordenar y confirmar orden; conservar append para consumidores sin edición explícita; evidencia sistema.
- **CA-04 — invariante:** respuestas desordenadas → solo la lista más reciente se aplica; progreso y errores por archivo y controles de teclado utilizables; evidencia sistema.
- **CA-05 — invariante:** actualización y pruebas → mismo almacenamiento, permisos y ámbitos; documentación y originales preservados; evidencia sistema.

## Riesgos y compatibilidad

Cancelar selección no revierte archivos subidos ni metadatos guardados. Las nuevas capacidades no autorizan borrar. La bandeja usa IDs y backend, no rutas del navegador. Los endpoints personalizados deben conservar los contratos documentados.

## Preguntas abiertas

Ninguna para esta primera entrega; nuevos metadatos y galería dentro del texto quedan fuera.
