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

    <div
        x-data
        x-on:formsettings-updated.window="$wire.$refresh()"
        @if ($selectedLabel) data-formsettings-selected-label="{{ $selectedLabel }}" @endif
    >
        <x-filament::dropdown placement="bottom-end" shift width="sm" max-height="32rem">
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
            ], key('formsettings-panel-' . $formKey))
        </x-filament::dropdown>
    </div>
@endif
