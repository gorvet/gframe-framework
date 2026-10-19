# Delta 01 — cierre ambiental y fallos de infraestructura

**Principal:** no
**Cierra:** AUDIT.md pasada 1; CA-04, CA-05; E-02, E-03.
**Hueco:** repetir evidencia Linux y ejecutar fallos HTTP reales con diagnóstico acotado.
**Estado:** implementada

## Listado

- [x] D01-T1 — Reejecutar perfil Linux/MySQL y contrastar su snapshot con el actual (CA-04).
  Verificar: nivel `ambiental`; Actions Ubuntu 24.04/PHP 8.3/MySQL 8; diez competencias, cero duplicados, recuperación y lote acotado; job aprobado con log y SHA. Windows: ejecución local del mismo día. Conservar ventana y métricas en AUDIT.md.
- [x] D01-T2 — Acreditar fallos de CLI/esquema y logs acotados mediante el delta de etapa 02 (CA-05).
  Verificar: nivel `sistema`; HTTP conserva respuesta, sin sesión añadida y sin tormenta de logs.

## Fuera de este delta

Producción, publicación y cambios a aplicaciones.
