<?php

namespace Blemli\FormSettings;

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
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('blemli/formsettings-for-filament');
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

            $tabIndex = fn (Field $component): array => ($index = $manager()->fieldTabIndex($component)) === null
                ? []
                : ['tabindex' => $index];

            if (method_exists($field, 'extraInputAttributes')) {
                $field->extraInputAttributes($tabIndex, merge: true);
            } else {
                $field->extraAttributes($tabIndex, merge: true);
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
