<?php

use Blemli\FormSettings\FormSettings;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;

/**
 * Stand-in manager that treats the given names as user-hidden, without
 * needing a Filament panel or Livewire page around the field.
 */
function fakeManager(array $hidden): void
{
    app()->instance(FormSettings::class, new class($hidden) extends FormSettings
    {
        public function __construct(protected array $hiddenNames = [])
        {
            parent::__construct();
        }

        public function fieldHidden(Field $component): bool
        {
            return in_array($component->getName(), $this->hiddenNames, true);
        }

        public function fieldLearnName(Field $component): ?string
        {
            return $component->getName();
        }
    });
}

it('hides a user-hidden field', function () {
    fakeManager(['zip']);

    expect(TextInput::make('zip')->isHidden())->toBeTrue()
        ->and(TextInput::make('other')->isHidden())->toBeFalse();
});

it('still hides when the resource chains its own hidden condition', function () {
    fakeManager(['zip']);

    // ->hidden() replaces the plugin's hidden slot (as ->hiddenOn() does);
    // the visible slot must keep enforcing the user's hide.
    expect(TextInput::make('zip')->hidden(fn (): bool => false)->isHidden())->toBeTrue()
        ->and(TextInput::make('other')->hidden(fn (): bool => false)->isHidden())->toBeFalse();
});

it('still hides when the resource chains its own visible condition', function () {
    fakeManager(['zip']);

    expect(TextInput::make('zip')->visible(fn (): bool => true)->isHidden())->toBeTrue()
        ->and(TextInput::make('other')->visible(fn (): bool => true)->isHidden())->toBeFalse();
});

it('keeps the developer-hidden state on fields the user left visible', function () {
    fakeManager([]);

    expect(TextInput::make('zip')->hidden(fn (): bool => true)->isHidden())->toBeTrue()
        ->and(TextInput::make('zip')->visible(fn (): bool => false)->isHidden())->toBeTrue();
});

it('marks the field root element for the overlay and usage tracker', function () {
    fakeManager([]);

    // Root-level attributes survive FilePond replacing the file input.
    expect(FileUpload::make('zip')->getExtraAttributes()['data-formsettings-name'] ?? null)->toBe('zip')
        ->and(TextInput::make('name')->getExtraAttributes()['data-formsettings-name'] ?? null)->toBe('name');
});
