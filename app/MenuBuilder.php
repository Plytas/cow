<?php

namespace App;

use Laravel\Prompts\Concerns\Colors;
use RuntimeException;

/**
 * Builds the option/meta arrays consumed by the TUI's autocomplete menus.
 * Pure presentation concerns — no IO or state of its own.
 */
class MenuBuilder
{
    use Colors;

    /**
     * Top-level menu: source repo, each clone, then the global actions.
     *
     * @param  array<string, string>  $globalActions  e.g. ['__new_clone__' => '+ New clone', ...]
     * @return array{0: array<string, string>, 1: array<string, string>}  [options, meta]
     */
    public function buildMain(Project $project, ?string $activePath, string $mainTarget, array $globalActions): array
    {
        $options = [];
        $meta    = [];

        foreach ($this->mainEntries($project, $activePath, $mainTarget) as [$name, $branch, $active]) {
            $label          = $active ? $this->green($name . '  ✓') : $name;
            $options[$name] = $label;
            $meta[$name]    = '⎇ ' . $branch;
        }

        return [$options + $globalActions, $meta];
    }

    /**
     * Per-clone action menu — what you see after selecting a clone.
     *
     * @return array<string, string>
     */
    public function buildCloneActions(string $name, string $valetType, string $mainTarget): array
    {
        $options = [];

        if ($valetType !== 'proxy') {
            $options['activate'] = $valetType === 'link' ? 'Activate (relink valet)' : 'Activate (link valet)';
        }

        $options['ide'] = 'Open in IDE';

        if ($name !== $mainTarget) {
            $options['delete'] = 'Delete';
        }

        $options['back'] = '←  Back';

        return $options;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: bool}>  [name, branch, active]
     */
    private function mainEntries(Project $project, ?string $activePath, string $mainTarget): array
    {
        $entries = [[
            $mainTarget,
            self::gitBranch($project->path()),
            self::isActive($project->path(), $activePath),
        ]];

        foreach ($project->clones() as $clone) {
            $entries[] = [
                $clone->name(),
                $clone->branch(),
                self::isActive($clone->path(), $activePath),
            ];
        }

        return $entries;
    }

    private static function gitBranch(string $path): string
    {
        try {
            return Shell::run('git -C ' . escapeshellarg($path) . ' branch --show-current');
        } catch (RuntimeException) {
            return '?';
        }
    }

    private static function isActive(string $path, ?string $activePath): bool
    {
        return $activePath !== null && rtrim($activePath, '/') === rtrim($path, '/');
    }
}
