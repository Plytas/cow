<?php

use App\ProjectResolver;

beforeEach(function () {
    $path = $_SERVER['HOME'] . '/.config/cow/projects.php';
    $this->configPath = $path;
    $this->configBackup = file_exists($path) ? file_get_contents($path) : null;

    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, "<?php\nreturn " . var_export([
        'clones_dir' => sys_get_temp_dir() . '/cow-test-clones',
        'ide_command' => 'phpstorm',
        'projects' => [
            ['name' => 'WG API', 'path' => '/code/wg-api', 'domain' => 'wg-api'],
            ['name' => 'WG Front', 'path' => '/code/wg-front', 'domain' => 'wg-front'],
        ],
    ], true) . ";\n");
});

afterEach(function () {
    if ($this->configBackup !== null) {
        file_put_contents($this->configPath, $this->configBackup);
    } else {
        @unlink($this->configPath);
    }
});

test('resolves project by exact name', function () {
    expect(ProjectResolver::byName('WG API')->name())->toBe('WG API');
});

test('resolves project case-insensitively', function () {
    expect(ProjectResolver::byName('wg api')->name())
        ->toBe('WG API')
        ->and(ProjectResolver::byName('WG api')->name())
        ->toBe('WG API');
});

test('throws when project not found', function () {
    ProjectResolver::byName('Unknown');
})->throws(InvalidArgumentException::class, "Project 'Unknown' not found");

test('error message lists available projects', function () {
    try {
        ProjectResolver::byName('Unknown');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('WG API')->toContain('WG Front');
    }
});

test('clone resolver returns project path for main', function () {
    $project = ProjectResolver::byName('WG API');
    expect(ProjectResolver::clone($project, 'main'))->toBe('/code/wg-api');
});

test('clone resolver throws for unknown clone', function () {
    $project = ProjectResolver::byName('WG API');
    ProjectResolver::clone($project, 'pr-999');
})->throws(InvalidArgumentException::class, "Clone 'pr-999' not found");
