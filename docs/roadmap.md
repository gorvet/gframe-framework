# Hoja de ruta

## Versión 0.9.0

- Completar la integración en una aplicación sin tenant.
- Consolidar la configuración por entorno mediante `.env` y `config/app.php`.
- Proporcionar cron, heartbeat, colas de notificaciones, cifrado y clasificación de texto desde el paquete.
- Proporcionar autenticación mediante contratos que no impongan el esquema de usuarios del proyecto.
- Admitir roles administrativos globales como capacidad opcional y configurable.
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

- Incorporar al repositorio los skills oficiales de GFrame, versionados junto con el framework.
- Mantener una fuente común para generar instrucciones compatibles con Codex y Claude Code.
- Añadir una comprobación que detecte diferencias entre el código, la documentación y los skills.
- Documentar cómo instalar y actualizar esas instrucciones en cada asistente.

## Próximas integraciones

- Auditar y separar los recursos públicos comunes sin mover archivos sin validación previa.
- Validar una aplicación completa sin tenant.
- Validar posteriormente tenancy, canales y tareas asíncronas en una aplicación que utilice esas capacidades.
- Preparar un proyecto base instalable con `composer create-project`.
