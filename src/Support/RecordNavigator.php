<?php

namespace Blemli\FormSettings\Support;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Page;
use Throwable;

class RecordNavigator
{
    public static function nextUrl(EditRecord $page): ?string
    {
        try {
            $record = $page->getRecord();
            $resource = $page::getResource();
            $keyName = $record->getKeyName();

            $next = $resource::getEloquentQuery()
                ->where($keyName, '>', $record->getKey())
                ->orderBy($keyName)
                ->first();

            return $next ? $resource::getUrl('edit', ['record' => $next]) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function indexUrl(Page $page): ?string
    {
        try {
            return $page::getResource()::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }
}
