---
name: gframe-orm-models
description: Build and refactor GFrame ORM models using a single ORM API with connection-aware behavior, dialect-separated SQL differences, reset/queryTable patterns, transactions, and stable response contracts.
---

# GFrame ORM Models

Use this when the task is mainly about models, ORM queries, persistence, transactions, list metadata, or model response contracts.

## Read Order

1. [references/model-boundaries-and-contracts.md](references/model-boundaries-and-contracts.md)
2. [references/orm-query-patterns.md](references/orm-query-patterns.md)
3. [references/orm-connections-and-dialects.md](references/orm-connections-and-dialects.md)

## Workflow

1. Confirm the model base table, primary key, and existing return style.
2. Confirm whether the model should use default connection or a named one (`protected $connection`).
3. Keep request validation and sanitization in controller, not in the model.
4. Write base-table queries on the model itself and cross-table queries with `queryTable()`.
5. Use transactions for multi-step writes on the same connection.
6. Return structured arrays with stable keys expected by controller and JS.
7. Only use raw SQL helpers when ORM methods are not enough.

## Hard Rules

- Do not modify GFrame source or vendored framework files to compensate for a project schema mismatch.
- If the database shape does not fit the core conventions, say so before coding and prefer adapting the project schema or app-layer queries.
- Models accept normalized arguments, not raw request arrays or superglobals.
- Generic required-field validation belongs in the controller.
- Route-level permission checks belong in middleware, not in the model.
- Keep DB and persistence logic in models.
- Move filesystem, external API, or reusable orchestration to services when it no longer belongs cleanly inside the model.
- Call `reset()` before a new query intent on a reused model instance.
- Use `queryTable()` only when leaving the model base table.
- Keep SQL-engine-specific behavior in dialect classes, not inside model methods.
- Prefer ORM API and model helpers over driver checks or manual DSN branching.
- Use `protected $connection` only when the model must not use default connection.
- Preserve current module response codes and shapes instead of forcing a new style into an established caller.
- Log internal exceptions and return stable public errors; do not expose SQL or connection messages to users.

## Return Contract

Common stable keys:

- `status`
- `message`
- `code`
- `data`
- `meta`

Some methods only need a subset. Keep the contract explicit and predictable.

## Use With Other Skills

- Use `gframe-backend` when the task also changes controller orchestration or route behavior.
- Use `gframe-core-architecture` when the task changes framework-level ORM, connection manager, or dialect conventions.
