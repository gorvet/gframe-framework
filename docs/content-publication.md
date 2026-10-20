# Publicación programada

`GFrame\Content\ScheduledContent` añade programación, cambio de fecha, cancelación y publicación por revisión a modelos que heredan de `VersionedContentModel` o `RevisionedContentModel`. Reutiliza el [cron existente](cron-runner.md); la aplicación conserva tabla, estados, permisos y rutas. No crea una tabla universal ni activa trabajos en instalaciones existentes.

## Modelo

Añada mediante una migración del proyecto `status VARCHAR(32) NOT NULL DEFAULT 'draft'` y `publish_at DATETIME NULL DEFAULT NULL` en MySQL; en SQLite use TEXT. Conserve la columna de revisión existente. Las fechas se almacenan en UTC. Añada un índice con estado, fecha y tenant según sus consultas.

En su modelo de `app/models`, adapte los campos existentes:

```php
class ArticleModel extends \GFrame\Content\RevisionedContentModel
{
    use \GFrame\Content\ScheduledContent;
    protected $table = 'articles';
    protected $fillable = ['content', 'revision', 'status', 'publish_at'];
    protected function historyFields(): array { return ['content', 'status', 'publish_at']; }
}
```

Sin historial puede heredar de `VersionedContentModel` y omitir `historyFields()`. Para otros nombres/valores, sobrescriba `publicationStatusColumn()`, `publicationDateColumn()`, `scheduledPublicationStatus()`, `publishedPublicationStatus()` y `cancelledPublicationStatus()`, todos protegidos con retorno string. Los valores predeterminados son `status`, `publish_at`, `scheduled`, `published` y `draft`.

## Programar, cambiar fecha o cancelar

Después de validar una ruta POST con auth, permiso de publicación, CSRF y autorización por registro/tenant:

```php
$scheduled = $model->reset()->schedulePublication(
    $articleID, $expectedRevision, $utcDate, $authorizedAuthorID,
    $authorizedTenantID, ['tenant_id' => $authorizedTenantID]
);
// Alternativa: cancelar una programación actual y volver a borrador.
$cancelled = $model->reset()->cancelPublication(
    $articleID, $expectedRevision, $authorizedAuthorID,
    $authorizedTenantID, ['tenant_id' => $authorizedTenantID]
);
```

En aplicaciones globales use tenant null y scope vacío. La fecha debe tener formato `Y-m-d H:i:s` y ser futura. Convierta la zona elegida por el usuario a UTC antes de llamar al modelo. Cambiar la fecha utiliza otra vez `schedulePublication()` con la revisión actual; la anterior queda inválida.

Cada éxito devuelve el contrato de guardado `content_saved` y `data.revision`; los conflictos devuelven `content_conflict`. No reemplace la revisión del formulario por la del servidor al enviar. El estado pasa a scheduled, también si antes estaba publicado: los lectores públicos deben exigir published. Cancelar pasa a draft y limpia la fecha. Otros estados requieren adaptar los métodos protegidos a su política.

Los guardados posteriores pueden conservar la fecha programada; el worker recoge la revisión vigente. Todos los escritores deben comparar y rotar revisión. Las operaciones conservan la conexión del modelo y, con historial, guardan snapshots y publicación en la misma transacción. Un fallo del historial revierte estado, fecha y revisión.

Al restaurar una revisión histórica, `ScheduledContent` conserva el estado y la fecha actuales; los valores históricos se mantienen para comparar, pero no se aplican como una orden de publicar o reprogramar. Para cambiar esos valores use los métodos de publicación con su autorización específica. Si el contenido ya está publicado, restaurar su texto actualiza ese contenido visible.

## Handler del proyecto

Cree una clase autoloadable, sin argumentos obligatorios de constructor, que herede de `GFrame\Content\ScheduledPublicationCronHandler` e implemente:

- `protected function publicationModel(): VersionedContentModel`: devuelve el modelo de su contenido, con `ScheduledContent` y la conexión correspondiente.
- `protected function publicationContext(array $task): array`: devuelve `scope` (filtros de igualdad), `author_id` entero positivo y `tenant_id` opcional. Resuelva actor y ámbito desde configuración o registros autorizados; no confíe en un tenant enviado por un visitante.
- `protected function mayPublish(array $row, array $context): bool`: vuelve a comprobar los permisos y restricciones actuales de ese registro. No existe sesión ni middleware en el worker. Si devuelve false, se omite el registro y se mantiene su programación para que el proyecto decida cómo corregirla o cancelarla.

Registre una tarea recurrente del handler del proyecto mediante su registrador de cron, una por ámbito autorizado:

```php
$result = (new \CronTaskService())->schedule(
    'articles.publish.' . $authorizedTenantID,
    ArticlePublicationCron::class,
    gmdate('Y-m-d H:i:s'),
    ['tenant_id' => $authorizedTenantID, 'batch' => 50],
    60
);
```

La clase del handler y su payload se seleccionan en el servidor. Una clave existente devuelve `cron_task_exists`: no registra otra tarea ni cambia sus opciones. Utilice el registrador y controles descritos en la guía de cron; no registre una tarea por cada edición. La fecha de publicación vive en el registro, por lo que cancelar/cambiar fecha no exige sincronizar otra fila de cron. Habilite `cron-runner` mediante el actualizador del proyecto si aún no está instalado.

El handler limita el lote a 1–100 registros, ordenados por fecha y PK. Devuelve `data.published` y `data.skipped`; permisos denegados y conflictos se omiten, errores de infraestructura se propagan al scheduler. Este conserva su política de fallos sin reintento genérico automático.

El modelo ofrece `dueContent($scope)` para listar pendientes vencidos y `publishScheduled($id, $expectedRevision, $authorID, $tenantID, $scope)` para un worker propio. La publicación vuelve a leer estado/fecha y compara fecha, estado y revisión en la escritura: cancelación, cambio de fecha y ejecución repetida no publican una versión antigua. Con `ContentTrash` se excluye automáticamente su columna de papelera; si emplea otro mecanismo de borrado lógico, incluya su filtro en el scope. Al restaurar de papelera una programación vencida puede volver a ser elegible: adapte esa política si desea exigir una fecha nueva.

Use cron real para puntualidad independiente del tráfico. Cron virtual solo se activa con actividad que llegue a PHP; sin tráfico, la publicación puede retrasarse. El intervalo y la duración de los lotes también influyen. Pruebe y configure el ejecutor en cada despliegue; instalar esta capacidad no configura el cron del sistema.

Lectores públicos, sitemap y buscador deben filtrar published y el ámbito correcto. Invalidaciones de caché, índices externos o notificaciones pertenecen al flujo del proyecto después del commit; no se garantiza entrega externa exactamente una vez.
