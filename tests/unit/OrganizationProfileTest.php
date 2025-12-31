<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\OrganizationProfile;

#[CoversClass(OrganizationProfile::class)]
class OrganizationProfileTest extends AbstractTestCase
{
    private OrganizationProfile $org_profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org_profile = new OrganizationProfile();
    }

    public function test_organization_profile_instantiates(): void
    {
        $this->assertInstanceOf(OrganizationProfile::class, $this->org_profile);
    }

    public function test_organization_profile_extends_wicket_acc(): void
    {
        $this->assertInstanceOf(\WicketAcc\WicketAcc::class, $this->org_profile);
    }
}
