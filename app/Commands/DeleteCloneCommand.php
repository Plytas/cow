<?php

namespace App\Commands;

use App\CloneCreator;
use App\Commands\Concerns\HandlesCommandErrors;
use App\ProjectResolver;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;

class DeleteCloneCommand extends Command
{
    use HandlesCommandErrors;

    protected $signature   = 'cow:delete {project} {clone} {--force} {--json}';
    protected $description = 'Delete a clone';

    public function handle(): int
    {
        return $this->respond(function () {
            $project   = ProjectResolver::byName($this->argument('project'));
            $cloneName = $this->argument('clone');

            if ($cloneName === 'main') {
                throw new InvalidArgumentException("Cannot delete 'main' — it is the source repository");
            }

            $path       = ProjectResolver::clone($project, $cloneName);
            $activePath = $project->valetType() === 'link' ? $project->activePath() : null;

            if ($activePath !== null && rtrim($activePath, '/') === rtrim($path, '/')) {
                throw new InvalidArgumentException("Cannot delete the currently active clone '$cloneName'. Activate another clone first.");
            }

            if (!$this->option('force') && !$this->option('json')) {
                if (!confirm("Delete clone '$cloneName' at $path?", false)) {
                    return;
                }
            } elseif (!$this->option('force') && $this->option('json')) {
                throw new InvalidArgumentException("Pass --force to delete non-interactively");
            }

            (new CloneCreator())->deleteTree($path);

            $this->jsonOrInfo(
                ['deleted' => true, 'name' => $cloneName, 'path' => $path],
                "✓ Deleted $path",
            );
        });
    }
}
