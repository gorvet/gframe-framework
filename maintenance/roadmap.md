# Hoja de ruta

Las mejoras opcionales de baja prioridad se registran en [Mejoras pendientes no urgentes](mejoras-pendientes.md), separadas de los errores y del trabajo en curso.

## Versión 1.0.0

- Validar la integración final en una aplicación real sin tenant.
- Consolidar la configuración por entorno mediante `.env` y `config/app.php`. **Completado.**
- Proporcionar cron, heartbeat, colas de notificaciones y cifrado opcional desde el paquete. **Completado.**
- Estabilizar el esquema estándar de autenticación con modelos ORM, servicios y roles normalizados. **Completado.**
- Completar la interfaz de instalación, el superadministrador y los perfiles estático, administrado y SaaS. **Completado.**
- Mantener compatibilidad temporal con las constantes históricas.
- Documentar cada cambio funcional y cada decisión de arquitectura.
- Revisar que la documentación y los skills evolucionen junto con el código.

## Futuras capacidades de contenido y operación

Registradas el 16 de octubre de 2026 tras comparar el framework con la copia local de WordPress. Las prioridades 1–5 están implementadas en el framework e incluidas en la entrega 4.3.0. Las restantes no tienen versión ni fecha de entrega asignada. Cada aplicación adopta expresamente estas capacidades.

| Prioridad | Característica | Alcance previsto y punto de partida |
| --- | --- | --- |
| 1 | Protección del guardado y edición simultánea | Implementada: [guardado y presencia](../docs/content-editing.md), con comparación atómica de revisión y ámbito. |
| 2 | Revisiones del contenido | Implementada: [historial](../docs/content-revisions.md), 10 revisiones por defecto, límite configurable, comparación, restauración y vaciado confirmado. |
| 3 | Vista previa privada en el sitio | Implementada: [preview privada](../docs/content-preview.md), plantilla pública y recarga tras persistir cambios. Barra pública administrativa aplazada. |
| 4 | Papelera y restauración | Implementada: [contrato optativo](../docs/content-trash.md), estado recuperable, restauración y borrado definitivo confirmado con limpieza SQL transaccional. |
| 5 | Publicación programada | Implementada: [publicación por cron](../docs/content-publication.md), fechas UTC, reprogramación, cancelación, revisión/estado/fecha condicionales e integración con historial y papelera. Cron virtual conserva su dependencia del tráfico. |
| 6 | Salud del sitio | Aplazada por el usuario el 19 de octubre de 2026. Propuesta futura: reunir diagnósticos de cron, correo, almacenamiento, base de datos y configuración, reutilizando los estados existentes y sin mostrar secretos. |
| 7 | Redirecciones al cambiar el slug | Aplazadas por el usuario el 19 de octubre de 2026 como mejora futura mediante un módulo opcional independiente. Véase [Mejoras pendientes no urgentes](mejoras-pendientes.md#redirecciones). |

Estas capacidades deben respetar los contratos de GFrame, mediante módulos opcionales y adaptadores cuando corresponda, sin imponer una tabla universal de artículos ni trasladar reglas de negocio al core. Las integraciones deben preservar autorización por contenido y tenant.

Los eventos generales de extensión quedan como propuesta por evaluar, sin implementación comprometida. Primero se identificarán necesidades que los registradores y puntos de extensión existentes no resuelvan; no se añadirá un sistema global de hooks solo por reproducir WordPress.

## Documentación y ayuda

- Crear una guía de inicio rápido para proyectos nuevos.
- Documentar configuración, rutas, middleware, ORM, vistas, permisos y tareas asíncronas.
- Preparar ejemplos mínimos que puedan ejecutarse y verificarse.
- Crear una landing pública de GFrame con documentación y ayuda.
- Publicar un historial de versiones y una guía de actualización.

## Desarrollo asistido por IA

- Mantener en el repositorio los skills oficiales de GFrame, versionados junto con el framework.
- Usar una fuente común e instalable para Codex y Claude Code.
- Ampliar la comprobación automática de diferencias entre código, documentación y skills.
- Documentar cómo instalar y actualizar esas instrucciones en cada asistente.

## Próximas integraciones

- Extraer únicamente infraestructura común y herramientas reutilizables (editor enriquecido, HTML a Markdown y Markdown a HTML); no convertir la lógica propia de Bebots, Dane o Base Confías en módulos genéricos por defecto.
- Mejorar el instalador visual por pasos, con módulos incluidos por perfil y dependencias resueltas sin selección redundante.

- Validar en aplicaciones reales los módulos opcionales de multimedia, notificaciones, WordPress headless y administración de usuarios.
- Integrar el catálogo de módulos y su publicador con el instalador visual. **Completado.**
- Validar una aplicación completa sin tenant.
- Validar posteriormente tenancy, canales y tareas asíncronas en una aplicación que utilice esas capacidades.
- Validar el generador integrado `composer new` en Windows y Linux.
