# Adjuntos de correo

Trabajo autorizado en el framework el 16 de octubre de 2026. Especialistas aplicados: `skills/gframe-notifications-mail/SKILL.md` y `skills/gframe-framework-maintenance/SKILL.md`, seleccionados mediante el orquestador canónico. Se conserva el cambio pendiente de la hoja de ruta.

Alcance: opciones de MailService para HTML y plantillas, síncronas y Async; validación de archivos locales o contenido binario y construcción MIME con PHPMailer. Async conserva bytes, no rutas que podrían desaparecer. No cambia esquemas, APIs de colas persistentes ni aplicaciones instaladas.

Comprobaciones previstas: MIME sin transporte real, bytes binarios, varios adjuntos, serialización y eliminación del original, rechazo de entradas inválidas y límites, regresión de plantillas, rate limit y notificaciones; sintaxis, enlaces y documentación. No se envían mensajes reales.

Verificación completada: PHP 8.3.35, PHPUnit con MailAttachmentsTest, MailTemplatesTest, MailRateLimiterTest, NotificationEmailTest, NotificationDocumentationTest y DocumentationLinksTest: 32 pruebas y 321 aserciones correctas. Sintaxis de ambos archivos de runtime válida; validador de skills: 14 skills y 79 enlaces correctos; git diff --check sin errores. Sin verificación SMTP externa ni entrega real. La prueba MIME usa PHPMailer::preSend; la prueba Async serializa la closure y comprueba la copia tras borrar el original, sin lanzar procesos ni SMTP.
