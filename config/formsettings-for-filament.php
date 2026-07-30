<?php

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;

// config for Blemli/FormSettings
return [

    /*
     * The database table used when persistence is enabled via
     * FormSettingsPlugin::make()->persist().
     */
    'table' => 'formsettings',

    /*
     * A prefix/suffix matching this pattern marks a TextInput as a price field.
     */
    'currency_pattern' => '/^(CHF|EUR|USD|GBP|\$|€|£)$/iu',

    /*
     * Heroicon shown next to each field type in the settings panel.
     * Keys are field class names, values are icon names.
     */
    'icons' => [
        ColorPicker::class => 'heroicon-o-swatch',
        Textarea::class => 'heroicon-o-bars-3-bottom-left',
        RichEditor::class => 'heroicon-o-document-text',
        MarkdownEditor::class => 'heroicon-o-document-text',
        Select::class => 'heroicon-o-chevron-up-down',
        Radio::class => 'heroicon-o-list-bullet',
        CheckboxList::class => 'heroicon-o-queue-list',
        Checkbox::class => 'heroicon-o-check-circle',
        Toggle::class => 'heroicon-o-check-circle',
        ToggleButtons::class => 'heroicon-o-squares-2x2',
        DatePicker::class => 'heroicon-o-calendar-days',
        DateTimePicker::class => 'heroicon-o-calendar-days',
        TimePicker::class => 'heroicon-o-clock',
        FileUpload::class => 'heroicon-o-paper-clip',
        TagsInput::class => 'heroicon-o-tag',
        KeyValue::class => 'heroicon-o-table-cells',
        Repeater::class => 'heroicon-o-rectangle-stack',
    ],

    'fallback_icon' => 'heroicon-o-cube',

    /*
     * Icons for TextInput, keyed by detected flavour.
     */
    'text_input_icons' => [
        'email' => 'heroicon-o-at-symbol',
        'password' => 'heroicon-o-key',
        'tel' => 'heroicon-o-phone',
        'url' => 'heroicon-o-link',
        'price' => 'heroicon-o-banknotes',
        'numeric' => 'heroicon-o-hashtag',
        'text' => 'heroicon-o-pencil',
    ],
];
