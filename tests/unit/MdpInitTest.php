<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Mdp\Init;

#[CoversClass(Init::class)]
class MdpInitTest extends AbstractTestCase
{
    private Init $mdp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mdp = new Init();
    }

    public function test_mdp_init_instantiates(): void
    {
        $this->assertInstanceOf(Init::class, $this->mdp);
    }
}
