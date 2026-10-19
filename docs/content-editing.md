# Guardado por revisión y aviso de otras sesiones

GFrame permite rechazar guardados obsoletos y avisar de otras sesiones activas. La aplicación mantiene sus tablas, rutas, permisos y publicación. El autoguardado privado es una copia de recuperación y conserva su control de versión independiente.

## Preparar el modelo

Añada mediante una migración de su aplicación una columna `revision` de texto, no nula, de hasta 128 bytes, a cada tabla protegida. Inicialice las filas existentes con una revisión no vacía, por ejemplo `initial`. `VARCHAR(128)` en MySQL o `TEXT` en SQLite admiten las nuevas revisiones de 32 caracteres. No utilice la fecha de modificación como única revisión. La migración pertenece a la aplicación; instalar presencia no modifica tablas de artículos.

Para registros nuevos genere siempre una revisión nueva con `bin2hex(random_bytes(16))`; no reutilice la revisión inicial de una migración ni la de un registro eliminado. Sin el módulo de presencia, incluya manualmente un campo oculto `content_revision` con la revisión del registro, escapada para HTML. Con presencia, el componente ya emite ese campo y no debe duplicarlo.

En `app/models/articles/ArticleModel.php`, adapte tabla, campos y namespace:

```php
namespace App\Models\Articles;

class ArticleModel extends \GFrame\Content\VersionedContentModel
{
    protected $table = 'articles';
    protected $fillable = ['title', 'content', 'revision'];
}
```

Conserva la API ORM y sus conexiones. Para otra columna de revisión, sobrescriba `protected function revisionColumn(): string` e inclúyala en `fillable`. El modelo base no requiere instalar el módulo de presencia.

## Guardar y publicar

El controlador valida y sanea los campos y comprueba que el usuario puede editar ese registro y su tenant. Mantenga `auth`, el permiso `can:*` y CSRF: automático en AJAX, explícito en rutas web de escritura. Use `HtmlSanitizer::sanitize()` para HTML enriquecido, según [saneamiento](html-sanitizer.md).

Capture la revisión que acompañaba al contenido al abrir el formulario. No la sustituya al enviar por la revisión actual leída del servidor: eso anularía la detección de conflictos.

Después de autorizar el registro, pase datos normalizados y un ámbito decidido por el servidor:

```php
$result = $model->reset()->saveRevision(
    $articleID,
    $expectedRevision,
    ['title' => $title, 'content' => $cleanHtml],
    ['tenant_id' => $authorizedTenantID]
);
```

En proyectos globales sin columna tenant, omita el cuarto argumento. Puede añadir filtros de igualdad como `is_deleted => 0`. Nunca derive columnas ni contexto de autorización de POST. El ámbito complementa la autorización del controlador.

La misma sentencia `UPDATE` compara PK, revisión exacta y ámbito y cambia contenido/revisión. Solo una escritura gana por revisión; esta rota incluso si el texto coincide. Se usa `whereExact()` del ORM para que la revisión distinga mayúsculas y espacios finales independientemente de la collation MySQL. El resto de las comparaciones conserva su contrato.

Los predicados anteriores deben limpiarse con `reset()` y pasarse como ámbito explícito: el helper rechaza condiciones pendientes para impedir que un OR amplíe la escritura. Conserva la conexión de `onConnection()`.

- Éxito: `status: success`, `code: content_saved`, `data.revision` nueva.
- Revisión obsoleta, registro eliminado o fuera del ámbito: `status: error`, `code: content_conflict`, sin contenido ni revisión ajenos.
- Intentar escribir PK, revisión, campo de ámbito o campo fuera de `fillable` produce una excepción de uso inválido. No pase todo POST al modelo.

El helper decide por filas afectadas, sin tomar el conteo previo `matched` como prueba de victoria. No abre ni confirma transacciones. Si guarda relaciones, conserve la transacción en la misma conexión e informe de éxito después del commit; rollback restaura también la revisión.

Adopte este camino en todos los escritores, incluidos publicar, importar y tareas de fondo. Una escritura directa que omita comparar y rotar revisión no queda protegida. Ejecute los efectos externos después de confirmar el guardado.

## Instalar presencia

Instale `content-editing` mediante [el actualizador](modulos-opcionales.md#añadir-módulos-a-un-proyecto-instalado), primero con `--dry-run` y respetando personalizaciones. Admite los perfiles managed, intranet y saas y MySQL/SQLite. Las dependencias se resuelven automáticamente. `content_editing_presence` contiene claves opacas y caducidades, sin artículos, nombres ni texto.

En `config/routes/routes_ajax_admin_articles.php`, con un permiso registrado por la aplicación:

```php
RouteBuilder::post('ajax/admin/articles/presence', 'articles/ArticleController@presence')
    ->middleware(['auth', 'can:articles.edit'])->registerFinal();
```

En `presence()`, valide el ID y cargue/autorice registro y tenant igual que al guardar. Solo entonces delegue:

```php
return (new \GFrame\Content\ContentPresenceController())->handle(
    'article:' . (int)$authorizedArticle['id']
);
```

El recurso es una clave de servidor de hasta 512 bytes. Identidad y tenant proceden de la sesión normalizada. Las contradicciones con el tenant activo se rechazan; el navegador no crea un contexto de tenant al enviarlo en POST. Autorizar el recurso sigue siendo responsabilidad del proyecto.

La respuesta contiene `data.other_sessions` y `message`, sin identidades ni tokens de otras sesiones. Cada instancia usa un token aleatorio; otra pestaña del mismo usuario también cuenta como sesión adicional.

## Mostrar el aviso

Combine el meta del componente con el de su vista:

```php
$editingAssets = require \GFrame\Modules\ModuleRuntime::file(
    'views', 'content-editing/contentEditing.meta.php', 'content-editing'
);
return ['js' => [...$editingAssets['js'], 'public/js/app/admin/articles/article-form.js']];
```

Dentro del formulario:

```php
$contentEditing = [
    'url' => site_url . 'ajax/admin/articles/presence?article_id=' . (int)$article['id'],
    'revision' => $article['revision'],
];
include \GFrame\Modules\ModuleRuntime::file(
    'views', 'content-editing/contentEditing.php', 'content-editing'
);
```

Se emiten el campo oculto `content_revision` y un aviso accesible. El controlador recibe ese campo como revisión esperada. Se requiere el puente estándar `#tokens` del header. Hay una petición activa por instancia, timeout de 10 segundos y comprobación cada 30 segundos. La presencia no deshabilita Guardar; ocultar la pestaña pausa nuevos pings y volver comprueba de nuevo. Sin ping, deja de contarse a los 90 segundos. Los servidores deben tener relojes sincronizados.

Los registros caducados de un recurso se limpian cuando recibe otro ping; los de recursos que no se vuelvan a abrir pueden conservarse, pero no se cuentan como activos. No existe bloqueo exclusivo ni liberación obligatoria al cerrar el navegador.

Use `readonly => true` para omitir el componente en vistas de lectura. Para HTML dinámico, `ContentEditing.mount(root)` inicializa y devuelve la instancia; antes de reemplazar el formulario llame a `instance.destroy()`. Una petición ya recibida puede terminar tras destruirla, pero caduca normalmente.

## Confirmar un guardado real

En el `.done()` existente, solo después del éxito de negocio y commit:

```javascript
const presence = window.ContentEditing.mount(document.querySelector('[data-content-editing]'));
// snapshot se capturó antes del envío; response confirma persistencia real.
presence?.confirmSaved(response.data.revision);
window.AdminRichTextEditor?.markSaved(snapshot, response.data.revision);
```

`confirmSaved()` actualiza únicamente el campo de revisión. En `content_conflict`, conserve formulario y texto, muestre el mensaje con el feedback estándar y no confirme snapshots. La recuperación local y [el autoguardado privado](content-autosave.md) siguen disponibles; use la revisión del modelo como `base_version` del autoguardado. Sin TinyMCE solo necesita actualizar la revisión del formulario tras el éxito.
