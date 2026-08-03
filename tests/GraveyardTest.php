<?php

use Blemli\FormSettings\Models\FormSetting;
use Blemli\FormSettings\Storage\DatabaseStore;

it('reports fields nobody touches across users', function () {
    $migration = require __DIR__ . '/../database/migrations/create_formsettings_table.php.stub';
    $migration->up();

    $run = fn (array $touched): array => ['touched' => $touched, 'first' => $touched[0] ?? null, 'action' => null];

    foreach ([1, 2] as $userId) {
        FormSetting::query()->create([
            'user_type' => 'App\\Models\\User',
            'user_id' => $userId,
            'key' => 'panel::form',
            'preset' => DatabaseStore::USAGE,
            'settings' => [
                'runs' => array_fill(0, 5, $run(['codename', 'mood'])),
                'fields' => ['codename', 'mood', 'coat_color'],
            ],
        ]);
    }

    $this->artisan('formsettings:graveyard', ['--min-runs' => 5])
        ->expectsOutputToContain('coat_color')
        ->doesntExpectOutputToContain('codename')
        ->assertSuccessful();
});

it('stays quiet without usage data', function () {
    $migration = require __DIR__ . '/../database/migrations/create_formsettings_table.php.stub';
    $migration->up();

    $this->artisan('formsettings:graveyard')
        ->expectsOutputToContain('No usage data yet')
        ->assertSuccessful();
});
