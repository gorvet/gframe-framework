# Delta 02 — Nginx y desconexión del lanzador

**Principal:** no
**Cierra:** AUDIT.md pase 3, no coincide; CA-02 y CA-05, E-08.
**Hueco:** Nginx puede cancelar el POST al cerrar el socket antes de que PHP-FPM inicie el endpoint.
**Estado:** implementada

- [x] D02-T1 — Documentar fastcgi_ignore_client_abort on en el manejador PHP del endpoint HTTP de Nginx; reflejarlo en ayuda y skill. Sin introducir un socket o manejador PHP en el fragmento reutilizable.
  Verificar: documentación y enlaces, comparación con configuración ejecutada.
- [x] D02-T2 — Comprobar la configuración corregida con TLS y PHP-FPM reales, incluyendo respuesta independiente, autenticación, certificados, sesión y límite.
  Verificar: job Linux dedicado sin omisiones ni fallos; demás jobs de CI aprobados.

No modifica aplicaciones instaladas ni el runtime del cron.

Verificación: CI 38819363756, commit 6fbd1a0, nueve jobs aprobados; HTTPS/FPM 1 prueba, 33 aserciones, sin omisiones. Ver HTTPS-FPM.md. Documentación/estructura Nginx: 5 pruebas, 143 aserciones. Skills: 14 y 79 enlaces.
