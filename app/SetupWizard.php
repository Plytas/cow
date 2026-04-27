<?php

namespace App;

use function Laravel\Prompts\autocomplete;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * First-run config wizard. Walks the user through choosing a clones
 * directory, IDE, and at least one project, then writes the result to
 * the config file.
 *
 * Also exposes promptForProject() for the "add project" flow used after
 * initial setup.
 */
class SetupWizard
{
    public static function runIfNeeded(): void
    {
        if (Config::exists()) {
            return;
        }

        info('Welcome to cow! Let\'s set up your config.');

        Config::save([
            'clones_dir'  => self::promptForClonesDir(),
            'ide_command' => self::promptForIde(),
            'projects'    => self::promptForInitialProjects(),
        ]);

        info('Config saved to ' . Config::path());
    }

    public static function promptForProject(): array
    {
        $name   = text(label: 'Project name', placeholder: 'My Project', required: true);
        $path   = self::promptForProjectPath();
        $domain = self::resolveDomain($path);

        return compact('name', 'path', 'domain');
    }

    private static function promptForClonesDir(): string
    {
        return autocomplete(
            label: 'Where should clones be stored?',
            options: fn(string $value) => glob(($value ?: $_SERVER['HOME'] . '/') . '*', GLOB_ONLYDIR) ?: [],
            default: $_SERVER['HOME'] . '/Code/clones',
            required: true,
            validate: fn(string $value) => !is_dir(dirname($value)) ? 'Parent directory does not exist' : null,
            hint: 'Use tab to accept, up/down to cycle.',
        );
    }

    private static function promptForIde(): string
    {
        return select(
            label: 'Which IDE do you use?',
            options: ['phpstorm' => 'PhpStorm', 'code' => 'VS Code', 'cursor' => 'Cursor'],
        );
    }

    private static function promptForInitialProjects(): array
    {
        $projects = [];

        do {
            $projects[] = self::promptForProject();
        } while (confirm('Add another project?', false));

        return $projects;
    }

    private static function promptForProjectPath(): string
    {
        return autocomplete(
            label: 'Absolute path to source repo',
            options: fn(string $value) => glob(($value ?: $_SERVER['HOME'] . '/') . '*', GLOB_ONLYDIR) ?: [],
            default: $_SERVER['HOME'] . '/',
            required: true,
            validate: fn(string $value) => is_dir($value) ? null : 'Directory does not exist',
            hint: 'Use tab to accept, up/down to cycle.',
        );
    }

    private static function resolveDomain(string $path): string
    {
        $detected = Valet::domainForPath($path);

        if ($detected !== null) {
            info("Detected valet domain: $detected");
            return $detected;
        }

        return text(
            label: 'Valet domain (without .test)',
            placeholder: 'myproject',
        );
    }
}
