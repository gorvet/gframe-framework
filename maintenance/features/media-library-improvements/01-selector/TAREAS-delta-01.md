# Delta 01 — Confirmar una selección vacía

**Principal:** no
**Cierra:** AUDIT.md pase 1, no coincide; CA-03 y CA-04, E-02.
**Estado:** auditada

- [x] D01-T1 — Al vaciar la selección invalidar el render anterior y actualizar la habilitación de confirmación.
  Verificar: `sistema`; servidor field retrasado, retirar ambas imágenes y confirmar []; pruebas de navegador y regresión PHP.

No altera el baseline ni el comportamiento append de consumidores ajenos.

Verificación de implementación: cinco pruebas JS/navegador aprobadas, sin omisiones. Se conserva el mismo caso que falló en E-02; ahora confirma [] tras retirar toda la composición con field retrasado. No constituye auditoría.
