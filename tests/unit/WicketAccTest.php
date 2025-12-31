<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\WicketAcc;

#[CoversClass(WicketAcc::class)]
class WicketAccTest extends AbstractTestCase
{
    private WicketAcc $wicket_acc;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the WordPress functions needed by WicketAcc
        \Brain\Monkey\Functions\stubs([
            'get_file_data' => ['Version' => '1.6.0'],
            'plugin_dir_path' => __DIR__ . '/../../',
            'plugin_dir_url' => 'http://example.com/wp-content/plugins/wicket-wp-account-centre/',
            'plugin_basename' => 'wicket-wp-account-centre/wicket-wp-account-centre.php',
        ]);

        $this->wicket_acc = WicketAcc::get_instance();
    }

    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = WicketAcc::get_instance();
        $instance2 = WicketAcc::get_instance();

        $this->assertSame($instance1, $instance2, 'WicketAcc::get_instance() should return the same instance');
    }

    public function test_instance_is_object(): void
    {
        $this->assertInstanceOf(WicketAcc::class, $this->wicket_acc);
    }

    public function test_plugin_url_property_exists(): void
    {
        $this->assertObjectHasProperty('plugin_url', $this->wicket_acc);
    }

    public function test_plugin_path_property_exists(): void
    {
        $this->assertObjectHasProperty('plugin_path', $this->wicket_acc);
    }

    public function test_instances_property_exists(): void
    {
        $property = new \ReflectionProperty(WicketAcc::class, 'instances');
        $this->assertTrue($property->isPrivate());
    }
}
