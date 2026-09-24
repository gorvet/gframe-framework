# Autenticación

GFrame separa la lógica de autenticación del esquema de usuarios de cada aplicación.

El proyecto implementa `GFrame\Auth\Contracts\AuthUserRepository`. El contrato permite buscar usuarios por correo o token, crear una cuenta pendiente y actualizar únicamente los campos relacionados con autenticación.

`GFrame\Auth\AuthService` proporciona:

- registro con normalización de correo y política de contraseña;
- verificación de cuenta;
- acceso sin revelar si falló el correo o la contraseña;
- solicitud y aplicación del restablecimiento de contraseña;
- reenvío seguro de la verificación;
- tokens aleatorios con caducidad configurable.

`GFrame\Auth\SessionManager` regenera la sesión al acceder, mantiene una identidad normalizada, admite claves adicionales del proyecto y destruye la sesión al salir.

Las vistas, mensajes de correo, roles iniciales, áreas, redirecciones y reglas particulares permanecen en cada aplicación.
