# Restatify Forms 1.1.0

- Delegate reCAPTCHA v3 and Turnstile verification to Shared 1.1.0.
- Require and bundle exact Shared 1.1.0; preserve local-root development loading.
- Preserve missing-key bypass behavior, trigger formats and score threshold 0.5.
- Add wrapper regression tests and fail packaging if the production build fails.

Update ZIP: `wp-restatify-forms-1.1.0.zip`.
WordPress 6.9+, PHP 8.0+. Install/update via the WordPress plugin uploader.
Coordinate with Shared 1.1.0; the ZIP contains its PHP payload.
