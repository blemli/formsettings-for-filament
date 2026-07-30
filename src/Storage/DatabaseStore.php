<?php

namespace Blemli\FormSettings\Storage;

use Blemli\FormSettings\Models\FormSetting;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class DatabaseStore implements SettingsStore
{
    public function get(string $key): ?array
    {
        return $this->query($key)->whereNull('preset')->first()?->settings;
    }

    public function put(string $key, array $settings): void
    {
        FormSetting::query()->updateOrCreate(
            [...$this->userColumns(), 'key' => $key, 'preset' => null],
            ['settings' => $settings],
        );
    }

    public function forget(string $key): void
    {
        $this->query($key)->whereNull('preset')->delete();
    }

    public function listPresets(string $key): array
    {
        return $this->query($key)->whereNotNull('preset')->orderBy('preset')->pluck('preset')->all();
    }

    public function getPreset(string $key, string $name): ?array
    {
        return $this->query($key)->where('preset', $name)->first()?->settings;
    }

    public function putPreset(string $key, string $name, array $settings): void
    {
        FormSetting::query()->updateOrCreate(
            [...$this->userColumns(), 'key' => $key, 'preset' => $name],
            ['settings' => $settings],
        );
    }

    public function deletePreset(string $key, string $name): void
    {
        $this->query($key)->where('preset', $name)->delete();
    }

    /**
     * @return Builder<FormSetting>
     */
    protected function query(string $key): Builder
    {
        return FormSetting::query()->where([...$this->userColumns(), 'key' => $key]);
    }

    /**
     * @return array{user_type: string|null, user_id: int|string|null}
     */
    protected function userColumns(): array
    {
        try {
            $user = Filament::auth()->user();
        } catch (Throwable) {
            $user = auth()->user();
        }

        return [
            'user_type' => $user ? $user::class : null,
            'user_id' => $user?->getAuthIdentifier(),
        ];
    }
}
