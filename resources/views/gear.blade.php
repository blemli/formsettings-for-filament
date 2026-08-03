@php
    $page = \Livewire\Livewire::current();
    $manager = app(\Blemli\FormSettings\FormSettings::class);
@endphp

@if ($page && $manager->isEnabledFor($page))
    @php
        $formKey = $manager->keyFor($page);
        $badge = $manager->customizationCount($page);
        $selectedLabel = $manager->selectedActionLabel($page);
    @endphp

    @php
        $learningEnabled = (bool) $manager->plugin()?->hasLearning();
    @endphp

    <div
        x-data
        x-on:formsettings-updated.window="$wire.$refresh()"
        class="formsettings-gear {{ $manager->plugin()?->isShownOnMobile() ? '' : 'formsettings-hide-mobile' }}"
        data-formsettings-formkey="{{ $formKey }}"
        data-formsettings-arrange-apply="{{ __('formsettings-for-filament::formsettings.arrange_apply') }}"
        data-formsettings-arrange-cancel="{{ __('formsettings-for-filament::formsettings.arrange_cancel') }}"
        @if ($learningEnabled) data-formsettings-learn="true" @endif
        @if ($selectedLabel) data-formsettings-selected-label="{{ $selectedLabel }}" @endif
    >
        <x-filament::dropdown placement="bottom-end" shift width="formsettings-width" max-height="32rem">
            <x-slot name="trigger">
                <x-filament::icon-button
                    icon="heroicon-o-cog-6-tooth"
                    color="gray"
                    size="lg"
                    :badge="$badge ?: null"
                    :label="__('formsettings-for-filament::formsettings.gear_label')"
                    :tooltip="__('formsettings-for-filament::formsettings.gear_label')"
                />
            </x-slot>

            @livewire('formsettings-panel', [
                'formKey' => $formKey,
                'fields' => $manager->describeFields($page),
                'actionOptions' => $manager->actionOptions($page),
                'presetsEnabled' => (bool) $manager->plugin()?->hasPresets(),
                'publishingEnabled' => (bool) ($manager->plugin()?->hasPresets() && $manager->plugin()?->hasPublishing() && $manager->plugin()?->isPersistent()),
                'learningEnabled' => $learningEnabled,
                'learnAfter' => $manager->plugin()?->learningThreshold() ?? 5,
                'suggestHiding' => ! $page instanceof \Filament\Resources\Pages\EditRecord,
                'predefinedPresets' => $manager->predefinedFor($page),
            ], key('formsettings-panel-' . $formKey))
        </x-filament::dropdown>

        @php
            $isCreatePage = $page instanceof \Filament\Resources\Pages\CreateRecord;
            $showBackButton = $manager->plugin()?->hasSaveAndBackButton()
                && ($isCreatePage || $page instanceof \Filament\Resources\Pages\EditRecord);
        @endphp

        @if ($showBackButton)
            {{-- Rendered hidden; the script moves it next to the primary
                 form action, where it belongs visually. --}}
            <x-filament::button
                color="gray"
                tag="button"
                type="button"
                hidden
                data-formsettings-back="true"
                wire:click="{{ $isCreatePage ? 'create' : 'save' }}('formsettings-back')"
            >
                {{ __($isCreatePage ? 'formsettings-for-filament::formsettings.actions.create_back' : 'formsettings-for-filament::formsettings.actions.save_back') }}
            </x-filament::button>
        @endif
    </div>
@endif
