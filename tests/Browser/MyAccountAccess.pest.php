<?php

declare(strict_types=1);

pest()->browser()->timeout(20_000);

function wicket_browser_base_url(): string
{
    $baseUrl = getenv('WICKET_BROWSER_BASE_URL') ?: 'https://localhost';

    return rtrim($baseUrl, '/');
}

function wicket_browser_options(): array
{
    $ignoreHttps = getenv('WICKET_BROWSER_IGNORE_HTTPS_ERRORS');

    if ($ignoreHttps === false || $ignoreHttps === '') {
        $host = (string) (parse_url(wicket_browser_base_url(), PHP_URL_HOST) ?: '');
        $ignoreHttps = in_array($host, ['localhost', '127.0.0.1'], true) ? '1' : '0';
    }

    $ignoreHttpsBool = in_array(strtolower((string) $ignoreHttps), ['1', 'true', 'yes', 'on'], true);

    return $ignoreHttpsBool ? ['ignoreHTTPSErrors' => true] : [];
}

it('blocks unauthenticated access to /my-account/', function (): void {
    visit(wicket_browser_base_url() . '/my-account/', wicket_browser_options())
        ->assertPathIs('/restricted-access/')
        ->assertQueryStringHas('referrer', '/my-account/dashboard/')
        ->assertSee('Restricted Access')
        ->assertSee('The full content of this page is restricted to members. Please login.');
});

it('redirects authenticated users from /my-account/ to /my-account/dashboard/ and shows dashboard', function (): void {
    $username = (string) (getenv('WICKET_BROWSER_USERNAME') ?: '');
    $password = (string) (getenv('WICKET_BROWSER_PASSWORD') ?: '');

    if ($username === '' || $password === '') {
        $this->markTestSkipped('Set WICKET_BROWSER_USERNAME and WICKET_BROWSER_PASSWORD to run authenticated browser tests.');
    }

    visit(wicket_browser_base_url() . '/my-account/', wicket_browser_options())
        ->assertPathIs('/restricted-access/')
        ->assertPresent('.login-button')
        ->assertAttributeContains('.login-button', 'href', 'https://pace-login.staging.wicketcloud.com/login')
        ->click('.login-button')
        ->wait(1.5)
        ->assertPresent('#fm1')
        ->assertPresent('#username')
        ->assertPresent('#password')
        ->type('#username', $username)
        ->type('#password', $password)
        ->assertScript(
            <<<'JS'
            (() => {
                const form = document.querySelector('#fm1');
                if (!form) {
                    return false;
                }
                form.submit();
                return true;
            })()
            JS,
            true
        )
        ->wait(4)
        ->assertPathIs('/my-account/dashboard/')
        ->assertVisible('.woocommerce-wicket--account-centre.wicket-acc-page-acc');
});
