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

it('groups fields by their tab label', function () {
    $panel = makePanel([
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'icon' => 'heroicon-o-pencil', 'required' => true, 'hideable' => false, 'group' => 'General'],
            ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => 'Details'],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-bars-3-bottom-left', 'required' => false, 'hideable' => true, 'group' => 'Details'],
        ],
    ]);

    $groups = $panel->instance()->getGroupedFieldsProperty();

    expect(array_column($groups, 'label'))->toBe(['General', 'Details'])
        ->and(array_column($groups[1]['fields'], 'name'))->toBe(['color', 'notes']);
});

it('reordering one tab group keeps the other groups in place', function () {
    $panel = makePanel([
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'icon' => 'heroicon-o-pencil', 'required' => true, 'hideable' => false, 'group' => 'General'],
            ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => 'Details'],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-bars-3-bottom-left', 'required' => false, 'hideable' => true, 'group' => 'Details'],
        ],
    ]);

    $panel->call('reorder', ['notes', 'color']);

    expect((new SessionStore)->get('panel::test-form')['order'])->toBe(['title', 'notes', 'color']);
});

it('clears the saved order when fields are dragged back to the natural order', function () {
    makePanel()
        ->call('reorder', ['notes', 'title', 'color'])
        ->call('reorder', ['title', 'color', 'notes']);

    expect((new SessionStore)->get('panel::test-form'))->toBeNull();
});

it('rejects preset names longer than 25 characters', function () {
    $panel = makePanel()
        ->set('newPresetName', str_repeat('x', 26))
        ->call('savePreset')
        ->assertHasErrors(['newPresetName' => 'max']);

    expect($panel->get('presets'))->toBe([]);
});

it('overwrites a preset only after a confirming second save', function () {
    $panel = makePanel()
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'compact')
        ->call('savePreset');

    $panel->call('toggleHidden', 'notes')
        ->set('newPresetName', 'compact')
        ->call('savePreset')
        ->assertHasErrors('newPresetName');

    expect((new SessionStore)->getPreset('panel::test-form', 'compact')['hidden'])->toBe(['color'])
        ->and($panel->get('confirmingOverwrite'))->toBe('compact');

    $panel->call('savePreset')->assertHasNoErrors();

    expect((new SessionStore)->getPreset('panel::test-form', 'compact')['hidden'])->toBe(['color', 'notes'])
        ->and($panel->get('newPresetName'))->toBe('')
        ->and($panel->get('confirmingOverwrite'))->toBeNull();
});

it('cancels a pending overwrite when the name changes', function () {
    $panel = makePanel()
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'compact')
        ->call('savePreset')
        ->call('toggleHidden', 'notes')
        ->set('newPresetName', 'compact')
        ->call('savePreset');

    expect($panel->get('confirmingOverwrite'))->toBe('compact');

    $panel->set('newPresetName', 'other')->call('savePreset')->assertHasNoErrors();

    expect($panel->get('presets'))->toBe(['compact', 'other'])
        ->and($panel->get('confirmingOverwrite'))->toBeNull();
});

it('tracks published preset names and hidden shared presets', function () {
    $panel = makePanel(['publishingEnabled' => true])
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'compact')
        ->call('savePreset')
        ->call('publishPreset', 'compact');

    expect($panel->get('publishedPresets'))->toBe(['compact'])
        ->and((new SessionStore)->publishedPresetNames('panel::test-form'))->toBe(['compact']);

    $panel->call('unpublishPreset', 'compact');
    expect($panel->get('publishedPresets'))->toBe([]);
});

it('blocks saving a preset that duplicates an existing configuration', function () {
    $panel = makePanel()
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'compact')
        ->call('savePreset')
        ->set('newPresetName', 'other')
        ->call('savePreset')
        ->assertHasErrors('newPresetName');

    expect($panel->get('presets'))->toBe(['compact']);
});

it('refuses to save or publish a default-config preset', function () {
    $panel = makePanel(['publishingEnabled' => true])
        ->set('newPresetName', 'noop')
        ->call('savePreset')
        ->assertHasErrors('newPresetName');

    expect($panel->get('presets'))->toBe([]);

    // A default-config preset that slipped in earlier is not publishable.
    (new SessionStore)->putPreset('panel::test-form', 'noop', ['order' => [], 'hidden' => [], 'entry_point' => null, 'action' => null]);

    $panel = makePanel(['publishingEnabled' => true])
        ->call('publishPreset', 'noop')
        ->assertHasErrors('newPresetName');

    expect($panel->get('publishedPresets'))->toBe([])
        ->and($panel->get('publishablePresets'))->toBe([]);
});

it('suggests making save and back the default when its button is used often', function () {
    $run = ['key' => 'panel::test-form', 'touched' => ['title'], 'first' => 'title', 'action' => 'back'];

    $panel = learningPanel()->call('recordUsage', [$run, $run]);

    $suggestion = collect($panel->instance()->getSuggestionsProperty())->firstWhere('type', 'action');

    expect($suggestion['action'])->toBe('save_back');

    $panel->call('applySuggestion', 'action|back');

    expect($panel->get('action'))->toBe('save_back')
        ->and(collect($panel->instance()->getSuggestionsProperty())->firstWhere('type', 'action'))->toBeNull();
});

it('sanitizes applied presets against the current form', function () {
    (new SessionStore)->putPreset('panel::test-form', 'dirty', [
        'order' => ['evil', 'notes', 'title'],
        'hidden' => ['title', 'color', 'ghost'],
        'entry_point' => 'ghost',
        'action' => 'nuke',
    ]);

    $panel = makePanel()->call('applyPreset', 'dirty');

    expect($panel->get('order'))->toBe(['notes', 'title'])
        ->and($panel->get('hidden'))->toBe(['color'])
        ->and($panel->get('entryPoint'))->toBeNull()
        ->and($panel->get('action'))->toBe('save');
});

it('deletes a published preset only after a confirming second click', function () {
    $panel = makePanel(['publishingEnabled' => true])
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'shared')
        ->call('savePreset')
        ->call('publishPreset', 'shared')
        ->call('deletePreset', 'shared');

    expect($panel->get('presets'))->toBe(['shared'])
        ->and($panel->get('confirmingDelete'))->toBe('shared');

    $panel->call('deletePreset', 'shared');
    expect($panel->get('presets'))->toBe([]);
});

function learningPanel(array $overrides = [])
{
    return makePanel(['learningEnabled' => true, 'learnAfter' => 2, ...$overrides]);
}

it('suggests entry point and hiding after consistent runs', function () {
    $run = ['key' => 'panel::test-form', 'touched' => ['color', 'title'], 'first' => 'color'];

    $panel = learningPanel()->call('recordUsage', [$run]);
    expect($panel->instance()->getSuggestionsProperty())->toBe([]);

    $panel->call('recordUsage', [$run]);
    $suggestions = $panel->instance()->getSuggestionsProperty();

    expect(array_column($suggestions, 'type'))->toBe(['entry', 'hide'])
        ->and($suggestions[0]['field'])->toBe('color')
        ->and($suggestions[1]['fields'])->toBe(['notes']);
});

it('only suggests the entry point when hiding suggestions are off', function () {
    $run = ['key' => 'panel::test-form', 'touched' => ['color'], 'first' => 'color'];

    $panel = learningPanel(['suggestHiding' => false])
        ->call('recordUsage', [$run, $run]);

    expect(array_column($panel->instance()->getSuggestionsProperty(), 'type'))->toBe(['entry']);
});

it('forgives an outlier run when suggesting', function () {
    $usual = ['key' => 'panel::test-form', 'touched' => ['color', 'title'], 'first' => 'color'];
    $outlier = ['key' => 'panel::test-form', 'touched' => ['color', 'title', 'notes'], 'first' => 'title'];

    $panel = learningPanel()->call('recordUsage', [$usual, $outlier, $usual]);

    $suggestions = $panel->instance()->getSuggestionsProperty();

    expect(array_column($suggestions, 'type'))->toBe(['entry', 'hide'])
        ->and($suggestions[0]['field'])->toBe('color')
        ->and($suggestions[1]['fields'])->toBe(['notes']);
});

it('stays quiet when runs are too inconsistent', function () {
    $a = ['key' => 'panel::test-form', 'touched' => ['color'], 'first' => 'color'];
    $b = ['key' => 'panel::test-form', 'touched' => ['notes'], 'first' => 'title'];

    $panel = learningPanel()->call('recordUsage', [$a, $b, $b, $a]);

    expect(array_column($panel->instance()->getSuggestionsProperty(), 'type'))->toBe(['hide']);
});

it('suggests a matching preset instead of piecemeal suggestions', function () {
    (new SessionStore)->putPreset('panel::test-form', 'match', [
        'order' => [],
        'hidden' => ['notes'],
        'entry_point' => 'color',
        'action' => null,
        'start_tab' => null,
    ]);

    $run = ['key' => 'panel::test-form', 'touched' => ['color', 'title'], 'first' => 'color'];
    $panel = learningPanel()->call('recordUsage', [$run, $run]);

    expect(array_column($panel->instance()->getSuggestionsProperty(), 'type'))->toBe(['preset']);

    $panel->call('applySuggestion', 'preset|own|match');

    expect($panel->get('hidden'))->toBe(['notes'])
        ->and($panel->get('entryPoint'))->toBe('color')
        ->and($panel->instance()->getSuggestionsProperty())->toBe([]);
});

it('suggests a start tab when entries begin in the same tab', function () {
    $fields = [
        ['name' => 'title', 'label' => 'Title', 'icon' => 'heroicon-o-pencil', 'required' => true, 'hideable' => false, 'group' => 'General'],
        ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => 'Details'],
        ['name' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-bars-3-bottom-left', 'required' => false, 'hideable' => true, 'group' => 'Details'],
    ];

    $runA = ['key' => 'panel::test-form', 'touched' => ['color'], 'first' => 'color'];
    $runB = ['key' => 'panel::test-form', 'touched' => ['notes'], 'first' => 'notes'];

    $panel = learningPanel(['fields' => $fields, 'suggestHiding' => false])
        ->call('recordUsage', [$runA, $runB]);

    $suggestion = collect($panel->instance()->getSuggestionsProperty())->firstWhere('type', 'start_tab');

    expect($suggestion['tab'])->toBe('Details');

    $panel->call('applySuggestion', 'start_tab|Details');
    expect($panel->get('startTab'))->toBe('Details')
        ->and((new SessionStore)->get('panel::test-form')['start_tab'])->toBe('Details');
});

it('toggles the start tab from the group label', function () {
    $fields = [
        ['name' => 'title', 'label' => 'Title', 'icon' => 'heroicon-o-pencil', 'required' => true, 'hideable' => false, 'group' => 'General'],
        ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => 'Details'],
    ];

    $panel = makePanel(['fields' => $fields])->call('setStartTab', 'Details');
    expect($panel->get('startTab'))->toBe('Details');

    $panel->call('setStartTab', 'unknown');
    expect($panel->get('startTab'))->toBe('Details');

    $panel->call('setStartTab', 'Details');
    expect($panel->get('startTab'))->toBeNull()
        ->and((new SessionStore)->get('panel::test-form'))->toBeNull();
});

it('applies and dismisses suggestions', function () {
    $run = ['key' => 'panel::test-form', 'touched' => ['color', 'title'], 'first' => 'color'];
    $panel = learningPanel()->call('recordUsage', [$run, $run]);

    $panel->call('applySuggestion', 'entry|color');
    expect($panel->get('entryPoint'))->toBe('color');

    $panel->call('dismissSuggestion', 'hide|notes');
    expect($panel->instance()->getSuggestionsProperty())->toBe([])
        ->and((new SessionStore)->getUsage('panel::test-form')['dismissed'])->toBe(['hide|notes']);
});

it('ignores malformed usage payloads and keeps a bounded buffer', function () {
    $panel = learningPanel()->call('recordUsage', [
        'junk',
        ['key' => '', 'touched' => ['a']],
        ['key' => 'panel::test-form', 'touched' => [123, '']],
        ['key' => 'panel::test-form', 'touched' => ['title'], 'first' => 42],
    ]);

    $runs = (new SessionStore)->getUsage('panel::test-form')['runs'] ?? [];

    expect($runs)->toHaveCount(1)
        ->and($runs[0])->toBe(['touched' => ['title'], 'first' => null, 'action' => null]);

    for ($i = 0; $i < 15; $i++) {
        $panel->call('recordUsage', [['key' => 'panel::test-form', 'touched' => ['title'], 'first' => 'title']]);
    }

    expect((new SessionStore)->getUsage('panel::test-form')['runs'])->toHaveCount(10);
});

it('renders no uncompiled js directives', function () {
    $panel = makePanel(['publishingEnabled' => true])
        ->call('toggleHidden', 'color')
        ->set('newPresetName', 'compact')
        ->call('savePreset')
        ->call('publishPreset', 'compact');

    expect($panel->html())->not->toContain('@js(');
});

it('applies predefined presets sanitized', function () {
    $panel = makePanel([
        'predefinedPresets' => [
            'Quick entry' => ['hidden' => ['color', 'ghost', 'title'], 'entry_point' => 'notes', 'action' => 'save_next'],
        ],
    ]);

    $panel->call('applyPredefined', 'Quick entry');

    expect($panel->get('hidden'))->toBe(['color'])
        ->and($panel->get('entryPoint'))->toBe('notes')
        ->and($panel->get('action'))->toBe('save_next');
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
