<?php

use Blemli\FormSettings\FormSettingsPlugin;

it('is authorized by default', function () {
    expect(FormSettingsPlugin::make()->isAuthorized())->toBeTrue();
});

it('can be locked down with a boolean', function () {
    expect(FormSettingsPlugin::make()->authorize(false)->isAuthorized())->toBeFalse();
});

it('evaluates an authorization closure with the user', function () {
    $plugin = FormSettingsPlugin::make()->authorize(fn ($user): bool => $user !== null);

    expect($plugin->isAuthorized())->toBeFalse();
});

it('denies a gate ability for guests', function () {
    expect(FormSettingsPlugin::make()->authorize('use-formsettings')->isAuthorized())->toBeFalse();
});
