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

    /**
     * Mark one of the current user's presets as published (or private).
     */
    public function setPresetPublished(string $key, string $name, bool $published): void;

    /**
     * Names of the current user's presets that are published.
     *
     * @return array<string>
     */
    public function publishedPresetNames(string $key): array;

    /**
     * Presets other users published for this form.
     *
     * @return array<int, array{user: string, name: string, owner: string|null}>
     */
    public function sharedPresets(string $key): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getSharedPreset(string $key, string $user, string $name): ?array;

    /**
     * "user|name" identifiers of shared presets the current user has hidden.
     *
     * @return array<string>
     */
    public function hiddenSharedPresets(string $key): array;

    /**
     * @param  array<string>  $ids
     */
    public function putHiddenSharedPresets(string $key, array $ids): void;

    /**
     * Usage statistics powering learn(): recent runs (field names
     * only) and dismissed suggestion hashes.
     * Shape: {runs: array, dismissed: array}.
     *
     * @return array<string, mixed>
     */
    public function getUsage(string $key): array;

    /**
     * @param  array<string, mixed>  $usage
     */
    public function putUsage(string $key, array $usage): void;
}
