<?php

namespace RequestHandler;

use HttpClient\Message\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class ServerRequest implements ServerRequestInterface
{
    private array $serverParams;
    private array $cookieParams = [];
    private array $queryParams = [];
    private array $uploadedFiles = [];
    private $parsedBody = null;
    private array $attributes = [];

    private string $method;
    private UriInterface $uri;
    private string $protocolVersion = '1.1';
    private array $headers = [];
    private StreamInterface $body;

    public function __construct(
        string $method,
        UriInterface $uri,
        array $headers,
        StreamInterface $body,
        string $protocolVersion,
        array $serverParams
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $body;

        $this->parsedBody = json_decode($body, true);

        $this->protocolVersion = $protocolVersion;
        $this->serverParams = $serverParams;
    }

    // --- Factory ---
    public static function createFromGlobals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = self::createUriFromGlobals($_SERVER);
        $headers = self::getAllHeaders();
        $body = new Stream(fopen('php://input', 'rb'));
        $protocol = str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'] ?? '1.1');
        $request = new self($method, $uri, $headers, $body, $protocol, $_SERVER);

        return $request
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET)
            ->withUploadedFiles(self::normalizeUploadedFiles($_FILES));
    }

    private static function createUriFromGlobals(array $server): UriInterface
    {
        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost';
        $port = $server['SERVER_PORT'] ?? null;
        $uri = $server['REQUEST_URI'] ?? '/';
        $uri = new Uri("$scheme://$host$uri");
        if ($port && !in_array($port, [80, 443])) {
            $uri = $uri->withPort((int)$port);
        }
        return $uri;
    }

    private static function getAllHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $name = str_replace('_', '-', substr($name, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    private static function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $field => $value) {
            if (is_array($value['name'])) {
                $normalized[$field] = self::normalizeNestedFiles($value);
            } else {
                $normalized[$field] = new UploadedFile(
                    $value['tmp_name'],
                    (int)$value['size'],
                    (int)$value['error'],
                    $value['name'],
                    $value['type']
                );
            }
        }
        return $normalized;
    }

    private static function normalizeNestedFiles(array $fileSpec): array
    {
        $files = [];
        foreach ($fileSpec['name'] as $key => $name) {
            $files[$key] = new UploadedFile(
                $fileSpec['tmp_name'][$key],
                $fileSpec['size'][$key],
                $fileSpec['error'][$key],
                $fileSpec['name'][$key],
                $fileSpec['type'][$key]
            );
        }
        return $files;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? $value : [$value];
        }
        return $normalized;
    }

    // --- ServerRequestInterface ---
    public function getServerParams(): array {
        return $this->serverParams;
    }

    public function getCookieParams(): array {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequestInterface {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody() {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null) {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): ServerRequestInterface {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute($name): ServerRequestInterface {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    // --- RequestInterface ---
    public function getRequestTarget(): string {
        $path = $this->uri->getPath();
        $query = $this->uri->getQuery();
        return $path . ($query ? "?$query" : '');
    }

    public function withRequestTarget($requestTarget): RequestInterface {
        throw new \BadMethodCallException("Not implemented");
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function withMethod($method): RequestInterface {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    public function getUri(): UriInterface {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
    }

    // --- MessageInterface ---
    public function getProtocolVersion(): string {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): MessageInterface {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    public function hasHeader($name): bool {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader($name): array {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine($name): string {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): MessageInterface {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = (array)$value;
        return $clone;
    }

    public function withAddedHeader($name, $value): MessageInterface {
        $clone = clone $this;
        $normalized = strtolower($name);
        $clone->headers[$normalized] = array_merge($this->getHeader($name), (array)$value);
        return $clone;
    }

    public function withoutHeader($name): MessageInterface {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);
        return $clone;
    }

    public function getBody(): StreamInterface {
        return $this->body;
    }

    public function withBody(StreamInterface $body): MessageInterface {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }
}
