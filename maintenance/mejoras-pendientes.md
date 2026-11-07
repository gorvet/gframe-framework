# Mejoras pendientes no urgentes

Esta lista recoge mejoras opcionales de GFrame que no bloquean las correcciones actuales. Registrar una mejora no autoriza su implementación; se retomará cuando se acuerde expresamente.

## Administración de usuarios

- [ ] **Cuenta y seguridad: cambio de contraseña.** Solicitud del 7 de noviembre de 2026: contraseña actual introducida manualmente, sin autocompletado; un único campo de contraseña nueva, sin confirmación duplicada; medidor de fortaleza del framework y mostrar/ocultar en ambos campos, siguiendo el formulario de registro. Revisar vista, JavaScript y contrato del endpoint al implementarlo. Registrado para una entrega posterior a la mejora multimedia en curso.

## Iconos y navegación del panel

- [ ] **Compatibilidad entre gicon y Bootstrap Icons.** Evaluar el mapeo ya realizado por el usuario en Enconfianza para emplear Bootstrap Icons conservando los nombres `gicon`. Inspeccionar primero esa implementación; no eliminar la compatibilidad de proyectos instalados. Bootstrap Icons es una dependencia separada de Bootstrap. La evaluación no autoriza una sustitución masiva en esta entrega.
- [ ] **Reducir la sangría del menú lateral.** Solicitud del 7 de noviembre de 2026, con captura de referencia: hay demasiado espacio a la izquierda entre títulos de grupo como «Gestión del sitio» y sus ítems «Portada de inicio», «Datos de facturación» y «Recordatorios de pago». Acercar los ítems al inicio del grupo y mantener alineados iconos/textos para mejorar la lectura; comprobar niveles de submenú, selección, foco y móvil. Pendiente de implementación.

## Administración de usuarios: entregado

- [x] **Añadir usuarios desde el admin.** Implementado con una vista propia en Gestión de usuarios, permiso `users.create` e invitación por correo para definir la contraseña y verificarla, según la decisión del propietario. Mantiene la jerarquía de roles, caducidad y consumo único del enlace, recuperación/reenvío y distinción entre alta completada y fallo del correo. Pruebas con cuentas temporales y transporte de correo simulado; formulario comprobado en navegador. Véase [la guía](../docs/user-admin.md).

## Campañas

- [ ] **Enlaces personalizados con variables.** Permitir enlaces cuyas partes dependan del destinatario (por ejemplo, su ID o un enlace de restablecimiento). Idea pendiente de concretar; no implementar todavía. Definir catálogo, generación segura en backend, permisos, caducidad y tratamiento de tokens sensibles. No permitir construir enlaces de acceso únicamente concatenando identificadores ni reutilizar tokens secretos como placeholders genéricos.

## Contenido y multimedia

- [ ] **Multimedia avanzada.** Interés confirmado por el usuario el 19 de octubre de 2026. Retomar el inventario WordPress/GFrame: edición visual no destructiva, imágenes adaptables, consulta de uso, operaciones masivas y procesamiento/metadatos adicionales. Concretar alcance antes de implementar; no confundir las propuestas con las mejoras ya entregadas del selector/editor.
- [ ] **Patrones reutilizables de escritura.** Interés del usuario, pendiente de aclarar y concretar: fragmentos preparados que se insertan en el contenido. Decidir copia independiente o contenido sincronizado; no convertirlo en constructor de páginas.
- [ ] **Importación/exportación de contenido.** Interés confirmado; pendiente de definir entidades, formato, medios, referencias y conflictos. No equivale a un backup completo ni autoriza acceso a otros proyectos.

## Redirecciones

- **Módulo de redirecciones descartado por el propietario.** Se retira de las mejoras previstas; no implementar.

## Auth: aviso administrativo de registro

- [x] **Avisar de nuevas cuentas a administradores o superadministrador.** Implementado el 2026-10-06 mediante `auth.registration_admin_notice`: desactivado por defecto, IDs opcionales con autorización administrativa global comprobada y canales `email`/`inbox`. El evento ocurre al crear la cuenta, no al verificarla; correo mediante Async, sin contraseñas ni tokens y sin alterar el registro ante fallos del aviso. Prueba dirigida con SQLite, selección de destinatarios, duplicados, reenvíos y fallo de transporte simulado. No acredita entrega SMTP real ni sustituye la investigación del correo de registro que el usuario no recibe.

## Alcance

- Drawflow: excluido por ahora por decisión del usuario; la integración original está incompleta.
- Imágenes de perfil de Dane: futura ampliación separada de Cuenta y seguridad, no una función obligatoria del módulo base. Pendiente de definir e implementar.

Las mejoras se implementarán en el framework, no directamente en los demos. Los errores, regresiones y tareas ya en curso no pertenecen a esta lista. Campañas, Notificaciones y Multimedia ya utilizan la estructura nativa con personalización por herencia. `alerts` es un componente frontend JS/CSS, sin rutas ni clases PHP; no requiere conversión al runtime MVC.

## Trabajo solicitado en cola

- Multimedia: integración nativa y recuperación de vistas, CSS y JS originales implementadas. Queda la comprobación del usuario en su proyecto instalado; no se ha actualizado el demo.
