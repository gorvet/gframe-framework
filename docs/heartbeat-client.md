# Cliente Heartbeat

El módulo `heartbeat-client` reúne las consultas periódicas del navegador, coordina las pestañas abiertas y sincroniza la expiración y el cierre de sesión. Se instala como dependencia de `auth-ui`.

## Integración

Publica `public/js/core/heartbeat.js`, `public/js/core/session.js` y la ruta `ajax/heartbeat`. El controlador original permanece en el paquete; las personalizaciones van en `app/controllers/heartbeat-client/HeartbeatController.php`.

La ruta requiere autenticación y utiliza `noRefreshSession()`: las consultas periódicas no prolongan la sesión. La pestaña líder consulta los canales registrados y comparte los resultados con las demás pestañas.

## Personalización

Extienda `GFrame\Modules\HeartbeatClient\Controllers\HeartbeatController`, conserve `parent::__construct()` y registre los canales del proyecto. No modifique el dispatcher ni consulte el estado de la cuenta en cada tick. Las operaciones que cambian datos necesitan rutas propias y protección CSRF.

La [guía de Heartbeat](heartbeat.md) documenta la ruta, los canales, sus respuestas, los eventos JavaScript, la coordinación entre pestañas y las reglas de seguridad.
