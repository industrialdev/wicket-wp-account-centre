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
use WicketAcc\Symfony\Component\Translation\Dumper\JsonFileDumper;
use WicketAcc\Symfony\Component\Translation\MessageCatalogue;

class JsonFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new JsonFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/resources.json', $dumper->formatCatalogue($catalogue, 'messages'));
    }

    public function testDumpWithCustomEncoding()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => '"bar"']);

        $dumper = new JsonFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/resources.dump.json', $dumper->formatCatalogue($catalogue, 'messages', ['json_encoding' => \JSON_HEX_QUOT]));
    }
}
