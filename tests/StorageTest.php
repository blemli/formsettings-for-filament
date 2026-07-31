<?php

use Blemli\FormSettings\Models\FormSetting;
use Blemli\FormSettings\Storage\DatabaseStore;
use Blemli\FormSettings\Storage\SessionStore;

it('round-trips settings through the session store', function () {
    $store = new SessionStore;
    $settings = ['order' => ['b', 'a'], 'hidden' => ['c'], 'entry_point' => 'b', 'action' => 'save_next'];

    expect($store->get('panel::form'))->toBeNull();

    $store->put('panel::form', $settings);
    expect($store->get('panel::form'))->toBe($settings);

    $store->forget('panel::form');
    expect($store->get('panel::form'))->toBeNull();
});

it('manages presets in the session store', function () {
    $store = new SessionStore;
    $settings = ['order' => [], 'hidden' => ['notes'], 'entry_point' => null, 'action' => null];

    expect($store->listPresets('k'))->toBe([]);

    $store->putPreset('k', 'fast entry', $settings);
    expect($store->listPresets('k'))->toBe(['fast entry'])
        ->and($store->getPreset('k', 'fast entry'))->toBe($settings);

    $store->deletePreset('k', 'fast entry');
    expect($store->listPresets('k'))->toBe([]);
});

it('round-trips settings and presets through the database store', function () {
    $migration = require __DIR__ . '/../database/migrations/create_formsettings_table.php.stub';
    $migration->up();

    $store = new DatabaseStore;
    $settings = ['order' => ['a'], 'hidden' => [], 'entry_point' => 'a', 'action' => null];

    $store->put('panel::form', $settings);
    expect($store->get('panel::form'))->toBe($settings)
        ->and(FormSetting::query()->count())->toBe(1);

    $store->put('panel::form', $settings);
    expect(FormSetting::query()->count())->toBe(1);

    $store->putPreset('panel::form', 'mine', $settings);
    expect($store->listPresets('panel::form'))->toBe(['mine'])
        ->and($store->getPreset('panel::form', 'mine'))->toBe($settings)
        ->and($store->get('panel::form'))->toBe($settings);

    $store->deletePreset('panel::form', 'mine');
    $store->forget('panel::form');
    expect(FormSetting::query()->count())->toBe(0);
});

it('publishes, shares and hides presets through the database store', function () {
    $migration = require __DIR__ . '/../database/migrations/create_formsettings_table.php.stub';
    $migration->up();

    $store = new DatabaseStore;
    $settings = ['order' => [], 'hidden' => ['notes'], 'entry_point' => null, 'action' => null];

    $store->putPreset('panel::form', 'mine', $settings);
    expect($store->publishedPresetNames('panel::form'))->toBe([]);

    $store->setPresetPublished('panel::form', 'mine', true);
    expect($store->publishedPresetNames('panel::form'))->toBe(['mine'])
        ->and($store->sharedPresets('panel::form'))->toBe([]);

    // A published preset from another user surfaces as shared.
    FormSetting::query()->create([
        'user_type' => 'App\\Models\\User',
        'user_id' => 42,
        'key' => 'panel::form',
        'preset' => 'theirs',
        'settings' => $settings,
        'published' => true,
    ]);

    $shared = $store->sharedPresets('panel::form');
    expect($shared)->toHaveCount(1)
        ->and($shared[0]['name'])->toBe('theirs')
        ->and($shared[0]['user'])->toBe('42')
        ->and($store->getSharedPreset('panel::form', '42', 'theirs'))->toBe($settings);

    // Hiding is per user and never touches the shared rows themselves.
    $store->putHiddenSharedPresets('panel::form', ['42|theirs']);
    expect($store->hiddenSharedPresets('panel::form'))->toBe(['42|theirs'])
        ->and($store->listPresets('panel::form'))->toBe(['mine'])
        ->and($store->sharedPresets('panel::form'))->toHaveCount(1);

    $store->putHiddenSharedPresets('panel::form', []);
    expect($store->hiddenSharedPresets('panel::form'))->toBe([]);

    $store->setPresetPublished('panel::form', 'mine', false);
    expect($store->publishedPresetNames('panel::form'))->toBe([]);
});
