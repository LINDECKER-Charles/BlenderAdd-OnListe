<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service permettant de télécharger des add-ons Blender et de générer des archives ZIP.
 * 
 * Il assure la gestion des téléchargements, la création d’archives, la suppression
 * de fichiers et la résolution des URLs d’archives depuis l’API Blender.
 */
class AddonDownloader
{
    private string $downloadDir;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $projectDir,
        private BlenderAPI $blenderAPI
    ) {
        $this->downloadDir = $projectDir . '/var/downloads';

        if (!is_dir($this->downloadDir)) {
            mkdir($this->downloadDir, 0775, true);
        }
    }

    /**
     * Télécharge les fichiers nécessaires et génère une archive ZIP si elle n’existe pas déjà.
     *
     * @param array $urls URLs directes vers les fichiers à inclure
     * @param string $zipName Nom du fichier ZIP à générer (ex: "collection_xyz.zip")
     * @return BinaryFileResponse Téléchargement de l’archive en réponse
     */
    public function downloadAndZip(array $urls, string $zipName): BinaryFileResponse
    {
        $zipPath = $this->downloadDir . '/collection/' . $zipName;
            
        if (file_exists($zipPath)) {
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

        foreach ($urls as $url) {
            $this->downloadAddon($url);
        }

        $zipDir = dirname($zipPath);
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0775, true);
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l’archive ZIP.");
        }

        foreach ($urls as $url) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            $filePath = $this->downloadDir . '/add-on/' . $filename;

            if (file_exists($filePath)) {
                $zip->addFile($filePath, $filename);
            }
        }

        $zip->close();

        if (!file_exists($zipPath)) {
            throw new \RuntimeException("Le fichier ZIP n’a pas été créé correctement.");
        }

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

    /**
     * Force la recréation d’un ZIP même s’il existe déjà.
     *
     * @param array $urls Liste des URLs directes des fichiers
     * @param string $zipName Nom de l’archive à créer
     * @return BinaryFileResponse Réponse contenant le fichier ZIP généré
     */
    public function downloadAndZipForce(array $urls, string $zipName): BinaryFileResponse
    {
        $zipPath = $this->downloadDir . '/collection/' . $zipName;

        
        foreach ($urls as $url) {
            $this->downloadAddon($url);
        }

        $zipDir = dirname($zipPath);
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0775, true);
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l’archive ZIP.");
        }

        foreach ($urls as $url) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            $filePath = $this->downloadDir . '/add-on/' . $filename;

            if (file_exists($filePath)) {
                $zip->addFile($filePath, $filename);
            }
        }

        $zip->close();

        if (!file_exists($zipPath)) {
            throw new \RuntimeException("Le fichier ZIP n’a pas été créé correctement.");
        }

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

    /**
     * Supprime un fichier ZIP existant.
     *
     * @param string $zipName Nom du fichier ZIP à supprimer
     * @return bool True si le fichier a été supprimé, False sinon
     */
    public function deleteZip(string $zipName): bool
    {
        $zipPath = $this->downloadDir . '/collection/' . $zipName;

        if (file_exists($zipPath)) {
            return unlink($zipPath);
        }

        return false;
    }

    /**
     * Télécharge un fichier distant dans le dossier "add-on" s’il n’existe pas encore localement.
     *
     * @param string $url URL directe vers le fichier à télécharger
     * @return string Chemin absolu du fichier téléchargé
     */
    public function downloadAddon(string $url): string
    {
        $parts = explode('/', $url);
        $filename = end($parts);

        $filePath = $this->downloadDir . '/add-on/' . $filename;

        if (!file_exists($filePath)) {
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0775, true);
            }

            $response = $this->httpClient->request('GET', $url);
            file_put_contents($filePath, $response->getContent());
        }
        /* dd($url, $filename, $filePath); */
        return $filePath;
    }

    /**
     * Résout les URLs de téléchargement d’archives depuis leurs URLs de page Blender.
     *
     * @param array $websiteUrls Liste des URLs de page officielle Blender
     * @return array Liste des URLs directes d’archives (archive_url)
     */
    public function resolveArchiveUrls(array $websiteUrls): array
    {
        $resolved = [];

        foreach ($websiteUrls as $websiteUrl) {
            $extension = $this->blenderAPI->findExtensionByWebsite($websiteUrl);
            if ($extension && isset($extension['archive_url'])) {
                $resolved[] = $extension['archive_url'];
            }
        }

        return $resolved;
    }

}
