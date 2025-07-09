<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AddonDownloader
{
    private string $downloadDir;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $projectDir
    ) {
        $this->downloadDir = $projectDir . '/var/addon_downloads';

        if (!is_dir($this->downloadDir)) {
            mkdir($this->downloadDir, 0775, true);
        }
    }

    public function downloadAndZip(array $urls, string $zipName): BinaryFileResponse
    {
        foreach ($urls as $url) {
            $this->downloadAddon($url);
        }

        $zipPath = $this->downloadDir . '/' . $zipName;
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l’archive ZIP.");
        }

        foreach ($urls as $url) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            $filePath = $this->downloadDir . '/' . $filename;

            if (file_exists($filePath)) {
                $zip->addFile($filePath, $filename);
            }
        }

        $zip->close();

        return new BinaryFileResponse(
            $zipPath,
            200,
            [],
            true,
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            false,
            true
        );
    }

    public function downloadAddon(string $url): string
    {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $filePath = $this->downloadDir . '/' . $filename;

        if (!file_exists($filePath)) {
            $response = $this->httpClient->request('GET', $url);
            file_put_contents($filePath, $response->getContent());
        }

        return $filePath;
    }
}
