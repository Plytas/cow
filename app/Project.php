<?php

namespace App;

use Illuminate\Support\Str;

readonly class Project
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return $this->config['name'];
    }

    public function path(): string
    {
        return $this->config['path'];
    }

    public function domain(): string
    {
        return $this->config['domain'];
    }

    public function slug(): string
    {
        return Str::slug($this->name());
    }

    public function clonesDir(): string
    {
        return Config::clonesDir() . '/' . $this->slug();
    }

    /** @return CloneDir[] */
    public function clones(): array
    {
        $dir = $this->clonesDir();

        if (!is_dir($dir)) {
            return [];
        }

        $entries = array_filter(
            scandir($dir),
            fn($e) => $e !== '.' && $e !== '..' && !str_starts_with($e, '.cow-deleting-') && is_dir("$dir/$e")
        );

        return array_values(array_map(fn($e) => new CloneDir("$dir/$e"), $entries));
    }

    public function valetType(): string
    {
        return Valet::type($this->domain());
    }

    public function activePath(): ?string
    {
        return Valet::activePath($this->domain());
    }

}
