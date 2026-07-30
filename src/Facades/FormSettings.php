<?php

namespace Blemli\FormSettings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Blemli\FormSettings\FormSettings
 */
class FormSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Blemli\FormSettings\FormSettings::class;
    }
}
