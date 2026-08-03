<?php

namespace Blemli\FormSettings;

use Blemli\FormSettings\Concerns\HasFormSettings;
use Blemli\FormSettings\Storage\DatabaseStore;
use Blemli\FormSettings\Storage\SessionStore;
use Blemli\FormSettings\Storage\SettingsStore;
use Blemli\FormSettings\Support\FieldIcons;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard\Step;
use Throwable;

class FormSettings
{
    /**
     * @var array<string, array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}>
     */
    protected array $settingsCache = [];

    /**
     * Developer-shipped presets that apply to the given page, from
     * FormSettingsPlugin::predefined() — keyed by preset name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function predefinedFor(object $livewire): array
    {
        $presets = [];

        foreach ($this->plugin()?->getPredefined() ?? [] as $class => $named) {
            $matches = $livewire instanceof $class;

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

    public function keyFor(object $livewire): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'default';
        $subject = $livewire::class;

        if (($this->plugin()?->isPerResource() ?? false)
            && ($livewire instanceof CreateRecord || $livewire instanceof EditRecord)) {
            try {
                $subject = $livewire::getResource();
            } catch (Throwable) {
            }
        }

        return $panelId . '::' . str_replace('\\', '.', $subject);
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
     * The field's name for the learn() usage tracker, or null when
     * learning is off — keeps the DOM attribute out of ordinary pages.
     */
    public function fieldLearnName(Field $component): ?string
    {
        try {
            if (! $this->isEnabledFor($component->getLivewire())) {
                return null;
            }

            if (! ($this->plugin()?->hasLearning() ?? false)) {
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
     * Describe the fields of the page's form in natural schema order.
     * Fields inside a tab (or wizard step) carry its label as `group`.
     *
     * @return array<array{name: string, label: string, icon: string, required: bool, hideable: bool, group: string|null}>
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

            $fields[$name] = [
                'name' => $name,
                'label' => (string) $field->getLabel(),
                'icon' => FieldIcons::for($field),
                'required' => $required,
                'hideable' => ! $required,
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
