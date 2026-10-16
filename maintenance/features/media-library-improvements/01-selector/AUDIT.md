# Auditoría — Selector

## Pase 1 — 2026-10-15

Baseline: CONCILIACION.md cerrada 2026-10-15; principal implementada. HEAD anterior 4f901dc, implementación en árbol de trabajo; cambios previos de toast e investigación conservados. Windows/PHP 8.3.35/SQLite/Chrome/Node. **Veredicto: no coincide.**

| Evidencia | Procedimiento | Esperado | Observado |
|---|---|---|---|
| E-01 | Filtro PHP de VERIFICACION.md repetido en auditoría | Sin regresiones | Salida 0; 35 pruebas, 1085 aserciones |
| E-02 | `node --test tests/js/media-picker-workflow.browser.test.cjs`, Chrome; se añade intento negativo con field retrasado y retirada de toda la selección | Confirmar composición vacía | Salida 1; botón permanece disabled tras eliminar todas las imágenes |
| E-03 | Validador de etapa | Sin errores | Salida 0; cinco advertencias sobre formato del tipo de CA |

| CA | Resultado | Motivo |
|---|---|---|
| CA-01 | cumple | Ejecución de implementación: subida real, lote inverso, errores, máximo y sesión cerrada; no se detecta contradicción en revisión |
| CA-02 | cumple | Guardado real normalizado y rechazo 403 de UX documentado; permisos/rutas sin cambios, scopes acreditados por E-01 |
| CA-03 | no cumple | E-02 contradice confirmación de composición vacía con solicitudes pendientes |
| CA-04 | parcial | E-02 deja un estado inválido de confirmación; otras secuencias, progreso y teclado cubiertos |
| CA-05 | cumple | E-01 y revisión de fuentes/docs: scopes, whitelist de fragmentos y publicación preservados |

No se modificó runtime durante el pase. La prueba adicional es un intento independiente de falsar la finalización. No hace falta mutación global: E-02 proporciona sensibilidad directa. El cierre requiere [delta 01](TAREAS-delta-01.md) y nueva auditoría contra el mismo baseline. La inyección 403 del fixture verifica la interfaz, no reemplaza una auditoría del motor de permisos.

## Pase 2 — revisión de convenciones, 2026-10-15

Mismo baseline; delta 01 implementado. Pruebas de regresión repetidas tras la corrección. E-04: cinco pruebas JS/navegador sin omisiones, incluida la composición vacía con respuesta retrasada; E-05: filtro PHP de E-01 repetido, 35 pruebas y 1085 aserciones, salida 0. La revisión de frontend-admin/backend exige que los nuevos textos de interfaz procedan de vistas/payloads: se detectaron textos nuevos de estado en JS. El HTML ya se renderiza correctamente en PHP, pero falta ese ajuste de convención.

CA-01, CA-02, CA-03 y CA-04: cumple con E-04/E-05 y revisión de código. CA-05: parcial por la ubicación de los textos nuevos. **Veredicto: no coincide.** [Delta 02](TAREAS-delta-02.md). No hay cambio de producto ni baseline. El pase 1 no acreditaba de nuevo todos los flujos al detenerse en E-02: su resultado sigue siendo negativo y las comprobaciones completas quedan registradas en este pase.

## Pase 3 — 2026-10-15

Delta 02 implementado; mismo baseline. E-06: filtro PHP repetido, 35 pruebas/1085 aserciones y validador estructural correctos (cinco advertencias de formato). E-07: cinco pruebas JS/navegador, cuatro aprobadas y una fallida: el recorrido de teclado no llegó al orden esperado al presionar Enter antes de asegurar shown.bs.modal. El recorrido individual había pasado, pero no se acepta una prueba intermitente. Textos nuevos revisados: proceden de vistas PHP y datos del backend.

**Veredicto: no coincide.** CA-01, CA-02 y CA-03: no verificado de nuevo por interrupción temprana del recorrido en E-07; CA-04: parcial; CA-05: cumple en fuentes/PHP, sujeto a completar regresión de navegador. [Delta 03](TAREAS-delta-03.md): sincronizar el test con la apertura efectiva de Bootstrap y repetir sin reparar comportamiento del producto por una suposición de prueba.

## Pase 4 — cierre, 2026-10-15

**Contra:** TAREAS.md principal y deltas 01–03 implementados.
**Baseline:** conciliación cerrada 2026-10-15, origen de tareas de la misma fecha; HEAD 4f901dc más cambios de implementación del árbol de trabajo. No se altera el baseline ni se incluyen aplicaciones instaladas. Entorno Windows, PHP 8.3.35, SQLite, Chrome real y Node/Playwright.

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno/fecha | Artefacto | Limitación |
|---|---|---|---|---|---|---|---|
| E-08 | CA-01–04 | Comando de cinco tests JS/navegador de VERIFICACION.md, repetido después del delta 03 | Todos los flujos y regresiones correctos, sin omisiones | Salida 0; cinco pruebas aprobadas, cuatro con Chrome; 25 segundos | Windows/Chrome/Node; 2026-10-15 | media-picker-workflow.browser.test.cjs y capturas .playwright-mcp | El error 403 y la URL externa se inyectan en el fixture; subidas, detalles, fragmentos y guardado sí usan PHP/SQLite originales |
| E-09 | CA-02, CA-03, CA-05 | Filtro PHP de VERIFICACION.md repetido concurrentemente con E-08 | Scope, metadatos, fragments y publicación preservados | Salida 0; 35 pruebas/1085 aserciones | Windows/PHP 8.3.35/SQLite; 2026-10-15 | Salida PHPUnit | No es la suite global de publicación |
| E-10 | CA-05 | `php bin/validate-skills.php`; git diff --check; sintaxis PHP/JS afectada | Convenciones/enlaces/sintaxis correctos | Salida 0; 14 skills y 79 enlaces; sin errores de whitespace/sintaxis | Mismo entorno/fecha | Salida CLI y fuentes | Avisos CRLF/LF de Git no son errores |
| E-11 | CA-04, CA-05 | Inspección de capturas y fuentes finales; validador de etapa | UX nativa y contrato estructural válido | Desktop 1440×1000 y móvil 390×844; contrato válido | Mismo entorno/fecha | VERIFICACION.md y capturas locales | Cinco advertencias de formato del tipo de CA; no se cambia la conciliación. Sin lector de pantalla real ni certificación WCAG |

### Criterios principales

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Éxitos seleccionados en orden de entrada pese a respuestas inversas; fallo parcial, máximo, selección simple y callbacks de sesión cerrada | T1 | cumple | E-08 | Transporte externo de hotlink simulado, sin tráfico a Internet |
| CA-02 | Detalles y guardado normalizado sin salir; cambios pendientes protegidos; fallo 403 no cambia SQLite; rutas auth/can y fuente CSRF conservadas | T2/T3 | cumple | E-08/E-09, inspección rutas | Se verifica UX de 403, no se reaudita el motor de autorización; ese motor no cambia |
| CA-03 | Composición completa de IDs, orden con Enter, retirar, confirmar vacío con field retrasado; edición explícita sustituye append y resto de campos conserva contrato | T2/T3, delta 01 | cumple | E-08/E-09 | Ordenación por botones; no se promete arrastre |
| CA-04 | Consulta reciente gana a antigua; progreso/errores visibles; teclado, foco y estados; reapertura espera cierre, test espera shown antes de teclado | T1/T3, deltas 01/03 | cumple | E-08/E-11 | Prueba visual con imágenes sintéticas mínimas |
| CA-05 | Sin tablas nuevas; mismo ámbito y originales; HTML y nuevos textos en PHP; serialización y validación del formulario consumidor conservadas; documentación/skills actualizadas | T3, delta 02 | cumple | E-09/E-10/E-11 | No implica release ni despliegue |

**Veredicto:** coincide

Etapa cerrada. Métodos principales y deltas cubiertos por ejecuciones nuevas, revisión inversa de cambios sin desvíos bloqueantes. Se conserva todo el historial negativo. No se aplicó una mutación global: los fallos reproducidos de cierre, composición vacía y sincronización del fixture acreditan sensibilidad de las aserciones.

### Revisión de alcance

Nueva selección y progreso en componentes existentes; respuesta save añade data normalizada sin retirar claves; fragmento selection permanece en lista explícita y respeta el máximo y propiedad. No se reintroducen relaciones genéricas, ámbitos nuevos ni renombrado físico. El render de tarjetas/bandeja y plantillas de progreso permanece en PHP; el JS coordina comportamiento y utiliza mensajes del backend/vista. Se respetan Bootstrap, tokens, alertas y nombres existentes. La corrección previa del toast y su test no se eliminan ni se consideran trabajo nuevo de este ciclo.
