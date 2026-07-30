<?php

namespace Blemli\FormSettings\Commands;

use Illuminate\Console\Command;

class FormSettingsCommand extends Command
{
    public $signature = 'formsettings-for-filament';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
