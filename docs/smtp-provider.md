# SMTP desde base de datos

Registra en `config/app.php` una clase autoloadable sin argumentos obligatorios:

```php
'mail' => ['smtp_provider' => DatabaseSmtpProvider::class],
```

Con `null` o sin esta opción se mantiene `.env`. Para una clase global, guarda este ejemplo en `app/services/DatabaseSmtpProvider.php`:

```php
<?php
use GFrame\Mail\Contracts\SmtpConfigurationProvider;

final class DatabaseSmtpProvider implements SmtpConfigurationProvider
{
    public function resolve(): array
    {
        $pdo = DatabaseManager::connection();
        $row = $pdo->query('SELECT host, port, username, password_encrypted, encryption, from_address, from_name FROM smtp_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('SMTP no configurado.');
        return [
            'host' => (string)$row['host'],
            'port' => (int)$row['port'],
            'username' => (string)$row['username'],
            'password' => ApplicationSecrets::decrypt($row['password_encrypted']),
            'encryption' => (string)$row['encryption'],
            'from' => (string)$row['from_address'],
            'from_name' => (string)$row['from_name'],
        ];
    }
}
```

La tabla y `ApplicationSecrets::decrypt()` pertenecen a la aplicación: implementa su esquema y descifrado. Su formulario administrativo debe comprobar permisos, validar datos y guardar la contraseña cifrada. Nunca devuelvas contraseñas al navegador ni las registres. El core no incluye pantalla ni almacenamiento de ajustes.

Cada envío consulta nuevamente al proveedor, incluso en instancias existentes. Los cambios guardados desde el admin afectan al siguiente envío directo, Async, de notificaciones, campañas o cron que utilice MailService. Evita cachés permanentes.

Los workers arrancan el proyecto y resuelven la clase desde `config/app.php`; necesitan autoload, conexión y claves de descifrado propios. El proveedor no debe depender de sesión web ni usuario conectado. Las credenciales no se capturan en las closures Async. Cambiar la clase registrada requiere reiniciar procesos persistentes; cambiar sus datos no.

Se conservan validación de host/remitente y transporte TLS (`ssl`/465 o `tls`/587 según el servidor). Devuelve todas las claves del contrato. Se mantienen `mail_host_not_configured`, `mail_sender_not_configured` y `mail_send_failed`; un fallo del proveedor devuelve este último sin registrar su excepción ni volver silenciosamente a `.env`.

La inyección `new MailService(configuration: $provider)` tiene prioridad para envíos síncronos de esa instancia. Async crea otra instancia y utiliza el registro del proyecto, igual que los servicios internos. Registra el proveedor en el proyecto para no cambiar cada llamada a MailService.
