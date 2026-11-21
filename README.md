# LNKTA Admin Dark — Lens Archive

Flat dark WordPress administration theme using the Lens Archive palette.

## Repository setup status

The GitHub Releases updater and GitHub Actions release workflow have been committed. **The full plugin source files and first tagged release are not yet published**, so automatic updates are not active yet.

The complete v2.0.0 source files are provided as a separate downloadable ZIP in the ChatGPT conversation. Extract that archive and upload the `lnkta-admin-dark/lnkta-admin-dark.php` and `lnkta-admin-dark/assets/admin.css` files to these exact locations in this repository. The updater file at `lnkta-admin-dark/includes/github-updater.php` and `.github/workflows/release.yml` are already present.

After committing the source, create and push Git tag `v2.0.0`; the workflow builds a GitHub Release with `lnkta-admin-dark.zip` attached. Install that plugin ZIP once in WordPress. Future newer tagged releases can then update the plugin automatically via the WordPress updater.

Do not tag the repository until the full plugin source is present; otherwise the release package will be incomplete.
