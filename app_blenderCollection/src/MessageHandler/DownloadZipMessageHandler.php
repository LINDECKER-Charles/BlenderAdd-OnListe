<?php

// src/MessageHandler/DownloadZipMessageHandler.php
namespace App\MessageHandler;

use App\Message\DownloadZipMessage;
use App\Service\AddonDownloader;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DownloadZipMessageHandler
{
    public function __construct(private AddonDownloader $addonDownloader) {}

    public function __invoke(DownloadZipMessage $message): void
    {
        $this->addonDownloader->downloadAndZipForce($message->urls, $message->zipName);
    }
}
