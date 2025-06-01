<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertTrue;

class WebControllerTest extends TestCase
{
    public function test_ok()
    {
        assertTrue(1==1);
    }
}