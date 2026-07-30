<?php

namespace Blemli\FormSettings\Storage;

interface SettingsStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function put(string $key, array $settings): void;

    public function forget(string $key): void;

    /**
     * @return array<string>
     */
    public function listPresets(string $key): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getPreset(string $key, string $name): ?array;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function putPreset(string $key, string $name, array $settings): void;

    public function deletePreset(string $key, string $name): void;
}
