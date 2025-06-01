<?php

namespace Router;

use Fig\Http\Message\StatusCodeInterface;
use HttpClient\Message\Request;

class JsonResponse implements StatusCodeInterface
{
    private string $json;
    private int $statusCode;

    public function __construct(array $data, int $statusCode = self::STATUS_OK)
    {
        $this->json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->statusCode = $statusCode;
    }
    
    public function __toString()
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        return $this->json;
    }
    
    public function getJson(): string
    {
        return $this->json;
    }
}
