<?php

namespace App\Commands;

use App\Commands\Concerns\HandlesCommandErrors;
use App\ProjectResolver;
use App\Shell;
use App\Valet;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;

class ActivateCloneCommand extends Command
{
    use HandlesCommandErrors;

    protected $signature   = 'cow:activate {project} {clone} {--json}';
    protected $description = 'Activate a clone by relinking valet and restarting PHP services';

    public function handle(): int
    {
        return $this->respond(function () {
            $project = ProjectResolver::byName($this->argument('project'));

            if ($project->valetType() === 'proxy') {
                throw new InvalidArgumentException("Project '{$project->name()}' is a proxy — cannot activate clones");
            }

            $path   = ProjectResolver::clone($project, $this->argument('clone'));
            $domain = $project->domain();

            Shell::run('valet link --secure ' . escapeshellarg($domain), $path);

            $services = Valet::restartPhpServices();

            $this->jsonOrInfo(
                ['activated' => true, 'path' => $path, 'services_restarted' => $services],
                "✓ Activated $path",
            );
        });
    }
}
