@php
    use Filament\Support\Enums\IconSize;

    use function Filament\Support\generate_icon_html;
@endphp

<div class="fi-ta-col-manager formsettings-panel">
    <div class="fi-ta-col-manager-header">
        <h3 class="fi-ta-col-manager-heading">
            {{ __('formsettings-for-filament::formsettings.heading') }}
        </h3>

        <div>
            <x-filament::link
                color="danger"
                tag="button"
                wire:click="resetSettings"
            >
                {{ __('formsettings-for-filament::formsettings.reset') }}
            </x-filament::link>
        </div>
    </div>

    @if ($learningEnabled && $this->suggestions !== [])
        <div class="fi-ta-col-manager-items">
            @foreach ($this->suggestions as $suggestion)
                <div
                    class="fi-ta-col-manager-item formsettings-suggestion"
                    wire:key="formsettings-suggestion-{{ md5($suggestion['hash']) }}"
                >
                    <span class="formsettings-suggestion-icon">
                        {{ generate_icon_html('heroicon-o-light-bulb', size: IconSize::Small) }}
                    </span>

                    <span class="formsettings-suggestion-text">
                        @if ($suggestion['type'] === 'entry')
                            {{ __('formsettings-for-filament::formsettings.suggestion_entry', ['field' => $suggestion['label']]) }}
                        @elseif ($suggestion['type'] === 'action')
                            {{ __('formsettings-for-filament::formsettings.suggestion_back', ['label' => $suggestion['label']]) }}
                        @elseif ($suggestion['type'] === 'preset')
                            {{ __('formsettings-for-filament::formsettings.suggestion_preset', ['name' => $suggestion['label']]) }}
                        @elseif ($suggestion['type'] === 'start_tab')
                            {{ __('formsettings-for-filament::formsettings.suggestion_start_tab', ['tab' => $suggestion['tab']]) }}
                        @else
                            {{ __('formsettings-for-filament::formsettings.suggestion_hide', ['count' => count($suggestion['fields']), 'fields' => implode(', ', $suggestion['labels'])]) }}
                        @endif
                    </span>

                    <button
                        type="button"
                        class="fi-icon-btn"
                        wire:click="applySuggestion(@js($suggestion['hash']))"
                        title="{{ __('formsettings-for-filament::formsettings.apply_suggestion') }}"
                    >
                        {{ generate_icon_html('heroicon-o-check', size: IconSize::Small) }}
                    </button>

                    <button
                        type="button"
                        class="fi-icon-btn"
                        wire:click="dismissSuggestion(@js($suggestion['hash']))"
                        title="{{ __('formsettings-for-filament::formsettings.dismiss_suggestion') }}"
                    >
                        {{ generate_icon_html('heroicon-o-x-mark', size: IconSize::Small) }}
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    @foreach ($this->groupedFields as $group)
        @if ($group['label'] !== null)
            <button
                type="button"
                class="formsettings-group-label {{ $startTab === $group['label'] ? 'formsettings-start-tab' : '' }}"
                wire:click="setStartTab(@js($group['label']))"
                wire:key="formsettings-group-label-{{ $group['label'] }}"
                title="{{ __('formsettings-for-filament::formsettings.set_start_tab') }}"
            >
                <span>{{ $group['label'] }}</span>

                @if ($startTab === $group['label'])
                    {{ generate_icon_html('heroicon-s-map-pin', size: IconSize::Small) }}
                @endif
            </button>
        @endif

        <div
            x-sortable
            x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
            data-sortable-animation-duration="300"
            class="fi-ta-col-manager-items formsettings-fields"
            wire:key="formsettings-group-{{ $group['label'] ?? 'default' }}"
        >
            @foreach ($group['fields'] as $field)
                @php
                    $isHidden = in_array($field['name'], $hidden, true);
                    $isEntryPoint = $field['name'] === $entryPoint;
                @endphp

                <div
                    x-sortable-item="{{ $field['name'] }}"
                    wire:key="formsettings-field-{{ $field['name'] }}"
                >
                    <div class="fi-ta-col-manager-item">
                        <label
                            class="fi-ta-col-manager-label"
                            title="{{ $field['label'] }}"
                            @if ($isHidden) style="text-decoration: line-through; opacity: 0.5;" @endif
                        >
                            {{ generate_icon_html($field['icon'], size: IconSize::Small) }}
                            <span>{{ $field['label'] }}</span>
                        </label>

                        <button
                            type="button"
                            class="fi-icon-btn"
                            wire:click="setEntryPoint(@js($field['name']))"
                            title="{{ __('formsettings-for-filament::formsettings.entry_point') }}"
                        >
                            {{ generate_icon_html($isEntryPoint ? 'heroicon-s-star' : 'heroicon-o-star', size: IconSize::Small) }}
                        </button>

                        @if ($field['hideable'])
                            <button
                                type="button"
                                class="fi-icon-btn"
                                wire:click="toggleHidden(@js($field['name']))"
                                title="{{ $isHidden ? __('formsettings-for-filament::formsettings.show_field') : __('formsettings-for-filament::formsettings.hide_field') }}"
                            >
                                {{ generate_icon_html($isHidden ? 'heroicon-o-eye-slash' : 'heroicon-o-eye', size: IconSize::Small) }}
                            </button>
                        @endif

                        @if ($field['locked'] ?? false)
                            <button
                                type="button"
                                class="fi-icon-btn"
                                disabled
                                title="{{ __('formsettings-for-filament::formsettings.locked_field') }}"
                            >
                                {{ generate_icon_html('heroicon-o-lock-closed', size: IconSize::Small) }}
                            </button>
                        @else
                            <button
                                x-sortable-handle
                                x-on:click.stop
                                class="fi-ta-col-manager-reorder-handle fi-icon-btn"
                                type="button"
                            >
                                {{ generate_icon_html('heroicon-o-bars-2', size: IconSize::Small) }}
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    @if ($actionOptions !== [])
        <div class="fi-ta-col-manager-header">
            <h3 class="fi-ta-col-manager-heading">
                {{ __('formsettings-for-filament::formsettings.submit_action') }}
            </h3>
        </div>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="action">
                @foreach ($actionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endif

    @if ($presetsEnabled || $predefinedPresets !== [])
        <div class="fi-ta-col-manager-header">
            <h3 class="fi-ta-col-manager-heading">
                {{ __('formsettings-for-filament::formsettings.presets') }}
            </h3>
        </div>

        <div class="fi-ta-col-manager-items">
            @foreach (array_keys($predefinedPresets) as $predefined)
                <div class="fi-ta-col-manager-item" wire:key="formsettings-predefined-{{ $predefined }}">
                    <label class="fi-ta-col-manager-label">
                        <x-filament::link
                            tag="button"
                            color="warning"
                            wire:click="applyPredefined({{ \Illuminate\Support\Js::from($predefined) }})"
                        >
                            {{ $predefined }}
                        </x-filament::link>

                        <span
                            class="formsettings-predefined-icon"
                            title="{{ __('formsettings-for-filament::formsettings.predefined_preset') }}"
                        >
                            {{ generate_icon_html('heroicon-o-bolt', size: IconSize::Small) }}
                        </span>
                    </label>
                </div>
            @endforeach
            @foreach ($presets as $preset)
                @php
                    $isPublished = in_array($preset, $publishedPresets, true);
                @endphp

                <div class="fi-ta-col-manager-item" wire:key="formsettings-preset-{{ $preset }}">
                    <label class="fi-ta-col-manager-label">
                        <x-filament::link
                            tag="button"
                            wire:click="applyPreset({{ \Illuminate\Support\Js::from($preset) }})"
                        >
                            {{ $preset }}
                        </x-filament::link>
                    </label>

                    @if ($publishingEnabled)
                        @php
                            $isPublishable = $isPublished || in_array($preset, $publishablePresets, true);
                            $publishTitle = $isPublished
                                ? __('formsettings-for-filament::formsettings.unpublish_preset')
                                : ($isPublishable
                                    ? __('formsettings-for-filament::formsettings.publish_preset')
                                    : __('formsettings-for-filament::formsettings.preset_default'));
                            // Blade's js directive does not compile inside
                            // component-tag attributes — build it in PHP.
                            $publishClick = ($isPublished ? 'unpublishPreset' : 'publishPreset')
                                . '(' . \Illuminate\Support\Js::from($preset) . ')';
                        @endphp

                        <x-filament::icon-button
                            :icon="$isPublished ? 'heroicon-s-globe-alt' : 'heroicon-o-globe-alt'"
                            :color="$isPublished ? 'success' : 'gray'"
                            size="sm"
                            :disabled="! $isPublishable"
                            :label="$publishTitle"
                            :title="$publishTitle"
                            wire:click="{{ $publishClick }}"
                        />
                    @endif

                    @php
                        $isConfirmingDelete = $confirmingDelete === $preset;
                    @endphp

                    <button
                        type="button"
                        class="fi-icon-btn {{ $isConfirmingDelete ? 'formsettings-overwrite' : '' }}"
                        wire:click="deletePreset(@js($preset))"
                        title="{{ $isConfirmingDelete ? __('formsettings-for-filament::formsettings.confirm_delete_published') : __('formsettings-for-filament::formsettings.delete_preset') }}"
                    >
                        {{ generate_icon_html($isConfirmingDelete ? 'heroicon-o-fire' : 'heroicon-o-trash', size: IconSize::Small) }}
                    </button>
                </div>
            @endforeach

            @foreach ($sharedPresets as $shared)
                <div
                    class="fi-ta-col-manager-item formsettings-shared"
                    wire:key="formsettings-shared-{{ $shared['user'] }}-{{ $shared['name'] }}"
                >
                    <label class="fi-ta-col-manager-label">
                        <x-filament::link
                            tag="button"
                            color="info"
                            wire:click="applySharedPreset({{ \Illuminate\Support\Js::from($shared['user']) }}, {{ \Illuminate\Support\Js::from($shared['name']) }})"
                        >
                            {{ $shared['name'] }}
                        </x-filament::link>

                        @if ($shared['owner'])
                            <span class="formsettings-shared-owner">({{ $shared['owner'] }})</span>
                        @endif
                    </label>

                    <button
                        type="button"
                        class="fi-icon-btn"
                        wire:click="hideSharedPreset(@js($shared['user']), @js($shared['name']))"
                        title="{{ __('formsettings-for-filament::formsettings.hide_preset') }}"
                    >
                        {{ generate_icon_html('heroicon-o-eye-slash', size: IconSize::Small) }}
                    </button>
                </div>
            @endforeach

            @if ($hiddenSharedPresets !== [])
                <button
                    type="button"
                    class="formsettings-hidden-toggle"
                    wire:click="toggleRevealHiddenShared"
                >
                    {{ trans_choice('formsettings-for-filament::formsettings.hidden_presets', count($hiddenSharedPresets), ['count' => count($hiddenSharedPresets)]) }}
                </button>

                @if ($revealHiddenShared)
                    @foreach ($hiddenSharedPresets as $shared)
                        <div
                            class="fi-ta-col-manager-item formsettings-shared formsettings-shared-hidden"
                            wire:key="formsettings-hidden-shared-{{ $shared['user'] }}-{{ $shared['name'] }}"
                        >
                            <label class="fi-ta-col-manager-label">
                                <span>{{ $shared['name'] }}</span>

                                @if ($shared['owner'])
                                    <span class="formsettings-shared-owner">({{ $shared['owner'] }})</span>
                                @endif
                            </label>

                            <button
                                type="button"
                                class="fi-icon-btn"
                                wire:click="restoreSharedPreset(@js($shared['user']), @js($shared['name']))"
                                title="{{ __('formsettings-for-filament::formsettings.restore_preset') }}"
                            >
                                {{ generate_icon_html('heroicon-o-eye', size: IconSize::Small) }}
                            </button>
                        </div>
                    @endforeach
                @endif
            @endif

            @if ($presetsEnabled)
                <div class="fi-ta-col-manager-item">
                    <x-filament::input.wrapper :valid="! $errors->has('newPresetName')">
                        <x-filament::input
                            type="text"
                            wire:model.live.debounce.500ms="newPresetName"
                            wire:keydown.enter="savePreset"
                            :placeholder="__('formsettings-for-filament::formsettings.preset_name')"
                        />
                    </x-filament::input.wrapper>

                    <button
                        type="button"
                        class="fi-icon-btn {{ $confirmingOverwrite !== null ? 'formsettings-overwrite' : '' }}"
                        wire:click="savePreset"
                        title="{{ $confirmingOverwrite !== null ? __('formsettings-for-filament::formsettings.overwrite_preset') : __('formsettings-for-filament::formsettings.save_preset') }}"
                    >
                        {{ generate_icon_html($confirmingOverwrite !== null ? 'heroicon-o-fire' : 'heroicon-o-plus', size: IconSize::Small) }}
                    </button>
                </div>
            @endif

            @error('newPresetName')
                {{-- wire:key remounts the element when the message
                     changes, so x-init re-scrolls it into view. --}}
                <p
                    data-validation-error
                    class="fi-fo-field-wrp-error-message formsettings-error"
                    wire:key="formsettings-error-{{ md5($message) }}"
                    x-data
                    x-init="$el.scrollIntoView({ block: 'nearest', behavior: 'smooth' })"
                >
                    {{ $message }}
                </p>
            @enderror
        </div>
    @endif
</div>
