# Revisiones del contenido

`content-revisions` conserva hasta **10 revisiones por contenido** de forma predeterminada. El límite puede subir o bajar. Permite comparar valores, restaurar una versión y borrar todas las revisiones de un contenido sin modificar su versión actual.

## Instalar y configurar

Añada el módulo optativo mediante [el actualizador](modulos-opcionales.md#añadir-módulos-a-un-proyecto-instalado), con `--dry-run` primero. Requiere base de datos MySQL o SQLite y los perfiles managed, intranet o saas. La tabla `content_revisions` se instala en la misma conexión donde la aplicación guarda el contenido; si usa conexiones nombradas, instale el esquema también en esa conexión.

En `config/app.php`, añada esta clave a su configuración existente:

```php
'content' => ['revisions' => ['limit' => 10]],
```

Se admite cualquier entero positivo. Cero no significa ilimitado ni desactivado: es inválido. El límite se aplica por contenido al siguiente guardado con historial; bajarlo no elimina de inmediato el historial de recursos que no se vuelvan a guardar. No existe caducidad por días.

## Modelo de la aplicación

Prepare su columna `revision` como indica [guardado por revisión](content-editing.md). Extienda el modelo optativo:

```php
class ArticleModel extends \GFrame\Content\RevisionedContentModel
{
    protected $table = 'articles';
    protected $fillable = ['title', 'content', 'revision'];
    protected function historyFields(): array { return ['title', 'content']; }
}
```

`historyFields()` decide qué valores se conservan y restauran. Excluya contraseñas, tokens, datos sensibles y relaciones. Los nombres pertenecen al código del modelo, nunca a POST. Cambiar esta lista exige adaptar explícitamente el historial anterior; una restauración con campos incompatibles se rechaza.

Puede sobrescribir `historyLimit(): int` para una política propia del modelo. Mantenga un entero positivo. El módulo reutiliza ORM y la conexión del modelo, incluida `onConnection()`; no añade repositorios ni tablas de artículos.

## Guardar con historial

El controlador autentica, valida y sanea los campos y autoriza el registro concreto. El autor procede de `$_SESSION['auth']['id']`; el tenant y el ámbito proceden del contexto autorizado del servidor.

```php
$result = $model->reset()->saveWithHistory(
    $articleID, $expectedRevision,
    ['title' => $title, 'content' => $cleanHtml],
    $actorID, $tenantID, ['tenant_id' => $tenantID]
);
```

En aplicaciones globales pase `null` como tenant y omita el ámbito si no corresponde. En aplicaciones con tenant, use coherentemente el mismo tenant en lectura, guardado, restauración y vaciado, además de filtrar la tabla de negocio con el ámbito autorizado. La clave del historial combina tabla, ID y tenant; los filtros adicionales de autorización no crean historiales distintos.

La operación es dueña de una transacción en esa misma conexión. No la invoque dentro de una transacción activa: se rechaza antes de escribir. El contenido, snapshot y recorte del historial se confirman juntos o se revierten juntos. Para orquestaciones adicionales, adapte el modelo explícitamente; no abra una segunda conexión para el historial.

Cada guardado exitoso conserva el estado confirmado, autor y fecha UTC. En la primera adopción también se captura el estado previo con autor `null`: se desconoce su autor histórico y la fecha corresponde a su captura, no a su publicación original. Ambas entradas cuentan dentro del límite. Con límite 1 solo queda el estado más reciente. Después de vaciar, un nuevo guardado vuelve a capturar su estado previo y el nuevo.

Use `saveWithHistory()` en todos los escritores; `saveRevision()` se rechaza en este modelo para evitar omitir accidentalmente el autor/historial. Las escrituras ORM directas siguen siendo responsabilidad de la aplicación. Un conflicto conserva el contrato `content_conflict` y no añade una entrada. El éxito devuelve `content_saved` y `data.revision`, compatible con la confirmación de snapshots del editor. El autoguardado privado no genera revisiones históricas.

## Listar y comparar

```php
$list = $model->reset()->revisions($articleID, $tenantID, $authorizedScope);
$comparison = $model->reset()->compareRevisions(
    $articleID, $leftRevisionID, $rightRevisionID, $tenantID, $authorizedScope
);
```

Autorice cada operación antes de llamar al modelo. La lista devuelve metadatos (ID, token de revisión, autor y fecha), sin snapshots completos. La comparación devuelve `before`, `after` y `changed` por campo, como datos; no los inserte mediante `innerHTML`.

Para mostrarla use el original PHP, escapado y personalizable por el mecanismo nativo:

```php
$revisionComparison = $comparison['data'];
include \GFrame\Modules\ModuleRuntime::file(
    'views', 'content-revisions/comparison.php', 'content-revisions'
);
```

Una entrada ajena o ya eliminada produce `content_revision_not_found`. Las cuentas de autores pueden dejar de existir: el historial no depende de un JOIN obligatorio a usuarios.

## Restaurar

```php
$result = $model->reset()->restoreRevision(
    $articleID, $revisionID, $expectedCurrentRevision,
    $actorID, $tenantID, $authorizedScope
);
```

Use una acción POST propia con `auth`, permiso de restauración de su aplicación y CSRF. Compruebe primero que el actor puede restaurar ese registro y solicite confirmación con `swalAlert` en la interfaz. La revisión esperada es la que vio el usuario, no una lectura nueva que elimine el conflicto.

Restaurar copia los campos permitidos mediante el mismo guardado condicional, genera un token nuevo y añade una entrada con el autor de la restauración. No reutiliza el token antiguo ni publica automáticamente. En modelos con `ScheduledContent`, conserva el estado y la fecha de publicación actuales aunque figuren en el snapshot: no vuelve a publicar un borrador ni reactiva una programación cancelada. Un contenido ya publicado sigue publicado; restaurar su texto modifica ese contenido visible y requiere la autorización correspondiente. Ante conflicto conserve el formulario y no confirme los snapshots. El HTML histórico se guarda como contenido, no se ejecuta al compararlo; su renderizado público debe mantener el saneamiento y autorización propios del proyecto.

## Borrar todas las revisiones

```php
$result = $model->reset()->clearRevisions(
    $articleID, $expectedCurrentRevision, $confirmed,
    $tenantID, $authorizedScope
);
```

La ruta POST debe exigir un permiso específico de borrado de historial, autorización del registro y CSRF. La interfaz pide confirmación mediante `swalAlert`; el controlador valida esa decisión y pasa un booleano estricto. Sin confirmación se devuelve `confirmation_required`.

El vaciado elimina todas las entradas de ese contenido/tenant, incluidas las que la lista no mostraba por un límite menor. No cambia su texto, revisión actual ni historiales ajenos. Compite con los guardados por el bloqueo del mismo registro: una revisión antigua devuelve `content_conflict`. El borrado es irreversible y libera datos lógicos; que el archivo físico de base de datos reduzca su tamaño depende del motor y su mantenimiento. No ejecuta VACUUM ni optimizaciones de tablas.

No se distribuye un endpoint universal de artículos: el proyecto mantiene sus rutas, permisos, feedback y formularios. Capture errores de almacenamiento en su frontera habitual con mensaje público seguro y logs internos; nunca devuelva SQL ni credenciales.
