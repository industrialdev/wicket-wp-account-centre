<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\WooCommerce;

#[CoversClass(WooCommerce::class)]
class WooCommerceTest extends AbstractTestCase
{
    public function test_woocommerce_instantiates(): void
    {
        // Mock the WooCommerce active check
        \Brain\Monkey\Functions\stubs([
            'is_plugin_active' => true,
        ]);

        $woo_commerce = new WooCommerce();

        $this->assertInstanceOf(WooCommerce::class, $woo_commerce);
    }

    public function test_woocommerce_extends_wicket_acc(): void
    {
        // Mock the WooCommerce active check
        \Brain\Monkey\Functions\stubs([
            'is_plugin_active' => true,
        ]);

        $woo_commerce = new WooCommerce();

        $this->assertInstanceOf(\WicketAcc\WicketAcc::class, $woo_commerce);
    }
}
