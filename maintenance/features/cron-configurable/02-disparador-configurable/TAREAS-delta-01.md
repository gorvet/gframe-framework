# Delta 01 — evidencia de límites del disparador

**Principal:** no
**Cierra:** AUDIT.md pasada 1; CA-05, CA-06, CA-07; E-03.
**Hueco:** faltan intentos HTTP con entradas hostiles, fallos reales de infraestructura y extremos de configuración.
**Estado:** auditada

## Listado

- [x] D01-T1 — Ejercitar parámetros/cookies/cuerpo hostiles y canales excluidos, sin código seleccionado desde HTTP (CA-05).
  Verificar: nivel `sistema`; servidor temporal y SQLite; ninguna ejecución del handler suministrado por el cliente.
- [x] D01-T2 — Romper PHP CLI y retirar cron_runtime; repetir peticiones y comprobar respuesta, reserva y diagnóstico acotado (CA-06).
  Verificar: nivel `sistema`; fallo real, respuesta HTTP conservada, ticket liberado y un solo log dentro del intervalo.
- [x] D01-T3 — Ejecutar extremos válidos e inválidos de intervalo/lote (CA-07).
  Verificar: nivel `conductual`; 1/86400 y 1/100 aceptados; valores exteriores rechazados sin lanzamiento.

## Fuera de este delta

Cambios de producto, publicación y sitios instalados.
