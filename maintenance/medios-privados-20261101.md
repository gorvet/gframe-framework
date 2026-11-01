# Medios privados y entrega protegida

Petición autorizada: estándar configurable, biblioteca pública/privada, descarga interna por permisos y enlaces externos independientes; sin reglas de negocio específicas. Incluye documentación, skills, publicación Composer y actualización del sitio local de documentación.

Skills canónicas: gframe-orchestrator, gframe-media-module, gframe-backend, gframe-auth-access, gframe-orm-models y gframe-framework-maintenance. Se conservan los ámbitos global/user/tenant y las firmas existentes mediante argumentos opcionales. No se crean carpetas lógicas ni reglas particulares de conservación.

Comprobaciones: almacenamiento y contención de rutas, migraciones SQLite/MySQL, autorización de listas/detalles/variantes, enlaces revocables/caducables y concurrencia, entrega HTTP, compatibilidad de medios, composer check/analyse/validate/audit, JavaScript y skills. Publicar únicamente tras resolver los errores nuevos; registrar límites reales.

Base limpia: 11824b7 (v4.6.0). Versión prevista: 4.7.0.

## Verificación local

- `composer check`: PHP válido (530 archivos), 616 pruebas / 6043 aserciones, 7 omisiones de infraestructura; skills válidos (14), 80 enlaces locales. MySQL usa una base temporal aislada; Apache real está habilitado. La primera ejecución con DSN sin base falló en un test ajeno que requiere una base seleccionada; se corrigió el entorno y se repitió toda la suite.
- Migraciones nuevas ejecutadas sobre SQLite y MySQL reales, tanto actualización de registros públicos existentes como esquema nuevo/baseline.
- Apache real: bloqueo físico de private/, enlace válido/revocado/inválido, GET/HEAD/rangos y rechazo de acceso interno anónimo.
- Chrome real: gestión y selector completos pasan (2/2) con transporte loopback PHP temporal porque Node rechaza sockets locales con EACCES en este host. Conserva respuestas HTTP reales. La primera ejecución amplia tuvo 97 éxitos, 2 fallos y 1 timeout; se corrigió la pérdida de origen al filtrar sin resultados y se repitieron ambos recorridos de medios con transporte funcional. El timeout del recorrido editorial instalado queda pendiente de CI.
- `composer validate --strict`, `composer audit --locked`, validación PHP de skills y `git diff --check`: correctos.
- Sitio local: HTTP 200 en https://localhost/gframe-docs/guias/archivos-privados, leyendo docs/ del framework. Se añadió la guía al grupo Contenido y multimedia, sin duplicar su contenido.
- Publicado el código en main (0653abd). CI confirma PHPStan, YAML, JavaScript sin navegador, MySQL, Redis, Nginx/PHP-CGI, cron HTTPS y procesos Windows. La primera matriz PHP de Linux detectó que el fallo simulado de rollback escribía en stderr y PHPUnit lo interpretaba como error de su proceso aislado; el test ahora dirige ese log al directorio temporal, como las otras pruebas de medios. Pendiente de confirmar la repetición de CI y publicar la etiqueta.

## Límites del contrato

API local de archivos y autorizaciones independiente de la biblioteca; no hay reglas por negocio. Los límites cuentan solicitudes GET autorizadas, no transferencias completas. La conservación/borrado se decide y programa desde cada proyecto; no hay borrado automático. Las rutas y servicios de emisión/revocación no añaden controles visuales. Las carpetas lógicas quedan fuera del alcance acordado. Los servicios PHP sin MediaAccessPolicy requieren un llamador previamente autorizado.
