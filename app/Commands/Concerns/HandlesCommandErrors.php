<?php

namespace App\Commands\Concerns;

use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

/**
 * Wraps the body of a command's handle() method so RuntimeException and
 * InvalidArgumentException become either a JSON `{"error": ...}` line or
 * a styled error message, depending on the --json flag.
 *
 * @mixin Command
 */
trait HandlesCommandErrors
{
    protected function respond(callable $work): int
    {
        try {
            $work();

            return Command::SUCCESS;
        } catch (RuntimeException|InvalidArgumentException $e) {
            if ($this->option('json')) {
                $this->line(json_encode(['error' => $e->getMessage()]));
            } else {
                $this->error($e->getMessage());
            }

            return Command::FAILURE;
        }
    }

    protected function jsonOrInfo(array $json, string $message): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($json));
        } else {
            $this->info($message);
        }
    }
}
