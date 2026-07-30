<?php

namespace Blemli\FormSettings;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class FormSettingsPlugin implements Plugin
{
    protected bool | Closure $isGlobal = false;

    protected bool | Closure $isPersistent = false;

    protected bool | Closure $hasPresets = false;

    public function getId(): string
    {
        return 'formsettings-for-filament';
    }

    /**
     * Show the gear on every Create/Edit page, without the HasFormSettings trait.
     */
    public function globally(bool | Closure $condition = true): static
    {
        $this->isGlobal = $condition;

        return $this;
    }

    /**
     * Store settings per user in the database instead of the session.
     */
    public function persist(bool | Closure $condition = true): static
    {
        $this->isPersistent = $condition;

        return $this;
    }

    /**
     * Let users save and load named setting presets.
     */
    public function presets(bool | Closure $condition = true): static
    {
        $this->hasPresets = $condition;

        return $this;
    }

    public function isGlobal(): bool
    {
        return (bool) $this->evaluate($this->isGlobal);
    }

    public function isPersistent(): bool
    {
        return (bool) $this->evaluate($this->isPersistent);
    }

    public function hasPresets(): bool
    {
        return (bool) $this->evaluate($this->hasPresets);
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER,
            fn (): string => view('formsettings-for-filament::gear')->render(),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    protected function evaluate(bool | Closure $value): bool
    {
        return (bool) ($value instanceof Closure ? $value() : $value);
    }
}
