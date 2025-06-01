<?php

namespace Router;

use Container\Container;

class Routes
{
    private array $routes = [];

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function register(string $requestMethod, string $route, callable |array $action): self
    {
        $this->routes[$requestMethod][$route] = $action;
        return $this;
    }

    public function get(string $route, callable |array $action): self
    {
        return $this->register('GET', $route, $action);
    }

    public function post(string $route, callable |array $action): self
    {
        return $this->register('POST', $route, $action);
    }
}