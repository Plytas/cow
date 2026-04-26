<?php

namespace App\Commands;

use App\ProjectResolver;
use App\Shell;
use App\Valet;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class ActivateCloneCommand extends Command
{
    protected $signature   = 'cow:activate {project} {clone} {--json}';
    protected $description = 'Activate a clone by relinking valet and restarting PHP services';

    public function handle(): int
    {
        try {
            $project = ProjectResolver::byName($this->argument('project'));

            if ($project->valetType() === 'proxy') {
                throw new InvalidArgumentException("Project '{$project->name()}' is a proxy — cannot activate clones");
            }

            $path   = ProjectResolver::clone($project, $this->argument('clone'));
            $domain = $project->domain();

            Shell::run('valet link --secure ' . escapeshellarg($domain), $path);

            $services = Valet::runningPhpServices();

            if ($services !== []) {
                $results = Process::concurrently(function (Pool $pool) use ($services) {
                    foreach ($services as $service) {
                        $pool->as($service)->command('brew services restart ' . escapeshellarg($service));
                    }
                });

                foreach ($results as $key => $result) {
                    if (!$result->successful()) {
                        throw new RuntimeException("Failed to restart $key: " . ($result->errorOutput() ?: $result->output()));
                    }
                }
            }

            if ($this->option('json')) {
                $this->line(json_encode([
                    'activated'          => true,
                    'path'               => $path,
                    'services_restarted' => $services,
                ]));
                return Command::SUCCESS;
            }

            $this->info("✓ Activated $path");
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
