<?php

namespace App;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

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
     * Uses a small `clonefile(2)` helper binary (compiled on demand) to clone
     * each top-level entry in parallel — roughly 10–15× faster than `cp -rcP`
     * on repos with large vendor/node_modules trees. Falls back to `cp -rcP`
     * if the helper can't be built or the parallel clone fails (e.g. cross-
     * volume destination, where clonefile() returns EXDEV).
     */
    public function cloneTree(string $source, string $dest): void
    {
        $helper = $this->ensureCloneHelper();

        if ($helper !== null && $this->parallelClone($helper, $source, $dest)) {
            return;
        }

        Shell::run('cp -rcP ' . escapeshellarg($source) . ' ' . escapeshellarg($dest));
    }

    /**
     * Run the clonefile helper across all top-level entries in $source with
     * up to 8 workers in parallel. Returns true on success; on any failure,
     * wipes $dest and returns false so the caller can fall back.
     */
    private function parallelClone(string $helper, string $source, string $dest): bool
    {
        $entries = array_values(array_diff(scandir($source) ?: [], ['.', '..']));

        if ($entries === []) {
            return mkdir($dest, 0755, true) || is_dir($dest);
        }

        if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
            return false;
        }

        $maxWorkers = 8;
        $running    = [];
        $ok         = true;

        foreach ($entries as $entry) {
            while (count($running) >= $maxWorkers) {
                $running = $this->reapFinished($running, $ok);

                if (count($running) >= $maxWorkers) {
                    usleep(1000);
                }
            }

            $process = new Process([$helper, $source . '/' . $entry, $dest . '/' . $entry]);
            $process->setTimeout(120);
            $process->start();
            $running[] = $process;
        }

        foreach ($running as $process) {
            $process->wait();

            if (!$process->isSuccessful()) {
                $ok = false;
            }
        }

        if (!$ok) {
            Shell::quietly('rm -rf ' . escapeshellarg($dest));
        }

        return $ok;
    }

    /**
     * @param  array<int, Process>  $running
     * @return array<int, Process>
     */
    private function reapFinished(array $running, bool &$ok): array
    {
        $remaining = [];

        foreach ($running as $process) {
            if ($process->isRunning()) {
                $remaining[] = $process;
                continue;
            }

            if (!$process->isSuccessful()) {
                $ok = false;
            }
        }

        return $remaining;
    }

    /**
     * Ensure the clonefile helper binary is compiled and cached. Returns its
     * path, or null if the helper can't be built (e.g. no `cc` available).
     */
    private function ensureCloneHelper(): ?string
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        $cacheDir = ($_SERVER['HOME'] ?? '') . '/.cache/cow';
        $binary   = $cacheDir . '/clone';

        if (is_executable($binary)) {
            return $binary;
        }

        $sourcePath = __DIR__ . '/../resources/clone.c';
        $sourceCode = @file_get_contents($sourcePath);

        if ($sourceCode === false) {
            return null;
        }

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            return null;
        }

        $tmpSource = $cacheDir . '/clone.c';

        if (file_put_contents($tmpSource, $sourceCode) === false) {
            return null;
        }

        $cmd = 'cc -O2 ' . escapeshellarg($tmpSource) . ' -o ' . escapeshellarg($binary);

        if (!Shell::quietly($cmd) || !is_executable($binary)) {
            return null;
        }

        return $binary;
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
