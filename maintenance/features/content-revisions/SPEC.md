# Historial de revisiones de contenido

**Estado:** implementada y auditada el 2026-10-19; [auditoría](01-history-restore/AUDIT.md) coincide. Cambios locales sin release.
**Origen:** prioridad 2 de [la hoja de ruta](../../roadmap.md).

## Resultado solicitado

Conservar versiones del contenido con autor y fecha, compararlas y restaurarlas con autorización. Complementar el autoguardado privado y reutilizar el guardado condicional por revisión existente.

## Contratos que se conservan

- La aplicación mantiene tablas de negocio, campos editables, rutas y autorización por contenido/tenant.
- Persistencia por modelos ORM; servicios para orquestación; sin repositorios, registradores ni hooks nuevos.
- Módulo optativo y adopción explícita. No activar historial ni actualizar instalaciones existentes automáticamente.
- Una restauración debe comparar la revisión actual y producir una revisión nueva; no recuperar un token antiguo ni sobrescribir cambios concurrentes.
- Historial y escritura de contenido en la misma conexión y transacción; un fallo del historial no puede presentar un guardado como completo.
- Proteger lecturas, comparaciones y restauraciones con permisos de la aplicación y CSRF para las escrituras. No aceptar tablas, campos, autor ni tenant seleccionados desde el navegador.

## Propuesta técnica para revisar

- Snapshots de campos de contenido expresamente permitidos por el modelo; excluir credenciales, tokens y datos de otras relaciones.
- Autor identificado por ID y fecha UTC, sin copiar nombres que puedan quedar obsoletos ni exigir que la cuenta siga existiendo.
- Historial del contenido confirmado, no una revisión por cada autoguardado privado.
- Comparación de valores por campo, presentados como texto escapado; no ejecutar HTML histórico.
- Restaurar mediante el mismo guardado versionado y conservar una nueva entrada que permita deshacer esa restauración.
- Retención de 10 revisiones por defecto, configurable a más o menos por aplicación; sin vencimiento por días. Decisión del usuario del 2026-10-19.
- Posibilidad de vaciar todas las revisiones del contenido autorizado para ahorrar espacio, sin eliminar ni modificar su versión actual. Requiere confirmación explícita y permiso de la aplicación; no es un borrado global de otros contenidos.

## Fuera de alcance

Colaboración en tiempo real, fusión automática, historial retrospectivo de cambios no registrados, archivos binarios, papelera, publicación programada y cambios a sitios instalados. No se publica una etiqueta Composer por esta conciliación.
