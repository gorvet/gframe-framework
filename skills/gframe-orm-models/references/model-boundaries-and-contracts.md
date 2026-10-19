# Model Boundaries and Contracts

## Versioned content writes

ScheduledContent uses protected saveRevisionConditionally()/saveHistoryConditionally() to compare code-owned lifecycle preconditions while changing status/date, without weakening the public save scope rule. Public signatures are unchanged. Consumers call schedulePublication()/cancelPublication()/publishScheduled(); keep these lifecycle fields out of arbitrary form writes. History and publication remain one transaction, and the project cron handler reauthorizes each record. See docs/content-publication.md.

Opt-in `GFrame\Content\ContentTrash` works on VersionedContentModel/RevisionedContentModel descendants. Projects own nullable deleted_at schema and include it in fillable. activeContent(scope)/trashedContent(scope) are explicit query helpers; generic ORM does not hide trashed rows. All normal saves must include deleted_at => null in the authorized scope and exclude lifecycle fields from editable fields. trashContent/restoreContent compare state, exact revision and scope and rotate revision; no text snapshots are created. deleteTrashedContent requires confirmation, owns its transaction, locks by CAS and invokes protected deleteTrashedRelations(row) for same-connection SQL cleanup; exceptions roll everything back. No files/network effects inside cleanup, no nested transaction owners. Projects decide history/autosave retention on physical deletion. See docs/content-trash.md in the effective package; auth/can/CSRF and every public reader remain project responsibilities.

For opted-in history use `RevisionedContentModel`, declare historyFields() and call saveWithHistory with server actor/tenant/scope. It owns the transaction on its model connection and rejects active transactions before writing. History/retention failure rolls back the content too. Defaults: content.revisions.limit=10, positive integer; clearRevisions requires explicit confirmation and the expected current revision, preserves current content/revision and only deletes that resource's history. Restore creates a fresh version/history entry through CAS. The project owns auth/can/CSRF and per-record authorization; no universal article route is supplied. See docs/content-revisions.md in the effective package.

Project models can explicitly extend `GFrame\Content\VersionedContentModel` (still ORM). The project owns its table and revision-column migration. `reset()->saveRevision($id, $expectedRevision, $normalizedFields, $serverScope)` performs the conditional write; success returns `content_saved`/`data.revision`, zero affected rows return `content_conflict`. Include revision in fillable; never pass full request arrays. The explicit equality scope preserves tenant/record restrictions; pending wheres are rejected to prevent OR broadening. Named connections are retained. The helper neither owns transactions nor authorizes actors. All writers must compare/rotate revisions. `whereExact()` compares revision bytes independently of collation; native dialects own the SQL. Custom dialects may add `exactComparisonExpression($quotedColumn)`; existing dialect implementations remain loadable, but this optional operation fails closed without that capability. See `docs/content-editing.md` in the effective package.

## What the Controller Owns

Controllers should own:

- reading `$_POST`, `$_REQUEST`, and route params
- required-field checks
- sanitization and type casting
- route-level and middleware-level access assumptions
- composing final payloads for the frontend

## What the Model Owns

Models should own:

- ORM queries and writes
- existence checks close to persistence
- data-level invariants and normalization tied to storage
- structured return arrays

## What the Model Should Not Do

- read raw request payloads
- inspect frontend form state
- duplicate generic required-field checks already done in controller
- duplicate `auth`, `admin`, or `can:*` logic

## Response Shape

These examples describe operation envelopes. `message`, `data` and `meta` are optional when the caller does not need them; raw ORM reads and writes keep their documented shapes.

Typical success:

```php
return [
  'status' => 'success',
  'message' => 'Saved',
  'data' => $data,
  'meta' => []
];
```

Typical error:

```php
return [
  'status' => 'error',
  'message' => 'Project not found',
  'code' => 'not_found'
];
```

Keep the module's existing code vocabulary if the frontend already depends on it.

## Empty States

Do not invent a new empty-state rule if the module already has one.

Current framework modules use both patterns depending on caller needs:

- success with empty `data`
- error with a meaningful `code`

Mirror the existing module contract first.

## Catch Blocks

Expected business rejections return the existing operation response without needing an exception. Catch `Exception` at the owning layer when it can log and recover or return a safe error. Avoid exposing internal database messages:

```php
catch (\Exception $exception) {
  error_log('[ModuleModel] ' . $exception->getMessage());
  return [
    'status' => 'error',
    'message' => 'No se pudo completar la operación.',
    'code' => 'operation_failed'
  ];
}
```

For a transaction owned by this method, `catch (\Throwable $exception)` may protect rollback for both exceptions and programming errors, then rethrow the original failure. Guard rollback with the connection's active-transaction state and use that same connection. An explicit outer error boundary may handle `Throwable`; ordinary model methods should not silently turn every programming error into `operation_failed`. Do not mechanically replace existing catch types.
