<?php

namespace App\Commands;

use Humbug\SelfUpdate\Updater as PharUpdater;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Components\Updater\Updater;
use ReflectionProperty;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class SelfUpdateCommand extends Command
{
    protected $name = 'self-update';
    protected $description = 'Update cow to the latest version';

    public function handle(Updater $updater): void
    {
        $this->output->title('Checking for a new version...');

        try {
            $phar = $this->pharUpdater($updater);

            if (!$phar->update()) {
                info('Already on the latest version.');
                return;
            }

            info('Updated from ' . $phar->getOldVersion() . ' to ' . $phar->getNewVersion() . '.');
        } catch (Throwable $e) {
            error('Update failed: ' . $e->getMessage());
        }
    }

    private function pharUpdater(Updater $updater): PharUpdater
    {
        $ref = new ReflectionProperty(Updater::class, 'updater');

        return $ref->getValue($updater);
    }
}
