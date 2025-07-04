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
use WicketAcc\Symfony\Component\Translation\Dumper\YamlFileDumper;
use WicketAcc\Symfony\Component\Translation\MessageCatalogue;

class YamlFileDumperTest extends TestCase
{
    public function testTreeFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add([
            'foo.bar1' => 'value1',
            'foo.bar2' => 'value2',
        ]);

        $dumper = new YamlFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/messages.yml', $dumper->formatCatalogue($catalogue, 'messages', ['as_tree' => true, 'inline' => 999]));
    }

    public function testLinearFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add([
            'foo.bar1' => 'value1',
            'foo.bar2' => 'value2',
        ]);

        $dumper = new YamlFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/messages_linear.yml', $dumper->formatCatalogue($catalogue, 'messages'));
    }
}
