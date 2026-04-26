<?php

namespace App;

use Illuminate\Support\Str;
use RuntimeException;

class CloneCreator
{
    /**
     * Create a clone of $project checked out at $branch.
     * Returns the absolute path to the new clone directory.
     *
     * @throws RuntimeException if the destination already exists or any shell command fails
     */
    public function create(Project $project, string $branch, string $cloneName): string
    {
        $source = $project->path();
        $dest   = $project->clonesDir() . '/' . $cloneName;

        if (is_dir($dest)) {
            throw new RuntimeException("Clone '$cloneName' already exists at $dest");
        }

        $parent = dirname($dest);

        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }

        Shell::run('cp -rcP ' . escapeshellarg($source) . ' ' . escapeshellarg($dest));

        $escapedDest   = escapeshellarg($dest);
        $escapedBranch = escapeshellarg($branch);

        if (Shell::quietly("git -C $escapedDest fetch origin $escapedBranch")) {
            Shell::run("git -C $escapedDest checkout $escapedBranch");
        } else {
            Shell::run("git -C $escapedDest checkout -b $escapedBranch");
        }

        Shell::quietly("git -C $escapedDest restore .");

        if (file_exists("$source/composer.json") && self::composerLockDiffers($source, $dest)) {
            Shell::run('composer -d ' . escapeshellarg($dest) . ' install --no-interaction');
        }

        return $dest;
    }

    public static function cloneNameFromBranch(string $branch): string
    {
        return self::truncateSlug(Str::slug(basename(str_replace('/', DIRECTORY_SEPARATOR, $branch))));
    }

    public static function cloneNameFromPr(int $number, string $title): string
    {
        $slug = $title !== '' ? '-' . self::truncateSlug(Str::slug($title)) : '';

        return 'pr-' . $number . $slug;
    }

    private static function truncateSlug(string $slug): string
    {
        if (mb_strlen($slug) <= 40) {
            return $slug;
        }

        return rtrim(mb_substr($slug, 0, 40), '-');
    }

    public static function composerLockDiffers(string $source, string $dest): bool
    {
        $sourceLock = "$source/composer.lock";
        $destLock   = "$dest/composer.lock";

        if (!file_exists($sourceLock) || !file_exists($destLock)) {
            return false;
        }

        return md5_file($sourceLock) !== md5_file($destLock);
    }
}
