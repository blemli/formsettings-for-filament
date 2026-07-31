# Changelog

All notable changes to `formsettings-for-filament` will be documented in this file.

## v0.2.0 - 2026-07-31

**Migration note:** the `formsettings` table gained a `published` boolean column. Existing installs must add it (`$table->boolean('published')->default(false)`); fresh installs get it from the stub.

- Tabs: the panel groups fields by their tab (or wizard step); `->ignoreGroups([...])` excludes noise groups such as per-locale translation tabs; an entry point inside an inactive tab activates that tab on load
- Per-user start tab: click a group label in the panel (pin icon) and the form opens on that tab — an entry point takes precedence
- Two-column field list in a wider panel on large screens; long labels truncate with a tooltip instead of squashing the row buttons
- Preset publishing via `->publish([rules])` (requires `persist()`): globe toggle (green when published), shared presets listed below own ones tagged "(Firstname L.)", per-user hiding with an "n hidden" restore line, optional preset-name rules (e.g. Blasp profanity check)
- Guardrails for presets: 25-char name limit with Filament-style inline validation, default-config and exact-duplicate-config saves blocked, identical already-published twins blocked, two-step overwrite and two-step delete of published presets (plus becomes a flame), applied presets sanitized against the current form
- `->saveAndBackButton()`: a visible "Save & back" / "Create & back" button next to the primary action; the save-and-back submit action is only offered while the button is enabled — and "create & create another" now respects the page's `canCreateAnother()`
- Passive learning via `->learn(after: N)`: pull-only suggestions inside the panel (never popups or badges) — hide rarely-used fields (Create pages), entry point, start tab, default save-and-back, or an existing preset whose configuration matches the usage exactly; N consistent runs among the last N + 2 so an outlier or two is forgiven; field names only, values are never read; dismissed suggestions never return
- Panel polish: suggestions on top, spacing fixes, validation messages scroll into view
- Fixed: the publish toggle emitted an uncompiled `@js()` expression and never fired

## v0.1.2 - 2026-07-30

- `formsettings:uninstall` now points to the exact `file:line` of any remaining `FormSettingsPlugin` registration in the app's providers (removing it prevents the post-uninstall crash) and offers to run `composer remove blemli/formsettings-for-filament` directly (`--force` keeps printing the hint instead)

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
