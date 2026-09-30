# Campañas de notificaciones

`notification-campaigns` crea envíos masivos inmediatos o programados. Depende de `notifications` y `cron-runner`, pero no depende de ningún transporte concreto.

## Responsabilidades

El módulo administra:

- contenido y estado de la campaña;
- canales seleccionados;
- audiencia y variables por destinatario;
- programación mediante `cron-runner`;
- creación de trabajos en `notification_queue`;
- pausa, reanudación, cancelación y progreso.

No envía correos ni mensajes directamente. Cada addon procesa los trabajos de su canal.

## Flujo

```text
campaña -> cron-runner -> notification_queue -> transporte registrado
```

Una campaña inmediata procesa su primer lote al crearse. Si quedan destinatarios, la tarea cron continúa procesando lotes. Una campaña programada espera hasta `scheduled_at`.

## Audiencias

La interfaz administrativa admite una entrada por línea:

```text
email:usuario@example.com
inbox:42
```

Cuando la campaña usa un solo canal, el prefijo puede omitirse. Para integrar audiencias de una aplicación, implemente `CampaignAudienceProvider`:

```php
final class CustomerAudience implements CampaignAudienceProvider
{
    public function recipients(array $criteria): iterable
    {
        yield [
            'recipients' => ['email' => 'user@example.com', 'inbox' => '42'],
            'variables' => ['name' => 'Ana'],
        ];
    }
}
```

El servicio acepta el proveedor sin conocer las tablas de usuarios, clientes o suscriptores del proyecto.

## Transportes

Los canales actuales son `inbox` y `email`. WhatsApp, Telegram, push u otros addons podrán participar sin modificar este módulo. Cada destinatario conserva un valor propio por canal, de modo que el identificador del inbox no se confunde con una dirección de correo.

## Prevención de duplicados

La audiencia se normaliza por campaña, canal y destinatario. La base de datos mantiene además una restricción única sobre esa combinación. Cada destinatario pasa de `pending` a `processing` mediante una reserva condicionada y luego a `queued` o `failed`. Cada trabajo utiliza una clave de deduplicación persistente, por lo que reintentar un lote no crea el mismo envío dos veces.

## Estados

- `scheduled`: espera su fecha o el siguiente lote.
- `running`: está generando trabajos.
- `paused`: no debe continuar hasta reanudarse.
- `completed`: todos los destinatarios fueron procesados.
- `failed`: ningún destinatario del lote pudo encolarse.
- `cancelled`: no generará nuevos trabajos.

Cancelar una campaña no elimina trabajos que ya estén en `notification_queue`.

## Permisos

- `notifications.campaigns.view`: consultar campañas.
- `notifications.campaigns.manage`: crear, procesar, pausar, reanudar y cancelar.

## Contratos

Las respuestas usan `status`, `code`, `message`, `data`, `meta` y `html`. Los errores internos se registran; no se devuelven excepciones ni detalles de base de datos a la interfaz.
