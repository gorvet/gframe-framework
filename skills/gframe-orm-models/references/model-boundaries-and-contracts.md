# Model Boundaries and Contracts

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
