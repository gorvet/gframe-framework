# Etapa 03 — Biblioteca dentro de la escritura

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md).
**Baseline:** 2026-10-15; el usuario prioriza integración con escritura antes de edición visual y metadatos. Concreción técnica sobre el editor actual y los contratos existentes, sin cambiar el formato de contenido a un constructor de páginas.

## Resultado observable

Desde TinyMCE, insertar una imagen de biblioteca o componer y reeditar una galería ordenada, conservando el texto, el cursor y el ciclo de guardado existente.

## Dependencias

- **DEP-01 — Selector y gestión:** cerradas, etapas 01 y 02.

## Entra

- Activación explícita por campo; editor sin biblioteca conserva su comportamiento y dependencias.
- Dos botones de escritura: imagen y galería; selección/subida mediante el picker existente. Reapertura de imagen/galería seleccionada, quitar/reordenar sin borrar archivos.
- Fragmento de inserción PHP por IDs, ámbito y tipo imagen. HTML semántico con `figure`, marcador de galería e IDs numéricos; sin estilos arbitrarios, tablas de relaciones ni esquema nuevo.
- Mantener posición, undo/redo, instancias independientes y respuestas antiguas/destrucción. Permisos y CSRF de las rutas actuales.
- Sanitizador y allowlist de TinyMCE preservan únicamente los nuevos elementos/atributos acotados. La aplicación sigue autorizando y guardando el contenido; los IDs HTML no conceden acceso.
- Metadatos, plantilla opcional de assets, documentación y pruebas de navegador con TinyMCE real, guardado saneado y reapertura.

## No entra

Edición de píxeles, nuevos metadatos del archivo, bloques de páginas, sustitución del editor, publicación, aplicaciones instaladas, autoactualizar textos ya guardados al cambiar el alt global, audio/vídeo dentro del texto ni almacenamiento de artículos.

## Criterios de aceptación

- **CA-01 — condición de salida:** campo habilitado → imagen y galería usan biblioteca real; orden y alt del servidor se insertan en el cursor y se reeditan sin duplicar; cancelar no cambia el documento. Evidencia sistema.
- **CA-02 — invariante acumulado:** varias instancias, undo/redo y destrucción durante selección/petición → solo se modifica la instancia y documento vigentes; no pierde texto ni inserta respuestas antiguas. Evidencia sistema.
- **CA-03 — invariante acumulado:** IDs ajenos/no imagen/excesivos o rutas del navegador → rechazo sin HTML parcial; sanitizado y recarga conservan orden y marcadores seguros, eliminan eventos/protocolos peligrosos y no confían en esos marcadores para autorización. Evidencia conductual y sistema.
- **CA-04 — invariante acumulado:** editor sin activación y selector/campos anteriores → comportamiento conservado; assets, formulario/saveAll, teclado y móvil comprobados; documentación/skills actualizadas sin migración ni permisos nuevos. Evidencia sistema y estructural.

## Riesgos y compatibilidad

TinyMCE y Bootstrap deben coordinar foco y cierre sin modal anidado. El HTML se guarda por cada aplicación con su sanitización/autorización, no mediante un CRUD genérico añadido al framework. Galerías sin CSS siguen siendo figuras e imágenes en orden. Los artículos que usen esta función requieren publicar recursos y alinear el sanitizador.

## Bloqueos

Ninguno.

## Preguntas abiertas

Ninguna para la integración acotada. Edición visual y nuevos metadatos quedan aplazados.

## Criterio para cerrar conciliación

Prioridad de escritura autorizada; se conserva TinyMCE y HTML, con biblioteca opcional.
