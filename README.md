# formsettings-for-filament

> rule your forms

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blemli/formsettings-for-filament.svg?style=flat-square)](https://packagist.org/packages/blemli/formsettings-for-filament)[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/blemli/formsettings-for-filament/run-tests.yml?branch=5.x&label=tests&style=flat-square)](https://github.com/blemli/formsettings-for-filament/actions?query=workflow%3Arun-tests+branch%3A5.x)[![Total Downloads](https://img.shields.io/packagist/dt/blemli/formsettings-for-filament.svg?style=flat-square)](https://packagist.org/packages/blemli/formsettings-for-filament)

A gear on your form pages that lets every user tune the form like the table column selector: reorder the tab order, hide optional fields, pick an autofocus entry point, and choose what the submit button does (save / save & next / save & back, `cmd+enter` included). Forms that use tabs (or wizard steps) show their fields grouped by tab, and an entry point inside another tab activates that tab on load.

![formsettings panel](docs/screenshot.png)

## Installation

```bash
composer require blemli/formsettings-for-filament
php artisan formsettings-for-filament:install   # publishes config + migration (only needed with persist())
```

## Usage

```php
use Blemli\FormSettings\FormSettingsPlugin;

$panel->plugin(
    FormSettingsPlugin::make()
        ->globally()   // gear on all Create/Edit pages …
        ->persist()    // … settings per user in the DB instead of the session
        ->presets()    // … named presets
        ->publish([Profanity::make()])        // … users can share presets with everyone (requires persist(); optional name rules, e.g. Blasp)
        ->ignoreGroups(['English', 'Deutsch'])   // … tabs that should NOT group the panel (e.g. translation tabs)
        ->saveAndBackButton()   // … a visible "Save & back" button; the save-and-back submit action is only offered when this is on
        ->learn(after: 5)   // … quiet usage-based suggestions inside the panel (field names only, never values)
        ->authorize('use-formsettings')   // … only for power users: bool, closure or gate ability (plays nice with Filament Shield)
);
```

Published presets appear below the user's own ones, tinted and tagged with the owner's first name. Anyone can hide a shared preset (eye icon); hidden ones collapse into a subtle "n hidden" line that reveals them again — no extra UI to restore. Saving over an existing preset name asks for a confirming second click (the plus turns into a flame).

With `learn()`, the plugin watches which fields each user actually fills (field names only — values are never read) and offers **pull-only** suggestions at the top of the gear panel — never popups or badges: hide the fields they rarely use (Create pages only), adopt their habitual first field as the entry point, make "Save & back" the default when they finish with it often, open the form on the tab they usually start in, and — when their usage exactly matches an existing preset (own or shared) — apply that preset instead of piecing it together. Matching is graceful: `learn(after: 5)` means 5 consistent runs among the last 7, so an outlier or two is forgiven. Each suggestion can be applied or dismissed once; dismissed suggestions never return.

Forms with tabs also get a per-user **start tab**: click a group label in the panel (pin icon marks the active one) and the form opens on that tab — unless an entry point is set, which activates its own tab.

Without `globally()`, opt single pages in with the `HasFormSettings` trait:

```php
use Blemli\FormSettings\Concerns\HasFormSettings;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    use HasFormSettings;
}
```

Uninstall cleanly with `php artisan formsettings:uninstall`.

## License

MIT — see [LICENSE](LICENSE.md).
