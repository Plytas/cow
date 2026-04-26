<?php

use App\CloneCreator;

// cloneNameFromBranch

test('branch name becomes slug', function () {
    expect(CloneCreator::cloneNameFromBranch('feat/my-feature'))->toBe('my-feature');
});

test('branch with spaces is slugified', function () {
    expect(CloneCreator::cloneNameFromBranch('feat/my feature'))->toBe('my-feature');
});

test('only last path segment is used', function () {
    expect(CloneCreator::cloneNameFromBranch('user/john/feat/cool-thing'))->toBe('cool-thing');
});

test('branch name longer than 40 chars is truncated', function () {
    $branch = 'feat/this-is-a-very-long-branch-name-that-exceeds-the-limit';
    expect(strlen(CloneCreator::cloneNameFromBranch($branch)))->toBeLessThanOrEqual(40);
});

test('truncated branch name does not end with a dash', function () {
    $branch = 'feat/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbb';
    expect(CloneCreator::cloneNameFromBranch($branch))->not->toEndWith('-');
});

// cloneNameFromPr

test('pr clone name includes number and title slug', function () {
    expect(CloneCreator::cloneNameFromPr(123, 'Fix the bug'))->toBe('pr-123-fix-the-bug');
});

test('pr clone name with empty title omits slug', function () {
    expect(CloneCreator::cloneNameFromPr(42, ''))->toBe('pr-42');
});

test('pr title longer than 40 chars is truncated', function () {
    $title = 'This is a very long PR title that definitely exceeds forty characters';
    $name = CloneCreator::cloneNameFromPr(1, $title);
    // "pr-1-" prefix + slug, slug portion must be <= 40 chars
    $slug = substr($name, strlen('pr-1-'));
    expect(strlen($slug))->toBeLessThanOrEqual(40);
});

// composerLockDiffers

function makeTempDirs(): array
{
    $base = sys_get_temp_dir() . '/cow-test-' . uniqid();
    mkdir("$base/a", 0755, true);
    mkdir("$base/b", 0755, true);
    return [$base, "$base/a", "$base/b"];
}

test('identical lock files returns false', function () {
    [$base, $a, $b] = makeTempDirs();
    file_put_contents("$a/composer.lock", '{"content":"same"}');
    file_put_contents("$b/composer.lock", '{"content":"same"}');

    expect(CloneCreator::composerLockDiffers($a, $b))->toBeFalse();

    exec("rm -rf $base");
});

test('different lock files returns true', function () {
    [$base, $a, $b] = makeTempDirs();
    file_put_contents("$a/composer.lock", '{"content":"old"}');
    file_put_contents("$b/composer.lock", '{"content":"new"}');

    expect(CloneCreator::composerLockDiffers($a, $b))->toBeTrue();

    exec("rm -rf $base");
});

test('missing lock file returns false', function () {
    [$base, $a, $b] = makeTempDirs();
    file_put_contents("$a/composer.lock", '{"content":"old"}');
    // no lock in b

    expect(CloneCreator::composerLockDiffers($a, $b))->toBeFalse();

    exec("rm -rf $base");
});
