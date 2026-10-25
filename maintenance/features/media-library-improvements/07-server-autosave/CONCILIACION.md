# Etapa 07 — Autoguardado en el servidor

**Estado:** cerrada

**Seguimiento conciliado — 2026-10-25:** Etapa cerrada; [AUDIT.md](AUDIT.md), pasada 1, acredita su cierre. La entrega 4.1.0 se publicó y está incluida en 4.4.1; no queda pendiente un commit, push o publicación de esta etapa.
**Spec:** [SPEC.md](../SPEC.md), continuación de protección.

## Resultado solicitado

Guardar automáticamente una copia privada del campo en el servidor, vinculada al mismo contenido, además de la recuperación local. No crear otro artículo de negocio ni cambiar la versión pública.

## Dependencias

- Etapa 06 cerrada: snapshots, confirmación, guardia de cambios y recuperación local disponibles.
- El módulo actual rich-text-editor no define una tabla/modelo de artículos ni permisos de edición de registros de negocio. La integración deberá preservar los servicios y permisos propios de las aplicaciones.

## Hallazgos de revisión

- El módulo solo declara runtime de vistas y assets, con dependencias jquery/tinymce; no tiene persistencia ni rutas de artículos.
- La receta canónica content-editor-search distingue edición HTML de almacenamiento/autorización de artículos.
- Las rutas AJAX de módulos existentes usan POST, auth y permisos específicos; no debe añadirse una escritura genérica a tablas/columnas elegidas por el navegador.
- Las confirmaciones existentes sirven para reconocer el contenido realmente persistido; no equivalen por sí solas a un autoguardado remoto.

## Entra

- Módulo optativo content-autosave, tabla privada propia MySQL/SQLite, modelo/servicio comunes y controlador auxiliar para rutas de cada aplicación. La aplicación autoriza el registro y proporciona clave/revisión actual; el navegador no elige tablas, usuarios ni permisos.
- Copia por usuario, tenant y recurso/campo, con versión opaca y escritura condicional atómica. Caducidad a 30 días y descarte con tombstone/versionado. Una copia privada no es un artículo nuevo ni una publicación.
- Integración por campo: URL del endpoint autorizado y revisión pública. POST con tokens del framework, carga/recuperación explícita, estado visible, pausa/intervalo configurable, una petición activa y reintentos limitados. Conservar la copia local ante fallo.
- Al confirmar publicación con nueva revisión, coordinar la eliminación de la copia antigua sin borrar cambios posteriores o copias concurrentes. El autoguardado privado no llama a markSaved ni declara publicación; se conserva el aviso de cambios sin publicar.
- Documentación, migración por instalación del módulo, pruebas SQLite/procesos y navegador, integración MySQL cuando esté disponible, auditoría y release solicitado.

## No entra

Modelos o tablas de artículos, autoguardado de todo el formulario, publicación automática, cambios a producción, edición colaborativa o inferir permisos de negocio. La aplicación sigue autorizando su contenido y guardando/publicando por su flujo existente.

## Aclaraciones

2026-10-15 — El usuario confirma «copia privada» y autoriza continuar, commit, push y etiqueta Composer. Las decisiones técnicas anteriores implementan ese alcance en el framework reusable. El endpoint debe pertenecer a una ruta protegida de la aplicación porque GFrame no tiene modelo de artículos.

## Criterios de aceptación

- **CA-01 — condición de salida:** autoguardar → persiste copia privada sanitizada, con scope de servidor y revisión actual; no cambia artículo público; usuario/tenant/recurso ajeno no accede. Evidencia sistema.
- **CA-02 — invariante acumulado:** dos escrituras con misma versión → solo una gana; revisión pública antigua o descarte antiguo no sobrescribe cambios nuevos; errores y expiración no simulan éxito. Evidencia sistema con procesos y prueba MySQL si disponible.
- **CA-03 — condición de salida:** editar → pausa/intervalo y un request activo, tokens presentes; fallo conserva texto/copia local y reintenta acotadamente; cargar copia requiere recuperar o descartar; read-only y destroy no envían cambios posteriores. Evidencia sistema: navegador real con PHP/SQLite.
- **CA-04 — invariante acumulado:** editar durante autosave/publicación → se conservan los cambios nuevos; confirmación de instancia antigua no afecta a la nueva; publicación limpia solo la copia de versión conocida y adopta nueva revisión. Evidencia sistema.
- **CA-05 — condición de salida:** módulo, docs, instalación/actualización y skills coherentes; regresiones pasan; entrega preparada para commit/push/tag Composer tras auditoría, sin tocar sitios instalados. Evidencia estructural y sistema.

## Preguntas abiertas

Ninguna.

## Estado del trabajo

Se conservan los cambios de las etapas previas; se incluirán en la entrega solicitada tras comprobarlos. No se modifican sitios instalados.
