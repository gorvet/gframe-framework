# Recuperación del acceso tras caducar el formulario

Alcance: fuentes del framework, sin actualizar aplicaciones ni publicar otra versión. Skills canónicas: orchestrator, auth-access, frontend-admin y framework-maintenance. El middleware CSRF conserva su validación y códigos.

Ampliación autorizada: commit, push y publicación Composer como v5.2.1. Se reutilizan las comprobaciones acotadas ya aprobadas; no se repite la batería general.

Contrato: successError identifica explícitamente la navegación ya gestionada. Los consumidores de autenticación no muestran avisos posteriores. Las solicitudes auth recuperan los campos CSRF desde un GET del login del mismo origen y reintentan solo una vez tras to_reload o fail_csrf; conservan el resto del payload. Un segundo rechazo o recuperación fallida termina con un mensaje visible, sin recargas ni bucles. La recuperación no ejecuta scripts ni sustituye el documento.

Verificación prevista: navegador con jQuery y scripts originales, sesión de invitado perdida/token antiguo, rechazo repetido, fallo de recuperación y respuestas HTTP de rechazo; regresiones acotadas de autenticación y sintaxis de los archivos editados.

Resultados: 13 pruebas PHP y 128 aserciones correctas (VisitorCsrfTest y AuthUiTest). Navegador Chrome con jQuery y scripts nativos: to_reload, fail_csrf en HTTP 200/403, rechazo repetido, respuesta sin campos y GET fallido; sin recarga ni ejecución del HTML recuperado, sin avisos vacíos, con campos conservados y bloqueo de envío liberado. Los rechazos HTTP del navegador son simulados; las pruebas PHP comprueban por separado el validador y las sesiones anónimas administradas. No se usaron cuentas ni sesiones reales. Regresiones de auth, callbacks y navegación gestionada correctas. No se ejecutó una batería general ni se modificó el validador CSRF.
