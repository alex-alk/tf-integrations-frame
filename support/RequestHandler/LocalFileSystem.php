<?php

namespace RequestHandler;

class LocalFilesystem implements FilesystemInterface
{
    public function isUploadedFile(string $file): bool
    {
        return is_uploaded_file($file);
    }
    public function moveUploadedFile(string $file, string $target): bool
    {
        return move_uploaded_file($file, $target);
    }
}