<?php

namespace RequestHandler;

use RequestHandler\Stream;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;

class UploadedFile implements UploadedFileInterface
{
    private string $file;
    private int $size;
    private int $error;
    private string $clientFilename;
    private string $clientMediaType;
    private bool $moved = false;
    private $filesystem;

    public function __construct(
        string $file,
        int $size,
        int $error,
        string $clientFilename,
        string $clientMediaType,
        ?FilesystemInterface $filesystem = null // inject dependency, with default
    ) {
        $this->file = $file;
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->filesystem = $filesystem ?: new LocalFilesystem();
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new \RuntimeException("File already moved");
        }
        return new Stream(fopen($this->file, 'rb'));
    }

    public function moveTo($targetPath): void
    {
        if ($this->moved) {
            throw new \RuntimeException("File already moved");
        }
        if (!$this->filesystem->isUploadedFile($this->file) ||
            !$this->filesystem->moveUploadedFile($this->file, $targetPath)) {
            throw new \RuntimeException("Failed to move uploaded file");
        }
        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}