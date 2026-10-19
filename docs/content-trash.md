# Papelera y restauración de contenido

El trait `GFrame\Content\ContentTrash` añade un ciclo optativo de papelera a los modelos que heredan de `VersionedContentModel` o `RevisionedContentModel`. No requiere instalar un módulo ni crea tablas de contenido. La aplicación conserva sus permisos, rutas y relaciones.

## Preparar el modelo

En una migración propia añada `deleted_at DATETIME NULL DEFAULT NULL` en MySQL o `deleted_at TEXT DEFAULT NULL` en SQLite. Conserve la columna `revision` descrita en [guardado por revisión](content-editing.md). Inicialmente los registros tienen `deleted_at = null`. Para tablas grandes, evalúe un índice compuesto con su tenant y filtros de listado.

En `app/models/articles/ArticleModel.php`, adapte namespace y tabla:

```php
namespace App\Models\Articles;

class ArticleModel extends \GFrame\Content\VersionedContentModel
{
    use \GFrame\Content\ContentTrash;

    protected $table = 'articles';
    protected $fillable = ['title', 'content', 'revision', 'deleted_at'];
}
```

También puede heredar de `RevisionedContentModel` y conservar `historyFields()` y `saveWithHistory()`. Excluya `deleted_at` de los campos de historial: la restauración de una revisión de texto no debe cambiar el estado de papelera. Las operaciones de papelera rotan la revisión del registro sin crear snapshots de texto.

Para otra columna de papelera, sobrescriba `protected function trashColumn(): string` e inclúyala en `fillable`. Debe ser distinta de la clave primaria y de la revisión. El trait conserva la conexión seleccionada mediante `onConnection()`.

## Lectores y escritores

```php
$scope = ['tenant_id' => $authorizedTenantID]; // Contexto del servidor.
$active = $model->reset()->activeContent($scope)->orderBy('id', 'DESC')->get();
$trashed = $model->reset()->trashedContent($scope)->orderBy('id', 'DESC')->get();
$article = $model->reset()->activeContent($scope)->where('id', '=', $articleID)->limit(1)->get()[0] ?? null;
```

En aplicaciones globales use `[]` como ámbito. Las consultas separan activos y papelera mediante null/no-null; puede añadir filtros y paginación mediante ORM. Los predicados previos se rechazan: use `reset()` y el ámbito explícito. Las columnas y valores del ámbito deben proceder del servidor; no añada un OR que amplíe la autorización.

Integre este filtro en todos los lectores públicos, búsquedas, feeds, sitemap, preview y tareas programadas. ORM genérico conserva su comportamiento: `get()`, `find()` y SQL directo no ocultan la papelera automáticamente.

En todos los guardados y publicaciones, añada `'deleted_at' => null` al ámbito de `saveRevision()` o `saveWithHistory()`. Esto rechaza escrituras en registros en papelera incluso si el cliente conoce la revisión actual. Mantenga una lista explícita de campos editables que excluya `deleted_at`; no pase POST completo. Retirar una entrada de un índice externo o invalidar su caché pertenece al flujo de la aplicación después del commit.

## Operaciones

En sus rutas POST administrativas conserve `auth`, `can:*`, CSRF y autorización del registro/tenant. Separe el permiso de borrado definitivo del de restauración según la política del proyecto. El controlador valida ID, revisión y confirmación; devuelve el array mediante el contrato AJAX habitual de Router.

```php
$result = $model->reset()->trashContent($articleID, $expectedRevision, $scope);
$result = $model->reset()->restoreContent($articleID, $expectedRevision, $scope);
$result = $model->reset()->deleteTrashedContent($articleID, $expectedRevision, $confirmed, $scope);
```

Son alternativas de acción, no una secuencia para ejecutar con la misma revisión. Tras cada éxito de papelera/restauración use `data.revision` como revisión nueva. Capture la revisión enviada con el formulario; no la sustituya por la leída al enviar.

| Operación | Estado requerido | Resultado |
|---|---|---|
| `trashContent` | Activo | Fecha UTC de eliminación, `content_trashed`, revisión nueva |
| `restoreContent` | En papelera | Fecha null, `content_restored`, revisión nueva |
| `deleteTrashedContent` | En papelera y confirmado | Borrado físico, `content_deleted`, `data.deleted = 1` |

Revisión antigua, estado incorrecto o scope ajeno devuelven `status: error`, `code: content_conflict`, sin datos del registro. La revisión compara bytes, también en MySQL con collation insensible a mayúsculas. Repetir la petición no vuelve a modificar el contenido. Sin confirmación de borrado definitivo se devuelve `confirmation_required`.

Papelera y restauración conservan texto, estado editorial, relaciones e historial. Restaurar también conserva el estado editorial anterior: si su política exige volver a borrador, impleméntelo expresamente en el modelo del proyecto y su misma transacción. No se programa una purga automática.

## Relaciones y borrado definitivo

El borrado definitivo es dueño de su transacción y rechaza transacciones ya activas. Obtiene el registro por actualización condicional antes de llamar a `deleteTrashedRelations(array $row)`, y solo confirma después de borrar el contenido. Sobrescriba ese método para sus relaciones SQL en la misma conexión, o use claves foráneas apropiadas. Una excepción revierte relaciones, registro y revisión.

```php
protected function deleteTrashedRelations(array $row): void
{
    self::queryTable('article_tags')
        ->onConnection($this->resolveCurrentConnectionName())
        ->where('article_id', '=', $row['id'])
        ->deleteWhere();
}
```

Adapte esta receta a las claves y ámbitos reales. El método predeterminado no borra relaciones: el proyecto decide qué eliminar y qué conservar, incluidas revisiones y autoguardados privados. No llame dentro de este método a operaciones que sean dueñas de otra transacción. No elimine archivos de la biblioteca multimedia solo por borrar el contenido que los referencia.

Archivos físicos, red y otras conexiones no participan en este rollback; gestione esos efectos después del commit mediante el mecanismo de entrega del proyecto. La interfaz de papelera, sus confirmaciones y la política de retención pertenecen al módulo consumidor.
