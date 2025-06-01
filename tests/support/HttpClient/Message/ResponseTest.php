<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use HttpClient\Message\Response;

class ResponseTest extends TestCase
{
    public function testConstructorDefaults()
    {
        $response = new Response('hello');

        $response->getReasonPhrase();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertInstanceOf(StreamInterface::class, $response->getBody());
    }

    public function testWithStatusIsImmutable()
    {
        $response = new Response('body', 200);
        $new = $response->withStatus(404);

        $this->assertNotSame($response, $new);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(404, $new->getStatusCode());
    }

    public function testWithHeaderAndHeaderRetrieval()
    {
        $response = new Response();
        $response = $response->withHeader('Content-Type', 'application/json');

        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertEquals(['application/json'], $response->getHeader('content-type'));
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testWithAddedHeader()
    {
        $response = new Response();
        $response = $response->withHeader('X-Test', 'one')->withAddedHeader('X-Test', 'two');

        $this->assertEquals(['one', 'two'], $response->getHeader('x-test'));
    }

    public function testWithoutHeader()
    {
        $response = (new Response())->withHeader('X-Test', 'value');
        $this->assertTrue($response->hasHeader('x-test'));

        $response = $response->withoutHeader('X-Test');
        $this->assertFalse($response->hasHeader('X-Test'));
    }

    public function testWithProtocolVersion()
    {
        $response = new Response();
        $new = $response->withProtocolVersion('2');

        $this->assertEquals('1.1', $response->getProtocolVersion());
        $this->assertEquals('2', $new->getProtocolVersion());
    }

    public function testWithBody()
    {
        $stream = $this->createMock(StreamInterface::class);

        $response = new Response();
        $new = $response->withBody($stream);

        $this->assertNotSame($response, $new);
        $this->assertSame($stream, $new->getBody());
    }
}
