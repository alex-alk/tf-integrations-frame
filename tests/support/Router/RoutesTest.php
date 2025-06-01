<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Router\Routes;

class RoutesTest extends TestCase
{
    public function testRegisterAddsRoute()
    {
        $routes = new Routes();
        $action = fn () => 'Hello';

        $routes->register('GET', '/test', $action);
        $allRoutes = $routes->getRoutes();

        $this->assertArrayHasKey('GET', $allRoutes);
        $this->assertArrayHasKey('/test', $allRoutes['GET']);
        $this->assertSame($action, $allRoutes['GET']['/test']);
    }

    public function testGetAddsGetRoute()
    {
        $routes = new Routes();
        $action = fn () => 'GET route';

        $routes->get('/home', $action);
        $allRoutes = $routes->getRoutes();

        $this->assertArrayHasKey('GET', $allRoutes);
        $this->assertArrayHasKey('/home', $allRoutes['GET']);
        $this->assertSame($action, $allRoutes['GET']['/home']);
    }

    public function testPostAddsPostRoute()
    {
        $routes = new Routes();
        $action = fn () => 'POST route';

        $routes->post('/submit', $action);
        $allRoutes = $routes->getRoutes();

        $this->assertArrayHasKey('POST', $allRoutes);
        $this->assertArrayHasKey('/submit', $allRoutes['POST']);
        $this->assertSame($action, $allRoutes['POST']['/submit']);
    }

    public function testFluentInterface()
    {
        $routes = new Routes();
        $result = $routes->get('/one', fn () => 'ok')->post('/two', fn () => 'ok');

        $this->assertInstanceOf(Routes::class, $result);
        $this->assertCount(1, $routes->getRoutes()['GET']);
        $this->assertCount(1, $routes->getRoutes()['POST']);
    }
}