<?php

namespace Container;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

// Simple DI Container
// use get to create new instances
// use set before get to get the same instance
class Container implements ContainerInterface
{
    private array $entries = [];

    // to make an explicit binding with callback: ->set(id, function(Container $c) {return new...})
    // usage: set(interfacename, classname);
    public function get(string $id)
    {
      
        if ($this->has($id)) {
            $entry = $this->entries[$id];

            // explicit binding
            if (is_callable($entry)) {
                return $entry($this);
            }

            $id = $entry;
        }
        return $this->resolve($id);
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    public function set(string $id, callable|string|object $concrete)
    {
        $this->entries[$id] = $concrete;
    }
    
    public function resolve(string $id)
    {
        // Inspect the class
        $reflectionClass = new ReflectionClass($id);
        if (!$reflectionClass->isInstantiable()) {
            throw new ContainerException('Class '. $id . ' is not instantialbe');
        }
        
        // Inspect the constructor
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return new $id;
        }
        
        // Inspect constructor parameters
        $parameters = $constructor->getParameters();
        if (!$parameters) {
            return new $id;
        }
        
        // If the constructor parameter is a class then try to resolve the class
        $dependencies = array_map(function(ReflectionParameter $param) use ($id) {
            $name = $param->getName();
            $type = $param->getType();
            
            if (!$type) {
                throw new ContainerException(
                    'Failed to resolve class ' . $id . ' because param ' . $name . ' is missing a type hint'
                );
            }
            
            if ($type instanceof ReflectionUnionType) {
                throw new ContainerException(
                    'Failed to resolve class ' . $id . ' because of union type for param ' . $name
                );
            }
            
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->get($type->getName());
            }
            throw new ContainerException(
                 'Failed to resolve class ' . $id . ' because of invalid param ' . $name
            );
        }, $parameters);
        return $reflectionClass->newInstanceArgs($dependencies);
    }
}