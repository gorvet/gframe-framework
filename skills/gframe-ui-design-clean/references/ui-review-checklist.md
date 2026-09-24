# UX/UI Review Checklist

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

- Mobile, tablet, and desktop layouts were checked.
- Critical actions remain visible and reachable.
- Inputs have labels, focus is visible, contrast is acceptable, and heading order is meaningful.
- Motion is limited and respects user preferences.
