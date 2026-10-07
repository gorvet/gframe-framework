# Mejoras pendientes no urgentes

Esta lista recoge mejoras opcionales de GFrame que no bloquean las correcciones actuales. Registrar una mejora no autoriza su implementación; se retomará cuando se acuerde expresamente.

## Administración de usuarios

- [ ] **Añadir usuarios desde el admin.** Prioridad baja; pendiente, sin implementar. Incorporar «Añadir usuario» dentro de Gestión de usuarios, con una vista propia y un permiso específico. Mantenerlo separado del registro público y de Mi cuenta. Antes de implementarlo, acordar cómo se entregará el acceso inicial y cómo se verificará la cuenta. Respetar la jerarquía de roles y los contratos de autenticación existentes.

## Campañas

- [ ] **Enlaces personalizados con variables.** Permitir enlaces cuyas partes dependan del destinatario (por ejemplo, su ID o un enlace de restablecimiento). Idea pendiente de concretar; no implementar todavía. Definir catálogo, generación segura en backend, permisos, caducidad y tratamiento de tokens sensibles. No permitir construir enlaces de acceso únicamente concatenando identificadores ni reutilizar tokens secretos como placeholders genéricos.

## Auth: aviso administrativo de registro

- [x] **Avisar de nuevas cuentas a administradores o superadministrador.** Implementado el 2026-10-06 mediante `auth.registration_admin_notice`: desactivado por defecto, IDs opcionales con autorización administrativa global comprobada y canales `email`/`inbox`. El evento ocurre al crear la cuenta, no al verificarla; correo mediante Async, sin contraseñas ni tokens y sin alterar el registro ante fallos del aviso. Prueba dirigida con SQLite, selección de destinatarios, duplicados, reenvíos y fallo de transporte simulado. No acredita entrega SMTP real ni sustituye la investigación del correo de registro que el usuario no recibe.

## Alcance

- Drawflow: excluido por ahora por decisión del usuario; la integración original está incompleta.
- Imágenes de perfil de Dane: futura ampliación separada de Cuenta y seguridad, no una función obligatoria del módulo base. Pendiente de definir e implementar.

Las mejoras se implementarán en el framework, no directamente en los demos. Los errores, regresiones y tareas ya en curso no pertenecen a esta lista. Campañas, Notificaciones y Multimedia ya utilizan la estructura nativa con personalización por herencia. `alerts` es un componente frontend JS/CSS, sin rutas ni clases PHP; no requiere conversión al runtime MVC.

## Trabajo solicitado en cola

- Multimedia: integración nativa y recuperación de vistas, CSS y JS originales implementadas. Queda la comprobación del usuario en su proyecto instalado; no se ha actualizado el demo.
