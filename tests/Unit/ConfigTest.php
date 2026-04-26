<?php

use App\Config;

beforeEach(function () {
    $this->originalHome = $_SERVER['HOME'];
    $this->tempHome = fakeHome();
    $_SERVER['HOME'] = $this->tempHome;
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->tempHome));
    $_SERVER['HOME'] = $this->originalHome;
});

test('path returns config file path under HOME', function () {
    expect(Config::path())->toBe($this->tempHome . '/.config/cow/projects.php');
});

test('exists returns false when config file is absent', function () {
    expect(Config::exists())->toBeFalse();
});

test('exists returns true after saving', function () {
    Config::save(['clones_dir' => '/tmp', 'ide_command' => 'phpstorm', 'projects' => []]);
    expect(Config::exists())->toBeTrue();
});

test('save creates the config directory if missing', function () {
    Config::save(['clones_dir' => '/tmp', 'ide_command' => 'phpstorm', 'projects' => []]);
    expect(is_dir($this->tempHome . '/.config/cow'))->toBeTrue();
});

test('load returns the saved data', function () {
    $data = ['clones_dir' => '/tmp/clones', 'ide_command' => 'code', 'projects' => []];
    Config::save($data);
    expect(Config::load())->toBe($data);
});

test('clonesDir returns clones_dir from config', function () {
    Config::save(['clones_dir' => '/my/clones', 'ide_command' => 'phpstorm', 'projects' => []]);
    expect(Config::clonesDir())->toBe('/my/clones');
});

test('ideCommand returns ide_command from config', function () {
    Config::save(['clones_dir' => '/tmp', 'ide_command' => 'cursor', 'projects' => []]);
    expect(Config::ideCommand())->toBe('cursor');
});

test('ideCommand defaults to phpstorm when key is absent', function () {
    Config::save(['clones_dir' => '/tmp', 'projects' => []]);
    expect(Config::ideCommand())->toBe('phpstorm');
});

test('projects returns the projects array', function () {
    $projects = [['name' => 'API', 'path' => '/code/api', 'domain' => 'api']];
    Config::save(['clones_dir' => '/tmp', 'ide_command' => 'phpstorm', 'projects' => $projects]);
    expect(Config::projects())->toBe($projects);
});

test('projects returns empty array when key is absent', function () {
    Config::save(['clones_dir' => '/tmp', 'ide_command' => 'phpstorm']);
    expect(Config::projects())->toBe([]);
});
