<?php

namespace Models;

class RequestLog
{
	public string $method;
	public string $url;
	public string $body;
	public array $headers;

	public function __construct(string $method, string $url, string $body = '', $headers = []) 
	{
		$this->method = $method;
		$this->url = $url;
		$this->body = $body;
		$this->headers = $headers;
	}

	public string $statusCode;
	public string $response;
	public string $duration;
}