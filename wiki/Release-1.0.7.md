# Release 1.0.7

Version 1.0.6 harmonisiert die Shared-Aufloesung fuer lokale und produktive Umgebungen.

## Highlights

- Shared-Resolver priorisiert lokales Root-Shared (`wp_restatify-shared/src/*`) fuer Development.
- Fallback laedt nur die benoetigte Version aus `wp-content/plugins/wp_restatify-shared/versions/<x.y.z>/` (oder MU-Plugins).
- Admin-Mail-Template-Editor referenziert die aufgeloeste Shared-Base-URL/-Path statt eines festen Pfads.
- Copilot-Repo-Beschreibung zur Shared-Loader-Reihenfolge aktualisiert.

## Release-prep refresh (2026-05-30)

- Kein Versionssprung: Release-Prep verbleibt auf `1.0.7`.
- UI-/Admin-Refactorings sowie Dark-Theme-nahe Frontend-Anpassungen konsolidiert.
- Mail-Editor-/Submission-/UI-Pfade und Test-Baseline fuer den koordinierten Rollout abgeglichen.

## Kompatibilitaet

- Plugin-Version: `1.0.7`
- WordPress: `6.9+`
- PHP: `8.0+`
- Keine Migration erforderlich

## Artefakt

- `wp-restatify-forms-1.0.6.zip`

