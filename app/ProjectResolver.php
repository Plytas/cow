<?php

namespace App;

use InvalidArgumentException;

class ProjectResolver
{
    /**
     * Resolve a project by name (case-insensitive).
     *
     * @throws InvalidArgumentException if no matching project is found
     */
    public static function byName(string $name): Project
    {
        foreach (Config::projects() as $data) {
            if (strcasecmp($data['name'], $name) === 0) {
                return new Project($data);
            }
        }

        $available = implode(', ', array_column(Config::projects(), 'name'));

        throw new InvalidArgumentException(
            "Project '$name' not found. Available: $available"
        );
    }

    /**
     * Resolve a clone by name within a project.
     * Pass 'main' to get the project's own source path.
     *
     * @throws InvalidArgumentException if the clone is not found
     */
    public static function clone(Project $project, string $name): string
    {
        if ($name === 'main') {
            return $project->path();
        }

        foreach ($project->clones() as $clone) {
            if ($clone->name() === $name) {
                return $clone->path();
            }
        }

        throw new InvalidArgumentException(
            "Clone '$name' not found in project '{$project->name()}'"
        );
    }
}
