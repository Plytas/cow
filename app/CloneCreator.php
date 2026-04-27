<?php

namespace App;

use FFI;
use FFI\Exception as FFIException;
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

        $this->cloneTree($source, $dest);

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

    /**
     * Recursively clone $source to $dest using APFS copy-on-write.
     *
     * Calls the libSystem `clonefile(2)` syscall directly via FFI — the
     * directory-level form recurses inside the kernel, ~8× faster than
     * `cp -rcP` on large repos. Falls back to `cp -rcP` if FFI is disabled
     * or the syscall returns an error (e.g. EXDEV on cross-volume destinations).
     */
    public function cloneTree(string $source, string $dest): void
    {
        self::sweepStaleTrash(dirname($dest));

        if (PHP_OS_FAMILY === 'Darwin' && self::clonefile($source, $dest)) {
            return;
        }

        Shell::run('cp -rcP ' . escapeshellarg($source) . ' ' . escapeshellarg($dest));
    }

    /**
     * Delete a clone tree. Renames it to a sibling `.cow-deleting-*` first
     * (a metadata-only operation on APFS within the same volume) and then
     * spawns a detached `rm -rf` to reclaim space asynchronously. From the
     * caller's perspective the deletion is effectively instant; the OS
     * finishes unlinking inodes in the background.
     *
     * Falls back to a synchronous `rm -rf` if the rename fails (e.g. the
     * trash sibling can't be created on the same filesystem).
     */
    public function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        self::sweepStaleTrash(dirname($path));

        $trash = dirname($path) . '/.cow-deleting-' . uniqid('', true) . '-' . basename($path);

        if (@rename($path, $trash)) {
            // Detach via shell `&` so the rm survives this process exiting.
            Shell::quietly('nohup rm -rf ' . escapeshellarg($trash) . ' >/dev/null 2>&1 &');
            return;
        }

        Shell::run('rm -rf ' . escapeshellarg($path));
    }

    /**
     * Asynchronously rm any leftover `.cow-deleting-*` siblings in $dir.
     * Self-heals trash from previous runs that crashed between rename and
     * background rm spawn (or whose async rm was interrupted).
     */
    private static function sweepStaleTrash(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/.cow-deleting-*', GLOB_NOSORT | GLOB_ONLYDIR) ?: [] as $stale) {
            Shell::quietly('nohup rm -rf ' . escapeshellarg($stale) . ' >/dev/null 2>&1 &');
        }
    }

    private static function clonefile(string $source, string $dest): bool
    {
        try {
            $libc = FFI::cdef(
                'int clonefile(const char *src, const char *dst, unsigned int flags);',
                'libSystem.dylib',
            );
        } catch (FFIException) {
            return false;
        }

        if ($libc->clonefile($source, $dest, 0) === 0) {
            return true;
        }

        if (is_dir($dest)) {
            Shell::quietly('rm -rf ' . escapeshellarg($dest));
        }

        return false;
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
