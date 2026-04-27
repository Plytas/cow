<?php

namespace App\Commands;

use App\Commands\Concerns\HandlesCommandErrors;
use App\Config;
use App\ProjectResolver;
use App\Shell;
use LaravelZero\Framework\Commands\Command;

class OpenCommand extends Command
{
    use HandlesCommandErrors;

    protected $signature   = 'cow:open {project} {clone=main} {--json}';
    protected $description = 'Open a clone in the configured IDE';

    public function handle(): int
    {
        return $this->respond(function () {
            $project = ProjectResolver::byName($this->argument('project'));
            $path    = ProjectResolver::clone($project, $this->argument('clone'));
            $ide     = Config::ideCommand();

            Shell::quietly($ide . ' ' . escapeshellarg($path));

            $this->jsonOrInfo(
                ['opened' => true, 'path' => $path, 'ide' => $ide],
                "✓ Opened $path in $ide",
            );
        });
    }
}
