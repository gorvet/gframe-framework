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

Shared services coordinate reusable rules and call ORM models for persistence. Do not add repository layers when a standard GFrame model already owns the table. Use a small contract only for a genuinely interchangeable external capability, such as a notification transport or project-specific profile store.

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

Expected persistence and business failures return structured arrays. Models may catch `Exception` and return `status`, `code`, and `message`; controllers propagate or adapt that response. Do not replace this flow with `Throwable`. Presentation remains outside the model and may become an error view, `swalAlert`, or `alertToast` according to the route and frontend contract.

## Response Contract Reminder

The stable backend keys are:

- `status`
- `message`
- `code`
- `data`
- `meta`
- `html`

Use `html` only when the frontend replaces a backend-rendered fragment.
