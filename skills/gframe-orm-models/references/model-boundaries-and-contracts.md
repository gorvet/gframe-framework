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
- duplicate generic `emptyField` checks already done in controller
- duplicate `auth`, `admin`, or `can:*` logic

## Response Shape

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

Use structured catches and avoid exposing internal database messages:

```php
catch (Throwable $exception) {
  error_log('[ModuleModel] ' . $exception->getMessage());
  return [
    'status' => 'error',
    'message' => 'No se pudo completar la operación.',
    'code' => 'operation_failed'
  ];
}
```

Rollback first if the method is inside a transaction.
