<?php

namespace App\Commands;

use App\Commands\Concerns\HandlesCommandErrors;
use App\ProjectResolver;
use App\Shell;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class ListClonesCommand extends Command
{
    use HandlesCommandErrors;

    protected $signature   = 'cow:list {project} {--json}';
    protected $description = 'List clones for a project';

    public function handle(): int
    {
        return $this->respond(function () {
            $project    = ProjectResolver::byName($this->argument('project'));
            $activePath = $project->valetType() === 'link' ? $project->activePath() : null;

            $entries = [[
                'name'   => 'main',
                'branch' => $this->gitBranch($project->path()),
                'path'   => $project->path(),
                'active' => $activePath !== null && rtrim($activePath, '/') === rtrim($project->path(), '/'),
            ]];

            foreach ($project->clones() as $clone) {
                $entries[] = [
                    'name'   => $clone->name(),
                    'branch' => $clone->branch(),
                    'path'   => $clone->path(),
                    'active' => $activePath !== null && rtrim($activePath, '/') === rtrim($clone->path(), '/'),
                ];
            }

            if ($this->option('json')) {
                $this->line(json_encode($entries));
                return;
            }

            foreach ($entries as $entry) {
                $active = $entry['active'] ? '  ✓' : '';
                $this->line("$entry[name]  ⎇ $entry[branch]{$active}  $entry[path]");
            }
        });
    }

    private function gitBranch(string $path): string
    {
        try {
            return Shell::run('git -C ' . escapeshellarg($path) . ' branch --show-current');
        } catch (RuntimeException) {
            return '';
        }
    }
}
