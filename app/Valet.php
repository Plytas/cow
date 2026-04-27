<?php

namespace App;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class Valet
{
    /**
     * Restart all running php* brew services in parallel.
     *
     * `valet restart` runs brew services stop/start which, once the plist is
     * loaded, treats the service as already-running and does NOT actually
     * kill the FPM master. Workers keep serving with stale realpath_cache
     * (TTL 120s). `brew services restart` (user scope, no sudo) actually
     * tears the process down and relaunches it. We restart every running
     * php* service since projects may span multiple PHP versions.
     *
     * @param  ?callable(string $service, string $line): void  $onOutput  optional progress callback for streaming output
     * @return string[]  list of services restarted (empty if none were running)
     */
    public static function restartPhpServices(?callable $onOutput = null): array
    {
        $services = self::runningPhpServices();

        if ($services === []) {
            return [];
        }

        $results = Process::concurrently(
            function (Pool $pool) use ($services) {
                foreach ($services as $service) {
                    $pool->as($service)->command('brew services restart ' . escapeshellarg($service));
                }
            },
            $onOutput === null ? null : function (string $type, string $output, string $key) use ($onOutput) {
                foreach (explode("\n", rtrim($output)) as $line) {
                    if ($line !== '') {
                        $onOutput($key, $line);
                    }
                }
            },
        );

        foreach ($results as $key => $result) {
            if (!$result->successful()) {
                throw new RuntimeException("$key failed: " . ($result->errorOutput() ?: $result->output()));
            }
        }

        return $services;
    }

    public static function type(string $domain): string
    {
        $sitesDir = $_SERVER['HOME'] . '/.config/valet/Sites';
        $nginxDir  = $_SERVER['HOME'] . '/.config/valet/Nginx';

        if (is_link("$sitesDir/$domain")) {
            return 'link';
        }

        if (file_exists("$nginxDir/$domain") && str_contains(file_get_contents("$nginxDir/$domain"), 'proxy_pass')) {
            return 'proxy';
        }

        return 'unknown';
    }

    public static function activePath(string $domain): ?string
    {
        $link = $_SERVER['HOME'] . "/.config/valet/Sites/$domain";

        if (!is_link($link)) {
            return null;
        }

        return readlink($link) ?: null;
    }

    public static function domainForPath(string $path): ?string
    {
        $sitesDir = $_SERVER['HOME'] . '/.config/valet/Sites';
        $nginxDir = $_SERVER['HOME'] . '/.config/valet/Nginx';
        $normalized = rtrim($path, '/');

        if (is_dir($sitesDir)) {
            foreach (scandir($sitesDir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $link = "$sitesDir/$entry";

                if (is_link($link) && rtrim(readlink($link), '/') === $normalized) {
                    return $entry;
                }
            }
        }

        $dirName = basename($normalized);

        if (is_dir($nginxDir)) {
            foreach (scandir($nginxDir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (str_starts_with($entry, $dirName . '.')) {
                    $file = "$nginxDir/$entry";
                    if (str_contains(file_get_contents($file), 'proxy_pass')) {
                        return $entry;
                    }
                }
            }
        }

        return null;
    }

    /**
     * List brew services that are started and belong to a `php` formula
     * (e.g. `php`, `php@8.4`). These are the FPM masters whose workers
     * hold stale realpath_cache entries after a symlink switch.
     *
     * @return string[]
     */
    public static function runningPhpServices(): array
    {
        try {
            $output = Shell::run('brew services list');
        } catch (RuntimeException) {
            return [];
        }

        return self::parsePhpServices($output);
    }

    public static function parsePhpServices(string $output): array
    {
        $services = [];

        foreach (explode("\n", $output) as $line) {
            if (!preg_match('/^(php(?:@[\d.]+)?)\s+started\b/', $line, $m)) {
                continue;
            }

            $services[] = $m[1];
        }

        return $services;
    }
}
