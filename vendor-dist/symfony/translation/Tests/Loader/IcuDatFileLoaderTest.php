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

use Symfony\Component\Config\Resource\FileResource;
use WicketAcc\Symfony\Component\Translation\Exception\InvalidResourceException;
use WicketAcc\Symfony\Component\Translation\Exception\NotFoundResourceException;
use WicketAcc\Symfony\Component\Translation\Loader\IcuDatFileLoader;

/**
 * @requires extension intl
 */
class IcuDatFileLoaderTest extends LocalizedTestCase
{
    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);
        $loader = new IcuDatFileLoader();
        $loader->load(__DIR__.'/../Fixtures/resourcebundle/corrupted/resources', 'es', 'domain2');
    }

    public function testDatEnglishLoad()
    {
        // bundled resource is build using pkgdata command which at least in ICU 4.2 comes in extremely! buggy form
        // you must specify an temporary build directory which is not the same as current directory and
        // MUST reside on the same partition. pkgdata -p resources -T /srv -d.packagelist.txt
        $loader = new IcuDatFileLoader();
        $resource = __DIR__.'/../Fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['symfony' => 'Symfony 2 is great'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource.'.dat')], $catalogue->getResources());
    }

    public function testDatFrenchLoad()
    {
        $loader = new IcuDatFileLoader();
        $resource = __DIR__.'/../Fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'fr', 'domain1');

        $this->assertEquals(['symfony' => 'Symfony 2 est génial'], $catalogue->all('domain1'));
        $this->assertEquals('fr', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource.'.dat')], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);
        $loader = new IcuDatFileLoader();
        $loader->load(__DIR__.'/../Fixtures/non-existing.txt', 'en', 'domain1');
    }
}
