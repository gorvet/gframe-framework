---
name: gframe-framework-maintenance
description: Maintain the standalone GFrame package, including Composer integration, configuration layers, tests, documentation, skills, installer development, and explicitly requested releases.
---

# GFrame Framework Maintenance

For new symbols or a naming review, use the matching-package [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Preserve existing APIs, keys and selectors; locate the companion in the effective package if installed separately.

Use this for changes to the reusable framework repository rather than one application's business code.

Creating or updating an application is a separate task against an identified project. The integration recipes below do not authorize changing a consuming application merely because the framework changed.

## Read Order

1. [references/repository-workflow.md](references/repository-workflow.md)
2. [references/documentation-and-skills.md](references/documentation-and-skills.md)

## Workflow

For an identified application's creation/update/integration, use the applicable mode in `references/repository-workflow.md` and the effective package's guides. The reusable-framework steps below apply only to shared package changes; an application update does not require implementing or releasing framework code. Installer-development checks apply only when changing the installer.

1. Confirm the change is reusable across GFrame applications.
2. Modify the standalone framework repository, never an application's vendored copy.
3. Add or update tests when observable behavior changes. For instructions or examples, verify the affected contracts and examples without inventing runtime changes.
4. Update the relevant framework documentation. Add changelog entries for release-relevant changes, not minor editorial adjustments unless requested.
5. Review the canonical skills under `skills/` and update any affected instructions.
6. Select checks for the affected surface: PHP lint/tests for runtime, JavaScript tests for frontend behavior, real-engine tests for migrations, and structural/link/example checks for skills. Validate Composer or audit dependencies when package metadata/dependencies are affected; run the required full checks before a requested release. Do not present skipped or unavailable checks as passed.
7. Leave the change reviewable with evidence and remaining integration checks. Commit, update a consuming project's lock, sync global skills, tag or publish only when that action is included in the user's authorized scope.

## Configuration

- Internal defaults belong in `config/defaults.php`.
- Stable project structure belongs in the application's `config/app.php`.
- Environment-specific values and secrets belong in `.env`.
- Preserve temporary legacy constants only through the compatibility bridge.

## Boundaries

- Do not move business rules, visual identity, project migrations, or application copy into GFrame.
- Optional modules should remain separate until their reusable contracts are proven.
- Do not publish or tag a release without explicit authorization.
- Preserve existing changes and pending work. A framework fix does not authorize refactoring unrelated modules or changing their contracts.
- Continue actions already authorized in the task without requesting the same permission again. When scope is unclear, complete the reviewable work that is already authorized and identify the specific unresolved action.
