<?php

namespace Tests\RequestHandler;

use PHPUnit\Framework\TestCase;
use RequestHandler\UploadedFile;
use RequestHandler\Stream;
use Psr\Http\Message\StreamInterface;
use RequestHandler\FilesystemInterface;
use RequestHandler\LocalFilesystem;

class UploadedFileTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        // Create a temporary file to simulate an uploaded file
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'upltest');
        file_put_contents($this->tmpFile, 'test content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testGetStreamReturnsStream()
    {
        $file = new UploadedFile($this->tmpFile, filesize($this->tmpFile), 0, 'file.txt', 'text/plain');
        $stream = $file->getStream();
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertStringContainsString('test content', (string)$stream);
    }

    public function testGetStreamThrowsIfMoved()
    {
        $file = new UploadedFile($this->tmpFile, filesize($this->tmpFile), 0, 'file.txt', 'text/plain');

        // Mark as moved via reflection since moveTo relies on is_uploaded_file which is tricky to mock
        $ref = new \ReflectionProperty($file, 'moved');
        $ref->setAccessible(true);
        $ref->setValue($file, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File already moved');

        $file->getStream();
    }

    public function testMoveToSuccess()
    {
        $mockFilesystem = $this->createMock(FilesystemInterface::class);
        $mockFilesystem->method('isUploadedFile')->willReturn(true);
        $mockFilesystem->method('moveUploadedFile')->willReturn(true);

        $localfs = new LocalFilesystem();

        $uploadedFile = new UploadedFile(
            'somefile.tmp',
            123,
            0,
            'clientname.jpg',
            'image/jpeg',
            $mockFilesystem
        );

        $uploadedFile->moveTo('/target/path');
        // Assert no exceptions and other behaviors...
        $this->expectNotToPerformAssertions();
        
        $localfs->moveUploadedFile('','');
    }

    public function testMoveToThrowsIfMoved()
    {
        $file = new UploadedFile($this->tmpFile, filesize($this->tmpFile), 0, 'file.txt', 'text/plain');

        $ref = new \ReflectionProperty($file, 'moved');
        $ref->setAccessible(true);
        $ref->setValue($file, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File already moved');

        $file->moveTo('/tmp/destination');
    }

    public function testMoveToThrowsIfMoveFails()
    {
        $file = new UploadedFile($this->tmpFile, filesize($this->tmpFile), 0, 'file.txt', 'text/plain');

        // We'll simulate failure by using a file that is not an uploaded file, so is_uploaded_file will fail
        // This will trigger the exception

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to move uploaded file');

        $file->moveTo('/tmp/nowhere');
    }

    public function testGetters()
    {
        $file = new UploadedFile($this->tmpFile, 1234, 1, 'foo.txt', 'text/html');

        $this->assertSame(1234, $file->getSize());
        $this->assertSame(1, $file->getError());
        $this->assertSame('foo.txt', $file->getClientFilename());
        $this->assertSame('text/html', $file->getClientMediaType());
    }
}
