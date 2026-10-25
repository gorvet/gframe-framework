# Delta 01 — ciclo HTTP de la sesión anónima

**Principal:** no
**Cierra:** AUDIT.md pasada 1; CA-03; E-02.
**Hueco:** falta prueba entre peticiones de varias pestañas y pérdida/recuperación de sesión.
**Estado:** auditada

## Listado

- [x] D01-T1 — Dos cargas con una cookie conservan token; otra sesión no puede usarlo; destruir la sesión invalida el formulario y recargar permite recuperar (CA-03).
  Verificar: nivel `sistema`; servidor PHP temporal, cookies y ficheros de sesión reales, POST protegido antes/después, sin usuarios reales.

## Fuera de este delta

Cambios de autenticación, antispam o aplicaciones consumidoras.
