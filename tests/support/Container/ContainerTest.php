<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Container\Container;
use Container\ContainerException;

class ContainerTest extends TestCase
{
    public function testSetAndGetScalarBinding()
    {
        $container = new Container();
        $container->set(FooInterface::class, Foo::class);

        $foo = $container->get(FooInterface::class);
        $this->assertInstanceOf(Foo::class, $foo);
    }

    public function testSetAndGetClosureBinding()
    {
        $container = new Container();
        $container->set(FooInterface::class, function () {
            return new Foo();
        });

        $foo = $container->get(FooInterface::class);
        $this->assertInstanceOf(Foo::class, $foo);
    }

    public function testAutoResolution()
    {
        $container = new Container();

        $bar = $container->get(Bar::class);
        $this->assertInstanceOf(Bar::class, $bar);
        $this->assertInstanceOf(Foo::class, $bar->foo);

        $fooc = $container->get(FooC::class);
        $this->assertInstanceOf(FooC::class, $fooc);
    }

    public function testThrowsForUninstantiableClass()
    {
        $this->expectException(ContainerException::class);

        $container = new Container();
        $container->get(AbstractThing::class);
    }

    public function testThrowsForMissingTypeHint()
    {
        $this->expectException(ContainerException::class);

        $container = new Container();
        $container->get(Baz::class);
    }

    public function testThrowsForUnionType()
    {
        $this->expectException(ContainerException::class);

        $container = new Container();
        $container->get(Qux::class);
    }

    public function testThrowsForInvalidBuiltinParamType()
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/invalid param/');

        $container = new Container();
        $container->get(InvalidParamClass::class);
    }
}

interface FooInterface {}
class Foo implements FooInterface {
}

class FooC implements FooInterface {
    public function __construct() {}
}

abstract class AbstractThing {}

class Bar {
    public Foo $foo;
    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }
}

class Baz {
    public function __construct($noTypeHint) {}
}

class Qux {
    public function __construct(Foo|string $param) {}
}

class InvalidParamClass {
    public function __construct(int $value) {}
}


