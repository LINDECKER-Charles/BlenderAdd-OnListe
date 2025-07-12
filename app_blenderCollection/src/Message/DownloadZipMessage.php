<?php
namespace App\Message;

class DownloadZipMessage
{
    public function __construct(
        public array $urls,
        public string $zipName
    ) {}
}