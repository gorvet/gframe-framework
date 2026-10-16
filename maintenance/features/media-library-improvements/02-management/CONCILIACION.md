# Etapa 02 — Gestión de la biblioteca

**Estado:** cerrada
**Spec:** [SPEC.md](../SPEC.md), entregas posteriores del inventario.
**Baseline:** 2026-10-15; continuación autorizada por el usuario («ok adelante») del trabajo de biblioteca.

## Resultado observable

Consultar los medios en cuadrícula o lista, ordenarlos y recorrer sus detalles con reproducción de audio y vídeo.

## Dependencias

- **DEP-01 — Selector:** cerrada; etapa 01, auditoría pase 4.

## Entra

- Vista de lista PHP para administración; cuadrícula predeterminada y selector conservados.
- Ordenación permitida por fecha de incorporación, nombre visible y tamaño; estado en URL, filtros y paginación.
- Anterior/siguiente dentro del resultado filtrado, incluso entre páginas; protección de cambios sin guardar, errores y respuestas obsoletas.
- Nombre visible y alt separados; reproductores nativos sin reproducción automática, enlace al archivo y liberación al cerrar/cambiar.
- Documentación, pruebas reales con SQLite y navegador, revisión de escritorio y móvil.

## No entra

Edición de píxeles, nuevos metadatos, búsqueda por autor, borrado masivo, almacenamiento privado, proxy de descargas, cambios de esquema, escritura ni sitios instalados. No se publica versión en esta etapa.

## Aclaraciones

La ordenación conserva por defecto la incorporación más reciente (ID descendente). El nombre visible no renombra el archivo físico. El enlace usa la URL existente; archivos externos dependen de su servidor y códec. No se promete descarga forzada entre orígenes.

## Criterios de aceptación

- **CA-01 — invariante acumulado:** cambiar vista/orden con filtros → resultado PHP coherente y URL recuperable; orden inválido usa el predeterminado; ámbitos y desempate estable conservados. Evidencia sistema y conductual.
- **CA-02 — condición de salida:** recorrer detalles → anterior/siguiente respeta filtros y páginas y desactiva extremos; errores no muestran otro archivo como guardado; cambios pendientes impiden abandonar hasta guardar o descartar. Evidencia sistema.
- **CA-03 — condición de salida:** abrir audio/vídeo → controles nativos sin autoplay; cambiar/cerrar libera el recurso; nombre visible y alt se guardan por las rutas existentes. Evidencia sistema.
- **CA-04 — invariante acumulado:** flujos con teclado, móvil, lista vacía y paginación → controles accesibles y selector anterior compatible; documentación, skills y pruebas proporcionales actualizadas, sin migración ni permisos nuevos. Evidencia sistema y estructural.

## Riesgos y compatibilidad

La lista y el selector comparten listado y ámbito; no se cambia la interfaz MediaRepository. Reproducción depende del navegador. La fixture no prueba el middleware de autorización ni un servidor de producción.

## Bloqueos

Ninguno.

## Preguntas abiertas

Ninguna dentro de esta entrega.

## Criterio para cerrar conciliación

Continuación acotada a gestión del inventario aprobado; las demás capacidades permanecen en entregas posteriores.
