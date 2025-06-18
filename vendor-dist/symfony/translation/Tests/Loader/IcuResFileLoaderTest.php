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

use Symfony\Component\Config\Resource\DirectoryResource;
use WicketAcc\Symfony\Component\Translation\Exception\InvalidResourceException;
use WicketAcc\Symfony\Component\Translation\Exception\NotFoundResourceException;
use WicketAcc\Symfony\Component\Translation\Loader\IcuResFileLoader;

/**
 * @requires extension intl
 */
class IcuResFileLoaderTest extends LocalizedTestCase
{
    public function testLoad()
    {
        // resource is build using genrb command
        $loader = new IcuResFileLoader();
        $resource = __DIR__.'/../Fixtures/resourcebundle/res';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new DirectoryResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);
        $loader = new IcuResFileLoader();
        $loader->load(__DIR__.'/../Fixtures/non-existing.txt', 'en', 'domain1');
    }

    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);
        $loader = new IcuResFileLoader();
        $loader->load(__DIR__.'/../Fixtures/resourcebundle/corrupted', 'en', 'domain1');
    }
}
