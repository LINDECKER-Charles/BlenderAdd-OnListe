<?php

namespace App\MessageHandler;

use App\Message\DownloadZipMessage;
use App\Service\AddonDownloader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DownloadZipMessageHandler
{
    public function __construct(
        private readonly AddonDownloader $addonDownloader,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(DownloadZipMessage $message): void
    {
        $this->logger->info('Téléchargement du ZIP : ' . $message->zipName);
        $this->addonDownloader->downloadAndZipForce($message->urls, $message->zipName);
    }
}
