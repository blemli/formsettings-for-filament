<?php

use Blemli\FormSettings\Livewire\SettingsPanel;
use Blemli\FormSettings\Storage\SessionStore;
use Livewire\Livewire;

function panelFields(): array
{
    return [
        ['name' => 'title', 'label' => 'Title', 'icon' => 'heroicon-o-pencil', 'required' => true, 'hideable' => false],
        ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true],
        ['name' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-bars-3-bottom-left', 'required' => false, 'hideable' => true],
    ];
}

function makePanel(array $overrides = [])
{
    return Livewire::test(SettingsPanel::class, [
        'formKey' => 'panel::test-form',
        'fields' => panelFields(),
        'actionOptions' => ['save' => 'Save', 'save_next' => 'Save & next', 'save_back' => 'Save & back'],
        'presetsEnabled' => true,
        ...$overrides,
    ]);
}

it('persists a reorder and dispatches the update event', function () {
    makePanel()
        ->call('reorder', ['notes', 'title', 'color'])
        ->assertDispatched('formsettings-updated');

    expect((new SessionStore)->get('panel::test-form')['order'])->toBe(['notes', 'title', 'color']);
});

it('ignores unknown fields in a reorder payload', function () {
    makePanel()->call('reorder', ['evil', 'notes', 'title', 'color']);

    expect((new SessionStore)->get('panel::test-form')['order'])->toBe(['notes', 'title', 'color']);
});

it('hides optional fields but never required ones', function () {
    $panel = makePanel()
        ->call('toggleHidden', 'color')
        ->call('toggleHidden', 'title');

    expect($panel->get('hidden'))->toBe(['color']);

    $panel->call('toggleHidden', 'color');
    expect($panel->get('hidden'))->toBe([])
        ->and((new SessionStore)->get('panel::test-form'))->toBeNull();
});

it('sets and toggles the entry point', function () {
    $panel = makePanel()->call('setEntryPoint', 'color');
    expect((new SessionStore)->get('panel::test-form')['entry_point'])->toBe('color');

    $panel->call('setEntryPoint', 'color');
    expect((new SessionStore)->get('panel::test-form'))->toBeNull();
});

it('clears the entry point when its field gets hidden', function () {
    $panel = makePanel()
        ->call('setEntryPoint', 'notes')
        ->call('toggleHidden', 'notes');

    expect($panel->get('entryPoint'))->toBeNull()
        ->and((new SessionStore)->get('panel::test-form')['hidden'])->toBe(['notes']);
});

it('persists the selected submit action', function () {
    makePanel()->set('action', 'save_next');

    expect((new SessionStore)->get('panel::test-form')['action'])->toBe('save_next');
});

it('resets everything', function () {
    makePanel()
        ->call('reorder', ['notes', 'title', 'color'])
        ->call('toggleHidden', 'color')
        ->call('resetSettings');

    expect((new SessionStore)->get('panel::test-form'))->toBeNull();
});

it('saves, applies and deletes presets', function () {
    $panel = makePanel()
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'compact')
        ->call('savePreset');

    expect($panel->get('presets'))->toBe(['compact'])
        ->and($panel->get('newPresetName'))->toBe('');

    $panel->call('resetSettings');
    expect((new SessionStore)->get('panel::test-form'))->toBeNull();

    $panel->call('applyPreset', 'compact');
    expect((new SessionStore)->get('panel::test-form')['hidden'])->toBe(['color']);

    $panel->call('deletePreset', 'compact');
    expect($panel->get('presets'))->toBe([]);
});
