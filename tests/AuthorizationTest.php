<?php

use Blemli\FormSettings\FormSettingsPlugin;
use Filament\Resources\Pages\CreateRecord;

it('is global by default and flips with optIn', function () {
    $make = fn () => FormSettingsPlugin::make();

    expect($make()->isGlobal())->toBeTrue()
        ->and($make()->optIn()->isGlobal())->toBeFalse()
        ->and($make()->optIn(false)->isGlobal())->toBeTrue()
        ->and($make()->optIn(fn (): bool => true)->isGlobal())->toBeFalse()
        ->and($make()->globally(false)->isGlobal())->toBeFalse();
});

it('excludes pages listed in except', function () {
    $page = new class extends CreateRecord {};

    expect(FormSettingsPlugin::make()->except([$page::class])->isExcepted($page))->toBeTrue()
        ->and(FormSettingsPlugin::make()->except([CreateRecord::class])->isExcepted($page))->toBeTrue()
        ->and(FormSettingsPlugin::make()->isExcepted($page))->toBeFalse()
        ->and(FormSettingsPlugin::make()->except(fn (object $p): bool => true)->isExcepted($page))->toBeTrue();
});

it('collects predefined presets per page class', function () {
    $page = new class extends CreateRecord {};

    $plugin = FormSettingsPlugin::make()->predefined([
        CreateRecord::class => ['Quick entry' => ['hidden' => ['a']]],
        'App\Some\OtherPage' => ['Elsewhere' => ['hidden' => ['b']]],
    ]);

    expect($plugin->getPredefined())->toHaveCount(2)
        ->and(FormSettingsPlugin::make()->isPerResource())->toBeFalse()
        ->and(FormSettingsPlugin::make()->perResource()->isPerResource())->toBeTrue();
});

it('hides on mobile unless opted in', function () {
    expect(FormSettingsPlugin::make()->isShownOnMobile())->toBeFalse()
        ->and(FormSettingsPlugin::make()->showOnMobile()->isShownOnMobile())->toBeTrue();
});

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
