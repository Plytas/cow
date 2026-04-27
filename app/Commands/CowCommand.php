<?php

namespace App\Commands;

use App\CloneCreator;
use App\CloneDir;
use App\Config;
use App\Project;
use App\SetupWizard;
use App\Shell;
use App\Valet;
use Closure;
use Illuminate\Support\Str;
use DateTimeImmutable;
use Exception;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Support\Logger;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\task;
use function Laravel\Prompts\text;

class CowCommand extends Command
{
    use Colors;

    private const ACTION_QUIT             = '__quit__';
    private const ACTION_NEW_CLONE        = '__new_clone__';
    private const ACTION_NEW_CLONE_PR     = '__new_clone_pr__';
    private const ACTION_NEW_CLONE_BRANCH = '__new_clone_branch__';
    private const ACTION_CHANGE_PROJECT   = '__change_project__';
    private const ACTION_ADD_PROJECT      = '__add_project__';
    private const TARGET_MAIN             = 'main';

    protected $signature   = 'cow';
    protected $description = 'Manage CoW project clones';

    private Project $project;

    public function handle(): void
    {
        SetupWizard::runIfNeeded();

        $this->project = $this->detectOrSelectProject();

        do {
            $choice = $this->showMainMenu();

            match ($choice) {
                self::ACTION_QUIT           => null,
                self::ACTION_CHANGE_PROJECT => $this->project = $this->selectProject(),
                self::ACTION_NEW_CLONE        => $this->newCloneFromText(),
                self::ACTION_NEW_CLONE_PR     => $this->newCloneFromPr(),
                self::ACTION_NEW_CLONE_BRANCH => $this->newCloneFromBranch(),
                default                     => $this->showCloneActionMenu($choice),
            };
        } while ($choice !== self::ACTION_QUIT);
    }

    // ─── Project Selection ──────────────────────────────────────────────────

    private function detectOrSelectProject(): Project
    {
        $cwd = getcwd();

        foreach (Config::projects() as $projectData) {
            $project = new Project($projectData);
            if (str_starts_with($cwd, $project->path()) || str_starts_with($cwd, $project->clonesDir())) {
                return $project;
            }
        }

        return $this->selectProject();
    }

    private function selectProject(): Project
    {
        $projects = Config::projects();
        $names    = array_column($projects, 'name');
        $options  = array_combine($names, $names);
        $options[self::ACTION_ADD_PROJECT] = '+ Add project';

        $selected = $this->askMenu('Select a project', $options);

        return match ($selected) {
            self::ACTION_ADD_PROJECT => $this->addProject(),
            default                  => new Project($projects[array_search($selected, $names)]),
        };
    }

    private function addProject(): Project
    {
        $data = SetupWizard::promptForProject();

        $config               = Config::load();
        $config['projects'][] = $data;
        Config::save($config);

        info("Project \"{$data['name']}\" added.");

        return new Project($data);
    }

    // ─── Main Menu ──────────────────────────────────────────────────────────

    private function showMainMenu(): string
    {
        [$options, $meta] = $this->buildMainMenu();

        return $this->askMenu(
            label: $this->project->name(),
            options: $options,
            info: fn(?string $value) => $meta[$value] ?? '',
        );
    }

    private function buildMainMenu(): array
    {
        $project    = $this->project;
        $activePath = $project->valetType() === 'link' ? $project->activePath() : null;

        $options = [];
        $meta    = [];

        foreach ($this->menuEntries($project, $activePath) as [$name, $branch, $active]) {
            $label          = $active ? $this->green($name . '  ✓') : $name;
            $options[$name] = $label;
            $meta[$name]    = '⎇ ' . $branch;
        }

        $options[self::ACTION_NEW_CLONE]        = '+ New clone';
        $options[self::ACTION_NEW_CLONE_PR]     = '+ New clone from PR';
        $options[self::ACTION_NEW_CLONE_BRANCH] = '+ New clone from branch';
        $options[self::ACTION_CHANGE_PROJECT]   = '⇄ Change project';
        $options[self::ACTION_QUIT]             = '✕ Quit';

        return [$options, $meta];
    }

    private function menuEntries(Project $project, ?string $activePath): array
    {
        $entries = [[
            self::TARGET_MAIN,
            $this->gitBranch($project->path()),
            $this->isActive($project->path(), $activePath),
        ]];

        foreach ($project->clones() as $clone) {
            $entries[] = [
                $clone->name(),
                $clone->branch(),
                $this->isActive($clone->path(), $activePath),
            ];
        }

        return $entries;
    }

    // ─── Clone Action Menu ──────────────────────────────────────────────────

    private function showCloneActionMenu(string $name): void
    {
        $path = $this->resolveTargetPath($name);

        if ($path === null) {
            error("Clone '$name' not found.");
            return;
        }

        $project  = $this->project;
        $isActive = $project->valetType() === 'link' && $this->isActive($path, $project->activePath());
        $info     = trim($this->gitBranch($path) . '  ' . ($isActive ? '✓ active' : ''));

        $action = $this->askMenu(
            label: $name,
            options: $this->buildCloneActions($name, $project->valetType()),
            info: fn(?string $_) => $info,
        );

        match ($action) {
            'activate' => $this->switchClone($path, $project->domain()),
            'ide'    => $this->openInIde($path),
            'delete' => $this->deleteClone($path, $project),
            'back'   => null,
        };
    }

    private function resolveTargetPath(string $name): ?string
    {
        if ($name === self::TARGET_MAIN) {
            return $this->project->path();
        }

        return $this->findClone($this->project->clones(), $name)?->path();
    }

    private function buildCloneActions(string $name, string $valetType): array
    {
        $options = [];

        if ($valetType !== 'proxy') {
            $options['activate'] = $valetType === 'link' ? 'Activate (relink valet)' : 'Activate (link valet)';
        }

        $options['ide'] = 'Open in IDE';

        if ($name !== self::TARGET_MAIN) {
            $options['delete'] = 'Delete';
        }

        $options['back'] = '←  Back';

        return $options;
    }

    // ─── Clone Actions ──────────────────────────────────────────────────────

    private function switchClone(string $clonePath, string $domain): void
    {
        $escapedDomain = escapeshellarg($domain);

        task(
            label: 'Switching valet link',
            callback: function (Logger $logger) use ($clonePath, $domain, $escapedDomain) {
                $this->timed($logger, "Linked $domain", fn() => Shell::stream(
                    $logger,
                    "valet link --secure $escapedDomain",
                    $clonePath,
                ));

                // `valet restart` runs brew services stop/start which, once the plist is loaded,
                // treats the service as already-running and does NOT actually kill the FPM master.
                // Workers keep serving with stale realpath_cache (TTL 120s). `brew services restart`
                // (user scope, no sudo) actually tears the process down and relaunches it.
                // Restart every running php* service since projects may span multiple PHP versions.
                $services = Valet::runningPhpServices();

                if ($services !== []) {
                    $this->timed($logger, 'Restarted ' . implode(', ', $services), function () use ($logger, $services) {
                        $logger->subLabel('brew services restart ' . implode(' ', $services) . ' (parallel)');

                        Valet::restartPhpServices(fn(string $key, string $line) => $logger->line("[$key] $line"));
                    });
                }

                return true;
            },
            keepSummary: true,
        );

        info("Switched to $clonePath");
    }

    private function openInIde(string $path): void
    {
        Shell::quietly(Config::ideCommand() . ' ' . escapeshellarg($path));
    }

    private function deleteClone(string $path, Project $project): void
    {
        if ($project->valetType() === 'link' && $this->isActive($path, $project->activePath())) {
            error('Cannot delete the currently active clone. Switch to another first.');
            return;
        }

        $name = basename($path);

        if (!confirm("Delete $name?", false)) {
            return;
        }

        task(
            label: 'Deleting clone',
            callback: function (Logger $logger) use ($path, $name) {
                $this->timed($logger, "Deleted $name", function () use ($logger, $path) {
                    $logger->subLabel("rename + async rm $path");
                    (new CloneCreator())->deleteTree($path);
                });
                return true;
            },
            keepSummary: true,
        );

        info("Deleted $name");
    }

    // ─── New Clone Flow ─────────────────────────────────────────────────────

    private function newCloneFromText(): void
    {
        $branch = text(
            label:       'Branch name',
            placeholder: 'feat/my-feature',
            required:    true,
            hint:        'The branch will be checked out in the new clone.',
            transform:   fn(string $v) => Str::slug($v),
        );

        $this->createClone($branch, CloneCreator::cloneNameFromBranch($branch));
    }

    private function newCloneFromPr(): void
    {
        $source = $this->project->path();

        try {
            $prs = spin(
                fn() => json_decode(
                    Shell::run('gh pr list --json number,title,headRefName,author,createdAt,isDraft --limit 200', $source),
                    true,
                ),
                'Fetching PRs…',
            );
        } catch (RuntimeException $e) {
            error('gh error: ' . $e->getMessage());
            return;
        }

        if (empty($prs)) {
            info('No open PRs found.');
            return;
        }

        $options = [];
        $info    = [];

        foreach ($prs as $pr) {
            $label           = sprintf('#%s  %s', $pr['number'], $pr['title']);
            $options[$label] = $label;

            $parts = ['⎇ ' . $pr['headRefName'], 'by ' . $pr['author']['login']];
            $parts[] = $this->relativeDate($pr['createdAt']);
            if ($pr['isDraft']) {
                $parts[] = '[Draft]';
            }
            $info[$label] = implode('  ·  ', $parts);
        }

        $selected = search(
            label:       'Select a PR',
            options:     fn(string $q) => array_filter($options, fn($l) => str_contains(strtolower($l), strtolower($q))),
            placeholder: 'Search by number or title',
            info:        fn(?string $v) => $info[$v] ?? '',
        );

        $pr        = collect($prs)->firstWhere('number', (int) ltrim(explode(' ', $selected)[0], '#'));
        $branch    = $pr['headRefName'];
        $cloneName = CloneCreator::cloneNameFromPr((int) $pr['number'], $pr['title']);

        $this->createClone($branch, $cloneName);
    }

    private function newCloneFromBranch(): void
    {
        $source = $this->project->path();

        try {
            // Fetch branch name + last commit subject + relative date in one pass
            $raw = spin(
                fn() => Shell::run(
                    "git for-each-ref refs/remotes --format='%(refname:short)|%(subject)|%(authorname)|%(committerdate:relative)'",
                    $source,
                ),
                'Fetching branches…',
            );
        } catch (RuntimeException $e) {
            error('git error: ' . $e->getMessage());
            return;
        }

        $options = [];
        $info    = [];

        foreach (explode("\n", trim($raw)) as $line) {
            [$ref, $subject, $author, $date] = array_pad(explode('|', $line, 4), 4, '');

            $ref = trim($ref);

            if ($ref === '' || str_contains($ref, 'HEAD')) {
                continue;
            }

            $short           = preg_replace('#^[^/]+/#', '', $ref);
            $options[$short] = $short;
            $info[$short]    = implode('  ·  ', array_filter([trim($subject), trim($author), trim($date)]));
        }

        $branch = search(
            label:       'Select a branch',
            options:     fn(string $q) => array_filter($options, fn($b) => str_contains(strtolower($b), strtolower($q))),
            placeholder: 'Search by branch name',
            info:        fn(?string $v) => $info[$v] ?? '',
        );

        $this->createClone($branch, CloneCreator::cloneNameFromBranch($branch));
    }

    private function createClone(string $branch, string $cloneName): void
    {
        $creator = new CloneCreator();
        $source  = $this->project->path();

        try {
            $dest = $creator->prepareDestination($this->project, $cloneName);
        } catch (RuntimeException $e) {
            error($e->getMessage());
            return;
        }

        task(
            label: "Creating clone $cloneName",
            callback: function (Logger $logger) use ($creator, $source, $dest, $branch) {
                $this->timed($logger, 'Copied source', function () use ($logger, $creator, $source, $dest) {
                    $logger->subLabel("clonefile($source, $dest)");
                    $creator->cloneTree($source, $dest);
                });

                $this->timed($logger, "Checked out $branch", function () use ($logger, $creator, $dest, $branch) {
                    $logger->subLabel("git checkout $branch");
                    $creator->checkoutBranch($dest, $branch, fn(string $line) => $logger->line($line));
                });

                if (CloneCreator::composerLockDiffers($source, $dest)) {
                    $this->timed($logger, 'Installed composer dependencies', function () use ($logger, $creator, $source, $dest) {
                        $logger->subLabel('composer install');
                        $creator->composerInstallIfNeeded($source, $dest, fn(string $line) => $logger->line($line));
                    });
                }

                return true;
            },
            keepSummary: true,
        );

        info("✓ Done: $dest [$branch]");
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function askMenu(string $label, array $options, ?Closure $info = null): string
    {
        return search(
            label: $label,
            options: fn(string $query) => array_filter(
                $options,
                fn(string $optionLabel) => str_contains(strtolower($optionLabel), strtolower($query)),
            ),
            placeholder: 'Quick search ...',
            hint: 'Type to search, ↑↓ to navigate, enter to select.',
            info: $info ?? (fn(?string $_) => ''),
        );
    }

    private function gitBranch(string $path): string
    {
        try {
            return Shell::run('git -C ' . escapeshellarg($path) . ' branch --show-current');
        } catch (RuntimeException) {
            return '?';
        }
    }

    private function isActive(string $path, ?string $activePath): bool
    {
        if ($activePath === null) {
            return false;
        }

        return rtrim($activePath, '/') === rtrim($path, '/');
    }

    private function timed(Logger $logger, string $label, Closure $callback): mixed
    {
        $start  = microtime(true);
        $result = $callback();
        $logger->success(sprintf('%s (%s)', $label, $this->formatDuration(microtime(true) - $start)));

        return $result;
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%dms', (int) round($seconds * 1000));
        }

        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        return sprintf('%dm%ds', (int) ($seconds / 60), (int) $seconds % 60);
    }

    /** @param CloneDir[] $clones */
    private function findClone(array $clones, string $name): ?CloneDir
    {
        return array_find($clones, fn(CloneDir $c) => $c->name() === $name);
    }

    private function relativeDate(string $isoDate): string
    {
        try {
            $diff = (new DateTimeImmutable($isoDate))->diff(new DateTimeImmutable());
        } catch (Exception) {
            return $isoDate;
        }

        return match (true) {
            $diff->days === 0 => 'today',
            $diff->days === 1 => 'yesterday',
            $diff->days < 30  => $diff->days . ' days ago',
            $diff->days < 365 => floor($diff->days / 30) . ' months ago',
            default           => floor($diff->days / 365) . ' years ago',
        };
    }
}
