<?php

namespace Blemli\FormSettings\Storage;

class SessionStore implements SettingsStore
{
    public function get(string $key): ?array
    {
        return session()->get("formsettings.{$key}");
    }

    public function put(string $key, array $settings): void
    {
        session()->put("formsettings.{$key}", $settings);
    }

    public function forget(string $key): void
    {
        session()->forget("formsettings.{$key}");
    }

    public function listPresets(string $key): array
    {
        return array_keys(session()->get("formsettings-presets.{$key}", []));
    }

    public function getPreset(string $key, string $name): ?array
    {
        return session()->get("formsettings-presets.{$key}.{$name}");
    }

    public function putPreset(string $key, string $name, array $settings): void
    {
        session()->put("formsettings-presets.{$key}.{$name}", $settings);
    }

    public function deletePreset(string $key, string $name): void
    {
        session()->forget("formsettings-presets.{$key}.{$name}");
    }
}
