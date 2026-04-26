<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Components\Updater\Updater;
use Throwable;

use function Laravel\Prompts\error;

class SelfUpdateCommand extends Command
{
    protected $name = 'self-update';
    protected $description = 'Update cow to the latest version';

    public function handle(Updater $updater): void
    {
        $this->output->title('Checking for a new version...');

        try {
            $updater->update($this->output);
        } catch (Throwable $e) {
            error('Update failed: ' . $e->getMessage());
        }
    }
}
