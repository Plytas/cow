<?php

namespace App\Commands;

use App\Config;
use App\ProjectResolver;
use App\Shell;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class OpenCommand extends Command
{
    protected $signature   = 'cow:open {project} {clone=main} {--json}';
    protected $description = 'Open a clone in the configured IDE';

    public function handle(): int
    {
        try {
            $project = ProjectResolver::byName($this->argument('project'));
            $path    = ProjectResolver::clone($project, $this->argument('clone'));
            $ide     = Config::ideCommand();

            Shell::quietly($ide . ' ' . escapeshellarg($path));

            if ($this->option('json')) {
                $this->line(json_encode(['opened' => true, 'path' => $path, 'ide' => $ide]));
                return Command::SUCCESS;
            }

            $this->info("✓ Opened $path in $ide");
            return Command::SUCCESS;
        } catch (RuntimeException|InvalidArgumentException $e) {
            if ($this->option('json')) {
                $this->line(json_encode(['error' => $e->getMessage()]));
                return Command::FAILURE;
            }
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
