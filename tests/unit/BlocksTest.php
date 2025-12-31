<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Blocks;

#[CoversClass(Blocks::class)]
class BlocksTest extends AbstractTestCase
{
    private Blocks $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blocks = new Blocks();
    }

    public function test_blocks_instantiates(): void
    {
        $this->assertInstanceOf(Blocks::class, $this->blocks);
    }

    public function test_blocks_extends_wicket_acc(): void
    {
        $this->assertInstanceOf(\WicketAcc\WicketAcc::class, $this->blocks);
    }
}
