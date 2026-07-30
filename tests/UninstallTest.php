<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function fakePublishedPaths(): array
{
    return [
        config_path('formsettings-for-filament.php'),
        lang_path('vendor/formsettings-for-filament/de/formsettings.php'),
        resource_path('views/vendor/formsettings-for-filament/panel.blade.php'),
        public_path('css/blemli/formsettings-for-filament/formsettings-styles.css'),
        public_path('js/blemli/formsettings-for-filament/formsettings-scripts.js'),
        database_path('migrations/2026_07_30_000000_create_formsettings_table.php'),
    ];
}

function publishFakeArtifacts(): array
{
    $contents = [
        config_path('formsettings-for-filament.php') => '<?php return [];',
        database_path('migrations/2026_07_30_000000_create_formsettings_table.php') => <<<'PHP'
            <?php
            use Illuminate\Database\Migrations\Migration;
            return new class extends Migration
            {
                public function up(): void {}
                public function down(): void {}
            };
            PHP,
    ];

    foreach (fakePublishedPaths() as $path) {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents[$path] ?? '/* fake published artifact */');
    }

    return fakePublishedPaths();
}

afterEach(function () {
    foreach (fakePublishedPaths() as $path) {
        File::delete($path);
    }

    File::deleteDirectory(lang_path('vendor/formsettings-for-filament'));
    File::deleteDirectory(resource_path('views/vendor/formsettings-for-filament'));
    File::deleteDirectory(public_path('css/blemli'));
    File::deleteDirectory(public_path('js/blemli'));
    File::deleteDirectory(app_path('Providers/Filament'));
});

it('removes every published artifact on uninstall', function () {
    $paths = publishFakeArtifacts();

    $this->artisan('formsettings:uninstall', ['--force' => true])->assertSuccessful();

    foreach ($paths as $path) {
        expect(File::exists($path))->toBeFalse($path . ' should have been removed');
    }

    expect(File::isDirectory(public_path('css/blemli')))->toBeFalse()
        ->and(File::isDirectory(public_path('js/blemli')))->toBeFalse()
        ->and(Schema::hasTable('formsettings'))->toBeFalse();
});

it('points to panel providers that still register the plugin', function () {
    $provider = app_path('Providers/Filament/AdminPanelProvider.php');
    File::ensureDirectoryExists(dirname($provider));
    File::put($provider, "<?php\n\n// ...\n\$panel->plugin(FormSettingsPlugin::make());\n");

    $this->artisan('formsettings:uninstall', ['--force' => true])
        ->expectsOutputToContain('FormSettingsPlugin is still registered')
        ->expectsOutputToContain($provider . ':4')
        ->assertSuccessful();
});

it('prints english output regardless of the app locale', function () {
    app()->setLocale('de');
    publishFakeArtifacts();

    $this->artisan('formsettings:uninstall', ['--force' => true])
        ->expectsOutputToContain('The following will be removed:')
        ->expectsOutputToContain('formsettings-for-filament was uninstalled. Finish with: composer remove blemli/formsettings-for-filament')
        ->assertSuccessful();
});
