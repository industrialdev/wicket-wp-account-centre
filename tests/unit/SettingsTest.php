<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Settings;

#[CoversClass(Settings::class)]
class SettingsTest extends AbstractTestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new Settings();
    }

    public function test_settings_instantiates(): void
    {
        $this->assertInstanceOf(Settings::class, $this->settings);
    }

    public function test_settings_extends_wicket_acc(): void
    {
        $this->assertInstanceOf(\WicketAcc\WicketAcc::class, $this->settings);
    }
}
