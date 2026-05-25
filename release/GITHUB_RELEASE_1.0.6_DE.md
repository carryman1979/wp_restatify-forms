# Restatify Forms 1.0.6

## Neu in diesem Release

- Shared-Resolver priorisiert lokales Root-Shared (`wp_restatify-shared/src/*`) fuer Entwicklungsumgebungen.
- Wenn Root-Shared nicht verfuegbar ist, wird nur die benoetigte Shared-Version aus `wp-content/plugins/wp_restatify-shared/versions/<x.y.z>/` (oder MU-Plugins) geladen.
- Admin-Mail-Template-Editor nutzt aufgeloeste Shared-Base-URL und Shared-Base-Path.
- Copilot-Repo-Richtlinie zur Shared-Loader-Reihenfolge aufgenommen.

## Kompatibilitaet

- Plugin-Version: `1.0.6`
- WordPress: `6.9+`
- PHP: `8.0+`
- Keine Migration erforderlich.

## Artefakt

- `wp-restatify-forms-1.0.6.zip`
