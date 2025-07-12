<?php
namespace App\Message;

class DeleteZipMessage
{
    public function __construct(
        private readonly string $zipName
    ) {}

    public function getZipName(): string
    {
        return $this->zipName;
    }
}