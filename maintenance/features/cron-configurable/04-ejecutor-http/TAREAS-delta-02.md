# Delta 02 — Nginx y desconexión del lanzador

**Principal:** no
**Cierra:** AUDIT.md pase 3, no coincide; CA-02 y CA-05, E-08.
**Hueco:** Nginx puede cancelar el POST al cerrar el socket antes de que PHP-FPM inicie el endpoint.
**Estado:** en curso

- [ ] D02-T1 — Documentar fastcgi_ignore_client_abort on en el manejador PHP del endpoint HTTP de Nginx; reflejarlo en ayuda y skill. Sin introducir un socket o manejador PHP en el fragmento reutilizable.
  Verificar: documentación y enlaces, comparación con configuración ejecutada.
- [ ] D02-T2 — Comprobar la configuración corregida con TLS y PHP-FPM reales, incluyendo respuesta independiente, autenticación, certificados, sesión y límite.
  Verificar: job Linux dedicado sin omisiones ni fallos; demás jobs de CI aprobados.

No modifica aplicaciones instaladas ni el runtime del cron.
