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

            // After replacePhar() rename()s the new bytes over the running PHAR,
            // the PHAR's autoloader backing is gone. Two consequences:
            //
            //   1. info()/error() (Laravel Prompts) lazy-load Note and a renderer
            //      the first time they're called — after the replace, those
            //      autoloads silently fail and the success message disappears.
            //      We use $this->output (Symfony OutputInterface, already loaded)
            //      instead.
            //
            //   2. PHP's shutdown sequence runs after handle() returns and trips
            //      on the replaced PHAR (exit 255 with no further output). We
            //      exit(0) here to skip framework/PHAR-engine teardown entirely.
            $old = $phar->getOldVersion();
            $new = $phar->getNewVersion();

            $this->output->writeln("<info>✓ Updated from $old to $new.</info>");
            exit(self::SUCCESS);
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
