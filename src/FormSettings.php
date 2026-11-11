<?php

namespace Blemli\FormSettings;

use Blemli\FormSettings\Concerns\HasFormSettings;
use Blemli\FormSettings\Storage\DatabaseStore;
use Blemli\FormSettings\Storage\SessionStore;
use Blemli\FormSettings\Storage\SettingsStore;
use Blemli\FormSettings\Support\FieldIcons;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class FormSettings
{
    /**
     * Query parameter that opens a page with one preset applied for that
     * visit only — the user's saved settings stay untouched.
     */
    public const PRESET_PARAM = 'formsettings-preset';

    /**
     * Query parameter naming where that preset comes from: the user's own
     * presets (default) or the developer's predefined() ones.
     */
    public const SOURCE_PARAM = 'formsettings-source';

    public const QUICK_OPERATIONS = ['create', 'edit'];

    /**
     * @var array<string, array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}>
     */
    protected array $settingsCache = [];

    /**
     * Fields locked by the developer via Field::formSettingsLocked().
     *
     * @var \WeakMap<Field, bool>
     */
    protected \WeakMap $lockedFields;

    /**
     * Pages opened with a preset for this visit only, and that preset's
     * sanitized settings once they were needed.
     *
     * @var \WeakMap<object, array{name: string, source: string, settings?: array<string, mixed>}>
     */
    protected \WeakMap $visits;

    protected bool $isResolvingVisit = false;

    public function __construct()
    {
        $this->lockedFields = new \WeakMap;
        $this->visits = new \WeakMap;
    }

    public function lockField(Field $field, bool $locked = true): void
    {
        $this->lockedFields[$field] = $locked;
    }

    public function isFieldLocked(Field $field): bool
    {
        return $this->lockedFields[$field] ?? false;
    }

    /**
     * Developer-shipped presets that apply to the given page, from
     * FormSettingsPlugin::predefined() — keyed by preset name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function predefinedFor(object | string $livewire): array
    {
        $presets = [];

        foreach ($this->plugin()?->getPredefined() ?? [] as $class => $named) {
            $matches = is_a($livewire, $class, allow_string: true);

            if (! $matches) {
                try {
                    $matches = method_exists($livewire, 'getResource') && $livewire::getResource() === $class;
                } catch (Throwable) {
                }
            }

            if ($matches) {
                $presets = [...$presets, ...$named];
            }
        }

        return $presets;
    }

    public function plugin(): ?FormSettingsPlugin
    {
        try {
            $panel = Filament::getCurrentPanel();

            if (! $panel?->hasPlugin('formsettings-for-filament')) {
                return null;
            }

            $plugin = $panel->getPlugin('formsettings-for-filament');

            return $plugin instanceof FormSettingsPlugin ? $plugin : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function store(): SettingsStore
    {
        return $this->plugin()?->isPersistent()
            ? app(DatabaseStore::class)
            : app(SessionStore::class);
    }

    public function isEnabledFor(?object $livewire): bool
    {
        if (! $livewire) {
            return false;
        }

        $plugin = $this->plugin();

        if (! $plugin?->isAuthorized()) {
            return false;
        }

        if ($plugin->isExcepted($livewire)) {
            return false;
        }

        if (in_array(HasFormSettings::class, class_uses_recursive($livewire), true)) {
            return true;
        }

        return $plugin->isGlobal()
            && ($livewire instanceof CreateRecord || $livewire instanceof EditRecord);
    }

    /**
     * The key a page's settings and presets are stored under. Takes a page
     * instance or — for code that only knows where a link leads — its class.
     *
     * @param  object | class-string  $livewire
     */
    public function keyFor(object | string $livewire): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'default';
        $subject = is_object($livewire) ? $livewire::class : $livewire;

        if (($this->plugin()?->isPerResource() ?? false)
            && (is_a($livewire, CreateRecord::class, allow_string: true) || is_a($livewire, EditRecord::class, allow_string: true))) {
            try {
                $subject = $livewire::getResource();
            } catch (Throwable) {
            }
        }

        return $panelId . '::' . str_replace('\\', '.', $subject);
    }

    /**
     * @param  object | class-string  $livewire
     */
    protected function isFormPage(object | string $livewire): bool
    {
        return is_a($livewire, CreateRecord::class, allow_string: true)
            || is_a($livewire, EditRecord::class, allow_string: true);
    }

    /**
     * With perResource(), settings saved under the old per-page keys
     * are moved to the resource key the first time it comes up empty.
     */
    protected function migrateLegacyKeys(object $livewire, string $key): void
    {
        try {
            if (! ($this->plugin()?->isPerResource() ?? false)) {
                return;
            }

            if (! ($livewire instanceof CreateRecord || $livewire instanceof EditRecord)) {
                return;
            }

            $panelId = Filament::getCurrentPanel()?->getId() ?? 'default';

            foreach ($livewire::getResource()::getPages() as $registration) {
                $page = is_object($registration) && method_exists($registration, 'getPage')
                    ? $registration->getPage()
                    : (is_string($registration) ? $registration : null);

                if (! is_string($page)) {
                    continue;
                }

                $legacy = $panelId . '::' . str_replace('\\', '.', $page);

                if ($legacy !== $key) {
                    $this->store()->migrateKey($legacy, $key);
                }
            }
        } catch (Throwable) {
        }
    }

    /**
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}
     */
    public function settingsFor(object $livewire): array
    {
        if (($visit = $this->visitSettings($livewire)) !== null) {
            return $visit;
        }

        $key = $this->keyFor($livewire);

        return $this->settingsCache[$key] ??= (function () use ($livewire, $key): array {
            $settings = $this->store()->get($key);

            if ($settings === null) {
                $this->migrateLegacyKeys($livewire, $key);
                $settings = $this->store()->get($key);
            }

            return $this->normalize($settings);
        })();
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}
     */
    public function normalize(?array $settings): array
    {
        return [
            'order' => array_values((array) ($settings['order'] ?? [])),
            'hidden' => array_values((array) ($settings['hidden'] ?? [])),
            'entry_point' => $settings['entry_point'] ?? null,
            'action' => $settings['action'] ?? null,
            'start_tab' => $settings['start_tab'] ?? null,
        ];
    }

    public function forgetCached(string $key): void
    {
        unset($this->settingsCache[$key]);
    }

    public function fieldHidden(Field $component): bool
    {
        try {
            $livewire = $component->getLivewire();

            if (! $this->isEnabledFor($livewire)) {
                return false;
            }

            return in_array($component->getName(), $this->settingsFor($livewire)['hidden'], true);
        } catch (Throwable) {
            return false;
        }
    }

    public function fieldTabIndex(Field $component): ?int
    {
        try {
            $livewire = $component->getLivewire();

            if (! $this->isEnabledFor($livewire)) {
                return null;
            }

            $order = $this->settingsFor($livewire)['order'];

            if ($order === []) {
                return null;
            }

            $position = array_search($component->getName(), $order, true);

            return $position === false ? null : $position + 1;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The field's name for the usage tracker and the arrange overlay,
     * or null on pages where the plugin is off.
     */
    public function fieldLearnName(Field $component): ?string
    {
        try {
            if (! $this->isEnabledFor($component->getLivewire())) {
                return null;
            }

            return $component->getName();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether the field belongs to the user's chosen start tab — the
     * script activates that tab on load via the first marked field.
     */
    public function fieldStartsTab(Field $component): bool
    {
        try {
            $livewire = $component->getLivewire();

            if (! $this->isEnabledFor($livewire)) {
                return false;
            }

            $startTab = $this->settingsFor($livewire)['start_tab'];

            return $startTab !== null && $this->fieldGroupLabel($component) === $startTab;
        } catch (Throwable) {
            return false;
        }
    }

    public function fieldIsEntryPoint(Field $component): bool
    {
        try {
            $livewire = $component->getLivewire();

            if (! $this->isEnabledFor($livewire)) {
                return false;
            }

            $entryPoint = $this->settingsFor($livewire)['entry_point'];

            return $entryPoint !== null && $component->getName() === $entryPoint;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The save actions available on the given page, keyed by identifier.
     *
     * @return array<string, string>
     */
    public function actionOptions(object $livewire): array
    {
        // Save-and-back stays consistent with create-another: the
        // option only exists when its visible button does.
        $hasBack = $this->plugin()?->hasSaveAndBackButton() ?? false;

        if ($livewire instanceof EditRecord) {
            return [
                'save' => __('formsettings-for-filament::formsettings.actions.save'),
                'save_next' => __('formsettings-for-filament::formsettings.actions.save_next'),
                ...($hasBack
                    ? ['save_back' => __('formsettings-for-filament::formsettings.actions.save_back')]
                    : []),
            ];
        }

        if ($livewire instanceof CreateRecord) {
            return [
                'create' => __('formsettings-for-filament::formsettings.actions.create'),
                // Resources may disable create-another — respect that
                // instead of offering a bypass.
                ...($livewire->canCreateAnother()
                    ? ['create_next' => __('formsettings-for-filament::formsettings.actions.create_next')]
                    : []),
                ...($hasBack
                    ? ['create_back' => __('formsettings-for-filament::formsettings.actions.create_back')]
                    : []),
            ];
        }

        return [];
    }

    public function defaultAction(object $livewire): ?string
    {
        return array_key_first($this->actionOptions($livewire));
    }

    public function selectedAction(object $livewire): ?string
    {
        $options = $this->actionOptions($livewire);

        if ($options === []) {
            return null;
        }

        $action = $this->settingsFor($livewire)['action'];

        return array_key_exists((string) $action, $options) ? $action : $this->defaultAction($livewire);
    }

    public function selectedActionLabel(object $livewire): ?string
    {
        $action = $this->selectedAction($livewire);

        return $action === null ? null : ($this->actionOptions($livewire)[$action] ?? null);
    }

    public function customizationCount(object $livewire): int
    {
        $settings = $this->settingsFor($livewire);

        return count($settings['hidden'])
            + ($settings['order'] === [] ? 0 : 1)
            + ($settings['entry_point'] === null ? 0 : 1)
            + ($settings['start_tab'] === null ? 0 : 1)
            + (($settings['action'] !== null && $settings['action'] !== $this->defaultAction($livewire)) ? 1 : 0);
    }

    /**
     * Strip everything from a settings array that does not fit the form:
     * unknown fields, fields that may not be hidden, a hidden entry
     * point, an action the page does not offer, an unknown tab. Presets
     * can be stale or hand-written — they must never hide a required
     * field.
     *
     * @param  array<string, mixed>|null  $settings
     * @param  array<array{name: string, hideable: bool, group: string|null}>  $fields
     * @param  array<string, string>  $actionOptions
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}
     */
    public function sanitize(?array $settings, array $fields, array $actionOptions): array
    {
        $settings = $this->normalize($settings);

        $known = array_column($fields, 'name');
        $hideable = array_column(array_filter($fields, fn (array $field): bool => $field['hideable']), 'name');
        $groups = array_filter(array_unique(array_column($fields, 'group')));

        $hidden = array_values(array_intersect($settings['hidden'], $hideable));
        $entry = $settings['entry_point'];

        return [
            'order' => array_values(array_intersect($settings['order'], $known)),
            'hidden' => $hidden,
            'entry_point' => (in_array($entry, $known, true) && ! in_array($entry, $hidden, true)) ? $entry : null,
            'action' => array_key_exists((string) $settings['action'], $actionOptions) ? $settings['action'] : null,
            'start_tab' => in_array($settings['start_tab'], $groups, true) ? $settings['start_tab'] : null,
        ];
    }

    /**
     * Open this page instance with a preset for this visit only: every
     * setting comes from the preset, nothing is written to the store.
     */
    public function startVisit(object $livewire, string $name, string $source = 'own'): void
    {
        if ($name === '') {
            return;
        }

        $this->visits[$livewire] = [
            'name' => $name,
            'source' => $source === 'predefined' ? 'predefined' : 'own',
        ];
    }

    public function endVisit(object $livewire): void
    {
        unset($this->visits[$livewire]);
    }

    /**
     * The preset this page instance was opened with, if any.
     *
     * @return array{name: string, source: string}|null
     */
    public function visitFor(object $livewire): ?array
    {
        $visit = $this->visits[$livewire] ?? null;

        return $visit === null ? null : ['name' => $visit['name'], 'source' => $visit['source']];
    }

    /**
     * The settings of the visit's preset, sanitized against the form —
     * resolved on first use, when the form is there to check against. A
     * preset that no longer exists quietly ends the visit.
     *
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}|null
     */
    protected function visitSettings(object $livewire): ?array
    {
        $visit = $this->visits[$livewire] ?? null;

        if ($visit === null || $this->isResolvingVisit) {
            return null;
        }

        if (isset($visit['settings'])) {
            return $visit['settings'];
        }

        // Describing the form evaluates field closures that ask for the
        // settings again — answer those with the stored ones meanwhile.
        $this->isResolvingVisit = true;

        try {
            $preset = $visit['source'] === 'predefined'
                ? ($this->predefinedFor($livewire)[$visit['name']] ?? null)
                : $this->store()->getPreset($this->keyFor($livewire), $visit['name']);

            if (! is_array($preset) || ! $this->isEnabledFor($livewire)) {
                unset($this->visits[$livewire]);

                return null;
            }

            $settings = $this->sanitize($preset, $this->describeFields($livewire), $this->actionOptions($livewire));
        } catch (Throwable) {
            unset($this->visits[$livewire]);

            return null;
        } finally {
            $this->isResolvingVisit = false;
        }

        $this->visits[$livewire] = [...$visit, 'settings' => $settings];

        return $settings;
    }

    /**
     * Whether a preset's settings carry the quick flag for an operation.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function isQuick(?array $settings, string $operation): bool
    {
        return in_array($operation, self::QUICK_OPERATIONS, true)
            && (bool) ($settings["quick_{$operation}"] ?? false);
    }

    /**
     * Mark one of the user's presets as a quick create / quick edit
     * preset. The flag lives inside the preset's settings — no migration.
     */
    public function setPresetQuick(string $key, string $name, string $operation, bool $quick = true): void
    {
        if (! in_array($operation, self::QUICK_OPERATIONS, true)) {
            return;
        }

        $settings = $this->store()->getPreset($key, $name);

        if ($settings === null) {
            return;
        }

        $settings["quick_{$operation}"] = $quick;

        $this->store()->putPreset($key, $name, $settings);
    }

    /**
     * Names of the user's presets under a key that are marked quick for
     * the operation.
     *
     * @return array<string>
     */
    public function quickPresets(string $key, string $operation): array
    {
        $store = $this->store();

        return array_values(array_filter(
            $store->listPresets($key),
            fn (string $name): bool => $this->isQuick($store->getPreset($key, $name), $operation),
        ));
    }

    /**
     * The URL of a resource page with a preset applied for that visit.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function urlWithPreset(string $resource, string $operation, string $name, string $source = 'own', array $parameters = []): string
    {
        return $resource::getUrl($operation, [
            ...$parameters,
            self::PRESET_PARAM => $name,
            ...($source === 'predefined' ? [self::SOURCE_PARAM => 'predefined'] : []),
        ]);
    }

    /**
     * One Filament action per quick preset of a resource's create or edit
     * form — each opens that page with the preset applied for the visit.
     * Plain actions, so they fit any action group (blemli's
     * splitbutton-for-filament lists them on its own, but nothing here
     * knows about it). Edit actions need a record and hide without one.
     *
     * @param  class-string  $resource
     * @return array<Action>
     */
    public function quickActions(string $resource, string $operation = 'create'): array
    {
        $plugin = $this->plugin();

        if (! $plugin?->isAuthorized() || ! $plugin->hasPresets() || ! in_array($operation, self::QUICK_OPERATIONS, true)) {
            return [];
        }

        try {
            $registration = $resource::getPages()[$operation] ?? null;
            $page = is_object($registration) && method_exists($registration, 'getPage')
                ? $registration->getPage()
                : (is_string($registration) ? $registration : null);
        } catch (Throwable) {
            return [];
        }

        if (! is_string($page) || ! $this->isEnabledForClass($page)) {
            return [];
        }

        $presets = [];

        foreach ($this->predefinedFor($page) as $name => $settings) {
            if ($this->isQuick($settings, $operation)) {
                $presets[(string) $name] = 'predefined';
            }
        }

        // A user's own preset wins over a predefined one of the same name.
        foreach ($this->quickPresets($this->keyFor($page), $operation) as $name) {
            $presets[$name] = 'own';
        }

        $actions = [];

        foreach ($presets as $name => $source) {
            $name = (string) $name;

            $actions[] = Action::make("formsettings-{$operation}-" . substr(md5("{$source}|{$name}"), 0, 8))
                ->label($name)
                ->icon('heroicon-o-bolt')
                ->visible(fn (?Model $record = null): bool => $operation !== 'edit' || $record !== null)
                ->url(fn (?Model $record = null): string => $this->urlWithPreset(
                    $resource,
                    $operation,
                    $name,
                    $source,
                    $operation === 'edit' ? ['record' => $record] : [],
                ));
        }

        return $actions;
    }

    /**
     * isEnabledFor() for code that only has the page's class: the gear
     * would show on that page for the current user.
     *
     * @param  class-string  $page
     */
    public function isEnabledForClass(string $page): bool
    {
        $plugin = $this->plugin();

        if (! $plugin?->isAuthorized() || $plugin->isExceptedClass($page)) {
            return false;
        }

        if (in_array(HasFormSettings::class, class_uses_recursive($page), true)) {
            return true;
        }

        return $plugin->isGlobal() && $this->isFormPage($page);
    }

    /**
     * Describe the fields of the page's form in natural schema order.
     * Fields inside a tab (or wizard step) carry its label as `group`.
     *
     * @return array<array{name: string, label: string, icon: string, required: bool, hideable: bool, locked: bool, group: string|null}>
     */
    public function describeFields(object $livewire): array
    {
        try {
            $schema = $livewire->getSchema('form');
        } catch (Throwable) {
            return [];
        }

        if (! $schema) {
            return [];
        }

        $fields = [];

        foreach ($schema->getFlatFields(withHidden: true) as $field) {
            if ($field instanceof Hidden) {
                continue;
            }

            $name = $field->getName();

            if (isset($fields[$name])) {
                continue;
            }

            try {
                $required = $field->isRequired();
            } catch (Throwable) {
                $required = true;
            }

            $locked = $this->isFieldLocked($field);

            $fields[$name] = [
                'name' => $name,
                'label' => (string) $field->getLabel(),
                'icon' => FieldIcons::for($field),
                'required' => $required,
                'hideable' => ! $required && ! $locked,
                'locked' => $locked,
                'group' => $this->fieldGroupLabel($field),
            ];
        }

        return array_values($fields);
    }

    /**
     * The label of the tab or wizard step enclosing the field, if any.
     */
    protected function fieldGroupLabel(Field $field): ?string
    {
        try {
            $plugin = $this->plugin();
            $component = $field->getContainer()->getParentComponent();

            while ($component !== null) {
                if ($component instanceof Tab || $component instanceof Step) {
                    $label = (string) $component->getLabel();

                    if ($label !== '' && ! ($plugin?->isGroupIgnored($component, $label) ?? false)) {
                        return $label;
                    }
                }

                $component = $component->getContainer()->getParentComponent();
            }
        } catch (Throwable) {
        }

        return null;
    }
}
