# Restatify Forms

Product: Restatify-Forms  
Slug: wp_restatify-forms  
Company: https://www.restatify.tech
Version: 1.0.6

A standalone WordPress plugin that provides a multi-form popup builder with a multi-step admin wizard, configurable field types, email/tel validation, CAPTCHA options, and flexible submission modes (wp_mail or custom endpoint).

**Requires:** WordPress 6.9+, PHP 8.0+  
**License:** GPL-2.0-or-later

---

## Features

- **Multiple forms** — create unlimited forms, each with its own ID and trigger link
- **Multi-step admin wizard** — Basisdaten → Felder → Sicherheit → Versand
- **Field builder** — drag & drop + Up/Down buttons, 9 field types, configurable labels, placeholders, required flag, and per-field validation modes
- **Supported field types:** text, email, tel, textarea, select, radio, checkbox, number, hidden
- **Validation:** Email: none / simple / regex / DNS check. Tel: none / simple / E.164
- **CAPTCHA:** Honeypot, Google reCAPTCHA v3, Cloudflare Turnstile (configurable per form)
- **Submission modes:**
  - **wp_mail()** — HTML template editor with placeholder chips, owner notification + optional submitter confirmation, configurable recipient list (TO / CC / BCC)
  - **Custom endpoint** — HTTP POST to any URL, JSON or form-encoded, auth: none / Bearer / Basic
- **Frontend popup** — triggered by `#restatify-form-{id}` anchor links, ESC-closable, inline success message with OK button + auto-close
- **Gutenberg block** — *Form Trigger* block for inserting trigger buttons/links in the editor

---

## Installation

1. Download the latest release ZIP from [Releases](../../releases).
2. In WordPress: **Plugins → Add New → Upload Plugin** → select the ZIP → Install → Activate.
3. Navigate to **Forms** in the WordPress admin sidebar.

---

## Usage

### Creating a form

1. Go to **Forms → Neues Formular erstellen**.
2. Fill in **Basisdaten** (title, ID, optional subtitle/text).
3. Add and configure fields in **Felder**.
4. Configure spam protection in **Sicherheit**.
5. Choose submission mode and configure templates or endpoint in **Versand**.
6. Click **Speichern**.

### Triggering a popup

After saving, the form's trigger link is shown as `#restatify-form-{id}`.  
Use it anywhere as a plain anchor:

```html
<a href="#restatify-form-kontakt">Kontakt aufnehmen</a>
```

Or use the **Form Trigger** Gutenberg block to insert a styled button or link.

### Placeholder reference

The following placeholders can be used in email subject/body templates:

| Placeholder | Description |
|---|---|
| `{form_title}` | Title of the form |
| `{site_name}` | WordPress site name |
| `{date}` | Submission date/time |
| `{fields_table}` | HTML table of all submitted fields |
| `{fields_text}` | Plain-text list of all submitted fields |

---

## Build

The Gutenberg block is compiled with [wp-scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).

```bash
npm install
npm run build      # production build
npm run start      # development watch mode
```

Compiled output lands in `build/block/`.

### Creating a release ZIP

```powershell
npm run package
# or directly:
pwsh -NoProfile -ExecutionPolicy Bypass -File ./scripts/create-release-zip.ps1
```

Output: `release/wp-restatify-forms-{version}.zip`

---

## Third-party notices

See [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

---

## Changelog

### 1.0.6

- Shared resolver and loader-order policy aligned for root-shared and exact-version fallback handling.
- Admin and frontend look-and-feel refinements (including dark-theme friendly styles) consolidated.
- Admin mail-editor helpers and submission/UI internals refactored while preserving trigger compatibility.
- Release-prep notes and tests refreshed for coordinated multi-repo rollout.

### 1.0.5

- Hotfix rebuild without version bump: replaced defective 1.0.5 release package.
- Added shared library installer/runtime resolver for versioned central shared path handling.
- Added packaged shared install payload in release ZIP to ensure `PrivacyLegalNotice` is available during install/update.

### 1.0.4

- Added JS and PHP unit test baseline for trigger/link handling and picker compatibility.
- Added shared-guideline references for AGENTS and Copilot instructions.
- Updated release documentation and wiki links for current maintenance release.

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
