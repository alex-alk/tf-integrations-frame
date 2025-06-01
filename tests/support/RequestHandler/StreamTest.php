<?php

namespace Tests\RequestHandler;

use PHPUnit\Framework\TestCase;
use RequestHandler\Stream;

class StreamTest extends TestCase
{
    private $resource;

    protected function setUp(): void
    {
        $this->resource = fopen('php://temp', 'r+');
        fwrite($this->resource, "hello world");
        rewind($this->resource);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    public function testConstructThrowsOnInvalidResource()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Stream('not a resource');
    }

    public function testGetSizeReturnsSize()
    {
        $stream = new Stream($this->resource);
        $this->assertEquals(11, $stream->getSize());
    }

    public function testToStringReturnsContent()
    {
        $stream = new Stream($this->resource);
        $this->assertSame("hello world", (string)$stream);
    }

    public function testCloseClosesStream()
    {
        $stream = new Stream($this->resource);
        $stream->close();
        $this->assertFalse(is_resource($this->resource));
    }

    public function testDetachReturnsResourceAndNullsProperty()
    {
        $stream = new Stream($this->resource);

        $res = $stream->detach();
        $this->assertSame($this->resource, $res);

        // Calling getSize() still works (returns original size)
        $this->assertEquals(11, $stream->getSize());

        // But calling tell() now should throw an error because stream is null
        $this->expectException(\Error::class);
        $stream->tell();
    }

    public function testTellReturnsCurrentPosition()
    {
        $stream = new Stream($this->resource);
        fseek($this->resource, 5);
        $this->assertEquals(5, $stream->tell());
    }

    public function testEofReturnsTrueWhenAtEnd()
    {
        $stream = new Stream($this->resource);
        fseek($this->resource, 11); // move to end

        // Read once to trigger EOF
        fread($this->resource, 1);

        $this->assertTrue($stream->eof());
    }

    public function testIsSeekableReturnsTrue()
    {
        $stream = new Stream($this->resource);
        $this->assertTrue($stream->isSeekable());
    }

    public function testSeekAndRewindMovesPosition()
    {
        $stream = new Stream($this->resource);
        $stream->seek(6);
        $this->assertEquals(6, ftell($this->resource));

        $stream->rewind();
        $this->assertEquals(0, ftell($this->resource));
    }

    public function testIsWritableDetectsWritableModes()
    {
        // The temp stream opened with 'r+' is writable
        $stream = new Stream($this->resource);
        $this->assertTrue($stream->isWritable());
    }

    public function testWriteWritesData()
    {
        $stream = new Stream($this->resource);
        $stream->seek(0);
        $bytes = $stream->write('abc');
        $this->assertEquals(3, $bytes);

        $stream->rewind();
        $contents = stream_get_contents($this->resource);
        $this->assertStringStartsWith('abc', $contents);
    }

    public function testIsReadableDetectsReadableModes()
    {
        $stream = new Stream($this->resource);
        $this->assertTrue($stream->isReadable());
    }

    public function testReadReadsData()
    {
        $stream = new Stream($this->resource);
        $stream->seek(0);
        $data = $stream->read(5);
        $this->assertEquals('hello', $data);
    }

    public function testGetContentsReturnsRemainingContent()
    {
        $stream = new Stream($this->resource);
        $stream->seek(6);
        $content = $stream->getContents();
        $this->assertEquals('world', $content);
    }

    public function testGetMetadataReturnsFullMetadata()
    {
        $stream = new Stream($this->resource);
        $meta = $stream->getMetadata();
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('mode', $meta);
    }

    public function testGetMetadataWithKeyReturnsValue()
    {
        $stream = new Stream($this->resource);
        $mode = $stream->getMetadata('mode');
        $this->assertIsString($mode);
    }

    public function testGetMetadataWithInvalidKeyReturnsNull()
    {
        $stream = new Stream($this->resource);
        $value = $stream->getMetadata('nonexistent');
        $this->assertNull($value);
    }

    public function testToStringReturnsStreamContents()
    {
        $stream = new Stream($this->resource);

        $this->assertEquals('hello world', $stream->__toString());
    }

    public function testToStringReturnsEmptyStringOnException()
    {
        $stream = new Stream($this->resource);
        $stream->detach(); // detach the resource, so __toString will fail

        $this->assertEquals('', (string) $stream);
    }
}
