<?php

namespace RequestHandler;

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    private $stream;
    private int $size;

    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a valid resource');
        }
        $this->stream = $stream;
        $this->size = fstat($this->stream)['size'] ?? 0;
    }

    public function __toString(): string
    {
        try {
            $this->seek(0);
            return stream_get_contents($this->stream);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        fclose($this->stream);
    }

    public function detach()
    {
        $result = $this->stream;
        $this->stream = null;
        return $result;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return ftell($this->stream);
    }

    public function eof(): bool
    {
        return feof($this->stream);
    }

    public function isSeekable(): bool
    {
        $meta = stream_get_meta_data($this->stream);
        return $meta['seekable'];
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        fseek($this->stream, $offset, $whence);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        $meta = stream_get_meta_data($this->stream);
        return strpbrk($meta['mode'], 'wca+');
    }

    public function write($string): int
    {
        return fwrite($this->stream, $string);
    }

    public function isReadable(): bool
    {
        $meta = stream_get_meta_data($this->stream);
        return strpbrk($meta['mode'], 'r+');
    }

    public function read($length): string
    {
        return fread($this->stream, $length);
    }

    public function getContents(): string
    {
        return stream_get_contents($this->stream);
    }

    public function getMetadata($key = null)
    {
        $meta = stream_get_meta_data($this->stream);
        return $key ? ($meta[$key] ?? null) : $meta;
    }
}
