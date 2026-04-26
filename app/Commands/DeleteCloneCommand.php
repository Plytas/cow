<?php

namespace App\Commands;

use App\ProjectResolver;
use App\Shell;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;

class DeleteCloneCommand extends Command
{
    protected $signature   = 'cow:delete {project} {clone} {--force} {--json}';
    protected $description = 'Delete a clone';

    public function handle(): int
    {
        try {
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
                    return Command::SUCCESS;
                }
            } elseif (!$this->option('force') && $this->option('json')) {
                throw new InvalidArgumentException("Pass --force to delete non-interactively");
            }

            Shell::run('rm -rf ' . escapeshellarg($path));

            if ($this->option('json')) {
                $this->line(json_encode(['deleted' => true, 'name' => $cloneName, 'path' => $path]));
                return Command::SUCCESS;
            }

            $this->info("✓ Deleted $path");
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
