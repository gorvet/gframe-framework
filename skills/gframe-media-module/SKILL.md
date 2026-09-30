---
name: gframe-media-module
description: Implement and maintain GFrame media libraries, uploads, scopes, reusable selectors, media fields, and content relations in standalone framework modules or GFrame applications.
---

# GFrame Media Module

Work from the framework module manifest and preserve the MVC boundaries already present in GFrame.

## Scope model

- Use `MediaScopeResolver` for application requests.
- Keep `global`, `tenant`, and `user` as the only storage scopes.
- Store content ownership in `media_relations`; do not invent a scope per content type.
- A tenant or user scope must fail when its identity cannot be resolved.

## Module contract

A complete reusable media module includes:

- MySQL and SQLite schemas;
- `MediaModel`, `MediaLibraryService`, storage and processor classes;
- an authenticated controller and routes for listing, upload and deletion;
- the library view and its public resources;
- reusable `mediaField` and `mediaPicker` components;
- `media-picker.js` y `media-field.js` declarados en el meta de cada vista que consume los componentes;
- fragmentos HTML del servidor para listado y vistas previas, con variantes `sthumb` y `gthumb` permitidas expresamente;
- publication tests for application files and resources.

Use the project's standard alerts, pagination, route middleware and CSRF fields. Keep visual styling in the module stylesheet and use Bootstrap for layout and interaction.
El campo múltiple guarda un arreglo JSON de IDs; el campo simple guarda un solo ID. El ámbito se resuelve en el servidor desde la sesión, no desde un `tenant_id` proporcionado por el cliente. No se deben marcar las capacidades de hotlink ni los controles avanzados del modal como extraídas hasta que tengan implementación y pruebas propias.

## Configuration

Read the selected scope from `media.scope`. The generated application config defaults to `global` outside multitenancy and `tenant` for SaaS installations. Do not add project-specific table names or business rules to the framework module.

## Verification

Run `composer check`, `composer validate --strict`, `composer audit` and `git diff --check` after changing the framework module.
