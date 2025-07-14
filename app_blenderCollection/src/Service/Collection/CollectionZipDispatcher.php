<?php

namespace App\Service\Collection;

use App\Entity\Liste;
use App\Message\DownloadZipMessage;
use App\Service\AddonDownloader;
use Symfony\Component\Messenger\MessageBusInterface;

class CollectionZipDispatcher
{
    private AddonDownloader $downloader;
    private MessageBusInterface $bus;

    public function __construct(AddonDownloader $downloader, MessageBusInterface $bus)
    {
        $this->downloader = $downloader;
        $this->bus = $bus;
    }

    /**
     * Prépare et envoie un message pour générer un zip d'une collection.
     *
     * @param Liste $liste
     * @return string Nom du zip généré
     */
    public function dispatchZipGeneration(Liste $liste): string
    {
        $addonUrls = array_map(
            fn($addon) => $addon->getIdBlender(),
            $liste->getAddons()->toArray()
        );

        $urls = $this->downloader->resolveArchiveUrls($addonUrls);

        $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';

        $this->bus->dispatch(new DownloadZipMessage($urls, $zipName));

        return $zipName;
    }
}
