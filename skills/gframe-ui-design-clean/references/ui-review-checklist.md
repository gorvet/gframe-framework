# UX/UI Review Checklist

Apply only the checks relevant to the requested change. Record each affected check as passed, failed, unverified or not applicable, with its evidence. A skill-only edit does not require opening or redesigning an application screen.

## Scope and Existing Contracts

- The implemented change matches the user's approved reference and task; no redesign was inferred from an audit alone.
- The applicable frontend skill supplies the technical view, meta, asset, selector and feedback contracts.
- Source ownership is correct: project overrides for project work, framework originals for an authorized framework change.
- Existing theme behavior and asset registration remain coherent; no duplicate menu/theme controller was introduced.

## User Flow

- The primary task is apparent without explanatory clutter.
- Primary and secondary actions have distinct emphasis.
- Navigation uses links; state-changing operations use buttons.
- Filters, pagination, empty states, errors, confirmations, and loading behavior are coherent.

## Bootstrap

- Layout uses Bootstrap containers, rows, columns, gutters, utilities, and components where appropriate.
- Custom CSS solves a real gap instead of recreating Bootstrap.
- Breakpoints reflect content needs rather than arbitrary device labels.
- Tables, forms, modals, offcanvas panels, accordions, alerts, and pagination retain expected behavior.

## Visual Restraint

- The interface does not look like a generic AI-generated dashboard.
- Cards are used for meaningful grouping, not as wrappers for every element.
- Border radii, shadows, gradients, pills, icons, and accent colors are not overused.
- Typography and spacing provide most of the hierarchy.
- No invented metrics, labels, copy, or actions were added for decoration.

## CSS Placement

- One-screen rules remain in the module stylesheet.
- Rules shared by several application views live in the application common layer.
- Framework source and vendored assets were not changed for a project-specific design.
- Existing tokens were reused and hardcoded colors were avoided unless explicitly requested.

## Content and States

- Realistic long content does not break the layout.
- Empty, loading, error, disabled, hover, focus, and expanded states are covered when relevant.
- Repeated components remain visually homogeneous.

## Responsive and Accessibility

- Affected viewport widths, long content and zoom behavior were checked in rendered output; record untested combinations.
- Critical actions remain visible and reachable.
- Inputs have labels, focus is visible, contrast is acceptable, and heading order is meaningful.
- Motion is limited and respects user preferences.

## Evidence Limits

- Source inspection is identified separately from rendered/browser verification.
- Existing automated tests are credited only for behaviors they exercise; static markup assertions are not a visual or keyboard audit.
- Unverified contrast, focus, responsive states or interactions remain visible instead of being marked complete.
- Recheck after fixes only where the change or a failure warrants it; do not expand a small task into a full product redesign.
