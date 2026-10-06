# Specialist Routing

Use responsibility and changed surface, not a keyword count. Names below identify canonical entrypoints in the confirmed package's `skills/` tree; availability and runtime compatibility must be established there. Do not assume a relative global directory contains the target project's package.

| Changed responsibility | Primary skill | Add only for the affected surface |
| --- | --- | --- |
| Bootstrap, route resolution, shared middleware/config/render architecture | `gframe-core-architecture` | Backend for actions/services; frontend for rendered views |
| HTTP actions/services and response contracts | `gframe-backend` | ORM for persistence; auth for identity/permissions changes |
| ORM query/model, dialect, connection or transaction | `gframe-orm-models` | Backend if exposing the operation through HTTP |
| Login, session, roles, permissions or account management | `gframe-auth-access` | Backend/admin/public for the edited endpoint/screen |
| Admin views, meta/assets, AJAX forms/listings | `gframe-frontend-admin` | Backend/ORM when their consumers or persistence change |
| Public pages, content rendering, view meta and SEO | `gframe-frontend-public` | Backend for an action; integrations for remote content |
| Requested visual/UX decisions | `gframe-ui-design-clean` | Matching admin/public technical skill; available design specialists only when relevant |
| Media library, picker/field, uploads, scopes and relations | `gframe-media-module` | Admin/public for the integration; auth for changed membership/session policy |
| Mail templates, inbox and delivery queues/transports | `gframe-notifications-mail` | Frontend/backend for a screen/action; jobs when worker execution/scheduling changes |
| Async, cron task, runner/worker operation or heartbeat channel | `gframe-background-jobs` | Domain specialist for business payload; auth for changed session policy |
| Campaign audience, dispatch, recurrence, automatic rules/lifecycle | `gframe-campaigns` | Jobs for changed task registration/execution; mail for changed delivery; admin/backend for a screen/action |
| Outbound HTTP/WordPress or inbound API/webhook/SSE | `gframe-integrations` | Public for rendered content; backend/auth/ORM when business access or persistence changes |
| Reusable package maintenance, installer/updater or an identified project's integration | `gframe-framework-maintenance` | Core for package/config discovery; the specialist whose shared behavior changes |

## Representative Selections

- **Admin CRUD with persistence:** admin + backend + ORM. Add auth only if changing permissions/identity logic; preserving existing route permissions does not require a new auth architecture.
- **Public contact form using existing Mail:** public + backend + mail. Mail's Async recipe is already covered there; jobs is needed if generic scheduling/execution itself changes. A contact email does not imply a campaign.
- **Render a WordPress article:** integrations + public. Add backend only for controller/service work beyond the existing remote-content recipe. The WordPress credential is not the application's user identity.
- **Insert an existing picker into a tenant admin form:** media + admin. Preserve verified server tenant; add auth if changing membership/session resolution. Add backend when adapting validation/saving of IDs, even for an existing field; add ORM when persistence/query logic changes. A new media relation is not required to trigger those responsibilities.
- **Create a scheduled campaign through the existing service:** campaigns. Add admin/backend for a new endpoint/screen, jobs for a new/custom cron handler or registrar, and mail for a changed transport/template. Inspect queue/dispatch outcomes without automatically editing all three subsystems.
- **Update an identified application:** maintenance's project-integration recipe + core's effective-package/config reference. Respect dry-run and personalized files. Do not edit the application's installed core, publish the standalone framework or synchronize global skills implicitly.

## External Workflow and Design Skills

Keep external skills separate from the GFrame package. When a task explicitly uses stages or has an active stage baseline, coordinate the available `feature-stages` family with the selected GFrame specialists: stage skills own conciliation/tasks/audit gates, and GFrame skills own technical contracts. Respect those gates once that workflow applies; do not generate stage documents for every small correction or call a stage closed from GFrame test counts alone.

For substantial UI design/redesign or an explicit UX request, use the available GORVET UI/UX pipeline/specialists with the matching GFrame frontend contracts. `gframe-ui-design-clean` retains the framework's visual conventions; it does not replace an invoked external pipeline's quality gates. Resolve the installed tool's qualified skill names rather than assuming identical plugin namespaces in every client. Small established UI changes use only the relevant path.

Neither coordination requires copying stages/UX into GFrame, making them mandatory plugin dependencies, or installing missing plugins automatically. If required external instructions are unavailable, state that limitation and continue only the part supported by the task and verified local contracts; do not claim the missing workflow ran.

## Scope and Missing Capability

For a standalone framework fix, use maintenance plus the specialist for the changed shared behavior. For a project feature, use the project's adapters/overrides. If the user explicitly requests a shared framework change, that authorization applies to the standalone source, not every consumer automatically.

If two projects use different releases, resolve each separately. New specialist APIs are guidance only after checking the older package's code/docs. Do not force an upgrade to make the current skill applicable. When a skill is unavailable, adapt using verified local contracts or report the specific missing capability; do not claim a global newest skill is version-matched.

If only a copy/move is requested, preserve the original intact and make only requested adjustments. If only naming is being inventoried, trace PHP/JS symbols, SQL/request fields and DOM attributes separately; do not transform them all into one spelling.
