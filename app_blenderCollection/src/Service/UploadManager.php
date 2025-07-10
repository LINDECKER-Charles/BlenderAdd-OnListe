<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class UploadManager
{
    private string $uploadDir;
    private SluggerInterface $slugger;
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(string $uploadDir, SluggerInterface $slugger)
    {
        $this->uploadDir = $uploadDir;
        $this->slugger = $slugger;
    }

    /**
     * Upload un fichier local vers un dossier défini et retourne le nom final.
     *
     * Si un nom personnalisé est fourni, il sera utilisé tel quel (y compris l’extension).
     * Sinon, un nom sécurisé sera généré à partir du nom original et d’un identifiant unique.
     * Le dossier de destination peut également être redéfini temporairement.
     *
     * @param UploadedFile $file         Fichier à uploader (doit être une image autorisée).
     * @param string|null $customName    Nom de fichier personnalisé avec extension (facultatif).
     * @param string|null $customPath    Dossier de destination (facultatif, défaut : dossier défini dans le service).
     *
     * @return string|null               Nom du fichier sauvegardé, ou null en cas d’échec ou de type non autorisé.
     */
    public function uploadLocalFile(UploadedFile $file, ?string $customName = null, ?string $customPath = null): ?string
    {
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return null;
        }

        $targetDir = $customPath ?? $this->uploadDir;

        $newFilename = $customName ?? (
            $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            . '-' . uniqid() . '.' . $file->guessExtension()
        );

        try {
            $file->move($targetDir, $newFilename);
            return $newFilename;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Télécharge une image distante et la sauvegarde localement
     *
     * @param string $url URL de l’image distante
     * @return string|null Nom du fichier sauvegardé (ou null en cas d’échec)
     */
    public function uploadFromUrl(string $url): ?string
    {
        $contents = @file_get_contents($url);
        if ($contents === false) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($tempPath, $contents);

        // Vérification du type MIME réel
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tempPath);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            unlink($tempPath);
            return null;
        }

        // Extension cohérente avec le type MIME
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $newFilename = 'remote_' . uniqid() . '.' . $extension;
        $finalPath = $this->uploadDir . '/' . $newFilename;

        try {
            copy($tempPath, $finalPath);
            unlink($tempPath);
            return $newFilename;
        } catch (\Exception $e) {
            unlink($tempPath);
            return null;
        }
    }
}
