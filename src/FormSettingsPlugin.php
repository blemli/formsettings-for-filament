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
    protected bool | Closure $isGlobal = true;

    protected bool | Closure $isPersistent = false;

    protected bool | Closure $hasPresets = false;

    /** @var bool | array<mixed> | Closure */
    protected bool | array | Closure $publishing = false;

    /** @var array<string> | Closure | null */
    protected array | Closure | null $ignoredGroups = null;

    protected bool | int | Closure $learning = false;

    protected bool | Closure $hasSaveAndBackButton = false;

    protected bool | string | Closure $authorization = true;

    public function getId(): string
    {
        return 'formsettings-for-filament';
    }

    /**
     * The gear shows on every Create/Edit page by default. Call this
     * to only show it on pages that use the HasFormSettings trait.
     */
    public function optIn(bool | Closure $condition = true): static
    {
        $this->isGlobal = $condition instanceof Closure
            ? fn (): bool => ! $condition()
            : ! $condition;

        return $this;
    }

    /**
     * Show the gear on every Create/Edit page — the default since
     * v0.3.0; kept for setups that call it explicitly.
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
     * Let users publish presets to everyone using the same form.
     * Requires persist(). Pass an array of validation rules (e.g.
     * Blasp's profanity rule) to vet preset names before they are
     * saved or published.
     *
     * @param  bool | array<mixed> | Closure  $condition
     */
    public function publish(bool | array | Closure $condition = true): static
    {
        $this->publishing = $condition;

        return $this;
    }

    public function hasPublishing(): bool
    {
        $value = $this->publishing instanceof Closure ? ($this->publishing)() : $this->publishing;

        return is_array($value) || (bool) $value;
    }

    /**
     * @return array<mixed>
     */
    public function getPublishRules(): array
    {
        $value = $this->publishing instanceof Closure ? ($this->publishing)() : $this->publishing;

        return is_array($value) ? $value : [];
    }

    /**
     * Add a visible "Save & back" / "Create & back" button next to the
     * page's primary action. The save-and-back submit action is only
     * offered in the panel when this button is enabled — the same
     * consistency Filament applies to create-another.
     */
    public function saveAndBackButton(bool | Closure $condition = true): static
    {
        $this->hasSaveAndBackButton = $condition;

        return $this;
    }

    public function hasSaveAndBackButton(): bool
    {
        return (bool) $this->evaluate($this->hasSaveAndBackButton);
    }

    /**
     * Watch how each user fills the form (field names only — never
     * values) and offer quiet, pull-only suggestions in the panel:
     * hide rarely-used fields (Create pages) and adopt the habitual
     * first field as the entry point (all pages). Pass an int N to
     * require N consistent runs among the last N + 2, so an outlier
     * or two is forgiven (default: 5 of the last 7).
     */
    public function learn(bool | int | Closure $after = true): static
    {
        $this->learning = $after;

        return $this;
    }

    public function hasLearning(): bool
    {
        $value = $this->learning instanceof Closure ? ($this->learning)() : $this->learning;

        return is_int($value) ? $value > 0 : (bool) $value;
    }

    public function learningThreshold(): int
    {
        $value = $this->learning instanceof Closure ? ($this->learning)() : $this->learning;

        return max(1, is_int($value) ? $value : 5);
    }

    /**
     * Tabs or wizard steps whose labels should NOT group the settings
     * panel — e.g. per-locale translation tabs. Accepts an array of
     * labels or a closure receiving the tab/step component.
     *
     * @param  array<string> | Closure  $groups
     */
    public function ignoreGroups(array | Closure $groups): static
    {
        $this->ignoredGroups = $groups;

        return $this;
    }

    public function isGroupIgnored(object $component, string $label): bool
    {
        if ($this->ignoredGroups === null) {
            return false;
        }

        if ($this->ignoredGroups instanceof Closure) {
            return (bool) ($this->ignoredGroups)($component);
        }

        return in_array($label, $this->ignoredGroups, true);
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
