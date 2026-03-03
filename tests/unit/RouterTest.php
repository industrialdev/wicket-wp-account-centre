<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Router;

class TestableRouter extends Router
{
    public ?string $redirectedTo = null;
    public string $dashboardUrl = '/my-account/dashboard/';

    protected function performRedirect(string $url): void
    {
        $this->redirectedTo = $url;
    }

    public function get_account_page_url($endpoint = '')
    {
        return $this->dashboardUrl;
    }
}

#[CoversClass(Router::class)]
class RouterTest extends AbstractTestCase
{
    private Router $router;
    private array $serverBackup;
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;

        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;

        parent::tearDown();
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

    public function test_acc_redirects_bails_out_when_cas_ticket_is_present(): void
    {
        $router = new TestableRouter();

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_post_type_archive')->justReturn(false);
        Functions\expect('wp_safe_redirect')->never();

        $_GET['ticket'] = 'ST-123456-abcdef';
        $_SERVER['REQUEST_URI'] = '/my-account/?ticket=ST-123456-abcdef';

        $router->accRedirects();

        $this->assertNull($router->redirectedTo);
    }

    public function test_acc_redirects_bails_out_when_cas_ticket_is_present_on_legacy_slug(): void
    {
        $router = new TestableRouter();

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_post_type_archive')->justReturn(false);
        Functions\expect('wp_safe_redirect')->never();

        $_GET['ticket'] = 'ST-987654-legacy';
        $_SERVER['REQUEST_URI'] = '/account-centre/orders/?ticket=ST-987654-legacy';

        $router->accRedirects();

        $this->assertNull($router->redirectedTo);
    }

    public function test_acc_redirects_redirects_localized_account_root_without_ticket(): void
    {
        $router = new TestableRouter();
        $router->dashboardUrl = '/fr/mon-compte/dashboard/';

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_post_type_archive')->justReturn(false);

        unset($_GET['ticket']);
        $_SERVER['REQUEST_URI'] = '/fr/mon-compte/';

        $router->accRedirects();

        $this->assertSame('/fr/mon-compte/dashboard/', $router->redirectedTo);
    }

    public function test_acc_redirects_does_not_redirect_account_subpages_without_ticket(): void
    {
        $router = new TestableRouter();

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_post_type_archive')->justReturn(false);

        unset($_GET['ticket']);
        $_SERVER['REQUEST_URI'] = '/my-account/orders/';

        $router->accRedirects();

        $this->assertNull($router->redirectedTo);
    }
}
