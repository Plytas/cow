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

        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout(120);
        $process->run(fn($type, $line) => $logger->line($line));

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
