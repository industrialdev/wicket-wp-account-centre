<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('WicketAcc\Constants')]
class ConstantsTest extends AbstractTestCase
{
    public function test_wicket_acc_version_is_defined(): void
    {
        // This test runs after the main plugin file is loaded
        $this->assertTrue(defined('WICKET_ACC_VERSION'), 'WICKET_ACC_VERSION constant should be defined');
    }

    public function test_wicket_acc_version_is_string(): void
    {
        $this->assertIsString(WICKET_ACC_VERSION, 'WICKET_ACC_VERSION should be a string');
    }

    public function test_wicket_acc_path_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_PATH'), 'WICKET_ACC_PATH constant should be defined');
    }

    public function test_wicket_acc_url_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_URL'), 'WICKET_ACC_URL constant should be defined');
    }

    public function test_wicket_acc_uploads_path_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_UPLOADS_PATH'), 'WICKET_ACC_UPLOADS_PATH constant should be defined');
    }

    public function test_wicket_acc_uploads_url_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_UPLOADS_URL'), 'WICKET_ACC_UPLOADS_URL constant should be defined');
    }

    public function test_wicket_acc_plugin_template_path_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_PLUGIN_TEMPLATE_PATH'), 'WICKET_ACC_PLUGIN_TEMPLATE_PATH constant should be defined');
    }

    public function test_wicket_acc_plugin_template_url_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_PLUGIN_TEMPLATE_URL'), 'WICKET_ACC_PLUGIN_TEMPLATE_URL constant should be defined');
    }

    public function test_wicket_acc_user_template_path_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_USER_TEMPLATE_PATH'), 'WICKET_ACC_USER_TEMPLATE_PATH constant should be defined');
    }

    public function test_wicket_acc_user_template_url_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_USER_TEMPLATE_URL'), 'WICKET_ACC_USER_TEMPLATE_URL constant should be defined');
    }

    public function test_wicket_acc_templates_folder_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_ACC_TEMPLATES_FOLDER'), 'WICKET_ACC_TEMPLATES_FOLDER constant should be defined');
    }

    public function test_wicket_acc_templates_folder_value(): void
    {
        $this->assertSame('account-centre', WICKET_ACC_TEMPLATES_FOLDER);
    }
}
