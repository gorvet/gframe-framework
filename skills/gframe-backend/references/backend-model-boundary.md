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

Expected business failures use the operation's established structured response. Models may catch `Exception` when they can log and handle the failure; controllers propagate or adapt that response. Use `Throwable` for transaction cleanup followed by rethrow, or at an explicit outer error boundary, rather than turning programming errors into routine business failures. Presentation remains outside the model and may become an error view, `swalAlert`, or `alertToast` according to the route and frontend contract.

## Verified Return Boundaries

| Producer → boundary → consumer | Established result |
| --- | --- |
| `SelfAccountService::changePassword` → `SelfAccountController::withMessage` → self-account JS | Service success is `status: success, code: password_updated` without a message; the controller adds its known public message only when absent. JS checks status and resets the form on success. Credentials retain their exact bytes. |
| `UserAdministrationService::paginate/assignRole` → user-admin controller → user-admin JS | A denied service call returns `status: unauthorized, code: forbidden`; the controller may supply the message. JS treats every status other than success as failure. Keep actor identity from the session and target IDs from the authorized request. |
| `UserModel::updateAuthUser` → account/administration service | The model accepts ORM `updated` or `no_change` and otherwise throws; the service converts a handled exception into its existing operation error. Raw ORM statuses are not the HTTP envelope. |
| `UserAdminController::list` → Router AJAX → `#userListMount` | Only a successful list proceeds to partial rendering. It preserves data/meta and adds html; the JS checks status before replacement. The module resolves its partial through ModuleRuntime and cleans its output buffer with finally. |

Source paths: `src/GFrame/Auth/{SelfAccountService,UserAdministrationService,UserModel}.php`, `resources/modules/{self-account,user-admin}/application/app/controllers/`, and each module's `javascript/` source. Inspect these in the effective package; this table does not require changing unrelated modules to match the examples.

Router's current AJAX action channel returns HTTP 200 after serializing business results; API may derive/add `http_code` for `error` or `unauthorized`. Middleware failures occur before the action and must be checked separately. Preserve exact codes and distinguish request delivery, persistence and external effects.

## Project-owned extensions

Extend migrated MVC modules through project PHP subclasses and project views with native fallback. Do not add a registry, callback file or global hook bus for controller/model/view personalization. Campaigns use an AutomaticCampaignModel subclass: definitions, eligibleUsers, eligible and variables. Inject the same subclass in CampaignController and an AutomaticCampaignCronHandler subclass; override automaticCronHandler in the project controller so saved rules schedule the project handler. Pass the model to emit for project events. The former AutomaticCampaignRegistry/config/notifications/automatic-campaigns.php callbacks are removed, not silently compatible. Preserve security, scope, response, cooldown, queue and history contracts. See docs/notification-campaigns.md. Retain actual external transport/repository interfaces where interchangeability is required.

## Response Contract Reminder

The core provides GFrame\Security\HtmlSanitizer for untrusted rich HTML (DOM required, fail closed without it); plain sanitize() strips tags and must not silently replace rich content. Optional lexical-search preserves BaseConfias PHP/JS ranking; apply authorization before passing rows to the engine, and use GFrameLexicalSearch in JS. See docs/html-sanitizer.md and docs/lexical-search.md.

The stable backend keys are:

- `status`
- `message`
- `code`
- `data`
- `meta`
- `html`

Use `html` only when the frontend replaces a backend-rendered fragment.

These are available keys, not a requirement to return all of them. Operation envelopes include `status`; `code` identifies a situation the caller needs to distinguish, and `message` supplies public feedback when needed. Raw ORM reads can return records and writes retain their documented result. Do not force them into an HTTP envelope.
