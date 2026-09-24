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

`GFrame\Auth\SelfAccountService` permite consultar el perfil propio, actualizar el nombre, cambiar la contraseña y desactivar la cuenta. El proyecto aporta un repositorio y una política de protección que debe impedir la baja del superadministrador.

Toda instalación debe crear un único superadministrador como primer usuario. Esta identidad tiene acceso superior, no puede desactivarse desde la cuenta propia y no depende de poseer el rol `admin`. Los administradores adicionales son opcionales, se asignan a otros usuarios y nunca equivalen al superadministrador.

Los nombres de los roles administrativos se configuran mediante `auth.administrator_roles`. La aplicación conecta su esquema de usuarios mediante la sesión normalizada y la política de protección.

Las vistas, mensajes de correo, roles iniciales, áreas, redirecciones y reglas particulares permanecen en cada aplicación.
