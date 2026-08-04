# Changelog

All notable changes to `formsettings-for-filament` will be documented in this file.

## v0.4.1 - 2026-08-04

- Fixed: hiding or showing a field slammed the settings panel shut — the page refresh that applies the change live morphed the dropdown back to its closed server-rendered state. The dropdown now carries a `wire:key`, which makes Filament render its panel with `wire:ignore.self` (the same guard Filament's table column manager uses), so the panel stays open while the form updates underneath
- Verified in the browser against the demo app: hide/show round-trip with the panel staying open, badge and panel rows updating live, click-away still closing the dropdown

## v0.4.0 - 2026-08-03

**Behavior change:** the gear is now hidden on small screens by default — call `->showOnMobile()` to keep it. Saved settings still apply on mobile; only the panel is unreachable there.

- `->except([...])` keeps the gear off listed pages even in global mode
- `->perResource()`: one settings record per resource instead of per page; existing per-page settings are migrated the first time the resource key comes up empty
- `->predefined([Resource::class => ['Name' => [...]]])`: ship presets with your app — they appear in every user's panel (bolt icon) but are never applied automatically
- `Field::formSettingsLocked()` pins a field: users can neither hide nor reorder it (lock icon instead of the drag handle)
- Arrange overlay: "Arrange on form" numbers the fields on the real form — click badges to chain a new order (arrows connect the picks), star sets the entry point, crossed eye hides; unclicked fields keep their place
- `php artisan formsettings:graveyard` lists fields nobody touches, aggregated across users (requires `persist()` + `learn()`)
- Hidden fields can no longer be entry points: the star is disabled and stale names are refused
- Fixed: user-hidden fields stayed visible when the resource chained its own `->hidden()` / `->hiddenOn()` condition (e.g. conditional file uploads, disabled system-ID fields) — the hide setting now sits in both of Filament's visibility slots, so resource code can replace either one and the survivor still hides
- Fixed: file uploads and non-native selects were invisible to the arrange overlay and the usage tracker — the field-name marker now renders on the field's root element, where FilePond & friends can't destroy it
- Verified in the browser against the demo app: panel and overlay hide/unhide round-trips on Edit and Create pages (including reload persistence), developer visibility conditions still winning where they should, overlay badges covering every field type

## v0.3.0 - 2026-07-31

**Behavior change:** the gear now shows on every Create/Edit page by default. Call `->optIn()` to only show it on pages using the `HasFormSettings` trait (`->globally()` still works for explicit setups).

- New README screenshot showing suggestions, the submit-action picker and published/shared presets
- The dropdown panel is sized by the plugin script (inline, important) — panel themes ship layered `!important` rules that override any plugin CSS, which kept the panel narrow and single-column in themed apps
- The dropdown uses its own `formsettings-width` class instead of Filament's `fi-width-*`

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
