<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Shortcodes;

#[CoversClass(Shortcodes::class)]
class ShortcodesTest extends AbstractTestCase
{
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    public function test_org_selector_applies_org_uuid_list_filter(): void
    {
        $_GET = [];

        Functions\when('add_shortcode')->justReturn(null);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('__')->alias(static fn (string $text): string => $text);
        Functions\when('WACC')->justReturn(new class {
            public function Language()
            {
                return new class {
                    public function getCurrentLanguage(): string
                    {
                        return 'en';
                    }
                };
            }
        });
        Functions\when('wicket_current_person')->justReturn(new class {
            public function included(): array
            {
                return [[
                    'type' => 'roles',
                    'attributes' => [
                        'name' => 'member',
                    ],
                    'relationships' => [
                        'resource' => [
                            'data' => [
                                'id' => 'org-123',
                                'type' => 'organizations',
                            ],
                        ],
                    ],
                ]];
            }
        });

        Functions\expect('apply_filters')
            ->once()
            ->with('wicket_acc_org_selector_org_uuid_list', ['org-123'])
            ->andReturn([]);

        $shortcodes = new Shortcodes();
        $output = $shortcodes->orgSelectorCallback();

        $this->assertSame('<p>No organizations found for your account.</p>', $output);
    }
}
