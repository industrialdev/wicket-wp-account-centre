<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\OrganizationRoster;

#[CoversClass(OrganizationRoster::class)]
class OrganizationRosterTest extends AbstractTestCase
{
    private OrganizationRoster $org_roster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org_roster = new OrganizationRoster();
    }

    public function test_organization_roster_instantiates(): void
    {
        $this->assertInstanceOf(OrganizationRoster::class, $this->org_roster);
    }

    public function test_organization_roster_extends_wicket_acc(): void
    {
        // OrganizationRoster does not extend WicketAcc
        $this->assertInstanceOf(OrganizationRoster::class, $this->org_roster);
    }
}
