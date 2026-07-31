<?php

namespace Blemli\FormSettings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $settings
 * @property string $key
 * @property string|null $preset
 * @property bool $published
 * @property string|null $user_type
 * @property int|string|null $user_id
 */
class FormSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'published' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('formsettings-for-filament.table', 'formsettings');
    }
}
