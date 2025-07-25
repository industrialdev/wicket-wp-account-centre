<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WicketAcc\Symfony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use WicketAcc\Symfony\Component\Translation\Exception\NotFoundResourceException;
use WicketAcc\Symfony\Component\Translation\Loader\PoFileLoader;

class PoFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadPlurals()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/plurals.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([
            'foo|foos' => 'bar|bars',
            '{0} no foos|one foo|%count% foos' => '{0} no bars|one bar|%count% bars',
        ], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/empty.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/non-existing.po';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadEmptyTranslation()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/empty-translation.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => ''], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testEscapedId()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/escaped-id.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $messages = $catalogue->all('domain1');
        $this->assertArrayHasKey('escaped "foo"', $messages);
        $this->assertEquals('escaped "bar"', $messages['escaped "foo"']);
    }

    public function testEscapedIdPlurals()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/escaped-id-plurals.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $messages = $catalogue->all('domain1');
        $this->assertArrayHasKey('escaped "foo"|escaped "foos"', $messages);
        $this->assertEquals('escaped "bar"|escaped "bars"', $messages['escaped "foo"|escaped "foos"']);
    }

    public function testSkipFuzzyTranslations()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/fuzzy-translations.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $messages = $catalogue->all('domain1');
        $this->assertArrayHasKey('foo1', $messages);
        $this->assertArrayNotHasKey('foo2', $messages);
        $this->assertArrayHasKey('foo3', $messages);
    }

    public function testMissingPlurals()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../Fixtures/missing-plurals.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([
            'foo|foos' => '-|bar|-|bars',
        ], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
    }
}
