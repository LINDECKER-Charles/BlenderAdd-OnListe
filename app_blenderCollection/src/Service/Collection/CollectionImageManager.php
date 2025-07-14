<?php

namespace App\Service\Collection;

use App\Service\UploadManager;
use App\Service\AddonsScraper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CollectionImageManager
{
    private UploadManager $uploadManager;
    private AddonsScraper $scraper;

    public function __construct(UploadManager $uploadManager, AddonsScraper $scraper)
    {
        $this->uploadManager = $uploadManager;
        $this->scraper = $scraper;
    }

    /**
     * Gère l'image d'une collection : upload local ou récupération via scraping.
     *
     * @param Request $request
     * @param array $addons Tableau de [$url, ...]
     * @return string|null Nom du fichier image ou null si aucun
     */
    public function resolveImage(Request $request, array $addons): ?string
    {
        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image');

        if ($imageFile) {
            return $this->uploadManager->uploadLocalFile($imageFile);
        }

        if (!empty($addons)) {
            $imageUrl = $this->scraper->getAddOnImage($addons[0][0]);
            if ($imageUrl) {
                return $this->uploadManager->uploadFromUrl($imageUrl);
            }
        }

        return null;
    }
}
