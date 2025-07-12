<?php 
namespace App\MessageHandler;

use App\Message\DeleteZipMessage;
use App\Service\AddonDownloader;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteZipMessageHandler
{
    public function __construct(
        private readonly AddonDownloader $downloader
    ) {}

    public function __invoke(DeleteZipMessage $message): void
    {
        $this->downloader->deleteZip($message->getZipName());
    }
}