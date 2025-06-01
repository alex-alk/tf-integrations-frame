<?php



interface FooInterface {}
class Foo implements FooInterface {}

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
