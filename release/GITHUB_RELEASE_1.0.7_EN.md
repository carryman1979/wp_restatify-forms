# Restatify Forms 1.0.7

## What's new

- Shared resolver now prioritizes local root shared (`wp_restatify-shared/src/*`) in development environments.
- If root shared is unavailable, it loads only the required shared version from `wp-content/plugins/wp_restatify-shared/versions/<x.y.z>/` (or MU-plugins).
- The admin mail-template editor now uses resolved shared base URL and shared base path.
- Copilot repo guidance now includes the shared loader order rule.

## Release-prep refresh (2026-05-30)

- No version bump: release prep remains on `1.0.7`.
- Admin/frontend look-and-feel refinements (including dark-theme related polish) were consolidated.
- Mail-editor helpers plus submission/UI refactor paths and tests were synchronized for rollout.

## Compatibility

- Plugin version: `1.0.7`
- WordPress: `6.9+`
- PHP: `8.0+`
- No migration required.

## Artifact

- `wp-restatify-forms-1.0.7.zip`

