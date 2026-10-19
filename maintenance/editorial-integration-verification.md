# Integración editorial en una aplicación temporal

2026-10-19. Fuente del framework: `6831ae3`; aplicación independiente creada por `ProjectInstaller`, perfil managed sin tenant, SQLite y datos ficticios. Ningún proyecto existente fue modificado.

## Recorrido comprobado

- Instalación de los módulos y publicación de sus recursos; arranque propio mediante `core/Load.php`, configuración, registro de módulos y Router.
- Login nativo con contraseña y sesión reales, auth/admin, CSRF y autorización del registro. Escritura sin token y registro de otro propietario rechazados.
- TinyMCE con formulario de guardado y selector de fecha; guardado por revisión y rechazo de formulario obsoleto.
- Botón de preview en la plantilla pública real del fixture; autoguardado privado actualiza la pestaña sin cambiar el contenido público. Cabeceras private/no-store/noindex comprobadas.
- Listado, restauración y vaciado confirmado de revisiones, conservando el contenido actual.
- Papelera oculta el artículo público, restauración lo recupera, borrado definitivo exige estado y confirmación.
- Programación retira el contenido de la publicación; cancelación funciona; después de vencer la fecha, el CLI instalado ejecuta el handler y publica. Segunda ejecución no cambia la revisión.
- Petición HTTP anónima de estado rechazada sin revelar el texto privado.

## Evidencia reproducible

Con `GFRAME_TEST_BROWSER` apuntando a Chrome, `NODE_PATH` a las dependencias con Playwright y PHP 8.3.35:

`node --test tests/js/editorial-integration.browser.test.cjs`

Resultado final: **1 prueba aprobada, ninguna omisión, exit 0**, 103,849 s. Log local: `.phpunit.cache/editorial-integration.log`. Lint de los 13 PHP del fixture, sintaxis de ambos JS y `git diff --check` correctos.

La prueba crea y elimina su directorio temporal; el servidor PHP escucha solo en loopback y se detiene al finalizar. No requiere correo ni credenciales reales.

## Límites y ajustes del fixture

El proceso Node/Chrome no puede abrir sockets locales en este entorno (`EACCES`). El navegador utiliza un puente PHP que realiza peticiones HTTP al servidor PHP real y devuelve sus respuestas/cookies, sin simular controladores ni resultados. El rechazo anónimo se verifica con ese transporte HTTP sin seguir la redirección. La primera aserción de restauración comparaba HTML de TinyMCE con texto sin entidades; se corrigió para comparar texto visible. Los ajustes afectan al arnés, no al runtime.

La aplicación usa un adaptador de autoload hacia este checkout de desarrollo. **No se ha probado descargar una versión de Composer**, ni un despliegue de producción, ni configuración de cron del sistema. Se valida integración global sin tenant; no sustituye las pruebas específicas de tenancy de cada módulo ni integra las funciones en aplicaciones existentes.

Solo se añaden fixtures y comprobación de integración. No se alteran contratos del framework para adaptar esta aplicación. Las propuestas futuras y los aplazamientos previos se conservan aparte.
