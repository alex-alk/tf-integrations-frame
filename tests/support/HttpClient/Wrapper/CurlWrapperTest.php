<?php

namespace Tests;

use CurlHandle;
use HttpClient\Exception\RequestException;
use HttpClient\HttpClient;
use HttpClient\Message\Request;
use HttpClient\Wrapper\CurlWrapper;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertTrue;

class CurlWrapperTest extends TestCase
{
    public function test_functions()
    {
        $wrapper = new CurlWrapper();
        $ch = $wrapper->init();
        assertNotFalse($ch);
        assertNotFalse($wrapper->setopt($ch, CURLOPT_RETURNTRANSFER, true));
        assertNotFalse($wrapper->setoptArray($ch, [CURLOPT_RETURNTRANSFER => true]));
        assertFalse($wrapper->exec($ch));
        assertFalse($wrapper->getinfo($ch, 0));
        assertNotFalse($wrapper->error($ch));
        assertNull($wrapper->close($ch));

        $multi = $wrapper->multiInit();
        assertNotNull($multi);
        assertNotNull($wrapper->multiAddHandle($multi, $ch));
        assertNotNull($wrapper->multiRemoveHandle($multi, $ch));
        assertEmpty($wrapper->multiExec($multi, $running));
        assertEmpty($wrapper->multiSelect($multi));
        assertEmpty($wrapper->multiGetContent($ch));
        assertNull($wrapper->multiClose($multi));
    }
}