<?php

namespace Blemli\FormSettings\Livewire;

use Blemli\FormSettings\FormSettings;
use Blemli\FormSettings\Storage\SettingsStore;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SettingsPanel extends Component
{
    public string $formKey;

    /**
     * Field metadata in natural schema order.
     *
     * @var array<array{name: string, label: string, icon: string, required: bool, hideable: bool}>
     */
    public array $fields = [];

    /** @var array<string, string> */
    public array $actionOptions = [];

    public bool $presetsEnabled = false;

    /** @var array<string> */
    public array $order = [];

    /** @var array<string> */
    public array $hidden = [];

    public ?string $entryPoint = null;

    public ?string $action = null;

    /** @var array<string> */
    public array $presets = [];

    public string $newPresetName = '';

    public function mount(): void
    {
        $manager = app(FormSettings::class);
        $settings = $manager->normalize($this->store()->get($this->formKey));

        $this->order = $settings['order'];
        $this->hidden = $settings['hidden'];
        $this->entryPoint = $settings['entry_point'];
        $this->action = $settings['action'] ?? array_key_first($this->actionOptions);

        $this->loadPresets();
    }

    /**
     * @param  array<string>  $names
     */
    public function reorder(array $names): void
    {
        $known = array_column($this->fields, 'name');

        $this->order = array_values(array_intersect($names, $known));

        $this->persistSettings();
    }

    public function toggleHidden(string $name): void
    {
        $field = collect($this->fields)->firstWhere('name', $name);

        if (! ($field['hideable'] ?? false)) {
            return;
        }

        if (in_array($name, $this->hidden, true)) {
            $this->hidden = array_values(array_diff($this->hidden, [$name]));
        } else {
            $this->hidden[] = $name;

            if ($this->entryPoint === $name) {
                $this->entryPoint = null;
            }
        }

        $this->persistSettings();
    }

    public function setEntryPoint(string $name): void
    {
        $this->entryPoint = ($this->entryPoint === $name) ? null : $name;

        $this->persistSettings();

        if ($this->entryPoint !== null) {
            $this->dispatch('formsettings-entry-changed');
        }
    }

    public function updatedAction(): void
    {
        if (! array_key_exists((string) $this->action, $this->actionOptions)) {
            $this->action = array_key_first($this->actionOptions);
        }

        $this->persistSettings();
    }

    public function resetSettings(): void
    {
        $this->order = [];
        $this->hidden = [];
        $this->entryPoint = null;
        $this->action = array_key_first($this->actionOptions);

        $this->store()->forget($this->formKey);
        $this->dispatch('formsettings-updated');
    }

    public function savePreset(): void
    {
        $name = trim($this->newPresetName);

        if ($name === '' || mb_strlen($name) > 50) {
            return;
        }

        $this->store()->putPreset($this->formKey, $name, $this->currentSettings());
        $this->newPresetName = '';
        $this->loadPresets();
    }

    public function applyPreset(string $name): void
    {
        $settings = $this->store()->getPreset($this->formKey, $name);

        if ($settings === null) {
            return;
        }

        $settings = app(FormSettings::class)->normalize($settings);

        $this->order = $settings['order'];
        $this->hidden = $settings['hidden'];
        $this->entryPoint = $settings['entry_point'];
        $this->action = $settings['action'] ?? array_key_first($this->actionOptions);

        $this->persistSettings();
    }

    public function deletePreset(string $name): void
    {
        $this->store()->deletePreset($this->formKey, $name);
        $this->loadPresets();
    }

    /**
     * @return array<array{name: string, label: string, icon: string, required: bool, hideable: bool}>
     */
    public function getSortedFieldsProperty(): array
    {
        if ($this->order === []) {
            return $this->fields;
        }

        $index = array_flip($this->order);
        $fields = $this->fields;

        usort($fields, fn (array $a, array $b): int => ($index[$a['name']] ?? PHP_INT_MAX) <=> ($index[$b['name']] ?? PHP_INT_MAX));

        return $fields;
    }

    public function render(): View
    {
        return view('formsettings-for-filament::livewire.settings-panel');
    }

    protected function persistSettings(): void
    {
        $settings = $this->currentSettings();

        $isDefault = $settings['order'] === []
            && $settings['hidden'] === []
            && $settings['entry_point'] === null
            && ($settings['action'] === null || $settings['action'] === array_key_first($this->actionOptions));

        if ($isDefault) {
            $this->store()->forget($this->formKey);
        } else {
            $this->store()->put($this->formKey, $settings);
        }

        $this->dispatch('formsettings-updated');
    }

    /**
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null}
     */
    protected function currentSettings(): array
    {
        return [
            'order' => $this->order,
            'hidden' => $this->hidden,
            'entry_point' => $this->entryPoint,
            'action' => $this->action,
        ];
    }

    protected function loadPresets(): void
    {
        $this->presets = $this->presetsEnabled ? $this->store()->listPresets($this->formKey) : [];
    }

    protected function store(): SettingsStore
    {
        return app(FormSettings::class)->store();
    }
}
