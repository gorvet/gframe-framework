---
name: gframe-framework-maintenance
description: Maintain and release the standalone GFrame package, including Composer integration, configuration layers, tests, documentation, changelog, skills, application lock updates, and installer development.
---

# GFrame Framework Maintenance

Use this for changes to the reusable framework repository rather than one application's business code.

## Read Order

1. [references/repository-workflow.md](references/repository-workflow.md)
2. [references/documentation-and-skills.md](references/documentation-and-skills.md)

## Workflow

1. Confirm the change is reusable across GFrame applications.
2. Modify the standalone framework repository, never an application's vendored copy.
3. Add or update tests for observable behavior.
4. Update the relevant framework documentation and `CHANGELOG.md`.
5. Review the canonical skills under `skills/` and update any affected instructions.
6. Run Composer validation, security audit, lint, tests, and skill validation.
7. Commit GFrame, update the consuming application's Composer lock, and verify its integration.

## Configuration

- Internal defaults belong in `config/defaults.php`.
- Stable project structure belongs in the application's `config/app.php`.
- Environment-specific values and secrets belong in `.env`.
- Preserve temporary legacy constants only through the compatibility bridge.

## Boundaries

- Do not move business rules, visual identity, project migrations, or application copy into GFrame.
- Optional modules should remain separate until their reusable contracts are proven.
- Do not publish or tag a release without explicit authorization.
