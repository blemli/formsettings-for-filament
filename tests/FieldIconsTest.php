<?php

use Blemli\FormSettings\Support\FieldIcons;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

it('maps field types to icons', function () {
    expect(FieldIcons::for(ColorPicker::make('color')))->toBe('heroicon-o-swatch')
        ->and(FieldIcons::for(Select::make('country')))->toBe('heroicon-o-chevron-up-down')
        ->and(FieldIcons::for(Toggle::make('active')))->toBe('heroicon-o-check-circle');
});

it('detects text input flavours', function () {
    expect(FieldIcons::for(TextInput::make('name')))->toBe('heroicon-o-pencil')
        ->and(FieldIcons::for(TextInput::make('email')->email()))->toBe('heroicon-o-at-symbol')
        ->and(FieldIcons::for(TextInput::make('secret')->password()))->toBe('heroicon-o-key')
        ->and(FieldIcons::for(TextInput::make('age')->numeric()))->toBe('heroicon-o-hashtag')
        ->and(FieldIcons::for(TextInput::make('price')->numeric()->prefix('CHF')))->toBe('heroicon-o-banknotes');
});
