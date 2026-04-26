<?php

namespace App;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Updater;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;
use RuntimeException;

class GithubReleasesStrategy implements StrategyInterface
{
    private string $localVersion;
    private string $remoteUrl;
    private string $packageName;

    public function getCurrentRemoteVersion(Updater $updater): string
    {
        [$vendor, $package] = explode('/', $this->packageName, 2);

        $apiUrl = "https://api.github.com/repos/$vendor/$package/releases/latest";

        set_error_handler([$updater, 'throwHttpRequestException']);
        $json = file_get_contents($apiUrl, false, stream_context_create([
            'http' => ['header' => "User-Agent: $package-self-update\r\n"],
        ]));
        restore_error_handler();

        if ($json === false) {
            throw new HttpRequestException("Request to GitHub API failed: $apiUrl");
        }

        $release = json_decode($json, true);

        if (empty($release['tag_name'])) {
            throw new RuntimeException('Could not parse release tag from GitHub API response.');
        }

        $remoteVersion = ltrim($release['tag_name'], 'v');

        $asset = collect($release['assets'] ?? [])
            ->first(fn(array $a) => $a['name'] === $package);

        if ($asset === null) {
            throw new RuntimeException("Asset '$package' not found in release {$release['tag_name']}.");
        }

        $this->remoteUrl = $asset['browser_download_url'];

        return $remoteVersion;
    }

    public function getCurrentLocalVersion(Updater $updater): string
    {
        return $this->localVersion;
    }

    public function download(Updater $updater): void
    {
        set_error_handler([$updater, 'throwHttpRequestException']);
        $data = file_get_contents($this->remoteUrl, false, stream_context_create([
            'http' => ['follow_location' => true],
        ]));
        restore_error_handler();

        if ($data === false) {
            throw new HttpRequestException('Failed to download: ' . $this->remoteUrl);
        }

        file_put_contents($updater->getTempPharFile(), $data);
    }

    public function setPackageName($name): void
    {
        $this->packageName = $name;
    }

    public function setCurrentLocalVersion($version): void
    {
        $this->localVersion = $version;
    }
}
