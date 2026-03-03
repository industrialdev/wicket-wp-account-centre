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

    public function test_redirect_matcher_redirects_localized_account_root(): void
    {
        $method = new \ReflectionMethod(Router::class, 'shouldRedirectRequestToDashboard');

        $this->assertTrue($method->invoke($this->router, '/fr/mon-compte/'));
        $this->assertTrue($method->invoke($this->router, '/es/mi-cuenta/'));
        $this->assertTrue($method->invoke($this->router, '/my-account/'));
    }

    public function test_redirect_matcher_preserves_legacy_old_slug_behavior(): void
    {
        $method = new \ReflectionMethod(Router::class, 'shouldRedirectRequestToDashboard');

        $this->assertTrue($method->invoke($this->router, '/account-center/orders/123/'));
        $this->assertTrue($method->invoke($this->router, '/fr/wc-compte/orders/'));
    }

    public function test_redirect_matcher_does_not_redirect_account_subpages(): void
    {
        $method = new \ReflectionMethod(Router::class, 'shouldRedirectRequestToDashboard');

        $this->assertFalse($method->invoke($this->router, '/fr/mon-compte/orders/'));
        $this->assertFalse($method->invoke($this->router, '/fr/my-account/dashboard/'));
        $this->assertFalse($method->invoke($this->router, '/'));
    }
}
