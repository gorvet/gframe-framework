# Backend, Service, and Model Boundary

Read this only as the backend-oriented summary. For deep ORM work, use `gframe-orm-models`.

## Controller Boundary

Controllers own the HTTP boundary:

- read route params and request payload
- validate required fields
- sanitize and cast values
- decide which model or service call to make
- compose the final payload for Router and JS

## Service Boundary

Use services when logic is reusable and does not belong cleanly in controller or model:

- filesystem processing
- external API integration
- multi-step orchestration
- subsystem-specific helpers

Shared services in GFrame must depend on contracts rather than an application's table names. Applications implement repository adapters and keep their own messages, views, roles, and domain policies.

## Model Boundary

Models own persistence:

- ORM queries and writes
- transactions
- data-level invariants near storage
- structured return arrays

Models should not:

- read superglobals directly
- enforce middleware-level access rules
- duplicate generic required-field checks already done in controller

## Response Contract Reminder

The stable backend keys are:

- `status`
- `message`
- `code`
- `data`
- `meta`
- `html`

Use `html` only when the frontend replaces a backend-rendered fragment.
