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
        $this->setPresetPublished($key, $name, false);
    }

    public function setPresetPublished(string $key, string $name, bool $published): void
    {
        $names = array_diff($this->publishedPresetNames($key), [$name]);

        if ($published) {
            $names[] = $name;
        }

        session()->put("formsettings-published.{$key}", array_values($names));
    }

    public function publishedPresetNames(string $key): array
    {
        return session()->get("formsettings-published.{$key}", []);
    }

    /**
     * Sessions are per user, so there are never presets from others.
     */
    public function sharedPresets(string $key): array
    {
        return [];
    }

    public function getSharedPreset(string $key, string $user, string $name): ?array
    {
        return null;
    }

    public function hiddenSharedPresets(string $key): array
    {
        return session()->get("formsettings-hidden-shared.{$key}", []);
    }

    public function putHiddenSharedPresets(string $key, array $ids): void
    {
        session()->put("formsettings-hidden-shared.{$key}", array_values($ids));
    }

    public function getUsage(string $key): array
    {
        return session()->get("formsettings-usage.{$key}", []);
    }

    public function putUsage(string $key, array $usage): void
    {
        session()->put("formsettings-usage.{$key}", $usage);
    }
}
