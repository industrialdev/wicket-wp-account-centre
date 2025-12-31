<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\OrganizationManagement;

#[CoversClass(OrganizationManagement::class)]
class OrganizationManagementTest extends AbstractTestCase
{
    private OrganizationManagement $org_management;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org_management = new OrganizationManagement();
    }

    public function test_organization_management_instantiates(): void
    {
        $this->assertInstanceOf(OrganizationManagement::class, $this->org_management);
    }

    public function test_organization_management_extends_wicket_acc(): void
    {
        $this->assertInstanceOf(\WicketAcc\WicketAcc::class, $this->org_management);
    }
}
