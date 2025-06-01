<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use RequestHandler\RequestHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Router\RouteNotFoundException;
use HttpClient\Message\Response;
use HttpClient\Message\Uri;

class RequestHandlerTest extends TestCase
{
    private $container;
    private $request;

    protected function setUp(): void
    {
        // Mock container
        $this->container = $this->createMock(ContainerInterface::class);

        // Mock request
        $this->request = $this->createMock(ServerRequestInterface::class);
    }

    public function testHandleWithSimpleCallableRouteReturnsResponse()
    {
        $routes = [
            'GET' => [
                '/simple' => fn() => new Response('ok', 200)
            ]
        ];

        $this->request->method('getMethod')->willReturn('GET');
        $uri = new Uri('/simple');
        $this->request->method('getUri')->willReturn($uri);

        $handler = new RequestHandler($routes, $this->container);

        $response = $handler->handle($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleWithSimpleClassMethodRouteReturnsResponse()
    {
        $routes = [
            'GET' => [
                '/classmethod' => [TestController::class, 'testMethod']
            ]
        ];

        $this->request->method('getMethod')->willReturn('GET');
        $this->request->method('getUri')->willReturn(new Uri('/classmethod'));

        $controller = $this->getMockBuilder(TestController::class)
            ->onlyMethods(['testMethod'])
            ->getMock();

        $controller->expects($this->once())
            ->method('testMethod')
            ->willReturn('response content');

        $this->container->method('get')
            ->with(TestController::class)
            ->willReturn($controller);

        $handler = new RequestHandler($routes, $this->container);

        $response = $handler->handle($this->request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('response content', (string)$response->getBody());
    }

    public function testHandleWithParameterizedRouteReturnsResponse()
    {
        $routes = [
            'GET' => [
                '/user/{id}' => [TestController::class, 'getUser']
            ]
        ];

        $this->request->method('getMethod')->willReturn('GET');
        $this->request->method('getUri')->willReturn(new Uri('/user/123'));

        $controller = $this->getMockBuilder(TestController::class)
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->expects($this->once())
            ->method('getUser')
            ->with('123') // fix here!
            ->willReturn('user 123');

        $this->container->method('get')
            ->with(TestController::class)
            ->willReturn($controller);

        // Fix method signature in TestController below to accept string id param directly
        $handler = new RequestHandler($routes, $this->container);

        $response = $handler->handle($this->request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('user 123', (string)$response->getBody());
    }

    public function testHandleThrowsRouteNotFoundExceptionForMissingRoute()
    {
        $routes = [];

        $this->request->method('getMethod')->willReturn('GET');
        $this->request->method('getUri')->willReturn(new Uri('/nonexistent'));

        $handler = new RequestHandler($routes, $this->container);

        $this->expectException(RouteNotFoundException::class);

        $handler->handle($this->request);
    }

    public function testHandleThrowsRouteNotFoundExceptionForMalformedParams()
    {
        $routes = [
            'GET' => [
                '/user/{id}/{extra}' => fn() => 'should not be called'
            ]
        ];

        $this->request->method('getMethod')->willReturn('GET');

        $uri = new Uri('/user/123');
        $this->request->method('getUri')->willReturn($uri);

        $handler = new RequestHandler($routes, $this->container);

        $this->expectException(RouteNotFoundException::class);

        $handler->handle($this->request);
    }

    public function testHandleWithParameterizedRouteCallable()
    {
        $routes = [
            'GET' => [
                '/user/{id}' => function() {
                    return new Response('callable response', 200);
                },
            ],
        ];

        $this->request->method('getMethod')->willReturn('GET');
        $this->request->method('getUri')->willReturn(new Uri('/user/123'));

        $handler = new RequestHandler($routes, $this->container);

        $response = $handler->handle($this->request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('callable response', (string) $response->getBody());
    }

    public function testHandleThrowsRouteNotFoundExceptionWhenInvalidSimpleRouteAction()
    {
        $routes = [
            'GET' => [
                '/test' => 'invalid_action',  // Not callable, not array [class, method]
            ],
        ];

        $this->request->method('getMethod')->willReturn('GET');
        $this->request->method('getUri')->willReturn(new Uri('/test'));

        $handler = new RequestHandler($routes, $this->container);

        $this->expectException(RouteNotFoundException::class);

        $handler->handle($this->request);
    }
}

/**
 * A simple test controller used to simulate dependency injection and route method calls.
 */
class TestController
{
    public function testMethod()
    {
        return 'called';
    }

    public function getUser(string $id)
    {
        return "user $id";
    }
}
