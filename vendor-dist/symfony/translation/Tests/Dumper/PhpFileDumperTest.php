<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WicketAcc\Symfony\Component\Translation\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use WicketAcc\Symfony\Component\Translation\Dumper\PhpFileDumper;
use WicketAcc\Symfony\Component\Translation\MessageCatalogue;

class PhpFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new PhpFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/resources.php', $dumper->formatCatalogue($catalogue, 'messages'));
    }
}
