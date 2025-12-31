<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Profile;

#[CoversClass(Profile::class)]
class ProfileTest extends AbstractTestCase
{
    public function test_profile_instantiates(): void
    {
        $profile = new Profile();

        $this->assertInstanceOf(Profile::class, $profile);
    }

    public function test_profile_extends_wicket_acc(): void
    {
        $profile = new Profile();

        $this->assertInstanceOf(\WicketAcc\WicketAcc::class, $profile);
    }

    public function test_is_custom_profile_picture_detects_default(): void
    {
        $profile = new Profile();

        \Brain\Monkey\Functions\stubs([
            'WACC' => new class {
                public function getAttachmentUrlFromOption() {
                    return '';
                }
            },
        ]);

        $default_url = WICKET_ACC_URL . '/assets/images/profile-picture-default.svg';
        $is_custom = $profile->isCustomProfilePicture($default_url);

        $this->assertFalse($is_custom);
    }

    public function test_is_custom_profile_picture_detects_custom(): void
    {
        $profile = new Profile();
        $custom_url = 'http://example.com/custom-uploads/profile-picture-123.jpg';

        \Brain\Monkey\Functions\stubs([
            'WACC' => new class {
                public function getAttachmentUrlFromOption() {
                    return '';
                }
            },
        ]);

        $is_custom = $profile->isCustomProfilePicture($custom_url);

        $this->assertTrue($is_custom);
    }
}
