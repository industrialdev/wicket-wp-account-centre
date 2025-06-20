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
use WicketAcc\Symfony\Component\Translation\Dumper\XliffFileDumper;
use WicketAcc\Symfony\Component\Translation\MessageCatalogue;

class XliffFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
        ]);
        $catalogue->setMetadata('foo', ['notes' => [['priority' => 1, 'from' => 'bar', 'content' => 'baz']]]);
        $catalogue->setMetadata('key', ['notes' => [['content' => 'baz'], ['content' => 'qux']]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-clean.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR'])
        );
    }

    public function testFormatCatalogueXliff2()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
            'translation.key.that.is.longer.than.eighty.characters.should.not.have.name.attribute' => 'value',
        ]);
        $catalogue->setMetadata('key', ['target-attributes' => ['order' => 1]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0-clean.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testFormatIcuCatalogueXliff2()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
        ], 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0+intl-icu.xlf',
            $dumper->formatCatalogue($catalogue, 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX, ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testFormatCatalogueWithCustomToolInfo()
    {
        $options = [
            'default_locale' => 'en_US',
            'tool_info' => ['tool-id' => 'foo', 'tool-name' => 'foo', 'tool-version' => '0.0', 'tool-company' => 'Foo'],
        ];

        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-tool-info.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', $options)
        );
    }

    public function testFormatCatalogueWithTargetAttributesMetadata()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
        ]);
        $catalogue->setMetadata('foo', ['target-attributes' => ['state' => 'needs-translation']]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-target-attributes.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR'])
        );
    }

    public function testFormatCatalogueWithNotesMetadata()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'baz' => 'biz',
        ]);
        $catalogue->setMetadata('foo', ['notes' => [
            ['category' => 'state', 'content' => 'new'],
            ['category' => 'approved', 'content' => 'true'],
            ['category' => 'section', 'content' => 'user login', 'priority' => '1'],
        ]]);
        $catalogue->setMetadata('baz', ['notes' => [
            ['id' => 'x', 'content' => 'x_content'],
            ['appliesTo' => 'target', 'category' => 'quality', 'content' => 'Fuzzy'],
        ]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-notes-meta.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testDumpCatalogueWithXliffExtension()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
        ]);
        $catalogue->setMetadata('foo', ['notes' => [['priority' => 1, 'from' => 'bar', 'content' => 'baz']]]);
        $catalogue->setMetadata('key', ['notes' => [['content' => 'baz'], ['content' => 'qux']]]);

        $dumper = new XliffFileDumper('xliff');

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-clean.xliff',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR'])
        );
    }

    public function testEmptyMetadataNotes()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'empty' => 'notes',
            'full' => 'notes',
        ]);
        $catalogue->setMetadata('empty', ['notes' => []]);
        $catalogue->setMetadata('full', ['notes' => [['category' => 'file-source', 'priority' => 1, 'content' => 'test/path/to/translation/Example.1.html.twig:27']]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0-empty-notes.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }
}
