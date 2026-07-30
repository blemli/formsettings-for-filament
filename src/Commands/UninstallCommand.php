<?php

namespace Blemli\FormSettings\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
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
            public_path('css/blemli/formsettings-for-filament'),
            public_path('js/blemli/formsettings-for-filament'),
            ...(glob(database_path('migrations/*_create_formsettings_table.php')) ?: []),
        ], fn (string $path): bool => File::exists($path));

        $this->info('The following will be removed:');
        $this->line("  - database table: {$table}");

        foreach ($publishedPaths as $path) {
            $this->line("  - {$path}");
        }

        if (Schema::hasTable($table) && ($this->option('force') || confirm("Drop the {$table} database table (all saved form settings and presets will be lost)?"))) {
            Schema::drop($table);
        }

        if ($publishedPaths !== [] && ($this->option('force') || confirm('Delete published config, translations, views, migrations and assets?'))) {
            foreach ($publishedPaths as $path) {
                File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
            }

            foreach ([public_path('css/blemli'), public_path('js/blemli')] as $dir) {
                if (File::isDirectory($dir) && File::files($dir) === [] && File::directories($dir) === []) {
                    File::deleteDirectory($dir);
                }
            }
        }

        $registrations = $this->panelProviderRegistrations();

        if ($registrations !== []) {
            $this->warn('FormSettingsPlugin is still registered in your panel provider(s) — remove the ->plugin(FormSettingsPlugin::make()...) call or the app will crash after composer remove:');

            foreach ($registrations as $location) {
                $this->line("  - {$location}");
            }
        }

        if (! $this->option('force') && confirm('Run "composer remove blemli/formsettings-for-filament" now?', default: $registrations === [])) {
            Process::path(base_path())
                ->forever()
                ->run(['composer', 'remove', 'blemli/formsettings-for-filament'], fn (string $type, string $output) => $this->output->write($output));

            $this->info('formsettings-for-filament was uninstalled.');
        } else {
            $this->info('formsettings-for-filament was uninstalled. Finish with: composer remove blemli/formsettings-for-filament');
        }

        return self::SUCCESS;
    }

    /**
     * Find FormSettingsPlugin registrations in the app's providers so
     * the user can remove them before the package code disappears.
     *
     * @return array<string>
     */
    protected function panelProviderRegistrations(): array
    {
        $locations = [];

        if (! File::isDirectory(app_path('Providers'))) {
            return $locations;
        }

        foreach (File::allFiles(app_path('Providers')) as $file) {
            foreach (explode("\n", File::get($file->getPathname())) as $index => $line) {
                if (str_contains($line, 'FormSettingsPlugin')) {
                    $locations[] = $file->getPathname() . ':' . ($index + 1);
                }
            }
        }

        return $locations;
    }
}
