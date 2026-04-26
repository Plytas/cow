<?php

namespace App\Commands;

use App\Config;
use App\Project;
use App\Valet;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class AddProjectCommand extends Command
{
    protected $signature   = 'cow:project-add {name} {path} {--domain=} {--json}';
    protected $description = 'Add a new project to the config';

    public function handle(): int
    {
        try {
            $name   = $this->argument('name');
            $path   = rtrim($this->argument('path'), '/');
            $domain = $this->option('domain');

            if (!is_dir($path)) {
                throw new InvalidArgumentException("Path does not exist: $path");
            }

            if ($domain === null) {
                $domain = Valet::domainForPath($path)
                    ?? throw new InvalidArgumentException(
                        'Could not auto-detect valet domain. Pass --domain=<domain> explicitly.'
                    );
            }

            $data = compact('name', 'path', 'domain');

            $config               = Config::load();
            $config['projects'][] = $data;
            Config::save($config);

            $project = new Project($data);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'name'   => $project->name(),
                    'path'   => $project->path(),
                    'domain' => $project->domain(),
                    'slug'   => $project->slug(),
                ]));
                return Command::SUCCESS;
            }

            $this->info("✓ Added project \"{$project->name()}\"");
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
