<?php

use App\CloneDir;

test('path returns the path given at construction', function () {
    $clone = new CloneDir('/code/clones/my-api/pr-123');
    expect($clone->path())->toBe('/code/clones/my-api/pr-123');
});

test('name returns the basename of the path', function () {
    $clone = new CloneDir('/code/clones/my-api/pr-123');
    expect($clone->name())->toBe('pr-123');
});

test('branch returns current branch of a real git repo', function () {
    $dir = sys_get_temp_dir() . '/cow-git-' . uniqid();
    mkdir($dir, 0755, true);
    exec("git -C $dir init && git -C $dir checkout -b test-branch");

    $clone = new CloneDir($dir);
    expect($clone->branch())->toBe('test-branch');

    exec('rm -rf ' . escapeshellarg($dir));
});

test('branch returns ? when path is not a git repo', function () {
    $dir = sys_get_temp_dir() . '/cow-nogit-' . uniqid();
    mkdir($dir, 0755, true);

    $clone = new CloneDir($dir);
    expect($clone->branch())->toBe('?');

    exec('rm -rf ' . escapeshellarg($dir));
});
