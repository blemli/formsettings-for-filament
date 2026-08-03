<?php

namespace Blemli\FormSettings\Livewire;

use Blemli\FormSettings\FormSettings;
use Blemli\FormSettings\Storage\SettingsStore;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SettingsPanel extends Component
{
    public string $formKey;

    /**
     * Field metadata in natural schema order.
     *
     * @var array<array{name: string, label: string, icon: string, required: bool, hideable: bool, locked?: bool, group?: string|null}>
     */
    public array $fields = [];

    /**
     * Developer-shipped presets from FormSettingsPlugin::predefined(),
     * keyed by name. Applied on request, never automatically.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $predefinedPresets = [];

    /** @var array<string, string> */
    public array $actionOptions = [];

    public bool $presetsEnabled = false;

    public bool $publishingEnabled = false;

    public bool $learningEnabled = false;

    public int $learnAfter = 5;

    /**
     * Whether unused fields may be suggested for hiding — off on Edit
     * pages, where users legitimately touch only the field they came
     * to change; the entry-point suggestion stays on everywhere.
     */
    public bool $suggestHiding = true;

    /** @var array<string> */
    public array $order = [];

    /** @var array<string> */
    public array $hidden = [];

    public ?string $entryPoint = null;

    public ?string $action = null;

    /**
     * Tab (group label) the form should open on — activated by the
     * script on page load unless an entry point takes precedence.
     */
    public ?string $startTab = null;

    /** @var array<string> */
    public array $presets = [];

    /** @var array<string> */
    public array $publishedPresets = [];

    /**
     * Own presets whose settings differ from the defaults — the only
     * ones worth publishing; default-config presets are noise.
     *
     * @var array<string>
     */
    public array $publishablePresets = [];

    /**
     * Preset name awaiting delete confirmation — deleting a published
     * preset removes it for everyone, so it takes a second click.
     */
    public ?string $confirmingDelete = null;

    /** @var array<int, array{user: string, name: string, owner: string|null}> */
    public array $sharedPresets = [];

    /** @var array<int, array{user: string, name: string, owner: string|null}> */
    public array $hiddenSharedPresets = [];

    public bool $revealHiddenShared = false;

    public string $newPresetName = '';

    /**
     * Preset name awaiting overwrite confirmation — saving an existing
     * name warns first; a second save with the same name overwrites.
     */
    public ?string $confirmingOverwrite = null;

    public function mount(): void
    {
        $manager = app(FormSettings::class);

        abort_unless($manager->plugin()?->isAuthorized() ?? true, 403);

        $settings = $manager->normalize($this->store()->get($this->formKey));

        $this->order = $settings['order'];
        $this->hidden = $settings['hidden'];
        $this->entryPoint = $settings['entry_point'];
        $this->action = $settings['action'] ?? array_key_first($this->actionOptions);
        $this->startTab = $settings['start_tab'];

        $this->loadPresets();
    }

    public function setStartTab(?string $label): void
    {
        $known = array_filter(array_unique(array_column($this->fields, 'group')));

        if ($label !== null && ! in_array($label, $known, true)) {
            return;
        }

        $this->startTab = ($this->startTab === $label) ? null : $label;

        $this->persistSettings();
    }

    /**
     * Apply a new sequence for a subset of fields (one sortable list —
     * the whole form, or a single tab group) while keeping every other
     * field in its current position.
     *
     * @param  array<string>  $names
     */
    public function reorder(array $names): void
    {
        $known = array_column($this->fields, 'name');
        $locked = array_column(array_filter($this->fields, fn (array $field): bool => $field['locked'] ?? false), 'name');
        $names = array_values(array_diff(array_intersect($names, $known), $locked));

        $current = array_column($this->getSortedFieldsProperty(), 'name');
        $moved = array_flip($names);
        $sequence = 0;
        $order = [];

        foreach ($current as $name) {
            $order[] = isset($moved[$name]) ? $names[$sequence++] : $name;
        }

        $this->order = ($order === $known) ? [] : $order;

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
        $this->startTab = null;

        $this->store()->forget($this->formKey);
        $this->dispatch('formsettings-updated');
    }

    public function updatedNewPresetName(): void
    {
        $this->confirmingOverwrite = null;

        $this->validateOnly('newPresetName');
    }

    public function savePreset(): void
    {
        $this->validate();

        $name = trim($this->newPresetName);

        if ($name === '') {
            return;
        }

        if ($this->settingsSignature($this->currentSettings()) === $this->settingsSignature(null)) {
            $this->addError('newPresetName', __('formsettings-for-filament::formsettings.preset_default_save'));

            return;
        }

        if (($existing = $this->presetWithSettings($this->currentSettings())) !== null) {
            $this->addError('newPresetName', __('formsettings-for-filament::formsettings.preset_duplicate', ['name' => $existing]));

            return;
        }

        if ($this->confirmingOverwrite !== $name && in_array($name, $this->presets, true)) {
            $this->confirmingOverwrite = $name;

            $this->addError('newPresetName', __('formsettings-for-filament::formsettings.preset_overwrite_confirm', ['name' => $name]));

            return;
        }

        $this->confirmingOverwrite = null;
        $this->resetErrorBag('newPresetName');

        $this->store()->putPreset($this->formKey, $name, $this->currentSettings());
        $this->newPresetName = '';
        $this->loadPresets();
    }

    public function applyPreset(string $name): void
    {
        $this->applySettings($this->store()->getPreset($this->formKey, $name));
    }

    public function applyPredefined(string $name): void
    {
        $this->applySettings($this->predefinedPresets[$name] ?? null);
    }

    /**
     * The arrange overlay finished a chain — the subset merge keeps
     * every unclicked field in place.
     *
     * @param  array<int, mixed>  $names
     */
    #[On('formsettings-arrange')]
    public function arrangeFromOverlay(array $names = []): void
    {
        $this->reorder(array_values(array_filter($names, 'is_string')));
    }

    #[On('formsettings-overlay-entry')]
    public function overlayEntry(string $name = ''): void
    {
        if ($name !== '') {
            $this->setEntryPoint($name);
        }
    }

    #[On('formsettings-overlay-hide')]
    public function overlayHide(string $name = ''): void
    {
        if ($name !== '') {
            $this->toggleHidden($name);
        }
    }

    public function deletePreset(string $name): void
    {
        if (in_array($name, $this->publishedPresets, true) && $this->confirmingDelete !== $name) {
            $this->confirmingDelete = $name;

            return;
        }

        $this->confirmingDelete = null;
        $this->store()->deletePreset($this->formKey, $name);
        $this->loadPresets();
    }

    public function publishPreset(string $name): void
    {
        if (! $this->publishingEnabled || ! in_array($name, $this->presets, true)) {
            return;
        }

        if (! in_array($name, $this->publishablePresets, true)) {
            $this->addError('newPresetName', __('formsettings-for-filament::formsettings.preset_default'));

            return;
        }

        if (($twin = $this->sharedPresetWithSettings($this->store()->getPreset($this->formKey, $name))) !== null) {
            $this->addError('newPresetName', __('formsettings-for-filament::formsettings.preset_duplicate_shared', ['name' => $twin]));

            return;
        }

        $rules = $this->publishRules();

        if ($rules !== []) {
            $validator = validator(['newPresetName' => $name], ['newPresetName' => $rules], $this->messages());

            if ($validator->fails()) {
                $this->addError('newPresetName', $validator->errors()->first('newPresetName'));

                return;
            }
        }

        $this->store()->setPresetPublished($this->formKey, $name, true);
        $this->loadPresets();
    }

    public function unpublishPreset(string $name): void
    {
        $this->store()->setPresetPublished($this->formKey, $name, false);
        $this->loadPresets();
    }

    public function applySharedPreset(string $user, string $name): void
    {
        if (! $this->publishingEnabled) {
            return;
        }

        $this->applySettings($this->store()->getSharedPreset($this->formKey, $user, $name));
    }

    public function hideSharedPreset(string $user, string $name): void
    {
        $ids = $this->store()->hiddenSharedPresets($this->formKey);
        $ids[] = "{$user}|{$name}";

        $this->store()->putHiddenSharedPresets($this->formKey, array_values(array_unique($ids)));
        $this->loadPresets();
    }

    public function restoreSharedPreset(string $user, string $name): void
    {
        $ids = array_diff($this->store()->hiddenSharedPresets($this->formKey), ["{$user}|{$name}"]);

        $this->store()->putHiddenSharedPresets($this->formKey, array_values($ids));
        $this->loadPresets();

        if ($this->hiddenSharedPresets === []) {
            $this->revealHiddenShared = false;
        }
    }

    public function toggleRevealHiddenShared(): void
    {
        $this->revealHiddenShared = ! $this->revealHiddenShared;
    }

    /**
     * Store completed form runs queued by the usage tracker. A run is
     * {key, touched: [field names], first: field name|null} — names
     * only, never values. Runs are kept in a small ring buffer.
     *
     * @param  array<int, mixed>  $runs
     */
    #[On('formsettings-usage')]
    public function recordUsage(array $runs = []): void
    {
        if (! $this->learningEnabled) {
            return;
        }

        foreach (array_slice($runs, 0, 20) as $run) {
            $key = is_array($run) ? ($run['key'] ?? null) : null;

            if (! is_string($key) || $key === '' || strlen($key) > 255) {
                continue;
            }

            $touched = array_values(array_filter(
                array_slice((array) ($run['touched'] ?? []), 0, 150),
                fn ($name): bool => is_string($name) && $name !== '' && strlen($name) <= 255,
            ));

            if ($touched === []) {
                continue;
            }

            $first = $run['first'] ?? null;
            $first = (is_string($first) && strlen($first) <= 255) ? $first : null;

            $action = (($run['action'] ?? null) === 'back') ? 'back' : null;

            $usage = $this->store()->getUsage($key);
            $usage['runs'] = array_slice(
                [...array_values((array) ($usage['runs'] ?? [])), ['touched' => $touched, 'first' => $first, 'action' => $action]],
                -max(10, $this->learnAfter + 2),
            );

            $this->store()->putUsage($key, $usage);
        }
    }

    /**
     * Pull-only suggestions computed from the usage ring buffer: shown
     * inside the panel when the user opens it, never pushed at them.
     *
     * @return array<int, array{type: string, hash: string, field?: string, label?: string, action?: string, name?: string, user?: string, tab?: string, fields?: array<string>, labels?: array<string>}>
     */
    public function getSuggestionsProperty(): array
    {
        if (! $this->learningEnabled) {
            return [];
        }

        $usage = $this->store()->getUsage($this->formKey);
        $allRuns = array_values((array) ($usage['runs'] ?? []));

        if (count($allRuns) < $this->learnAfter) {
            return [];
        }

        // Graceful matching: require learnAfter consistent runs among
        // the last learnAfter + 2, so an outlier or two is forgiven
        // (default: 5 of the last 7).
        $runs = array_slice($allRuns, -($this->learnAfter + 2));
        $required = $this->learnAfter;

        $dismissed = array_values((array) ($usage['dismissed'] ?? []));
        $suggestions = [];

        // The habitual first field: unambiguous mode over the window.
        $firstCounts = array_count_values(array_filter(
            array_map(fn (array $run) => $run['first'] ?? null, $runs),
            fn ($first): bool => is_string($first) && $first !== '',
        ));
        arsort($firstCounts);
        $firstMode = array_key_first($firstCounts);
        $firstRunnerUp = array_values($firstCounts)[1] ?? 0;

        if ($firstMode === null || $firstCounts[$firstMode] < $required || $firstRunnerUp >= $required) {
            $firstMode = null;
        }

        $untouchedCount = function (string $name) use ($runs): int {
            return count(array_filter($runs, fn (array $run): bool => ! in_array($name, (array) ($run['touched'] ?? []), true)));
        };

        // A preset that matches the learned pattern exactly supersedes
        // the piecemeal entry-point and hide suggestions.
        $presetMatch = null;

        if ($this->suggestHiding) {
            $unusedAll = collect($this->fields)
                ->filter(fn (array $field): bool => $field['hideable'] && $untouchedCount($field['name']) >= $required)
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            if ($unusedAll !== [] || $firstMode !== null) {
                $currentSignature = $this->settingsSignature($this->currentSettings());

                $matchesUsage = function (?array $settings) use ($unusedAll, $firstMode, $currentSignature): bool {
                    if ($settings === null || $this->settingsSignature($settings) === $currentSignature) {
                        return false;
                    }

                    $settings = app(FormSettings::class)->normalize($settings);
                    $hidden = $settings['hidden'];
                    sort($hidden);

                    return $hidden === $unusedAll && $settings['entry_point'] === $firstMode;
                };

                foreach ($this->presets as $preset) {
                    if ($matchesUsage($this->store()->getPreset($this->formKey, $preset))) {
                        $hash = "preset|own|{$preset}";

                        if (! in_array($hash, $dismissed, true)) {
                            $presetMatch = ['type' => 'preset', 'hash' => $hash, 'name' => $preset, 'label' => $preset];
                        }

                        break;
                    }
                }

                if ($presetMatch === null) {
                    foreach ($this->sharedPresets as $shared) {
                        if ($matchesUsage($this->store()->getSharedPreset($this->formKey, $shared['user'], $shared['name']))) {
                            $hash = "preset|{$shared['user']}|{$shared['name']}";

                            if (! in_array($hash, $dismissed, true)) {
                                $presetMatch = ['type' => 'preset', 'hash' => $hash, 'name' => $shared['name'], 'user' => $shared['user'], 'label' => $shared['name']];
                            }

                            break;
                        }
                    }
                }
            }
        }

        if ($presetMatch !== null) {
            $suggestions[] = $presetMatch;
        }

        if ($presetMatch === null && $firstMode !== null && $firstMode !== $this->entryPoint) {
            $field = collect($this->fields)->firstWhere('name', $firstMode);
            $hash = "entry|{$firstMode}";

            if ($field && ! in_array($firstMode, $this->hidden, true) && ! in_array($hash, $dismissed, true)) {
                $suggestions[] = ['type' => 'entry', 'hash' => $hash, 'field' => $firstMode, 'label' => $field['label']];
            }
        }

        // No consistent first field, but a consistent first tab: offer
        // to open the form on that tab. A consistent field already
        // activates its own tab via the entry point.
        if ($firstMode === null) {
            $groupOf = array_column($this->fields, 'group', 'name');

            $groupCounts = array_count_values(array_filter(
                array_map(fn (array $run) => $groupOf[$run['first'] ?? ''] ?? null, $runs),
                fn ($group): bool => is_string($group) && $group !== '',
            ));
            arsort($groupCounts);
            $groupMode = array_key_first($groupCounts);
            $groupRunnerUp = array_values($groupCounts)[1] ?? 0;
            $groupHash = "start_tab|{$groupMode}";

            if ($groupMode !== null
                && $groupCounts[$groupMode] >= $required
                && $groupRunnerUp < $required
                && $groupMode !== $this->startTab
                && ! in_array($groupHash, $dismissed, true)
            ) {
                $suggestions[] = ['type' => 'start_tab', 'hash' => $groupHash, 'tab' => $groupMode];
            }
        }

        $backKey = collect(array_keys($this->actionOptions))->first(fn (string $key): bool => str_ends_with($key, '_back'));
        $backCount = count(array_filter($runs, fn (array $run): bool => ($run['action'] ?? null) === 'back'));
        $backHash = 'action|back';

        if ($backKey !== null
            && $backCount >= $required
            && $this->action !== $backKey
            && ! in_array($backHash, $dismissed, true)
        ) {
            $suggestions[] = ['type' => 'action', 'hash' => $backHash, 'action' => $backKey, 'label' => $this->actionOptions[$backKey]];
        }

        if ($presetMatch === null && $this->suggestHiding) {
            $unused = collect($this->fields)->filter(fn (array $field): bool => $field['hideable']
                && ! in_array($field['name'], $this->hidden, true)
                && $untouchedCount($field['name']) >= $required);

            $hash = 'hide|' . $unused->pluck('name')->sort()->implode(',');

            if ($unused->isNotEmpty() && ! in_array($hash, $dismissed, true)) {
                $suggestions[] = [
                    'type' => 'hide',
                    'hash' => $hash,
                    'fields' => $unused->pluck('name')->values()->all(),
                    'labels' => $unused->pluck('label')->values()->all(),
                ];
            }
        }

        return $suggestions;
    }

    public function applySuggestion(string $hash): void
    {
        foreach ($this->getSuggestionsProperty() as $suggestion) {
            if ($suggestion['hash'] !== $hash) {
                continue;
            }

            if ($suggestion['type'] === 'entry') {
                $this->entryPoint = $suggestion['field'];
                $this->persistSettings();
                $this->dispatch('formsettings-entry-changed');
            }

            if ($suggestion['type'] === 'action') {
                $this->action = $suggestion['action'];
                $this->persistSettings();
            }

            if ($suggestion['type'] === 'preset') {
                isset($suggestion['user'])
                    ? $this->applySharedPreset($suggestion['user'], $suggestion['name'])
                    : $this->applyPreset($suggestion['name']);
            }

            if ($suggestion['type'] === 'start_tab') {
                $this->startTab = $suggestion['tab'];
                $this->persistSettings();
            }

            if ($suggestion['type'] === 'hide') {
                $this->hidden = array_values(array_unique([...$this->hidden, ...$suggestion['fields']]));

                if (in_array($this->entryPoint, $suggestion['fields'], true)) {
                    $this->entryPoint = null;
                }

                $this->persistSettings();
            }
        }
    }

    public function dismissSuggestion(string $hash): void
    {
        $usage = $this->store()->getUsage($this->formKey);
        $usage['dismissed'] = array_values(array_unique([...array_values((array) ($usage['dismissed'] ?? [])), $hash]));

        $this->store()->putUsage($this->formKey, $usage);
    }

    /**
     * The fields bundled by their tab (or wizard step) label, in natural
     * group order — one unlabeled group when the form has no tabs.
     *
     * @return array<array{label: string|null, fields: array<array{name: string, label: string, icon: string, required: bool, hideable: bool, group?: string|null}>}>
     */
    public function getGroupedFieldsProperty(): array
    {
        $groups = [];

        foreach ($this->fields as $field) {
            $key = $field['group'] ?? '';

            $groups[$key] ??= ['label' => ($field['group'] ?? null) ?: null, 'fields' => []];
        }

        foreach ($this->getSortedFieldsProperty() as $field) {
            $groups[$field['group'] ?? '']['fields'][] = $field;
        }

        return array_values($groups);
    }

    /**
     * @return array<array{name: string, label: string, icon: string, required: bool, hideable: bool, group?: string|null}>
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
            && $settings['start_tab'] === null
            && ($settings['action'] === null || $settings['action'] === array_key_first($this->actionOptions));

        if ($isDefault) {
            $this->store()->forget($this->formKey);
        } else {
            $this->store()->put($this->formKey, $settings);
        }

        $this->dispatch('formsettings-updated');
    }

    /**
     * @return array{order: array<string>, hidden: array<string>, entry_point: string|null, action: string|null, start_tab: string|null}
     */
    protected function currentSettings(): array
    {
        return [
            'order' => $this->order,
            'hidden' => $this->hidden,
            'entry_point' => $this->entryPoint,
            'action' => $this->action,
            'start_tab' => $this->startTab,
        ];
    }

    /**
     * Apply stored settings, sanitized against the current form —
     * presets can be stale or come from other users, so they must not
     * hide required fields or reference unknown ones.
     *
     * @param  array<string, mixed>|null  $settings
     */
    protected function applySettings(?array $settings): void
    {
        if ($settings === null) {
            return;
        }

        $settings = app(FormSettings::class)->normalize($settings);

        $known = array_column($this->fields, 'name');
        $hideable = array_column(array_filter($this->fields, fn (array $field): bool => $field['hideable']), 'name');

        $this->order = array_values(array_intersect($settings['order'], $known));
        $this->hidden = array_values(array_intersect($settings['hidden'], $hideable));

        $entry = $settings['entry_point'];
        $this->entryPoint = (in_array($entry, $known, true) && ! in_array($entry, $this->hidden, true)) ? $entry : null;

        $action = $settings['action'];
        $this->action = array_key_exists((string) $action, $this->actionOptions) ? $action : array_key_first($this->actionOptions);

        $knownGroups = array_filter(array_unique(array_column($this->fields, 'group')));
        $this->startTab = in_array($settings['start_tab'], $knownGroups, true) ? $settings['start_tab'] : null;

        $this->persistSettings();
    }

    /**
     * Canonical fingerprint of a settings array, for spotting presets
     * that are exactly the same configuration.
     *
     * @param  array<string, mixed>|null  $settings
     */
    protected function settingsSignature(?array $settings): string
    {
        $settings = app(FormSettings::class)->normalize((array) $settings);

        return json_encode([
            $settings['order'],
            $settings['hidden'],
            $settings['entry_point'],
            $settings['action'] ?? array_key_first($this->actionOptions),
            $settings['start_tab'],
        ]) ?: '';
    }

    /**
     * The name of an own preset holding exactly these settings, if any.
     *
     * @param  array<string, mixed>|null  $settings
     */
    protected function presetWithSettings(?array $settings): ?string
    {
        $signature = $this->settingsSignature($settings);

        foreach ($this->presets as $preset) {
            if ($this->settingsSignature($this->store()->getPreset($this->formKey, $preset)) === $signature) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * The name of a shared preset holding exactly these settings, if any.
     *
     * @param  array<string, mixed>|null  $settings
     */
    protected function sharedPresetWithSettings(?array $settings): ?string
    {
        $signature = $this->settingsSignature($settings);

        foreach ([...$this->sharedPresets, ...$this->hiddenSharedPresets] as $shared) {
            $twin = $this->store()->getSharedPreset($this->formKey, $shared['user'], $shared['name']);

            if ($twin !== null && $this->settingsSignature($twin) === $signature) {
                return $shared['name'];
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'newPresetName' => ['string', 'max:25', ...($this->publishingEnabled ? $this->publishRules() : [])],
        ];
    }

    /**
     * Custom preset-name rules from FormSettingsPlugin::publish([...]),
     * e.g. a Blasp profanity check. Resolved at runtime because rule
     * objects are not serializable as Livewire props.
     *
     * @return array<mixed>
     */
    protected function publishRules(): array
    {
        return app(FormSettings::class)->plugin()?->getPublishRules() ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'newPresetName.max' => __('formsettings-for-filament::formsettings.preset_name_too_long'),
        ];
    }

    protected function loadPresets(): void
    {
        $this->presets = $this->presetsEnabled ? $this->store()->listPresets($this->formKey) : [];
        $this->publishedPresets = [];
        $this->publishablePresets = [];
        $this->sharedPresets = [];
        $this->hiddenSharedPresets = [];

        if (! $this->presetsEnabled || ! $this->publishingEnabled) {
            return;
        }

        $store = $this->store();

        $this->publishedPresets = $store->publishedPresetNames($this->formKey);

        $defaultSignature = $this->settingsSignature(null);

        $this->publishablePresets = array_values(array_filter(
            $this->presets,
            fn (string $preset): bool => $this->settingsSignature($store->getPreset($this->formKey, $preset)) !== $defaultSignature,
        ));

        $hidden = $store->hiddenSharedPresets($this->formKey);

        foreach ($store->sharedPresets($this->formKey) as $preset) {
            if (in_array("{$preset['user']}|{$preset['name']}", $hidden, true)) {
                $this->hiddenSharedPresets[] = $preset;
            } else {
                $this->sharedPresets[] = $preset;
            }
        }
    }

    protected function store(): SettingsStore
    {
        return app(FormSettings::class)->store();
    }
}
