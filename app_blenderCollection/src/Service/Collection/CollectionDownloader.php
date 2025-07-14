<?php

namespace App\Service\Collection;

use App\Entity\Liste;
use App\Service\AdminLogger;
use App\Service\AddonDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CollectionDownloader
{
    public function __construct(
        private readonly AddonDownloader $downloader,
        private readonly EntityManagerInterface $em,
        private readonly AdminLogger $logger,
        private readonly Security $security
    ) {}

    /**
     * Gère le téléchargement des add-ons d'une collection sous forme d’archive ZIP.
     *
     * - Récupère les URLs des add-ons.
     * - Incrémente le compteur de téléchargement.
     * - Retourne le fichier ZIP prêt à être téléchargé.
     *
     * @param Liste $liste
     * @return BinaryFileResponse
     */
    public function download(Liste $liste): BinaryFileResponse
    {
        $addonUrls = array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray());
        $urls = $this->downloader->resolveArchiveUrls($addonUrls);

        $liste->setDownload($liste->getDownload() + 1);
        $this->em->flush();

        $user = $this->security->getUser();
        if ($user) {
            $this->logger->log(
                'Download Collection',
                $user,
                $liste->getName() . ' #' . $liste->getId(),
                'Téléchargement de la collection'
            );
        }
        $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
        return $this->downloader->downloadAndZip($urls, $zipName);
    }
}
