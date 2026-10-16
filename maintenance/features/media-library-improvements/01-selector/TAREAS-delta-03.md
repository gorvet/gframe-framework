# Delta 03 — Sincronización de teclado

**Principal:** no
**Cierra:** AUDIT.md pase 3, no coincide; CA-01–04, E-07.
**Estado:** implementada

- [x] D03-T1 — Esperar shown.bs.modal antes de interactuar con teclado en el fixture, conservando la pulsación Enter real y todas las aserciones de orden.
  Verificar: `sistema`; recorrido completo y suite de navegador con casos existentes, sin omisiones, bajo ejecución concurrente con regresión PHP.

No altera el runtime ni el baseline.

Verificación: cinco pruebas JS/navegador aprobadas concurrentemente con 35 pruebas PHP/1085 aserciones, sin omisiones en navegador. La ordenación continúa usando Enter, sin reemplazarla por un clic para eludir el caso.
