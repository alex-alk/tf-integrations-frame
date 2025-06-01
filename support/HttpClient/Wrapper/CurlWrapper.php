<?php

namespace HttpClient\Wrapper;

use CurlHandle;
use CurlMultiHandle;

class CurlWrapper
{
    // Single handle
    public function init(): CurlHandle
    {
        return curl_init();
    }

    public function setopt($ch, int $option, mixed $value): bool
    {
        return curl_setopt($ch, $option, $value);
    }

    public function setoptArray($ch, array $options): bool
    {
        return curl_setopt_array($ch, $options);
    }

    public function exec($ch): string|bool
    {
        return curl_exec($ch);
    }

    public function getinfo($ch, int $option): mixed
    {
        return curl_getinfo($ch, $option);
    }

    public function error($ch): string
    {
        return curl_error($ch);
    }

    public function close($ch): void
    {
        curl_close($ch);
    }

    // Multi handle
    public function multiInit(): CurlMultiHandle
    {
        return curl_multi_init();
    }

    public function multiAddHandle($mh, $ch): int
    {
        return curl_multi_add_handle($mh, $ch);
    }

    public function multiRemoveHandle($mh, $ch): int
    {
        return curl_multi_remove_handle($mh, $ch);
    }

    public function multiExec($mh, &$stillRunning): int
    {
        return curl_multi_exec($mh, $stillRunning);
    }

    public function multiSelect($mh, float $timeout = 1.0): int
    {
        return curl_multi_select($mh, $timeout);
    }

    public function multiGetContent($ch): string|false
    {
        return curl_multi_getcontent($ch);
    }

    public function multiClose($mh): void
    {
        curl_multi_close($mh);
    }
}