<?php

namespace Tests\HttpClient\Message;

use HttpClient\Message\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UriTest extends TestCase
{
    public function testConstructorParsesFullUri()
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=1#frag');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('query=1', $uri->getQuery());
        $this->assertSame('frag', $uri->getFragment());

        $expectedAuthority = 'user:pass@example.com:8080';
        $this->assertSame($expectedAuthority, $uri->getAuthority());

        $expectedString = 'https://user:pass@example.com:8080/path?query=1#frag';
        $this->assertSame($expectedString, (string)$uri);
    }

    public function testConstructorEmptyUriDefaults()
    {
        $uri = new Uri('');

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('', $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
        $this->assertSame('', $uri->getAuthority());
        $this->assertSame('', (string)$uri);
    }

    public function testConstructorThrowsOnMalformedUri()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed URI');

        // Use a string that parse_url returns false for:
        // parse_url returns false for an empty host after scheme, e.g. "http:///path"
        new Uri('http:///path');
    }

    public function testWithSchemeReturnsNewInstanceWithLowercaseScheme()
    {
        $uri = new Uri();
        $newUri = $uri->withScheme('HTTP');

        $this->assertNotSame($uri, $newUri);
        $this->assertSame('', $uri->getScheme());
        $this->assertSame('http', $newUri->getScheme());
    }

    public function testWithUserInfoReturnsNewInstance()
    {
        $uri = new Uri();
        $newUri = $uri->withUserInfo('user', 'pass');

        $this->assertNotSame($uri, $newUri);
        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('user:pass', $newUri->getUserInfo());

        // test without password
        $newUri2 = $uri->withUserInfo('user');

        $this->assertSame('user', $newUri2->getUserInfo());
    }

    public function testWithHostReturnsNewInstance()
    {
        $uri = new Uri();
        $newUri = $uri->withHost('example.com');

        $this->assertNotSame($uri, $newUri);
        $this->assertSame('', $uri->getHost());
        $this->assertSame('example.com', $newUri->getHost());
    }

    public function testWithPortReturnsNewInstance()
    {
        $uri = new Uri();
        $newUri = $uri->withPort(1234);

        $this->assertNotSame($uri, $newUri);
        $this->assertNull($uri->getPort());
        $this->assertSame(1234, $newUri->getPort());
    }

    public function testWithPathReturnsNewInstance()
    {
        $uri = new Uri();
        $newUri = $uri->withPath('/newpath');

        $this->assertNotSame($uri, $newUri);
        $this->assertSame('', $uri->getPath());
        $this->assertSame('/newpath', $newUri->getPath());
    }

    public function testWithQueryReturnsNewInstanceAndStripsLeadingQuestionMark()
    {
        $uri = new Uri();
        $newUri = $uri->withQuery('?foo=bar');

        $this->assertNotSame($uri, $newUri);
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('foo=bar', $newUri->getQuery());

        // Passing string without question mark keeps it unchanged
        $newUri2 = $uri->withQuery('baz=qux');
        $this->assertSame('baz=qux', $newUri2->getQuery());
    }

    public function testWithFragmentReturnsNewInstanceAndStripsLeadingHash()
    {
        $uri = new Uri();
        $newUri = $uri->withFragment('#section1');

        $this->assertNotSame($uri, $newUri);
        $this->assertSame('', $uri->getFragment());
        $this->assertSame('section1', $newUri->getFragment());

        // Passing string without hash keeps it unchanged
        $newUri2 = $uri->withFragment('section2');
        $this->assertSame('section2', $newUri2->getFragment());
    }

    public function testToStringFormatsCorrectly()
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withUserInfo('user', 'pass')
            ->withHost('example.com')
            ->withPort(8443)
            ->withPath('/some/path')
            ->withQuery('a=1&b=2')
            ->withFragment('top');

        $expected = 'https://user:pass@example.com:8443/some/path?a=1&b=2#top';
        $this->assertSame($expected, (string)$uri);
    }

    public function testGetAuthorityReturnsCorrectFormat()
    {
        $uri = (new Uri())
            ->withUserInfo('user', 'pass')
            ->withHost('host.com')
            ->withPort(1234);

        $expected = 'user:pass@host.com:1234';
        $this->assertSame($expected, $uri->getAuthority());

        // No userInfo
        $uriNoUser = (new Uri())
            ->withHost('host.com')
            ->withPort(1234);
        $this->assertSame('host.com:1234', $uriNoUser->getAuthority());

        // No port
        $uriNoPort = (new Uri())
            ->withUserInfo('user')
            ->withHost('host.com');
        $this->assertSame('user@host.com', $uriNoPort->getAuthority());

        // Neither userInfo nor port
        $uriSimple = (new Uri())->withHost('host.com');
        $this->assertSame('host.com', $uriSimple->getAuthority());
    }
}
