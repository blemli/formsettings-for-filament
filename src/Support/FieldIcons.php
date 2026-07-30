<?php

namespace Blemli\FormSettings\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Throwable;

class FieldIcons
{
    public static function for(Field $field): string
    {
        if ($field instanceof TextInput) {
            return static::forTextInput($field);
        }

        foreach (config('formsettings-for-filament.icons', []) as $class => $icon) {
            if ($field instanceof $class) {
                return $icon;
            }
        }

        return config('formsettings-for-filament.fallback_icon', 'heroicon-o-cube');
    }

    protected static function forTextInput(TextInput $field): string
    {
        $icons = config('formsettings-for-filament.text_input_icons', []);

        try {
            $type = $field->getType();
        } catch (Throwable) {
            $type = null;
        }

        if (is_string($type) && isset($icons[$type])) {
            return $icons[$type];
        }

        if (static::isPrice($field)) {
            return $icons['price'] ?? 'heroicon-o-banknotes';
        }

        try {
            if ($type === 'number' || $field->isNumeric()) {
                return $icons['numeric'] ?? 'heroicon-o-hashtag';
            }
        } catch (Throwable) {
            // keep default
        }

        return $icons['text'] ?? 'heroicon-o-pencil';
    }

    protected static function isPrice(TextInput $field): bool
    {
        $pattern = config('formsettings-for-filament.currency_pattern');

        if (! $pattern) {
            return false;
        }

        try {
            foreach ([$field->getPrefixLabel(), $field->getSuffixLabel()] as $label) {
                if (is_scalar($label) && preg_match($pattern, trim((string) $label))) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
