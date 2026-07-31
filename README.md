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
        ->optIn()      // dont show the gear anywhere except on Pages with the HasFormSettings Trait
        ->persist()    // … settings per user in the DB instead of the session
        ->presets()    // … named presets
        ->publish([Profanity::make()])  // … users can share presets with ohters (requires persist(); you can validate the name)
        ->ignoreGroups(['English', 'Deutsch'])   // … tabs that should NOT group the panel (e.g. translation tabs)
        ->saveAndBackButton()   // to quickly edit things
        ->learn(after: 5)   // … quiet usage-based suggestions inside the panel "you often start in xyz, set as entrypoint?"
        ->authorize('use-formsettings')   // only show the settings to users which pass this gate
);
```

With `optIn()`, single pages join via the trait:

```php
use Blemli\FormSettings\Concerns\HasFormSettings;

class EditPost extends EditRecord
{
    use HasFormSettings;
}
```



## Uninstall

Uninstall cleanly with `php artisan formsettings:uninstall`.

## License

MIT — see [LICENSE](LICENSE.md).
