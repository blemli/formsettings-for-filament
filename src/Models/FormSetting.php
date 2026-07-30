<?php

namespace Blemli\FormSettings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $settings
 * @property string $key
 * @property string|null $preset
 */
class FormSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getTable(): string
    {
        return config('formsettings-for-filament.table', 'formsettings');
    }
}
