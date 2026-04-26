<?php

namespace App\Commands;

use App\CloneCreator;
use App\ProjectResolver;
use App\Shell;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class CreateCloneCommand extends Command
{
    protected $signature   = 'cow:create {project} {--branch=} {--pr=} {--json}';
    protected $description = 'Create a clone from a branch name or PR number';

    public function handle(): int
    {
        try {
            $project = ProjectResolver::byName($this->argument('project'));
            $branch  = $this->option('branch');
            $pr      = $this->option('pr');

            if (($branch === null) === ($pr === null)) {
                throw new InvalidArgumentException('Provide exactly one of --branch or --pr');
            }

            if ($pr !== null) {
                [$branch, $cloneName] = $this->resolveFromPr((int) $pr, $project->path());
            } else {
                $cloneName = CloneCreator::cloneNameFromBranch($branch);
            }

            $dest = (new CloneCreator())->create($project, $branch, $cloneName);

            if ($this->option('json')) {
                $this->line(json_encode(['name' => $cloneName, 'branch' => $branch, 'path' => $dest]));
                return Command::SUCCESS;
            }

            $this->info("✓ Done: $dest [$branch]");
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

    private function resolveFromPr(int $prNumber, string $source): array
    {
        $json = Shell::run("gh pr view $prNumber --json headRefName,title", $source);
        $data = json_decode($json, true);

        $branch = $data['headRefName'] ?? '';
        $title  = $data['title'] ?? '';

        if ($branch === '') {
            throw new RuntimeException("Could not determine branch for PR #$prNumber");
        }

        return [$branch, CloneCreator::cloneNameFromPr($prNumber, $title)];
    }
}
