<?php

namespace Blemli\FormSettings;

use Blemli\FormSettings\Commands\GraveyardCommand;
use Blemli\FormSettings\Commands\UninstallCommand;
use Blemli\FormSettings\Livewire\SaveActionHook;
use Blemli\FormSettings\Livewire\SettingsPanel;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FormSettingsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'formsettings-for-filament';

    public static string $viewNamespace = 'formsettings-for-filament';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations($this->getMigrations())
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FormSettings::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        Livewire::component('formsettings-panel', SettingsPanel::class);
        Livewire::componentHook(SaveActionHook::class);

        // Developer guardrail: ->formSettingsLocked() pins a field —
        // users can neither hide nor reorder it.
        Field::macro('formSettingsLocked', function (bool $locked = true) {
            app(FormSettings::class)->lockField($this, $locked);

            return $this;
        });

        $this->configureFields();
        $this->configureActions();
    }

    /**
     * Attach lazily-evaluated closures to every form field so the
     * user's saved settings (hide, tab order, entry point) apply at
     * render time on enabled pages — and stay inert everywhere else.
     */
    protected function configureFields(): void
    {
        Field::configureUsing(function (Field $field): void {
            $manager = fn (): FormSettings => app(FormSettings::class);

            $field->hidden(fn (Field $component): bool => $manager()->fieldHidden($component));
            $field->disabled(fn (Field $component): bool => $manager()->fieldHidden($component));
            $field->autofocus(fn (Field $component): bool => $manager()->fieldIsEntryPoint($component));

            $attributes = function (Field $component) use ($manager): array {
                $attributes = [];

                if (($index = $manager()->fieldTabIndex($component)) !== null) {
                    $attributes['tabindex'] = $index;
                }

                if ($manager()->fieldIsEntryPoint($component)) {
                    $attributes['data-formsettings-entry'] = 'true';
                }

                if (($name = $manager()->fieldLearnName($component)) !== null) {
                    $attributes['data-formsettings-name'] = $name;
                }

                if ($manager()->fieldStartsTab($component)) {
                    $attributes['data-formsettings-start-tab'] = 'true';
                }

                return $attributes;
            };

            if (method_exists($field, 'extraInputAttributes')) {
                $field->extraInputAttributes($attributes, merge: true);
            } else {
                $field->extraAttributes($attributes, merge: true);
            }
        });
    }

    /**
     * Tag the page's primary submit action so the panel script can
     * relabel it and bind mod+enter to it.
     */
    protected function configureActions(): void
    {
        Action::configureUsing(function (Action $action): void {
            if ($action->getName() === 'createAnother') {
                $action->hidden(function (): bool {
                    $manager = app(FormSettings::class);
                    $livewire = Livewire::current();

                    return $manager->isEnabledFor($livewire)
                        && $manager->selectedAction($livewire) === 'create_next';
                });

                return;
            }

            if (! in_array($action->getName(), ['save', 'create'], true)) {
                return;
            }

            $action->extraAttributes(function (): array {
                $manager = app(FormSettings::class);

                return $manager->isEnabledFor(Livewire::current())
                    ? ['data-formsettings-primary' => 'true']
                    : [];
            }, merge: true);
        });
    }

    protected function getAssetPackageName(): ?string
    {
        return 'blemli/formsettings-for-filament';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Js::make('formsettings-scripts', __DIR__ . '/../resources/dist/formsettings.js'),
            Css::make('formsettings-styles', __DIR__ . '/../resources/dist/formsettings.css'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            UninstallCommand::class,
            GraveyardCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_formsettings_table',
        ];
    }
}
