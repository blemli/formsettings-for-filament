@php
    use Filament\Support\Enums\IconSize;

    use function Filament\Support\generate_icon_html;
@endphp

<div class="fi-ta-col-manager">
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

    <div
        x-sortable
        x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
        data-sortable-animation-duration="300"
        class="fi-ta-col-manager-items"
    >
        @foreach ($this->sortedFields as $field)
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

                    <button
                        x-sortable-handle
                        x-on:click.stop
                        class="fi-ta-col-manager-reorder-handle fi-icon-btn"
                        type="button"
                    >
                        {{ generate_icon_html('heroicon-o-bars-2', size: IconSize::Small) }}
                    </button>
                </div>
            </div>
        @endforeach
    </div>

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

    @if ($presetsEnabled)
        <div class="fi-ta-col-manager-header">
            <h3 class="fi-ta-col-manager-heading">
                {{ __('formsettings-for-filament::formsettings.presets') }}
            </h3>
        </div>

        @foreach ($presets as $preset)
            <div class="fi-ta-col-manager-item" wire:key="formsettings-preset-{{ $preset }}">
                <label class="fi-ta-col-manager-label">
                    <x-filament::link
                        tag="button"
                        wire:click="applyPreset(@js($preset))"
                    >
                        {{ $preset }}
                    </x-filament::link>
                </label>

                <button
                    type="button"
                    class="fi-icon-btn"
                    wire:click="deletePreset(@js($preset))"
                    title="{{ __('formsettings-for-filament::formsettings.delete_preset') }}"
                >
                    {{ generate_icon_html('heroicon-o-trash', size: IconSize::Small) }}
                </button>
            </div>
        @endforeach

        <div class="fi-ta-col-manager-item">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="newPresetName"
                    wire:keydown.enter="savePreset"
                    :placeholder="__('formsettings-for-filament::formsettings.preset_name')"
                />
            </x-filament::input.wrapper>

            <button
                type="button"
                class="fi-icon-btn"
                wire:click="savePreset"
                title="{{ __('formsettings-for-filament::formsettings.save_preset') }}"
            >
                {{ generate_icon_html('heroicon-o-plus', size: IconSize::Small) }}
            </button>
        </div>
    @endif
</div>
