# Cuenta, iconos y menú pendientes de 5.1.0

Implementación solicitada el 7 de noviembre de 2026. No publicar otra etiqueta. Fuentes del framework; Enconfianza2 se consulta solo para reproducir su mapeo intacto. No modificar aplicaciones instaladas.

Actualización de alcance: el usuario autorizó después commit, push, etiqueta y actualización local del sitio documental. Publicación prevista 5.2.0, con las comprobaciones acotadas ya realizadas; no se repiten suites generales.

Skills canónicas: gframe-orchestrator, gframe-auth-access, gframe-backend, gframe-frontend-admin y gframe-framework-maintenance. Comprobaciones acotadas: servicio/UI de cuenta, publicación de iconos y navegador de las tres zonas afectadas; sin suite general ni actualización del sitio documental.

Decisiones: formulario con contraseña actual autocomplete=off y una única nueva contraseña, medidor password-utils y botones mostrar/ocultar ya usados por el registro. Confirmación opcional en el servicio; se valida si un consumidor anterior la envía. Se preservan bytes, contraseña actual, política y revocación de sesiones.

Iconos: copiar el mapeo y distribución Bootstrap Icons 1.13.2 de Enconfianza2 sin reconstruirlos. Los 136 iconos simples originales tienen equivalencia. Mantener estilo, clases, rutas y glifos multicolor originales como compatibilidad; dependencia bootstrap-icons publicada localmente. Menú: reducir 20 px la sangría de enlaces principales y submenús, incluidos panel expandido y hover; conservar la jerarquía y el modo compacto.

Verificación: SelfAccountServiceTest + SelfAccountUiTest, 15 pruebas/63 aserciones correctas. Una prueba de navegador específica ejecuta el formulario y controlador nativos con una cuenta ficticia en memoria: actual incorrecta rechazada, envío de una sola nueva contraseña sin confirmación y con CSRF, contraseña actual obligatoria, medidor, ambos controles Mostrar/Ocultar y limpieza de campos/estado tras guardar. También publica gframe-icons mediante el publicador real, verifica la dependencia Bootstrap Icons, las 136 equivalencias y la fuente aplicada, y mide la sangría de la barra. Escritorio 1440×900 y móvil 390×844 sin desbordamiento. Se conserva el flujo previo de revocación del servicio; sin mutar cuentas reales. Capturas locales en .playwright-mcp/account-password-*.

Lint de los PHP/JS afectados y diff correctos. La prueba de iconos/documentación existente pasa (2/2). No se ejecutan suites generales, no se cambian aplicaciones instaladas, no se publica ni se crea etiqueta.
