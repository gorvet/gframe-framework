# Cifrado de datos de aplicación

`GFrame\Security\Encryption` cifra y descifra strings mediante **AES-256-GCM**. El payload incluye versión, IV aleatorio, tag de autenticación y ciphertext.

La clase no se configura automáticamente ni obtiene una clave por sí sola: el proyecto debe construirla con una clave no vacía.

## Uso básico

```php
use GFrame\Security\Encryption;

$key = (string)($_ENV['DATA_ENCRYPTION_KEY'] ?? getenv('DATA_ENCRYPTION_KEY') ?: '');
$encryption = new Encryption($key);

$encrypted = $encryption->encrypt('contenido sensible');
$plainText = $encryption->decrypt($encrypted);
```

`encrypt()` y `decrypt()` trabajan con strings. Serializa estructuras antes de cifrarlas y valida su forma después de descifrarlas.

Ejemplo con JSON:

```php
$payload = json_encode([
    'account' => $account,
    'token' => $token,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$encrypted = $encryption->encrypt($payload);
```

Al recuperar:

```php
$data = json_decode(
    $encryption->decrypt($encrypted),
    true,
    512,
    JSON_THROW_ON_ERROR
);
```

El cifrado protege el contenido; no sustituye la validación de la estructura de datos.

## Qué hace internamente

La implementación actual:

1. rechaza una clave vacía;
2. deriva una clave binaria de 256 bits mediante SHA-256 sobre el string recibido;
3. obtiene la longitud de IV requerida por `aes-256-gcm`;
4. genera un IV aleatorio con `random_bytes()` en cada cifrado;
5. cifra con `openssl_encrypt()` y obtiene el tag GCM;
6. construye un payload JSON versionado;
7. codifica el JSON completo en Base64.

La estructura lógica del payload es:

```json
{
  "v": 1,
  "iv": "...base64...",
  "tag": "...base64...",
  "data": "...base64..."
}
```

Después, ese JSON se vuelve a codificar en Base64 para formar el string almacenado.

No dependas de esta representación para lógica de negocio. Trátala como formato interno de `Encryption`.

## Integridad

GCM es un modo de cifrado autenticado. El tag permite detectar modificaciones del ciphertext, IV o una clave incorrecta durante el descifrado.

Si OpenSSL no puede validar y descifrar el contenido, `decrypt()` lanza `RuntimeException` en lugar de devolver datos parciales.

## Gestión de la clave

La seguridad de los datos depende de la clave que suministre la aplicación.

El esqueleto de GFrame ya contempla `APP_KEY` en `.env`, pero `Encryption` **no la lee automáticamente**. Debes decidir explícitamente qué clave utiliza cada caso.

Para datos persistentes suele ser preferible una variable dedicada:

```dotenv
DATA_ENCRYPTION_KEY="una-clave-larga-generada-aleatoriamente"
```

Razones para separar una clave de cifrado persistente de otras claves de aplicación:

- su rotación puede tener un ciclo distinto;
- cambiar una clave utilizada para datos almacenados exige volver a cifrar esos datos;
- una clave dedicada reduce acoplamiento entre funciones de seguridad distintas.

No guardes la clave en el repositorio ni junto al ciphertext en la misma tabla o archivo.

## Rotación de claves

El payload actual incluye una versión de formato (`v`), pero **no incluye un identificador de clave**.

Por tanto, si necesitas rotación de claves para datos persistentes, la aplicación debe definir su estrategia. Por ejemplo:

```text
registro
  encrypted_value
  encryption_key_version
```

Al leer:

1. seleccionas la clave correspondiente a `encryption_key_version`;
2. descifras;
3. opcionalmente vuelves a cifrar con la clave actual;
4. actualizas la versión de clave.

No reemplaces simplemente la variable de entorno si todavía existen datos cifrados con la clave anterior: dejarían de poder descifrarse.

La versión interna `v` del payload identifica el **formato del cifrado**, no qué secreto concreto se utilizó.

## Errores

El constructor lanza `InvalidArgumentException` si la clave está vacía.

`encrypt()` puede lanzar `RuntimeException` si:

- el cifrado no está disponible;
- OpenSSL no puede cifrar;
- no se genera tag;
- no se puede serializar el payload.

`decrypt()` lanza `InvalidArgumentException` cuando el payload no tiene el formato/versionado esperado o sus componentes Base64 son inválidos. Lanza `RuntimeException` cuando OpenSSL no puede descifrar o autenticar el contenido.

Ejemplo:

```php
try {
    $secret = $encryption->decrypt($storedValue);
} catch (InvalidArgumentException | RuntimeException $exception) {
    error_log('[Encrypted data] ' . $exception->getMessage());
    return ['status' => 'error', 'code' => 'encrypted_data_unavailable'];
}
```

No devuelvas detalles criptográficos internos al navegador.

## Cuándo utilizarlo

Casos razonables:

- credenciales de integraciones que deban persistirse cifradas;
- tokens externos recuperables que la aplicación necesite usar posteriormente;
- ciertos campos confidenciales cuyo valor original deba recuperarse.

No lo utilices para contraseñas de usuarios. Las contraseñas necesitan **hashing no reversible**, no cifrado reversible; utiliza el sistema de autenticación y política de contraseñas de GFrame.

Tampoco es un sustituto del control de acceso: un usuario sin permiso no debe recibir el ciphertext ni el plaintext solo porque esté cifrado en la base de datos.

## Cifrar antes de persistir

Ejemplo simplificado dentro de un service:

```php
use GFrame\Security\Encryption;

final class IntegrationCredentialService
{
    private Encryption $encryption;

    public function __construct()
    {
        $key = (string)($_ENV['DATA_ENCRYPTION_KEY'] ?? getenv('DATA_ENCRYPTION_KEY') ?: '');
        $this->encryption = new Encryption($key);
    }

    public function protectToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('El token no puede estar vacío.');
        }

        return $this->encryption->encrypt($token);
    }

    public function revealToken(string $encrypted): string
    {
        return $this->encryption->decrypt($encrypted);
    }
}
```

La vista no debe recibir la clave. Mantén el descifrado dentro del servicio que realmente necesita el secreto.

## Qué no cubre esta clase

`Encryption` no proporciona actualmente:

- almacenamiento o bóveda de claves;
- rotación automática;
- identificadores de clave dentro del payload;
- cifrado de archivos por streaming;
- associated authenticated data (AAD) configurable;
- hashing de contraseñas;
- firma digital.

No atribuyas esas capacidades a la clase por el hecho de utilizar AES-GCM.

## Requisitos

El entorno PHP necesita OpenSSL con soporte para `aes-256-gcm` y una fuente segura disponible para `random_bytes()`.

Para contenido HTML permitido consulta [Sanitización de HTML](html-sanitizer.md). Para autenticación y contraseñas consulta [Autenticación](autenticacion.md) y [Utilidades de contraseñas](password-utils.md).
