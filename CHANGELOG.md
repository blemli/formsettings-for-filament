# Changelog

All notable changes to `formsettings-for-filament` will be documented in this file.

## v0.1.1 - 2026-07-30

- `formsettings:uninstall` now removes everything the installer published: the timestamped migration copy in `database/migrations` and the Filament assets under `public/{css,js}/blemli/formsettings-for-filament/` (empty `blemli` parent dirs are cleaned up too)
- Artisan command output is no longer translated — console output is always English, independent of the app locale
- The install command no longer asks to star the repo on GitHub
- README: code example for opting in single pages via the `HasFormSettings` trait

## v0.1.0 - 2026-07-30

Initial release.

- Gear dropdown on form pages (per-page `HasFormSettings` trait or `->globally()`), badge shows the number of customizations
- Field list with type icons (text, e-mail, password, number, price, color, date, …), drag-reorder controls the tab order
- Optional fields can be hidden (crossed eye) — they are removed from the form and skipped
- One field can be the entry point (star) — it gets autofocus
- Submit action picker: save / save & next / save & back (create / create & another / create & back), the main button becomes the selected action, `cmd+enter` triggers it
- Session storage by default, per-user database storage via `->persist()`
- Named presets via `->presets()`, one-click reset to defaults
- German + English translations, `formsettings:uninstall` command

Browser QA (Chrome, mouseless-demo host): happy path for hide/reorder/entry point/all six submit actions, DB persistence round-trips across pages and reloads, dark + light mode, `cmd+enter`, presets save/apply/delete, reset, create page, zero console errors, all network requests 200/304.
