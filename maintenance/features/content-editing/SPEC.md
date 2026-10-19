# Protección del guardado y presencia editorial

**Estado:** implementada y auditada el 2026-10-19; [etapa 01](01-save-presence/AUDIT.md) coincide. Cambios locales sin release.

## Resultado

Evitar que dos ediciones sobrescriban silenciosamente el mismo contenido y avisar de otras sesiones activas. La aplicación conserva sus tablas, autorización, rutas y publicación.

## Requisitos

- R-01: comparar revisión y actualizar contenido/revisión en la misma escritura; una revisión antigua no guarda ni publica.
- R-02: reutilizar ORM, conexiones y modelos por herencia; sin tabla universal de artículos, repositorios, registradores o hooks nuevos.
- R-03: presencia temporal por tenant/recurso y sesión de edición, con caducidad tras desconexión; informativa, sin bloqueo exclusivo ni toma forzada.
- R-04: ruta propia de aplicación con auth, permiso y CSRF; servidor determina recurso y tenant. El navegador no elige tablas, columnas ni identidades.
- R-05: conflicto conserva formulario y borradores; solo éxito real adopta revisión nueva y confirma snapshots.
- R-06: MySQL/SQLite, pruebas concurrentes y negativas, instalación optativa, docs y skills coherentes. No cambiar aplicaciones instaladas ni publicar versiones automáticamente.

## Decisiones técnicas

Modelo base abstracto VersionedContentModel con API ORM, revisión opaca en columna de la tabla propia y actualización condicional por PK/revisión/ámbito explícito. El proyecto migra su columna revision; el framework no migra tablas desconocidas. Módulo optativo content-editing para presencia compartida, con modelo, servicio y controlador auxiliar; original PHP y JS por manifest/meta. Ping cada 30 s y caducidad 90 s. Solo se comunica el número de otras sesiones; no se publican nombres ni tokens de otros editores.

## Fuera de alcance

Colaboración carácter a carácter, fusión automática, bloqueo obligatorio, revisiones históricas, papelera, vista previa y publicación programada. La presencia no autoriza ni sustituye la comparación atómica. Todas las escrituras de contenido protegido deben adoptar el guardado versionado; escrituras antiguas que lo omitan siguen fuera de su garantía.
