<?php

namespace App\Commands;

use App\Commands\Concerns\HandlesCommandErrors;
use App\Config;
use App\Project;
use LaravelZero\Framework\Commands\Command;

class ProjectsCommand extends Command
{
    use HandlesCommandErrors;

    protected $signature   = 'cow:projects {--json}';
    protected $description = 'List configured projects';

    public function handle(): int
    {
        return $this->respond(function () {
            $projects = array_map(
                fn(array $data) => [
                    'name'   => $data['name'],
                    'path'   => $data['path'],
                    'domain' => $data['domain'],
                    'slug'   => (new Project($data))->slug(),
                ],
                Config::projects(),
            );

            if ($this->option('json')) {
                $this->line(json_encode($projects));
                return;
            }

            foreach ($projects as $project) {
                $this->line("{$project['name']}  {$project['domain']}  {$project['path']}");
            }
        });
    }
}
