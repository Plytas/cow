<?php

namespace App\Commands;

use App\Config;
use App\Project;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class ProjectsCommand extends Command
{
    protected $signature   = 'cow:projects {--json}';
    protected $description = 'List configured projects';

    public function handle(): int
    {
        try {
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
                return Command::SUCCESS;
            }

            foreach ($projects as $project) {
                $this->line("{$project['name']}  {$project['domain']}  {$project['path']}");
            }

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
