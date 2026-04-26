<?php

use App\Valet;

// ─── type() / activePath() / domainForPath() ────────────────────────────────

beforeEach(function () {
    $this->originalHome = $_SERVER['HOME'];
    $this->tempHome = fakeHome();
    $this->sitesDir = $this->tempHome . '/.config/valet/Sites';
    $this->nginxDir = $this->tempHome . '/.config/valet/Nginx';
    mkdir($this->sitesDir, 0755, true);
    mkdir($this->nginxDir, 0755, true);
    $_SERVER['HOME'] = $this->tempHome;
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->tempHome));
    $_SERVER['HOME'] = $this->originalHome;
});

test('type returns link when a symlink exists in Sites', function () {
    symlink('/code/my-project', $this->sitesDir . '/my-project');
    expect(Valet::type('my-project'))->toBe('link');
});

test('type returns proxy when nginx config contains proxy_pass', function () {
    file_put_contents($this->nginxDir . '/my-project', 'proxy_pass http://localhost:3000;');
    expect(Valet::type('my-project'))->toBe('proxy');
});

test('type returns unknown for unrecognised domain', function () {
    expect(Valet::type('unknown-domain'))->toBe('unknown');
});

test('activePath returns null when domain is not a symlink', function () {
    expect(Valet::activePath('my-project'))->toBeNull();
});

test('activePath returns the symlink target', function () {
    symlink('/code/my-project', $this->sitesDir . '/my-project');
    expect(Valet::activePath('my-project'))->toBe('/code/my-project');
});

test('domainForPath finds domain via Sites symlink', function () {
    symlink('/code/my-project', $this->sitesDir . '/my-project');
    expect(Valet::domainForPath('/code/my-project'))->toBe('my-project');
});

test('domainForPath returns null when no symlink matches', function () {
    expect(Valet::domainForPath('/code/unknown'))->toBeNull();
});

test('domainForPath finds domain via Nginx proxy config', function () {
    file_put_contents($this->nginxDir . '/my-project.test', 'proxy_pass http://localhost:3000;');
    expect(Valet::domainForPath('/code/my-project'))->toBe('my-project.test');
});

test('domainForPath ignores trailing slash on path', function () {
    symlink('/code/my-project', $this->sitesDir . '/my-project');
    expect(Valet::domainForPath('/code/my-project/'))->toBe('my-project');
});

// ─── parsePhpServices() ─────────────────────────────────────────────────────

test('parsePhpServices returns started php services', function () {
    $output = "Name       Status  User File\nphp        started vytas ~/Library/LaunchAgents/homebrew.mxcl.php.plist\nnginx      started vytas ~/Library/LaunchAgents/homebrew.mxcl.nginx.plist";
    expect(Valet::parsePhpServices($output))->toBe(['php']);
});

test('parsePhpServices returns versioned php services', function () {
    $output = "php@8.3    started vytas ~/Library/LaunchAgents/homebrew.mxcl.php@8.3.plist\nphp@8.1    stopped vytas -";
    expect(Valet::parsePhpServices($output))->toBe(['php@8.3']);
});

test('parsePhpServices returns multiple started php services', function () {
    $output = "php        started vytas -\nphp@8.3    started vytas -\nnginx      started vytas -";
    expect(Valet::parsePhpServices($output))->toBe(['php', 'php@8.3']);
});

test('parsePhpServices ignores stopped php services', function () {
    $output = "php        stopped vytas -";
    expect(Valet::parsePhpServices($output))->toBe([]);
});

test('parsePhpServices returns empty array for empty output', function () {
    expect(Valet::parsePhpServices(''))->toBe([]);
});
