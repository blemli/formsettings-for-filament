<?php

namespace Blemli\FormSettings\Commands;

use Blemli\FormSettings\Models\FormSetting;
use Blemli\FormSettings\Storage\DatabaseStore;
use Illuminate\Console\Command;

class GraveyardCommand extends Command
{
    protected $signature = 'formsettings:graveyard
        {--min-runs=10 : Minimum recorded runs before a form is reported}
        {--threshold=20 : Report fields touched in at most this percentage of runs}';

    protected $description = 'Fields your users rarely touch, aggregated across all users (field names only, never values)';

    public function handle(): int
    {
        $rows = FormSetting::query()->where('preset', DatabaseStore::USAGE)->get();

        if ($rows->isEmpty()) {
            $this->components->info('No usage data yet — enable learn() with persist() and let your users work for a while.');

            return self::SUCCESS;
        }

        $minRuns = max(1, (int) $this->option('min-runs'));
        $threshold = (int) $this->option('threshold');
        $reported = false;

        foreach ($rows->groupBy('key') as $key => $group) {
            $runs = $group->flatMap(fn (FormSetting $row) => array_values((array) ($row->settings['runs'] ?? [])));
            $universe = $group
                ->flatMap(fn (FormSetting $row) => array_values((array) ($row->settings['fields'] ?? [])))
                ->unique()
                ->values();

            $total = $runs->count();

            if ($total < $minRuns) {
                continue;
            }

            $counts = array_fill_keys($universe->all(), 0);

            foreach ($runs as $run) {
                foreach ((array) ($run['touched'] ?? []) as $field) {
                    if (is_string($field)) {
                        $counts[$field] = ($counts[$field] ?? 0) + 1;
                    }
                }
            }

            asort($counts);

            $graveyard = array_filter(
                $counts,
                fn (int $count): bool => (int) round($count / $total * 100) <= $threshold,
            );

            if ($graveyard === []) {
                continue;
            }

            $reported = true;
            $this->newLine();
            $this->components->twoColumnDetail("<fg=cyan;options=bold>{$key}</>", "{$total} runs, " . $group->count() . ' user(s)');

            foreach ($graveyard as $field => $count) {
                $percent = (int) round($count / $total * 100);

                $this->components->twoColumnDetail(
                    "  {$field}",
                    $count === 0 ? '<fg=red>never touched</>' : "<fg=yellow>{$count}/{$total} ({$percent}%)</>",
                );
            }
        }

        if (! $reported) {
            $this->components->info('No graveyard candidates — every field gets used. Nice form!');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
