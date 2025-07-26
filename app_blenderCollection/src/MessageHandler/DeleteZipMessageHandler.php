<?php 
namespace App\MessageHandler;

use App\Message\DeleteZipMessage;
use App\Service\AddonDownloader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteZipMessageHandler
{
    public function __construct(
        private readonly AddonDownloader $downloader,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(DeleteZipMessage $message): void
    {
        $this->logger->info(message: 'Suppression du ZIP : ' . $message->getZipName());
        $this->downloader->deleteZip($message->getZipName());
    }
}
