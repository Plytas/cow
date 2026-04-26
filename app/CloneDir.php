<?php

namespace App;

use RuntimeException;

readonly class CloneDir
{
    public function __construct(private string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function name(): string
    {
        return basename($this->path);
    }

    public function branch(): string
    {
        try {
            return Shell::run("git -C " . escapeshellarg($this->path) . " branch --show-current");
        } catch (RuntimeException) {
            return '?';
        }
    }
}
