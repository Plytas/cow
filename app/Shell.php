<?php

namespace App;

use Laravel\Prompts\Support\Logger;
use RuntimeException;
use Symfony\Component\Process\Process;

class Shell
{
    public static function run(string $command, ?string $cwd = null): string
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput() ?: $process->getOutput());
        }

        return trim($process->getOutput());
    }

    public static function stream(Logger $logger, string $command, ?string $cwd = null): string
    {
        $logger->subLabel($command);

        return self::runWithOutput($command, fn(string $line) => $logger->line($line), $cwd);
    }

    /**
     * Run a command and invoke $onLine for every line of output (stdout or stderr).
     * Returns the full trimmed stdout.
     */
    public static function runWithOutput(string $command, callable $onLine, ?string $cwd = null): string
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout(120);
        $process->run(function ($type, $output) use ($onLine) {
            foreach (explode("\n", rtrim($output)) as $line) {
                if ($line !== '') {
                    $onLine($line);
                }
            }
        });

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput() ?: $process->getOutput());
        }

        return trim($process->getOutput());
    }

    public static function quietly(string $command, ?string $cwd = null): bool
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful();
    }
}
