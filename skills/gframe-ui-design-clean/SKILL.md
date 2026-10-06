---
name: gframe-ui-design-clean
description: Design and review modern Bootstrap UX/UI for GFrame admin and public views, emphasizing real task flows, restrained visual hierarchy, responsive behavior, accessibility, and reusable application components instead of generic AI-dashboard styling.
---

# GFrame UX/UI with Bootstrap

Use this skill when the task is visual design, interaction structure, responsive layout, usability, or frontend consistency in a GFrame application or an explicitly requested framework UI improvement. It does not authorize a redesign merely because code or skills are being audited.

Read [references/bootstrap-patterns.md](references/bootstrap-patterns.md) before changing a screen and [references/ui-review-checklist.md](references/ui-review-checklist.md) before closing the task.

## Coordinate With Technical Frontend Rules

| Task surface | Technical reference |
| --- | --- |
| Admin view, form, list or modal | [Canonical admin structure and acceptance checks](../gframe-frontend-admin/references/view-form-structure.md) and [assets/meta ownership](../gframe-frontend-admin/references/project-structure-meta.md) |
| Public page or navigation | [Public templates and assets](../gframe-frontend-public/references/public-template-and-assets.md); use that skill's SEO/meta references when those concerns change |
| AJAX feedback | [Existing request and feedback helpers](../gframe-frontend-admin/references/ajax-feedback-pattern.md) |

Use this skill to decide hierarchy and usability; keep technical view conventions in their canonical references. Preserve the user's approved design and inspect an approved screen of the same type. A generic pattern here does not replace its title, actions, padding, selectors, template, theme or response contracts. If companion references are unavailable in an installed copy, locate them in the project's resolved GFrame package rather than substituting unrelated global instructions.

## Start From the User Task

1. Identify the primary user, their goal, and the decision or action the screen must support.
2. Inspect adjacent GFrame views before designing so the result belongs to the same product.
3. Establish information hierarchy and interaction order before adding decoration.
4. Use Bootstrap components and utilities as the baseline, then add the smallest application-specific CSS needed.
5. Verify real content, long labels, empty states, errors, loading, mobile behavior, and keyboard access.

## Bootstrap-First Rules

- Prefer the current Bootstrap version already installed by the project; do not introduce another UI framework.
- Use `container` or `container-fluid`, `row`, `col-*`, gutters, spacing utilities, flex utilities, forms, buttons, navs, tables, badges, alerts, pagination, modals, offcanvas, accordions, and dropdowns before custom replacements.
- Use responsive columns intentionally. Do not force every screen into identical equal-width cards.
- Use flexbox for local alignment. Follow the applicable frontend skill's Bootstrap/columns/flex-first layout rule; use CSS Grid only when those cannot express the required relationship. Do not replace an established layout merely to apply this preference.
- Preserve native Bootstrap behavior and accessibility attributes instead of restyling components until they become unfamiliar.
- Avoid hardcoded widths when a Bootstrap container or column expresses the intended measure.

## Avoid Generic AI Styling

- Do not default to oversized gradient heroes, floating glass panels, neon glows, excessive pills, decorative blobs, or dashboards made entirely of cards.
- Do not place every paragraph, list, filter, and action inside another rounded box.
- Do not manufacture metrics, badges, icons, helper copy, or secondary actions merely to make a screen look populated.
- Do not use huge headings when the page task needs density and scanning.
- Avoid excessive border radii, shadows, gradients, and color accents. Let spacing, typography, alignment, and grouping create hierarchy.
- Do not replace established product patterns with a fashionable but inconsistent design.

## Product Consistency

- Framework assets and application assets are separate. Do not patch an installed GFrame package for a project-only design. For an authorized framework improvement, edit skeleton/module originals and respect their manifest asset targets.
- Keep view-specific rules in the module stylesheet.
- Move genuinely reused application patterns to the project's established shared layer, such as `public/css/app/common.css`; do not force a runtime module's assets into legacy application paths.
- Change framework-level tokens or shared assets only for an explicitly authorized GFrame-wide improvement.
- Reuse existing card radius, typography, button hierarchy, form sizing, sidebar treatment, hero rhythm, and content widths.
- Preserve the existing theme selector, persistence and controller. Inspect loaded Bootstrap variables and module styles before adding tokens or another theme layer; use the canonical frontend asset rules for their locations.

## Interaction and Content

- Make the primary action obvious and limit competing button emphasis.
- Use links for navigation and buttons for actions.
- Keep filters close to the results they control and preserve state consistently.
- Prefer progressive disclosure when secondary information would overwhelm the initial scan.
- Use clear labels and specific feedback. Placeholder text does not replace a label.
- Preserve server-rendered markup and backend response contracts unless the task explicitly changes them.

## Responsive and Accessible Baseline

- Design mobile, tablet, and desktop behavior deliberately rather than relying on accidental wrapping.
- Keep critical actions reachable without horizontal scrolling.
- Ensure visible focus, usable keyboard order, clear labels, sufficient contrast, and meaningful headings.
- Use ARIA only when native HTML and Bootstrap semantics are insufficient.
- Respect reduced motion when adding animation.

## Boundaries

- Do not change controllers, models, permissions, or validation rules for a visual-only task.
- If the UX problem requires backend or framework behavior, state that dependency before expanding scope.
- Do not modify shared CSS merely to fix one isolated screen.

## Verification Evidence

For an implementation, review the affected states in the rendered screen and record what passed, failed or could not be checked. Static source inspection cannot establish responsive appearance, contrast or keyboard behavior. Scale verification to the actual change; do not claim a browser review or introduce new product controls solely to satisfy a generic checklist.

For edits to this skill alone, validate its structure, links and agreement with the canonical frontend rules. Do not redesign screens or rerun unrelated runtime suites as part of that documentation task.
