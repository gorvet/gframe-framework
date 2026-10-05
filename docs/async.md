# Tareas en segundo plano con Async

Async ejecuta una función PHP en otro proceso, fuera de la petición que la crea. Forma parte del núcleo y puede utilizarse para generar documentos, procesar archivos o llamar a servicios externos. [Mail](mail.md) lo utiliza para enviar correo.

## Crear una tarea

Desde una aplicación GFrame ya iniciada:

```php
<?php
$recordID = 25; // ID obtenido y autorizado en el servidor.

try {
    (new Async())->create(static function () use ($recordID): void {
        // Instancia aquí el servicio del proyecto y procesa el registro.
        error_log('[Mi proyecto] Tarea iniciada para el registro ' . $recordID);
    });
    return ['status' => 'success', 'code' => 'task_started'];
} catch (Exception $exception) {
    error_log('[Mi proyecto Async] ' . $exception->getMessage());
    return ['status' => 'error', 'code' => 'task_start_failed'];
}
```

`Async` es una clase global, sin namespace. `create(Closure $closure): void` serializa la función y lanza el worker. No devuelve el resultado de la tarea ni un identificador de seguimiento. El éxito del ejemplo confirma el lanzamiento, no la finalización.

Valida datos, permisos y pertenencia al tenant antes de crear la tarea. El worker no repite el middleware de la ruta. Para operaciones sensibles o diferidas, el servicio ejecutado debe volver a comprobar el estado de los registros antes de modificarlos.

## Datos y servicios del worker

Captura mediante `use` únicamente los datos necesarios, preferiblemente IDs, cadenas y arrays pequeños. Crea dentro de la función los servicios y modelos del proyecto. Evita capturar conexiones PDO, recursos abiertos, archivos abiertos o el controlador completo mediante `$this`.

La función se serializa con Laravel Serializable Closure. El worker carga el bootstrap del proyecto y ejecuta la función en PHP CLI. El autoload y la configuración del proyecto vuelven a estar disponibles; el contexto de la petición original no se transmite automáticamente.

No dependas de `$_POST`, de las cabeceras del navegador ni de su sesión para identificar al actor. Captura expresamente los IDs ya verificados que necesite el servicio. Las variables capturadas son una copia del momento de creación: consulta de nuevo la base de datos si necesitas el estado actual.

Una tarea debe poder ejecutarse sin emitir una respuesta HTTP. No renderices una vista ni intentes mostrar `swalAlert` desde ella. Guarda el resultado en un registro del proyecto si el usuario debe consultarlo después y actualiza la interfaz mediante sus endpoints habituales.

## Requisitos del servidor

- La aplicación debe estar iniciada y definir `ABSPATH`.
- Debe existir un ejecutable PHP CLI con las extensiones que necesita la tarea.
- PHP debe permitir `exec()` y el sistema debe permitir lanzar procesos.
- El worker necesita acceso al proyecto, sus dependencias, su entorno y sus archivos.
- El directorio temporal debe permitir escritura para payloads grandes.

Async busca y comprueba PHP CLI. Puedes fijar el ejecutable en `.env`:

```dotenv
GFRAME_PHP_BINARY="/usr/bin/php"
```

En Windows, utiliza una ruta como `C:/xampp/php/php.exe`. No indiques PHP-FPM ni el módulo de Apache. Si defines una ruta inválida, el lanzamiento falla en lugar de buscar otro ejecutable.

En Windows se lanza un proceso oculto mediante PowerShell; en otros sistemas se utiliza un proceso de shell en segundo plano. El worker es `bin/async-worker.php` del paquete. No necesita una ruta pública ni una llamada cron para cada tarea.

## Fallos y seguimiento

`create()` puede lanzar una excepción si falta el bootstrap, no puede serializar la función, no encuentra PHP CLI o falla el lanzamiento. Captúrala en la operación que solicita la tarea y devuelve el contrato habitual de error.

Un fallo posterior del worker no llega a ese `catch`: la petición puede haber terminado. Gestiona los errores dentro de la tarea y registra un estado de éxito o fallo cuando el proceso de negocio lo requiera. En Unix la salida del proceso se descarta; no uses `echo` como mecanismo de seguimiento. Configura el log PHP del ejecutable CLI.

Async no incluye cola persistente, reintentos, prioridades, supervisión, límite de concurrencia ni garantía de entrega. Cada llamada lanza un proceso; evita usarlo para crear miles de procesos en una petición. Para lotes o tareas que deban sobrevivir a reinicios, guarda trabajos pendientes en el proyecto y procésalos mediante una estrategia de cola y tareas programadas.

Diseña las operaciones de forma idempotente si pueden solicitarse varias veces. No supongas que dos tareas terminan en el mismo orden en que se crearon ni que comparten una transacción con el controlador. Confirma primero la transacción de la petición y después lanza la tarea que depende de esos datos.

## Seguridad y actualización

`run(string $serialized): void` y `ClosureWrapper` forman el mecanismo de transporte interno. No aceptes funciones serializadas, payloads ni rutas de archivos enviados por usuarios: deserializar una tarea ejecuta código PHP del proyecto.

Los payloads grandes utilizan archivos temporales que el worker elimina al leerlos. No captures secretos innecesarios y limita el acceso al almacenamiento temporal y a los procesos del sistema. Un lanzamiento aparentemente aceptado cuyo worker no llega a iniciar puede dejar un archivo pendiente.

Antes de actualizar las dependencias de serialización o el código que usan tareas activas, deja que terminen. Async no mantiene copias versionadas del código de aplicación para cada ejecución.
