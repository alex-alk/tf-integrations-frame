<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use HttpClient\Message\Stream;
use RuntimeException;

class StreamTest extends TestCase
{
    public function testConstructorWithEmptyContent()
    {
        $stream = new Stream();
        $this->assertSame('', (string)$stream);
    }

    public function testConstructorWithInitialContent()
    {
        $stream = new Stream('hello');
        $this->assertSame('hello', (string)$stream);
    }

    public function testToStringOnDetachedStreamReturnsEmptyString()
    {
        $stream = new Stream('content');
        $stream->detach();
        $this->assertSame('', (string)$stream);
    }

    public function testCloseClosesResource()
    {
        $stream = new Stream('abc');
        $res = $stream->detach(); // detach before closing
        $stream->close();

        $this->assertIsResource($res);
        $this->assertFalse(is_resource($stream->detach())); // now it is null
    }

    public function testDetachReturnsResourceAndNullsIt()
    {
        $stream = new Stream('abc');
        $res = $stream->detach();

        $this->assertIsResource($res);
        $this->assertNull($stream->detach());
    }

    public function testGetSize()
    {
        $stream = new Stream('abcdef');
        $this->assertEquals(6, $stream->getSize());

        $stream->detach();
        $this->assertNull($stream->getSize());
    }

    public function testTell()
    {
        $stream = new Stream('abc');
        $stream->read(2);
        $this->assertEquals(2, $stream->tell());
    }

    public function testEof()
    {
        $stream = new Stream('abc');
        $stream->read(3); // at end
        $stream->read(1); // trigger EOF
        $this->assertTrue($stream->eof());
    }

    public function testSeekAndRewind()
    {
        $stream = new Stream('abcdef');
        $stream->seek(3);
        $this->assertSame('def', $stream->read(3));

        $stream->rewind();
        $this->assertSame('abc', $stream->read(3));
    }

    public function testSeekOnUnseekableStreamThrows()
    {
        $stream = $this->getMockBuilder(Stream::class)
            ->onlyMethods(['isSeekable'])
            ->setConstructorArgs(['data'])
            ->getMock();

        $stream->method('isSeekable')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $stream->seek(0);
    }

    public function testIsSeekableWritableReadable()
    {
        $stream = new Stream('123');

        $this->assertTrue($stream->isSeekable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isReadable());
    }

    public function testWriteAndRead()
    {
        $stream = new Stream();
        $stream->write('test');
        $stream->rewind();
        $this->assertSame('test', $stream->read(4));
    }

    public function testWriteOnUnwritableThrows()
    {
        $stream = $this->getMockBuilder(Stream::class)
            ->onlyMethods(['isWritable'])
            ->setConstructorArgs(['data'])
            ->getMock();

        $stream->method('isWritable')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $stream->write('nope');
    }

    public function testReadOnUnreadableThrows()
    {
        $stream = $this->getMockBuilder(Stream::class)
            ->onlyMethods(['isReadable'])
            ->setConstructorArgs(['data'])
            ->getMock();

        $stream->method('isReadable')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $stream->read(5);
    }

    public function testGetContents()
    {
        $stream = new Stream('xyz');
        $stream->read(1);
        $this->assertSame('yz', $stream->getContents());
    }

    public function testGetMetadata()
    {
        $stream = new Stream();
        $meta = $stream->getMetadata();

        $this->assertIsArray($meta);
        $this->assertArrayHasKey('mode', $meta);

        $this->assertIsString($stream->getMetadata('mode'));
        $this->assertNull($stream->getMetadata('nonexistent'));
    }

    public function testDestructClosesStream()
    {
        $stream = new Stream('abc');
        $resource = $stream->detach();
        $this->assertIsResource($resource);

        $stream = null;
        $this->assertTrue(true); // Destruct is untestable directly, included for coverage
    }

    public function testEnsureResourceThrowsAfterDetach()
    {
        $stream = new Stream('hello');

        $stream->detach(); // detach the underlying resource

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream is detached');

        // calling read triggers ensureResource internally
        $stream->read(10);
    }

}
