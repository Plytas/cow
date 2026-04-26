<?php

use App\Project;

test('slug converts spaces to hyphens', function () {
    $project = new Project(['name' => 'My API', 'path' => '/x', 'domain' => 'x']);
    expect($project->slug())->toBe('my-api');
});

test('slug lowercases the name', function () {
    $project = new Project(['name' => 'WG API', 'path' => '/x', 'domain' => 'x']);
    expect($project->slug())->toBe('wg-api');
});

test('slug strips special characters', function () {
    $project = new Project(['name' => 'My App (v2)!', 'path' => '/x', 'domain' => 'x']);
    expect($project->slug())->toBe('my-app-v2');
});
