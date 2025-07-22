<?php

namespace App\Tests\Service;

use App\Service\UploadManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class UploadManagerTest extends TestCase
{
    private function createUploadManager(string $uploadDir = '/tmp'): UploadManager
    {
        $slugger = $this->createMock(SluggerInterface::class);
        $slugger->method('slug')->willReturn('image_name');

        return new UploadManager($uploadDir, $slugger);
    }

    public function testUploadLocalFileRejectsUnsupportedMimeType()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/pdf');

        $uploadManager = $this->createUploadManager();

        $result = $uploadManager->uploadLocalFile($file);
        $this->assertNull($result);
    }

    public function testUploadLocalFileAcceptsValidImageAndMovesIt()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getClientOriginalName')->willReturn('original name.jpg');
        $file->method('guessExtension')->willReturn('jpg');

        $file->expects($this->once())
             ->method('move')
             ->with(
                 $this->equalTo('/tmp'),
                 $this->matchesRegularExpression('/^image_name_[a-z0-9]+\.jpg$/')
             );

        $uploadManager = $this->createUploadManager();

        $filename = $uploadManager->uploadLocalFile($file);
        $this->assertMatchesRegularExpression('/^image_name_[a-z0-9]+\.jpg$/', $filename);
    }

    public function testUploadLocalFileUsesCustomName()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('image/png');
        $file->method('guessExtension')->willReturn('png');

        $file->expects($this->once())
            ->method('move')
            ->with(
                $this->equalTo('/tmp'),
                $this->matchesRegularExpression('/^custom_filename_[a-z0-9]+\.png$/')
            );

        $slugger = $this->createMock(SluggerInterface::class);
        $slugger->method('slug')->with('custom_filename', '_')->willReturn('custom_filename');

        $uploadManager = new UploadManager('/tmp', $slugger);
        $result = $uploadManager->uploadLocalFile($file, 'custom_filename.png');

        $this->assertMatchesRegularExpression('/^custom_filename_[a-z0-9]+\.png$/', $result);
    }
}
