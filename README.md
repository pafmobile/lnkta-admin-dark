# LNKTA Admin Dark — Lens Archive

Flat WordPress administration design inspired by the LNKTA Vintage Lens Archive palette.

## Install

Download **lnkta-admin-dark.zip** from the [latest GitHub Release](https://github.com/pafmobile/lnkta-admin-dark/releases/latest), then install via WordPress → Plugins → Add Plugin → Upload Plugin, replacing any previous version.

## Automatic updates

As of **v2.0.0**, this plugin checks the latest public GitHub Release (cached for six hours) and registers newer releases as WordPress plugin updates. Background updates are enabled for this specific plugin. The release asset must be named **lnkta-admin-dark.zip** and the plugin folder must remain **lnkta-admin-dark**.

To publish a new version:
1. Update `Version:` and the CSS enqueue version in `lnkta-admin-dark/lnkta-admin-dark.php`.
2. Update `LNKTA_Admin_Dark_GitHub_Updater::VERSION` in `lnkta-admin-dark/includes/github-updater.php`.
3. Commit the updated source to `main`. GitHub Actions packages the plugin and publishes a new `vX.Y.Z` Release automatically when that version has not been released already.

Keep the repository public so WordPress can download releases. Review code and test each release before bumping versions, since enabled background updates may deploy the next release without additional interaction.

## Theme

- Background: `#151516`
- Panel: `#1B1B1C`
- Primary text: `#EBE3D4`
- Copper accent: `#CF6F38`
