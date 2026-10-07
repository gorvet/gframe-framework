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

For new symbols or a naming review, use the matching-version [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Code variables such as `$userID` and columns such as `user_id` follow different conventions.

## Workflow

1. Confirm the model base table, primary key, and existing return style.
2. Confirm whether the model should use default connection or a named one (`protected $connection`).
3. Keep request validation and sanitization in controller, not in the model.
4. Write base-table queries on the model itself and cross-table queries with `queryTable()`.
5. Use transactions for multi-step writes on the same connection.
6. Preserve the established raw ORM result or operation envelope expected by the caller; compose list metadata explicitly.
7. Only use raw SQL helpers when ORM methods are not enough.

## Hard Rules

- For application work, do not modify an installed GFrame package to compensate for a project schema mismatch. An explicitly requested framework ORM change belongs in this repository and requires its own compatibility checks.
- If the database shape does not fit the core conventions, say so before coding and prefer adapting the project schema or app-layer queries.
- Models accept normalized arguments, not raw request arrays or superglobals.
- Generic required-field validation belongs in the controller.
- Route-level permission checks belong in middleware, not in the model.
- Keep DB and persistence logic in models.
- Move filesystem, external API, or reusable orchestration to services when it no longer belongs cleanly inside the model.
- Call `reset()` before a new query intent on a reused model instance.
- `newQuery()` clones current state; it is not a reset. Keep identical filters and the same connection when separating count and list queries.
- Use `queryTable()` only when leaving the model base table.
- Empty `whereIn()`/`orWhereIn()` lists add a false condition with the requested AND/OR. Older package versions omitted it: verify the effective version. Keep authorization correctly grouped and omit optional filters explicitly when an empty list means all records.
- Choose dynamic sort columns/expressions from a code-owned allowlist. SQL value bindings do not protect identifier or expression arguments.
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

These keys describe operation envelopes, not every ORM return. Envelopes include `status`; add `code` when the caller needs a stable reason and `message` when public feedback is needed. Queries may return raw records, and writes retain their documented result. Preserve the caller's established shape.

## Use With Other Skills

- Use `gframe-backend` when the task also changes controller orchestration or route behavior.
- Use `gframe-core-architecture` when the task changes framework-level ORM, connection manager, or dialect conventions.

## Verification

For query recipes, inspect SQL/bindings and exercise representative rows on the applicable configured engine. Check reused state, empty selections, pagination and named connections when affected. SQLite evidence does not establish MySQL behavior; use the existing engine integration checks for dialect-specific changes. For skill-only edits, validate the instructions, references and executable examples without changing ORM semantics or introducing unrelated runtime tests.
