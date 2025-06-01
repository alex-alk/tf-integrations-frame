<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Router\View;
use Router\ViewNotFoundException;

class ViewTest extends TestCase
{
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = __DIR__ . '/../../../views';

        if (!file_exists($this->viewsPath)) {
            mkdir($this->viewsPath, 0777, true);
        }

        file_put_contents($this->viewsPath . '/test.php', 'Hello, <?= $name ?>!');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->viewsPath . '/test.php')) {
            unlink($this->viewsPath . '/test.php');
        }

        if (is_dir($this->viewsPath)) {
            rmdir($this->viewsPath);
        }
    }

    public function testMakeCreatesInstance()
    {
        $view = View::make('test', ['name' => 'ChatGPT']);
        $this->assertInstanceOf(View::class, $view);
    }

    public function testRenderReturnsRenderedContent()
    {
        $view = View::make('test', ['name' => 'World']);
        $output = $view->render();

        $this->assertSame('Hello, World!', trim($output));
    }

    public function testRenderThrowsExceptionForMissingView()
    {
        $this->expectException(ViewNotFoundException::class);
        $view = View::make('nonexistent');
        $view->render();
    }

    public function testToStringReturnsRenderedOutput()
    {
        $view = View::make('test', ['name' => 'PHPUnit']);
        $this->assertSame('Hello, PHPUnit!', trim((string)$view));
    }
}
