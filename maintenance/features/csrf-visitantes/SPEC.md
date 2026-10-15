# CSRF para visitantes

**Estado:** alcance aprobado para implementación el 2026-10-15; auditoría separada pendiente.
**Fecha:** 2026-10-15.

## Contexto

El usuario solicita protección CSRF en formularios públicos de visitantes. El honeypot no sustituye esta protección. Esta feature no es una dependencia del cron configurable.

## Situación comprobada

- `SessionRuntime::start()` puede iniciar sesiones anónimas, pero no genera tokens CSRF.
- `SessionManager::login()` genera `csrfToken` y `csrfTimestamp` al autenticar.
- El header del skeleton publica campos desde sesión, que pueden estar vacíos antes del login.
- El middleware valida los tokens, mientras las rutas del módulo `auth-ui` excluyen expresamente CSRF.

## Alcance aprobado

- **R-01:** disponer de token válido en sesiones anónimas que presentan formularios de acciones públicas.
- **R-02:** helper reutilizable para formularios y AJAX; no basta con emitir campos vacíos.
- **R-03:** validación del servidor y revisión explícita de exclusiones en autenticación; conservar protecciones contra bots.
- **R-04:** preservar renovación al autenticar, comportamiento entre pestañas y sesiones caducadas; revisar respuesta de token ausente sin exigir login a un visitante.
- **R-05:** respetar rutas `noSession()` y evitar tokens de sesión compartidos mediante caché pública.
- **R-06:** pruebas de token ausente, incorrecto, de otra sesión, origen inválido, rotación, sesión caducada y flujos públicos afectados; documentar actualización de formularios personalizados.

## Fuera de alcance

- Cron, aplicaciones consumidoras y sustitución general de controles antispam.
- Activar sesiones en todas las páginas públicas de manera indiscriminada.

## Diseño técnico adoptado

SessionRuntime genera tokens al iniciar sesiones normales; el helper CsrfToken::fields() no inicia sesiones y devuelve vacío en noSession. Los formularios Auth originales incluyen tokens y sus rutas mantienen CSRF. Se conservan los nombres existentes y la renovación al autenticar. Las respuestas con token son privadas/no-store y los formularios personalizados necesitan conservar envío y validación.
