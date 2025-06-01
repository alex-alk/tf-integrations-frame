<?php

namespace RequestHandler;

interface FilesystemInterface
{
    public function isUploadedFile(string $file): bool;
    public function moveUploadedFile(string $file, string $target): bool;
}