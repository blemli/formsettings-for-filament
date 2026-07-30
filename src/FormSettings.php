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
use Throwable;

class FormSettings
{
    /**
     * @var array<string, array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null}>
     */
    protected array $settingsCache = [];

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

        if (! $plugin) {
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

        return $panelId . '::' . str_replace('\\', '.', $livewire::class);
    }

    /**
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null}
     */
    public function settingsFor(object $livewire): array
    {
        $key = $this->keyFor($livewire);

        return $this->settingsCache[$key] ??= $this->normalize($this->store()->get($key));
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null}
     */
    public function normalize(?array $settings): array
    {
        return [
            'order' => array_values((array) ($settings['order'] ?? [])),
            'hidden' => array_values((array) ($settings['hidden'] ?? [])),
            'entry_point' => $settings['entry_point'] ?? null,
            'action' => $settings['action'] ?? null,
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
        if ($livewire instanceof EditRecord) {
            return [
                'save' => __('formsettings-for-filament::formsettings.actions.save'),
                'save_next' => __('formsettings-for-filament::formsettings.actions.save_next'),
                'save_back' => __('formsettings-for-filament::formsettings.actions.save_back'),
            ];
        }

        if ($livewire instanceof CreateRecord) {
            return [
                'create' => __('formsettings-for-filament::formsettings.actions.create'),
                'create_next' => __('formsettings-for-filament::formsettings.actions.create_next'),
                'create_back' => __('formsettings-for-filament::formsettings.actions.create_back'),
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
            + (($settings['action'] !== null && $settings['action'] !== $this->defaultAction($livewire)) ? 1 : 0);
    }

    /**
     * Describe the fields of the page's form in natural schema order.
     *
     * @return array<array{name: string, label: string, icon: string, required: bool, hideable: bool}>
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
            ];
        }

        return array_values($fields);
    }
}
