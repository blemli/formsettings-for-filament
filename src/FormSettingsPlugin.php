<?php

namespace Blemli\FormSettings;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Throwable;

class FormSettingsPlugin implements Plugin
{
    protected bool | Closure $isGlobal = false;

    protected bool | Closure $isPersistent = false;

    protected bool | Closure $hasPresets = false;

    protected bool | string | Closure $authorization = true;

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

    /**
     * Restrict who gets the gear. Accepts a boolean, a closure that
     * receives the authenticated user (or null), or a Gate ability
     * name — e.g. a permission defined via Filament Shield.
     */
    public function authorize(bool | string | Closure $condition): static
    {
        $this->authorization = $condition;

        return $this;
    }

    public function isAuthorized(): bool
    {
        try {
            $user = Filament::auth()->user();
        } catch (Throwable) {
            $user = auth()->user();
        }

        if ($this->authorization instanceof Closure) {
            return (bool) ($this->authorization)($user);
        }

        if (is_string($this->authorization)) {
            return (bool) $user?->can($this->authorization);
        }

        return $this->authorization;
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
