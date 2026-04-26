<?php

namespace App;

class Config
{
    public static function path(): string
    {
        return $_SERVER['HOME'] . '/.config/cow/projects.php';
    }

    public static function exists(): bool
    {
        return file_exists(static::path());
    }

    public static function load(): array
    {
        return require static::path();
    }

    public static function clonesDir(): string
    {
        return static::load()['clones_dir'];
    }

    public static function ideCommand(): string
    {
        return static::load()['ide_command'] ?? 'phpstorm';
    }

    public static function projects(): array
    {
        return static::load()['projects'] ?? [];
    }

    public static function save(array $data): void
    {
        $dir = dirname(static::path());

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(static::path(), "<?php\n\nreturn " . var_export($data, true) . ";\n");
    }
}
