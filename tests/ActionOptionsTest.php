<?php

use Blemli\FormSettings\FormSettings;
use Filament\Resources\Pages\CreateRecord;

// Without a registered plugin, neither create-another nor the
// save-and-back button can be enabled beyond the page defaults.

it('offers create another only when the page allows it', function () {
    $page = new class extends CreateRecord
    {
        protected static bool $canCreateAnother = true;
    };

    expect(app(FormSettings::class)->actionOptions($page))
        ->toHaveKeys(['create', 'create_next'])
        ->not->toHaveKey('create_back');
});

it('omits create another when the page disables it', function () {
    $page = new class extends CreateRecord
    {
        protected static bool $canCreateAnother = false;
    };

    expect(app(FormSettings::class)->actionOptions($page))
        ->toHaveKey('create')
        ->not->toHaveKeys(['create_next', 'create_back']);
});
