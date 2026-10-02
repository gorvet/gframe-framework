# Hoja de ruta

Las mejoras opcionales de baja prioridad se registran en [Mejoras pendientes no urgentes](mejoras-pendientes.md), separadas de los errores y del trabajo en curso.

## Versión 0.9.0

- Validar la integración final en una aplicación real sin tenant.
- Consolidar la configuración por entorno mediante `.env` y `config/app.php`. **Completado.**
- Proporcionar cron, heartbeat, colas de notificaciones y cifrado opcional desde el paquete. **Completado.**
- Estabilizar el esquema estándar de autenticación con modelos ORM, servicios y roles normalizados. **Completado.**
- Completar la interfaz de instalación, el superadministrador y los perfiles estático, administrado y SaaS. **Completado.**
- Mantener compatibilidad temporal con las constantes históricas.
- Documentar cada cambio funcional y cada decisión de arquitectura.
- Revisar que la documentación y los skills evolucionen junto con el código.

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
