<?php

namespace Blemli\FormSettings\Storage;

use Blemli\FormSettings\Models\FormSetting;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class DatabaseStore implements SettingsStore
{
    /**
     * Reserved preset name for the row that stores which shared
     * presets the user has hidden — never shown as a preset itself.
     */
    protected const HIDDEN_SHARED = '__formsettings_hidden_shared__';

    /**
     * Reserved preset name for the row holding learn() usage stats.
     * Public so the graveyard command can aggregate across users.
     */
    public const USAGE = '__formsettings_usage__';

    /**
     * @var array<string>
     */
    protected const RESERVED = [self::HIDDEN_SHARED, self::USAGE];

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
        return $this->presetQuery($key)->orderBy('preset')->pluck('preset')->all();
    }

    public function getPreset(string $key, string $name): ?array
    {
        return $this->presetQuery($key)->where('preset', $name)->first()?->settings;
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
        $this->presetQuery($key)->where('preset', $name)->delete();
    }

    public function setPresetPublished(string $key, string $name, bool $published): void
    {
        $this->presetQuery($key)->where('preset', $name)->update(['published' => $published]);
    }

    public function publishedPresetNames(string $key): array
    {
        return $this->presetQuery($key)->where('published', true)->orderBy('preset')->pluck('preset')->all();
    }

    public function sharedPresets(string $key): array
    {
        $user = $this->userColumns();

        $rows = FormSetting::query()
            ->where('key', $key)
            ->where('published', true)
            ->whereNotNull('preset')
            ->whereNotIn('preset', self::RESERVED)
            ->orderBy('preset')
            ->get()
            ->reject(fn (FormSetting $row): bool => $row->user_type === $user['user_type']
                && (string) $row->user_id === (string) $user['user_id']);

        $owners = $this->ownerFirstNames($rows);

        return $rows
            ->map(fn (FormSetting $row): array => [
                'user' => (string) $row->user_id,
                'name' => (string) $row->preset,
                'owner' => $owners[$row->user_type . '|' . $row->user_id] ?? null,
            ])
            ->values()
            ->all();
    }

    public function getSharedPreset(string $key, string $user, string $name): ?array
    {
        return FormSetting::query()
            ->where('key', $key)
            ->where('published', true)
            ->where('user_id', $user)
            ->where('preset', $name)
            ->first()
            ?->settings;
    }

    public function hiddenSharedPresets(string $key): array
    {
        $settings = $this->query($key)->where('preset', self::HIDDEN_SHARED)->first()?->settings;

        return array_values((array) ($settings['ids'] ?? []));
    }

    public function putHiddenSharedPresets(string $key, array $ids): void
    {
        if ($ids === []) {
            $this->query($key)->where('preset', self::HIDDEN_SHARED)->delete();

            return;
        }

        FormSetting::query()->updateOrCreate(
            [...$this->userColumns(), 'key' => $key, 'preset' => self::HIDDEN_SHARED],
            ['settings' => ['ids' => array_values($ids)]],
        );
    }

    public function getUsage(string $key): array
    {
        return $this->query($key)->where('preset', self::USAGE)->first()->settings ?? [];
    }

    public function putUsage(string $key, array $usage): void
    {
        FormSetting::query()->updateOrCreate(
            [...$this->userColumns(), 'key' => $key, 'preset' => self::USAGE],
            ['settings' => $usage],
        );
    }

    public function migrateKey(string $from, string $to): void
    {
        foreach ($this->query($from)->get() as $row) {
            $collides = $this->query($to)
                ->when(
                    $row->preset === null,
                    fn (Builder $query) => $query->whereNull('preset'),
                    fn (Builder $query) => $query->where('preset', $row->preset),
                )
                ->exists();

            $collides ? $row->delete() : $row->update(['key' => $to]);
        }
    }

    /**
     * @return Builder<FormSetting>
     */
    protected function query(string $key): Builder
    {
        return FormSetting::query()->where([...$this->userColumns(), 'key' => $key]);
    }

    /**
     * The current user's real presets — excludes the reserved
     * hidden-shared bookkeeping row.
     *
     * @return Builder<FormSetting>
     */
    protected function presetQuery(string $key): Builder
    {
        return $this->query($key)
            ->whereNotNull('preset')
            ->whereNotIn('preset', self::RESERVED);
    }

    /**
     * Resolve owner display names ("Firstname L.") in one query per
     * user model class instead of one per preset.
     *
     * @param  Collection<int, FormSetting>  $rows
     * @return array<string, string|null>
     */
    protected function ownerFirstNames(Collection $rows): array
    {
        $names = [];

        foreach ($rows->groupBy('user_type') as $type => $group) {
            $class = (string) $type;

            if ($class === '' || ! class_exists($class)) {
                continue;
            }

            try {
                $owners = $class::query()->findMany($group->pluck('user_id')->unique()->all());

                foreach ($owners as $owner) {
                    $name = method_exists($owner, 'getFilamentName')
                        ? $owner->getFilamentName()
                        : $owner->name;

                    $names[$class . '|' . $owner->getKey()] = $this->shortName((string) $name);
                }
            } catch (Throwable) {
                // Owners stay anonymous when the user model misbehaves.
            }
        }

        return $names;
    }

    protected function shortName(string $name): ?string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return null;
        }

        $short = $words[0];

        if (count($words) > 1) {
            $short .= ' ' . mb_substr(end($words), 0, 1) . '.';
        }

        return $short;
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
