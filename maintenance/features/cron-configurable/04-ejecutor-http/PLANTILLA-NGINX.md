# Plantilla del cron en el fragmento Nginx

Solicitud posterior al cierre: incluir el bloque del cron en nginx.conf, dejar la dirección PHP comentada y explicar su configuración y los cambios de versión de PHP. No cambia el comportamiento predeterminado ni la conciliación histórica: el bloque completo se distribuye comentado para impedir servir código PHP por activar una ubicación sin fastcgi_pass.

Skills canónicas: orchestrator, framework-maintenance y background-jobs. Se conservan los cambios ajenos de medios y no se modifican proyectos instalados.

La guía explica copiar la dirección PHP actual del proyecto, revisar esa dirección al cambiar PHP, descomentar el bloque completo, evitar duplicados y validar/recargar Nginx. Apache no necesita estas directivas; los proxies requieren comprobar su política de desconexión.

Comprobación local: 14 pruebas y 863 aserciones (Documentación, fragmento y actualización), sin fallos. Sintaxis PHP válida. La integración HTTPS/FPM ahora activa y adapta la plantilla original publicada; la directiva de desconexión ya no se aplica al manejador PHP general del fixture. Validación Linux pendiente del CI de este cambio.
