<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Router;

#[CoversClass(Router::class)]
class RouterTest extends AbstractTestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = new Router();
    }

    public function test_router_instantiates(): void
    {
        $this->assertInstanceOf(Router::class, $this->router);
    }
}
