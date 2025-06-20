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
use WicketAcc\Symfony\Component\Translation\Dumper\PoFileDumper;
use WicketAcc\Symfony\Component\Translation\MessageCatalogue;

class PoFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar', 'bar' => 'foo', 'foo_bar' => 'foobar', 'bar_foo' => 'barfoo']);
        $catalogue->setMetadata('foo_bar', [
            'comments' => [
                'Comment 1',
                'Comment 2',
            ],
            'flags' => [
                'fuzzy',
                'another',
            ],
            'sources' => [
                'src/file_1',
                'src/file_2:50',
            ],
        ]);
        $catalogue->setMetadata('bar_foo', [
            'comments' => 'Comment',
            'flags' => 'fuzzy',
            'sources' => 'src/file_1',
        ]);

        $dumper = new PoFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/resources.po', $dumper->formatCatalogue($catalogue, 'messages'));
    }

    public function testDumpPlurals()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add([
            'foo|foos' => 'bar|bars',
            '{0} no foos|one foo|%count% foos' => '{0} no bars|one bar|%count% bars',
        ]);

        $dumper = new PoFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/plurals.po', $dumper->formatCatalogue($catalogue, 'messages'));
    }
}
