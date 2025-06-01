<?php

namespace Tests;

use HttpClient\Message\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class RequestTest extends TestCase
{
    public function testConstructorSetsBasicValues()
    {
        $request = new Request('GET', 'https://example.com', 'body', ['Content-Type' => 'application/json']);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals(['application/json'], $request->getHeader('content-type'));
        $this->assertEquals('/'.http_build_query([]), $request->getRequestTarget());
    }

    public function testWithMethodIsImmutable()
    {
        $original = new Request('GET', 'https://example.com');
        $modified = $original->withMethod('POST');

        $this->assertNotSame($original, $modified);
        $this->assertEquals('GET', $original->getMethod());
        $this->assertEquals('POST', $modified->getMethod());
    }

    public function testWithHeaderAndHeaderRetrieval()
    {
        $request = new Request('GET', 'https://example.com');
        $request = $request->withHeader('Content-Type', 'application/json');

        $this->assertTrue($request->hasHeader('content-type'));
        $this->assertEquals(['application/json'], $request->getHeader('Content-Type'));
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testWithAddedHeader()
    {
        $request = new Request('GET', 'https://example.com');
        $request = $request->withHeader('X-Test', 'one')->withAddedHeader('X-Test', 'two');

        $this->assertEquals(['one', 'two'], $request->getHeader('x-test'));
    }

    public function testWithoutHeader()
    {
        $request = new Request('GET', 'https://example.com');
        $request = $request->withHeader('X-Test', 'value');
        $this->assertTrue($request->hasHeader('X-Test'));

        $request = $request->withoutHeader('X-Test');
        $this->assertFalse($request->hasHeader('X-Test'));
    }

    public function testWithUriChangesUri()
    {
        $originalUri = $this->createMock(UriInterface::class);
        $newUri = $this->createMock(UriInterface::class);
        $newUri->method('getHost')->willReturn('newhost.com');
        $newUri->method('getPort')->willReturn(null);

        $request = new Request('GET', 'https://original.com');
        $modified = $request->withUri($newUri);

        $this->assertNotSame($request, $modified);
        $this->assertSame($newUri, $modified->getUri());
        $this->assertEquals(['newhost.com'], $modified->getHeader('host'));
    }

    public function testWithProtocolVersion()
    {
        $request = new Request('GET', 'https://example.com');
        $new = $request->withProtocolVersion('2');

        $this->assertNotSame($request, $new);
        $this->assertEquals('2', $new->getProtocolVersion());
    }

    public function testWithBody()
    {
        $stream = $this->createMock(StreamInterface::class);
        $request = new Request('GET', 'https://example.com');
        $new = $request->withBody($stream);

        $this->assertNotSame($request, $new);
        $this->assertSame($stream, $new->getBody());
    }

    public function testGetRequestTarget()
    {
        $request = new Request('GET', 'https://example.com/path?foo=bar');
        $this->assertEquals('/path?foo=bar', $request->getRequestTarget());
    }

    public function testWithRequestTarget()
    {
        $request = new Request('GET', 'https://example.com');
        $modified = $request->withRequestTarget('*');

        $this->assertEquals('*', $modified->getRequestTarget());
    }
}
