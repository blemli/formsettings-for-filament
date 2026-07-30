<?php

namespace Blemli\FormSettings\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;

class UninstallCommand extends Command
{
    public $signature = 'formsettings:uninstall {--force : Skip all confirmation prompts}';

    public $description = 'Uninstall formsettings-for-filament: drop its table and remove published files';

    public function handle(): int
    {
        $table = config('formsettings-for-filament.table', 'formsettings');

        $publishedPaths = array_filter([
            config_path('formsettings-for-filament.php'),
            lang_path('vendor/formsettings-for-filament'),
            resource_path('views/vendor/formsettings-for-filament'),
        ], fn (string $path): bool => File::exists($path));

        $this->info(__('formsettings-for-filament::formsettings.uninstall.intro'));
        $this->line("  - database table: {$table}");

        foreach ($publishedPaths as $path) {
            $this->line("  - {$path}");
        }

        if (Schema::hasTable($table) && ($this->option('force') || confirm(__('formsettings-for-filament::formsettings.uninstall.confirm_table', ['table' => $table])))) {
            Schema::drop($table);
        }

        if ($publishedPaths !== [] && ($this->option('force') || confirm(__('formsettings-for-filament::formsettings.uninstall.confirm_published')))) {
            foreach ($publishedPaths as $path) {
                File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
            }
        }

        $this->info(__('formsettings-for-filament::formsettings.uninstall.done'));

        return self::SUCCESS;
    }
}
