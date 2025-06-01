<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use RequestHandler\ServerRequest;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use RequestHandler\UploadedFile;

class ServerRequestTest extends TestCase
{
    private UriInterface $uriMock;
    private StreamInterface $streamMock;

    protected function setUp(): void
    {
        $this->uriMock = $this->createMock(UriInterface::class);
        $this->streamMock = $this->createMock(StreamInterface::class);
    }

    private function createRequest(array $headers = []): ServerRequest
    {
        return new ServerRequest(
            'POST',
            $this->uriMock,
            $headers,
            $this->streamMock,
            '1.1',
            ['SERVER_NAME' => 'localhost']
        );
    }

    public function testConstructorAndGetters()
    {
        $headers = ['Content-Type' => 'application/json'];
        $request = $this->createRequest($headers);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertSame($this->uriMock, $request->getUri());
        $this->assertSame($this->streamMock, $request->getBody());
        $this->assertEquals(['application/json'], $request->getHeader('Content-Type'));
    }

    public function testWithCookieParams()
    {
        $request = $this->createRequest();
        $new = $request->withCookieParams(['foo' => 'bar']);

        $this->assertNotSame($request, $new);
        $this->assertEquals(['foo' => 'bar'], $new->getCookieParams());
    }

    public function testWithQueryParams()
    {
        $request = $this->createRequest();
        $new = $request->withQueryParams(['q' => 'search']);

        $this->assertNotSame($request, $new);
        $this->assertEquals(['q' => 'search'], $new->getQueryParams());
    }

    public function testWithParsedBody()
    {
        $request = $this->createRequest();
        $parsed = ['foo' => 'bar'];
        $new = $request->withParsedBody($parsed);

        $this->assertNotSame($request, $new);
        $this->assertEquals($parsed, $new->getParsedBody());
    }

    public function testWithAndGetAttribute()
    {
        $request = $this->createRequest();
        $new = $request->withAttribute('id', 42);

        $this->assertNotSame($request, $new);
        $this->assertEquals(42, $new->getAttribute('id'));
        $this->assertEquals(null, $request->getAttribute('id'));
    }

    public function testWithoutAttribute()
    {
        $request = $this->createRequest()->withAttribute('id', 42);
        $new = $request->withoutAttribute('id');

        $this->assertNull($new->getAttribute('id'));
    }

    public function testWithHeaderAndGetHeaderLine()
    {
        $request = $this->createRequest();
        $new = $request->withHeader('X-Test', ['A', 'B']);

        $this->assertEquals(['A', 'B'], $new->getHeader('X-Test'));
        $this->assertEquals('A, B', $new->getHeaderLine('X-Test'));
    }

    public function testWithAddedHeader()
    {
        $request = $this->createRequest();
        $req = $request->withHeader('X-Foo', 'Bar');
        $new = $req->withAddedHeader('X-Foo', 'Baz');

        $this->assertEquals(['Bar', 'Baz'], $new->getHeader('X-Foo'));
    }

    public function testWithoutHeader()
    {
        $request = $this->createRequest(['X-Foo' => 'Bar']);
        $new = $request->withoutHeader('X-Foo');

        $this->assertFalse($new->hasHeader('X-Foo'));
    }

    public function testWithMethod()
    {
        $request = $this->createRequest();
        $new = $request->withMethod('PUT');

        $this->assertNotSame($request, $new);
        $this->assertEquals('PUT', $new->getMethod());
    }

    public function testWithUri()
    {
        $uri2 = $this->createMock(UriInterface::class);
        $request = $this->createRequest();
        $new = $request->withUri($uri2);

        $this->assertNotSame($request, $new);
        $this->assertSame($uri2, $new->getUri());
    }

    public function testWithBody()
    {
        $body2 = $this->createMock(StreamInterface::class);
        $request = $this->createRequest();
        $new = $request->withBody($body2);

        $this->assertSame($body2, $new->getBody());
    }

    public function testGetRequestTarget()
    {
        $this->uriMock->method('getPath')->willReturn('/test');
        $this->uriMock->method('getQuery')->willReturn('a=1&b=2');

        $request = $this->createRequest();
        $this->assertEquals('/test?a=1&b=2', $request->getRequestTarget());
    }

    public function testWithRequestTargetThrows()
    {
        $this->expectException(\BadMethodCallException::class);

        $request = $this->createRequest();
        $request->withRequestTarget('/override');
    }

    public function testCreateFromGlobals()
    {
        // Backup superglobals
        $backupServer = $_SERVER;
        $backupGet = $_GET;
        $backupPost = $_POST;
        $backupCookie = $_COOKIE;
        $backupFiles = $_FILES;

        // Simulate request
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/upload?debug=true',
            'HTTP_HOST' => 'example.com',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_PORT' => 8080,
            'HTTPS' => 'on'
        ];
        $_GET = ['debug' => 'true'];
        $_POST = ['title' => 'Test'];
        $_COOKIE = ['session' => 'abc123'];
        $_FILES = [
            'file' => [
                'name' => 'image.png',
                'type' => 'image/png',
                'tmp_name' => '/tmp/php123',
                'error' => 0,
                'size' => 123456
            ]
        ];

        // Run the factory
        $request = \RequestHandler\ServerRequest::createFromGlobals();

        // Assert method
        $this->assertEquals('POST', $request->getMethod());

        // Assert URI
        $this->assertInstanceOf(\Psr\Http\Message\UriInterface::class, $request->getUri());
        $this->assertEquals('/upload', $request->getUri()->getPath());

        // Assert protocol
        $this->assertEquals('1.1', $request->getProtocolVersion());

        // Assert cookies, GET, POST, FILES
        $this->assertEquals($_COOKIE, $request->getCookieParams());
        $this->assertEquals($_GET, $request->getQueryParams());
        $this->assertEquals($_POST, $request->getParsedBody());
        $this->assertArrayHasKey('file', $request->getUploadedFiles());
        $this->assertInstanceOf(UploadedFile::class, $request->getUploadedFiles()['file']);

        // Restore superglobals
        $_SERVER = $backupServer;
        $_GET = $backupGet;
        $_POST = $backupPost;
        $_COOKIE = $backupCookie;
        $_FILES = $backupFiles;
    }

    public function testGetAttributesReturnsAllAttributes()
    {
        $uri = new \HttpClient\Message\Uri('http://example.com');
        $body = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $request = new \RequestHandler\ServerRequest('GET', $uri, [], $body, '1.1', []);

        $request = $request->withAttribute('foo', 'bar');
        $request = $request->withAttribute('baz', 123);

        $attributes = $request->getAttributes();

        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('foo', $attributes);
        $this->assertArrayHasKey('baz', $attributes);
        $this->assertSame('bar', $attributes['foo']);
        $this->assertSame(123, $attributes['baz']);
    }

    public function testWithProtocolVersionReturnsNewInstanceWithUpdatedVersion()
    {
        $uri = new \HttpClient\Message\Uri('http://example.com');
        $body = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $request = new \RequestHandler\ServerRequest('GET', $uri, [], $body, '1.1', []);

        $newRequest = $request->withProtocolVersion('2.0');

        $this->assertNotSame($request, $newRequest);
        $this->assertEquals('2.0', $newRequest->getProtocolVersion());
        $this->assertEquals('1.1', $request->getProtocolVersion());
    }

    public function testGetHeadersReturnsNormalizedHeaders()
    {
        $uri = new \HttpClient\Message\Uri('http://example.com');
        $body = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        // Provide mixed-case headers to check normalization
        $headers = [
            'Content-Type' => 'application/json',
            'ACCEPT' => ['application/xml', 'text/html'],
        ];

        $request = new \RequestHandler\ServerRequest('GET', $uri, $headers, $body, '1.1', []);

        $retrievedHeaders = $request->getHeaders();

        $this->assertIsArray($retrievedHeaders);
        $this->assertArrayHasKey('content-type', $retrievedHeaders);
        $this->assertArrayHasKey('accept', $retrievedHeaders);
        $this->assertEquals(['application/json'], $retrievedHeaders['content-type']);
        $this->assertEquals(['application/xml', 'text/html'], $retrievedHeaders['accept']);
    }

    public function testGetServerParamsReturnsConstructorValue()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'POST',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];

        $uri = new \HttpClient\Message\Uri('http://example.com');
        $body = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $request = new \RequestHandler\ServerRequest('POST', $uri, [], $body, '1.1', $serverParams);

        $this->assertSame($serverParams, $request->getServerParams());
    }

    public function testNormalizeNestedFilesReturnsUploadedFileInstances()
    {
        // Create temporary files for the test
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'phpunit_');
        file_put_contents($tmpFile1, 'dummy content 1');

        $tmpFile2 = tempnam(sys_get_temp_dir(), 'phpunit_');
        file_put_contents($tmpFile2, 'dummy content 2');

        $fileSpec = [
            'name' => ['file1.txt', 'file2.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => [$tmpFile1, $tmpFile2],  // use real temp files
            'error' => [0, 0],
            'size' => [strlen('dummy content 1'), strlen('dummy content 2')],
        ];

        $reflection = new \ReflectionClass(\RequestHandler\ServerRequest::class);
        $method = $reflection->getMethod('normalizeNestedFiles');
        $method->setAccessible(true);

        $result = $method->invoke(null, $fileSpec);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        foreach ($result as $file) {
            $this->assertInstanceOf(\RequestHandler\UploadedFile::class, $file);
        }

        // Cleanup temporary files
        unlink($tmpFile1);
        unlink($tmpFile2);
    }



    public function testGetAllHeadersReturnsHeadersFromServer()
    {
        // Simulate $_SERVER with HTTP_ headers
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'PHPUnit',
            'NON_HTTP_HEADER' => 'should not appear',
        ];

        $reflection = new \ReflectionClass(\RequestHandler\ServerRequest::class);
        $method = $reflection->getMethod('getAllHeaders');
        $method->setAccessible(true);

        // Backup original $_SERVER and replace for testing
        $backupServer = $_SERVER;
        $_SERVER = $server;

        $headers = $method->invoke(null);

        $_SERVER = $backupServer; // restore

        $this->assertArrayHasKey('HOST', $headers);
        $this->assertArrayHasKey('USER-AGENT', $headers);
        $this->assertEquals('example.com', $headers['HOST']);
        $this->assertEquals('PHPUnit', $headers['USER-AGENT']);
        $this->assertArrayNotHasKey('NON_HTTP_HEADER', $headers);
    }

    public function testNormalizeUploadedFilesHandlesSingleAndNestedFiles()
    {
        // Prepare single file array (non-nested)
        $tmpFile = tempnam(sys_get_temp_dir(), 'phpunit_');
        file_put_contents($tmpFile, 'dummy content');

        $singleFiles = [
            'file1' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => $tmpFile,
                'error' => 0,
                'size' => strlen('dummy content'),
            ],
        ];

        // Prepare nested files array
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'phpunit_');
        file_put_contents($tmpFile1, 'nested content 1');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'phpunit_');
        file_put_contents($tmpFile2, 'nested content 2');

        $nestedFiles = [
            'file2' => [
                'name' => ['file1.txt', 'file2.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => [$tmpFile1, $tmpFile2],
                'error' => [0, 0],
                'size' => [strlen('nested content 1'), strlen('nested content 2')],
            ],
        ];

        $allFiles = $singleFiles + $nestedFiles;

        $reflection = new \ReflectionClass(\RequestHandler\ServerRequest::class);
        $method = $reflection->getMethod('normalizeUploadedFiles');
        $method->setAccessible(true);

        $result = $method->invoke(null, $allFiles);

        // Assertions for single file case
        $this->assertArrayHasKey('file1', $result);
        $this->assertInstanceOf(\RequestHandler\UploadedFile::class, $result['file1']);

        // Assertions for nested files case
        $this->assertArrayHasKey('file2', $result);
        $this->assertIsArray($result['file2']);
        $this->assertCount(2, $result['file2']);
        foreach ($result['file2'] as $uploadedFile) {
            $this->assertInstanceOf(\RequestHandler\UploadedFile::class, $uploadedFile);
        }

        // Clean up
        unlink($tmpFile);
        unlink($tmpFile1);
        unlink($tmpFile2);
    }

}
